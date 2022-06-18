<?php
namespace Blackprint;

// ToDo: still unfinished
class RoutePort {
	public $in = []; // Allow incoming route from multiple path
	public $out = null; // Only one route/path
	public $disableOut = false;
	public $disabled = false;
	public $_isPaused = false;
	public $iface = null;

	public function __construct(&$iface){
		$this->iface = &$iface;
	}

	// For creating output cable
	public function connectPort(RoutePort $port){
		if($port === $this) return false;
		$this->out->disconnect();

		$cable = new Constructor\Cable($port, $this); // ToDo: null?
		$this->out = &$cable;
		$port->in[] = &$cable;

		$cable->isRoute = true;
		$cable->output = &$this;
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