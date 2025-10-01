<?php
namespace Blackprint;

class RoutePort {
	public $in = []; // Allow incoming route from multiple path
	public $out = null; // Only one route/path
	public $_outTrunk = null; // If have branch
	public $disableOut = false;
	public $disabled = false;
	public $_isPaused = false;
	public $isRoute = true;
	public $source = null;
	public $type = null;
	public $name = 'BPRoute';

	/** @var \Blackprint\Interfaces */
	public $iface = null;
	public $_scope = null;

	public function __construct(&$iface){
		$this->iface = &$iface;
		$this->_scope = $iface->_scope ?? null;
	}

	// Connect other route port (this .out to other .in port)
	public function routeTo(?Interfaces &$iface=null){
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
		if(!$cable->isRoute) throw new \Exception("Cable must be created from route port before can be connected to other route port. Please use .routeTo(interface) instead if possible.");
		if(in_array($cable, $this->in, true)) return false;
		// if($this->iface->node->update === null){
		// 	$cable->disconnect();
		// 	throw new \Exception("node.update() was not defined for this node");
		// }

		$this->in[] = &$cable;
		$cable->input = &$this;
		$cable->target = &$this;
		$cable->isRoute = true;
		$cable->_connected();

		return true;
	}

	public function routeIn(&$_cable=null, $_force=false){
		$node = &$this->iface->node;
		if($node->disablePorts) return;

		// Add to execution list if the OrderedExecution is in Step Mode
		$executionOrder = &$node->instance->executionOrder;
		if($executionOrder->stepMode && $_cable && !$_force){
			$executionOrder->_addStepPending($_cable, 1);
			return;
		}

		if($this->iface->_enum !== \Blackprint\Nodes\Enums::BPFnInput)
			$node->_bpUpdate();
		else $node->routes->routeOut();
	}

	public function routeOut(){
		if($this->disableOut) return;

		if($this->iface->node->disablePorts) return;
		if($this->out == null){
			if($this->iface->_enum === Nodes\Enums::BPFnOutput)
				return $this->iface->parentInterface->node->routes->routeIn();

			return;
		}


		$targetRoute = &$this->out->input;
		if($targetRoute === null) return;

		$_enum = &$targetRoute->iface->_enum;
		if($_enum === null)
			return $targetRoute->routeIn($this->out);

		// if($_enum === Nodes\Enums::BPFnMain)
		// 	return $targetRoute->iface->_proxyInput->routes->routeIn($this->out);

		// if($_enum === Nodes\Enums::BPFnOutput){
		// 	$targetRoute->iface->node->update(\Blackprint\Utils::$_null);
		// 	return $targetRoute->iface->parentInterface->node->routes->routeOut();
		// }

		return $targetRoute->routeIn($this->out);
	}
}