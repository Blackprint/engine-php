<?php
namespace Blackprint\Constructor;

class OrderedExecution {
	public $index = 0;
	public $length = 0;
	public $initialSize = 30;
	public $pause = false;
	public $stepMode = false;

	/** @var array<\Blackprint\Node> */
	public $list;

	public function __construct($size=30){
		$this->initialSize = &$size;
		$this->list = array_fill(0, $size, null);
	}

	public function isPending(&$node){
		return in_array($node, $this->list);
	}

	public function clear(){
		$list = &$this->list;
		for ($i=$this->index, $n=$this->length; $i < $n; $i++) {
			$list[$i] = null;
		}

		$this->length = $this->index = 0;
	}

	public function add(&$node){
		if($this->isPending($node)) return;

		$i = $this->index + 1;
		if($i >= $this->initialSize || $this->length >= $this->initialSize)
			throw new \Exception("Execution order limit was exceeded");

		$this->list[$this->length++] = $node;
	}

	public function &_next(){
		if($this->index >= $this->length)
			return \Blackprint\Utils::$_null;

		$i = $this->index;
		$temp = $this->list[$this->index++];
		$this->list[$i] = null;

		if($this->index >= $this->length)
			$this->index = $this->length = 0;

		return $temp;
	}

	// Can be async function if the programming language support it
	public function next(){
		if($this->pause) return;
		if($this->stepMode) $this->pause = true;

		/** @var \Blackprint\Node */
		$next = $this->_next(); // next => node
		if($next == null) return;

		$_proxyInput = null;
		$nextIface = &$next->iface;
		$next->_bpUpdating = true;

		if($next->partialUpdate && $next->update == null)
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
					else if($inp->_hasUpdateCable !== null){
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
			if($this->stepMode) $this->pause = false;
		}
	}
}