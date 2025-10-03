<?php
namespace Blackprint\Constructor;

class ExecutionOrder {
	public $index = 0;
	public $lastIndex = 0;
	public $initialSize = 30;
	public $stop = false;
	public $pause = false;
	public $stepMode = false;
	public $_lockNext = false;
	public $_nextLocked = false;
	private $_execCounter = null;
	private $_rootExecOrder = ['stop' => false];

	// Pending because stepMode
	private $_pRequest = [];
	private $_pRequestLast = [];
	private $_pTrigger = [];
	private $_pRoute = [];
	private $_hasStepPending = false;

	// Cable who trigger the execution order's update (with stepMode)
	private $_tCable;
	private $_lastCable;
	private $_lastBeforeNode;

	/** @var array<\Blackprint\Node> */
	public $list;

	public function __construct(public &$instance, $size=30){
		$this->initialSize = &$size;
		$this->list = array_fill(0, $size, null);

		// Cable who trigger the execution order's update (with stepMode)
		$this->_tCable = new \Ds\Map(); // { Node => Set<Cable> }
	}

	public function isPending(&$node=null){
		if($this->index === $this->lastIndex) return false;
		if($node === null) return true;
		return in_array($node, $this->list, true);
	}

	public function clear(){
		$list = &$this->list;
		for ($i=$this->index; $i < $this->lastIndex; $i++) {
			$list[$i] = null;
		}

		$this->lastIndex = $this->index = 0;
	}

	public function add(&$node, &$_cable=null){
		if($this->stop || $this->_rootExecOrder['stop'] || $this->isPending($node)) return;
		$this->_isReachLimit();

		$this->list[$this->lastIndex++] = $node;
		if($this->stepMode) {
			if($_cable != null) $this->_tCableAdd($node, $_cable);
			$this->_emitNextExecution();
		}
	}
	public function _tCableAdd($node, $cable){
		if($this->stop || $this->_rootExecOrder['stop']) return;
		$tCable = &$this->_tCable; // Cable triggerer
		$sets = $tCable->get($node);
		if($sets === null) {
			$sets = new \Ds\Set();
			$tCable->put($node, $sets);
		}

		$sets->add($cable);
	}

	public function _isReachLimit(){
		$i = $this->index + 1;
		if($i >= $this->initialSize || $this->lastIndex >= $this->initialSize)
			throw new \Exception("Execution order limit was exceeded");
	}

	public function &_next(){
		if($this->stop || $this->_rootExecOrder['stop']) return \Blackprint\Utils::$_null;
		if($this->index >= $this->lastIndex)
			return \Blackprint\Utils::$_null;

		$i = $this->index;
		$temp = $this->list[$this->index++];
		$this->list[$i] = null;

		if($this->index >= $this->lastIndex)
			$this->index = $this->lastIndex = 0;

		if($this->stepMode) $this->_tCable->remove($temp);
		return $temp;
	}

	public function _emitPaused($afterNode, $beforeNode, $triggerSource, $cable, $cables=null){
		if($this->stop || $this->_rootExecOrder['stop']) return;
		$this->instance->_emit('execution.paused', new EvExecutionPaused(
			$afterNode,
			$beforeNode,
			$cable,
			$cables,
			$triggerSource,
		));
	}

	public function _addStepPending($cable, $triggerSource){
		if($this->stop || $this->_rootExecOrder['stop']) return;
		// 0 = execution order, 1 = route, 2 = trigger port, 3 = request
		if($triggerSource === 1 && !in_array($cable, $this->_pRoute)) $this->_pRoute[] = &$cable;
		if($triggerSource === 2 && !in_array($cable, $this->_pTrigger)) $this->_pTrigger[] = &$cable;
		if($triggerSource === 3){
			$hasCable = false;
			$list = $this->_pRequest;
			foreach ($list as &$val) {
				if($val === $cable){
					$hasCable = true;
					break;
				}
			}

			if($hasCable === false){
				$cableCall = null;
				$inputPorts = $cable->input->iface->input;

				foreach ($inputPorts as $key => &$port) {
					if($port->_calling){
						$cables = &$port->cables;
						foreach ($cables as &$_cable) {
							if($_cable->_calling){
								$cableCall = &$_cable;
								break;
							}
						}
						break;
					}
				}

				$list[] = [
					'cableCall'=> &$cableCall,
					'cable'=> &$cable,
				];
			}
		}

		$this->_hasStepPending = true;
		$this->_emitNextExecution();
	}

