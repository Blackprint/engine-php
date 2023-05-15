<?php
namespace Blackprint\Constructor;

class OrderedExecution {
	public $index = 0;
	public $length = 0;
	public $initialSize = 30;
	public $pause = false;
	public $stepMode = false;
	private $_processing = false;
		
	// Pending because stepMode
	private $_pRequest = [];
	private $_pRequestLast = [];
	private $_pTrigger = [];
	private $_pRoute = [];
	private $_hasStepPending = false;
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

	public function isPending(&$node){
		if($this->index === $this->length) return false;
		return in_array($node, $this->list);
	}

	public function clear(){
		$list = &$this->list;
		for ($i=$this->index, $n=$this->length; $i < $n; $i++) {
			$list[$i] = null;
		}

		$this->length = $this->index = 0;
	}

	public function add(&$node, &$_cable=null){
		if($this->isPending($node)) return;
		$this->_isReachLimit();

		$this->list[$this->length++] = $node;
		if($this->stepMode) {
			if($_cable != null) $this->_tCableAdd($node, $_cable);
			$this->_emitNextExecution();
		}
	}
	public function _tCableAdd($node, $cable){
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
		if($i >= $this->initialSize || $this->length >= $this->initialSize)
			throw new \Exception("Execution order limit was exceeded");
	}

	public function &_next(){
		if($this->index >= $this->length)
			return \Blackprint\Utils::$_null;

		$i = $this->index;
		$temp = $this->list[$this->index++];
		$this->list[$i] = null;

		if($this->index >= $this->length)
			$this->index = $this->length = 0;

		if($this->stepMode) $this->_tCable->remove($temp);
		return $temp;
	}

	public function _emitPaused($afterNode, $beforeNode, $triggerSource, $cable, $cables=null){
		$this->instance->_emit('execution.paused', new EvExecutionPaused(
			$afterNode,
			$beforeNode,
			$cable,
			$cables,
			$triggerSource,
		));
	}

	public function _addStepPending($cable, $triggerSource){
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

	// For step mode
	public function _emitNextExecution($afterNode=null){
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
			if($cables) $cables = $cables->toArray();

			return $this->_emitPaused($afterNode, $beforeNode, 0, null, $cables);
		}
		elseif($triggerSource === 3)
			return $this->_emitPaused($inputNode, $outputNode, $triggerSource, $cable);
		else return $this->_emitPaused($outputNode, $inputNode, $triggerSource, $cable);
	}

	public function _checkStepPending(){
		if(!$this->_hasStepPending) return;
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

			$node->update();

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
		if($this->_processing) return;
		if($this->pause && !$force) return;
		if($this->_checkStepPending()) return;
		if($this->stepMode) $this->pause = true;

		/** @var \Blackprint\Node */
		$next = $this->_next(); // next => node
		if($next == null) return;
		$this->_processing = true;

		$_proxyInput = null;
		$nextIface = &$next->iface;
		$next->_bpUpdating = true;

		if($next->partialUpdate)
			$next->partialUpdate = false;

		$skipUpdate = count($next->routes->in) !== 0;
		if($nextIface->_enum === \Blackprint\Nodes\Enums::BPFnMain){
			$_proxyInput = &$nextIface->_proxyInput;
			$_proxyInput->_bpUpdating = true;
		}

		try{
			if($next->partialUpdate){
				$portList = &$nextIface->input;
				foreach($portList as &$inp){
					if($inp->feature === \Blackprint\PortType::ArrayOf){
						if($inp->_hasUpdate !== false){
							$inp->_hasUpdate = false;

							if(!$skipUpdate){
								$cables = &$inp->cables;
								foreach($cables as &$cable){
									if(!$cable->_hasUpdate) continue;
									$cable->_hasUpdate = false;

									// Make this async if possible
									$next->update($cable);
								}
							}
						}
					}
					elseif($inp->_hasUpdateCable !== null){
						$cable = $inp->_hasUpdateCable;
						$inp->_hasUpdateCable = null;

						// Make this async if possible
						if(!$skipUpdate) $next->update($cable);
					}
				}
			}

			$next->_bpUpdating = false;
			if($_proxyInput) $_proxyInput->_bpUpdating = false;

			// Make this async if possible
			if(!$next->partialUpdate && !$skipUpdate) $next->_bpUpdate();
		} catch(\Exception $e) {
			if($_proxyInput) $_proxyInput->_bpUpdating = false;

			$this->clear();
			throw $e;
		} finally {
			if($this->stepMode) $this->_emitNextExecution($next);
		}

		$this->_processing = false;
		$this->next();
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