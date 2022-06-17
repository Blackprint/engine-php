<?php
namespace Blackprint;

class RoutePort {
	public $in = []; // Allow incoming route from multiple path
	public $out = null; // Only one route/path
	public $disableOut = false;
	public $disabled = false;
	public $_isPaused = false;
	public $iface = null;
	public $_scope = null;

	public function __construct(&$iface){
		$this->iface = &$iface;
		$this->_scope = &$iface->_scope;
	}

	// For creating output cable
	public function &createCable($cable){
		$this->out->disconnect();
		if(!$cable)
			$cable = $this->out = new Constructor\Cable($this, null); // ToDo: null?

		$cable->isRoute = true;
		$cable->output = &$this;

		return $cable;
	}

	// Connect to input route
	public function connectCable($cable){
		if(in_array($cable, $this->in)) return false;
		if($this->iface->node->update === null){
			$cable->disconnect();
			throw new \Exception("node.update() was not defined for this node");
		}

		$this->in[] = &$cable;
		$cable->target = $cable->input = &$this;
		$cable->connected = true;

		return true;
	}

	public function routeIn(){
		$node = &$this->iface->node;
		$node->update();
		$node->routes->routeOut();
	}

	public function routeOut(){
		if($this->disableOut) return;
		if($this->out == null){
			if($this->iface->enum === Nodes\Enums::BPFnOutput)
				return $this->iface->_funcMain->node->routes->routeIn();

			return;
		}

		$this->out->visualizeFlow();

		$targetRoute = $this->out->input;
		if($targetRoute == null) return;

		$_enum = $targetRoute->iface->enum;

		if($_enum === null)
			return $targetRoute->routeIn();

		if($_enum === Nodes\Enums::BPFnMain)
			return $targetRoute->iface->_proxyInput->routes->routeIn();

		if($_enum === Nodes\Enums::BPFnOutput){
			$targetRoute->iface->node->update();
			return $targetRoute->iface->_funcMain->node->routes->routeOut();
		}

		return $targetRoute->routeIn();
	}
}