	// Using singleNodeExecutionLoopLimit may affect performance
	private function _checkExecutionLimit(){
		$limit = \Blackprint\settings('singleNodeExecutionLoopLimit');
		if($limit == null || $limit == 0) {
			$this->_execCounter = null;
			return;
		}
		if($this->lastIndex - $this->index === 0) {
			$this->_execCounter?->clear();
			return;
		}

		$node = $this->list[$this->index];
		if($node == null) throw new \Exception("Empty");

		$map = $this->_execCounter ??= new \Ds\Map();
		if(!$map->has($node)) $map->put($node, 0);

		$count = $map->get($node) + 1;
		$map->put($node, $count);

		if($count > $limit){
			error_log("Execution terminated at " . $node->iface);
			$this->stepMode = true;
			$this->pause = true;
			$this->_execCounter->clear();

			$message = "Single node execution loop exceeded the limit ({$limit}): " . $node->iface->namespace;
			$this->instance->_emit('execution.terminated', ['reason' => $message, 'iface' => $node->iface]);
			return true;
		}
	}

	// For step mode
	public function _emitNextExecution($afterNode=null){
		if($this->stop || $this->_rootExecOrder['stop']) return;
		$triggerSource = 0; $beforeNode = null;

		if(count($this->_pRequest) !== 0){
			$triggerSource = 3;
			$cable = &$this->_pRequest[0]->cable;
		}
		elseif(count($this->_pRequestLast) !== 0){
			$triggerSource = 0;
			$beforeNode = &$this->_pRequestLast[0]->node;
		}
		elseif(count($this->_pTrigger) !== 0){
			$triggerSource = 2;
			$cable = &$this->_pTrigger[0];
		}
		elseif(count($this->_pRoute) !== 0){
			$triggerSource = 1;
			$cable = &$this->_pRoute[0];
		}

		if($cable != null){
			if($this->_lastCable === $cable) return; // avoid duplicate event trigger

			$inputNode = &$cable->input->iface->node;
			$outputNode = &$cable->output->iface->node;
		}

		if($triggerSource === 0){
			if($beforeNode === null)
				$beforeNode = $this->list[$this->index];

			// avoid duplicate event trigger
			if($this->_lastBeforeNode === $beforeNode) return;

			$cables = $this->_tCable->get($beforeNode); // Set<Cables>
			if($cables) {
				$cables = $cables->toArray();
				return $this->_emitPaused($afterNode, $beforeNode, 0, null, $cables);
			}
		}
		elseif($triggerSource === 3) return $this->_emitPaused($inputNode, $outputNode, $triggerSource, $cable);
		else return $this->_emitPaused($outputNode, $inputNode, $triggerSource, $cable);
	}

