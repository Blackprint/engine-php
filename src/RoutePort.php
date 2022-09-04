<?php
namespace Blackprint;

class RoutePort {
	public $in = []; // Allow incoming route from multiple path
	public $out = null; // Only one route/path
	public $disableOut = false;
	public $disabled = false;
	public $_isPaused = false;
	public $isRoute = true;
	public $iface = null;

	public function __construct(&$iface){
		$this->iface = &$iface;
	}

	// Connect other route port (this .out to other .in port)
	public function routeTo(Interfaces &$iface=null){
		$this->out?->disconnect();

		if($iface === null){ // Route ended
			$cable = new Constructor\Cable($this, null);
			$cable->isRoute = true;
			$this->out = &$cable;
			return true;
		}

		$port = &$iface->node->routes;

		$cable = new Constructor\Cable($this, $port);
		$cable->isRoute = true;
		$cable->output = &$this;
		$this->out = &$cable;
		$port->in[] = &$cable; // ToDo: check if this empty if the connected cable was disconnected

		$cable->_connected();
		return true;
	}

	// Connect to input route
	public function connectCable($cable){
		if(in_array($cable, $this->in)) return false;
		if($this->iface->node->update === null){
			$cable->disconnect();
			throw new \Exception("node.update() was not defined for this node");
		}

		$this->in[] = &$cable;
		$cable->input = &$this;
		$cable->target = &$this;
		$cable->_connected();

		return true;
	}

	public function routeIn(&$cable){
		$node = &$this->iface->node;
		$node->update($cable);
		$node->routes->routeOut();
	}

	public function routeOut(){
		if($this->disableOut) return;
		if($this->out == null){
			if($this->iface->_enum === Nodes\Enums::BPFnOutput){
				$temp = null;
				return $this->iface->_funcMain->node->routes->routeIn($temp);
			}

			return;
		}

		$this->out->visualizeFlow();

		$targetRoute = &$this->out->input;
		if($targetRoute === null) return;

		$_enum = &$targetRoute->iface->_enum;
		$cable = &$this->out;

		if($_enum === null)
			return $targetRoute->routeIn($cable);

		if($_enum === Nodes\Enums::BPFnMain)
			return $targetRoute->iface->_proxyInput->routes->routeIn($cable);

		if($_enum === Nodes\Enums::BPFnOutput){
			$targetRoute->iface->node->update($cable);
			return $targetRoute->iface->_funcMain->node->routes->routeOut($cable);
		}

		return $targetRoute->routeIn($cable);
	}
}