	public function _checkStepPending(){
		if($this->stop || $this->_rootExecOrder['stop']) return;
		if(!$this->_hasStepPending) return;
		if($this->_checkExecutionLimit()) return;

		$_pRequest = &$this->_pRequest;
		$_pRequestLast = &$this->_pRequestLast;
		$_pTrigger = &$this->_pTrigger;
		$_pRoute = &$this->_pRoute;

		if(count($_pRequest) !== 0){
			[ &$cable, &$cableCall ] = array_shift($_pRequest);
			$currentIface = &$cable->output->iface;
			$current = &$currentIface->node;

			// cable->visualizeFlow();
			$currentIface->_requesting = true;
			try {
				$current->request($cable);
			}
			finally {
				$currentIface->_requesting = false;
			}

			$inpIface = &$cable->input->iface;

			// Check if the cable was the last requester from a node
			$isLast = true;
			foreach ($_pRequest as &$value) {
				if($value->cable->input->iface === $inpIface){
					$isLast = false;
				}
			}

			if($isLast){
				$this->$_pRequestLast[] = [
					'node'=> &$inpIface->node,
					'cableCall'=> &$cableCall,
				];

				if($cableCall !== null)
					$this->_tCableAdd($cableCall->input->iface->node, $cableCall);
			}

			$this->_tCableAdd($inpIface->node, $cable);
			$this->_emitNextExecution();
		}
		elseif(count($_pRequestLast) !== 0){
			[ $node, $cableCall ] = array_pop($_pRequestLast);

			$node->update(); // await

			if($cableCall != null)
				$cableCall->input->_call($cableCall);

			$this->_tCable->remove($node);
			$this->_emitNextExecution();
		}
		elseif(count($_pTrigger) !== 0){
			$cable = array_shift($_pTrigger);
			$current = $cable->input;

			// cable->visualizeFlow();
			$current->_call($cable);

			$this->_emitNextExecution();
		}
		elseif(count($_pRoute) !== 0){
			$cable = array_shift($_pRoute);

			// $cable->visualizeFlow();
			$cable->input->routeIn($cable, true);

			$this->_emitNextExecution();
		}
		else return false;

		if(count($_pRequest) === 0 && count($_pRequestLast) === 0 && count($_pTrigger) === 0 && count($_pRoute) === 0)
			$this->_hasStepPending = false;

		return true;
	}

	// Can be async function if the programming language support it
	public function next($force=false){
		if($this->stop || $this->_rootExecOrder['stop'] || $this->_nextLocked) return;
		if($this->stepMode) $this->pause = true;
		if($this->pause && !$force) return;
		if(count($this->instance->ifaceList) === 0) return;
		if($this->_checkStepPending()) return;

		/** @var \Blackprint\Node */
		$next = $this->_next(); // next => node
		if($next == null) return;

		$skipUpdate = count($next->routes->in) !== 0;
		$nextIface = &$next->iface;
		$next->_bpUpdating = true;

		if($next->partialUpdate && $next->update == null)
			$next->partialUpdate = false;

		$_proxyInput = null;
		if($nextIface->_enum === \Blackprint\Nodes\Enums::BPFnMain){
			$_proxyInput = &$nextIface->_proxyInput;
			$_proxyInput->_bpUpdating = true;
		}

		if($this->_lockNext) $this->_nextLocked = true;

		try{
			if($next->partialUpdate){
				$portList = &$nextIface->input;
				foreach($portList as &$inp){
					if($inp->feature === \Blackprint\PortType::ArrayOf){
						if($inp->_hasUpdate){
							$inp->_hasUpdate = false;

							if(!$skipUpdate){
								$cables = &$inp->cables;
								foreach($cables as &$cable){
									if(!$cable->_hasUpdate) continue;
									$cable->_hasUpdate = false;

									$next->update($cable); // await
								}
							}
						}
					}
					elseif($inp->_hasUpdateCable !== null){
						$cable = $inp->_hasUpdateCable;
						$inp->_hasUpdateCable = null;

						if(!$skipUpdate) $next->update($cable); // await
					}
				}
			}

			$next->_bpUpdating = false;
			if($_proxyInput !== null) $_proxyInput->_bpUpdating = false;

			// Make this async if possible
			if(!$skipUpdate) {
				if(!$next->partialUpdate) $next->_bpUpdate(); // await
				elseif($next->bpFunction !== null) $nextIface->bpInstance->executionOrder->start(); // start execution runner
			}
		} catch(\Exception $e) {
			if($_proxyInput !== null) $_proxyInput->_bpUpdating = false;

			$this->clear();
			throw $e;
		} finally {
			$this->_nextLocked = false;
			if($this->stepMode) $this->_emitNextExecution($next);
		}
	}

	public function start(){
		if($this->stop || $this->_rootExecOrder['stop'] || $this->_nextLocked || $this->pause) return;

		$this->_lockNext = true;
		for ($i=$this->index; $i < $this->lastIndex; $i++) {
			$this->next();
		}
		$this->_lockNext = false;
	}
}

class EvExecutionPaused{
	function __construct(
		public $afterNode,
		public $beforeNode,
		public $cable,
		public $cables,

		// 0 = execution order, 1 = route, 2 = trigger port, 3 = request
		// execution priority: 3, 2, 1, 0
		public $triggerSource,
	){}
}