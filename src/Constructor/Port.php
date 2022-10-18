<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;
use \Blackprint\PortType;
use \Blackprint\Port as PortFeature;
use \Blackprint\Nodes\Enums;

enum Args {
	case NoArgs;
}

$NOOP = function(){};

class Port extends CustomEvent {
	/** @var string */
	public $name;
	public $type;

	/** @var array<Cable> */
	public $cables = [];
	public $source;
	
	/** @var \Blackprint\Interfaces */
	public $iface;
	public $default;
	public $value = null;
	public $_sync = true;
	public $allowResync = false; // Retrigger connected node's .update when the output value is similar
	public $feature = null;
	public $onConnect = false;
	public $splitted = false;
	public $struct = null;
	public $isRoute = false;

	public $_ghost = false;
	public $_name = null;
	public $_callAll = null;
	public $_cache = null;
	public $_func = null;
	public $_hasUpdate = false;
	public $_hasUpdateCable = null;

	/** @var \Blackprint\Node */
	public $_node = null;

	public function __construct(&$portName, &$type, &$def, &$which, &$iface, &$feature){
		$this->name = &$portName;
		$this->type = &$type;
		$this->source = &$which;
		$this->iface = &$iface;
		$this->_node = &$iface->node;

		if($feature === false){
			$this->default = &$def;
			return;
		}

		// this.value;
		if($feature === PortType::Trigger){
			$this->default = function() use(&$def) {
				$def($this);

				if($this->iface->_enum !== Enums::BPFnMain)
					$this->iface->node->routes->routeOut();
			};
		}
		elseif($feature === PortType::StructOf)
			$this->struct = &$def;
		else $this->default = &$def;

		$this->feature = &$feature;
	}

	public function _getPortFeature(){
		if($this->feature === PortType::ArrayOf){
			return PortFeature::ArrayOf($this->type);
		}
		elseif($this->feature === PortType::Default){
			return PortFeature::Default($this->type, $this->default);
		}
		elseif($this->feature === PortType::Trigger){
			return PortFeature::Trigger($this->_func);
		}
		elseif($this->feature === PortType::Union){
			return PortFeature::Union($this->type);
		}

		throw new \Exception("Port feature not recognized");
	}

	public function disconnectAll($hasRemote=false){
		$cables = &$this->cables;
		foreach ($cables as &$cable) {
			if($hasRemote)
				$cable->_evDisconnected = true;

			$cable->disconnect();
		}
	}

	public function createLinker(){
		// Callable port
		if($this->source === 'output' && ($this->type === Types::Function || $this->type === Types::Route)){
			$this->_sync = false;

			if($this->type === Types::Function)
				return $this->_callAll = createCallablePort($this);
			else return $this->_callAll = createCallableRoutePort($this);
		}

		if($this->feature === PortType::Trigger)
			return $this->default;
	}

	// Only for output port
	public function sync(){
		// Check all connected cables, if any node need to synchronize
		$cables = &$this->cables;
		$thisNode = &$this->_node;
		$skipSync = $thisNode->routes->out !== null;
		$instance = &$thisNode->instance;

		$singlePortUpdate = false;
		if(!$thisNode->_bpUpdating){
			$singlePortUpdate = true;
			$thisNode->_bpUpdating = true;
		}

		if($thisNode->routes->out !== null
		   && $thisNode->iface->_enum === Enums::BPFnMain
		   && $thisNode->iface->bpInstance->executionOrder->length !== 0){
			$skipSync = true;
		}

		foreach ($cables as &$cable) {
			$inp = &$cable->input;
			if($inp === null) continue;
			$inp->_cache = null;

			$inpIface = &$inp->iface;
			$temp = new \Blackprint\EvPortValue($inp, $this, $cable);
			$inp->emit('value', $temp);
			$inpIface->emit('port.value', $temp);

			if($skipSync === false && $thisNode->_bpUpdating){
				if($inpIface->node->partialUpdate){
					if($inp->feature === \Blackprint\PortType::ArrayOf){
						$inp->_hasUpdate = true;
						$cable->_hasUpdate = true;
					}
					else $inp->_hasUpdateCable = $cable;
				}

				if($inpIface->_requesting === false)
					$instance->executionOrder->add($inp->_node);
			}

			// Skip sync if the node has route cable
			if($skipSync || $thisNode->_bpUpdating) continue;

			// echo "\n4. {$inp->name} = {$inpIface->title}, {$inpIface->_requesting}";

			$node = &$inpIface->node;
			if($inpIface->_requesting === false && count($node->routes->in) === 0)
				$node->_bpUpdate();
		}

		if($singlePortUpdate){
			$thisNode->_bpUpdating = false;
			$thisNode->instance->executionOrder->next();
		}
	}

	public function disableCables($enable=false){
		$cables = &$this->cables;

		if($enable === true) foreach ($cables as &$cable)
			$cable->disabled = 1;
		elseif($enable === false) foreach ($cables as &$cable)
			$cable->disabled = 0;
		else foreach ($cables as &$cable)
			$cable->disabled += $enable;
	}

	public function _cableConnectError($name, $obj, $severe=true){
		$msg = "Cable notify: {$name}";
		if(isset($obj['iface'])) $msg .= "\nIFace: {$obj['iface']->namespace}";

		if(isset($obj['port']))
			$msg .= "\nFrom port: {$obj['port']->name} (iface: {$obj['port']->iface->namespace})\n - Type: {$obj['port']->source} ({$obj['port']->type->name})";

		if(isset($obj['target']))
			$msg .= "\nTo port: {$obj['target']->name} (iface: {$obj['target']->iface->namespace})\n - Type: {$obj['target']->source} ({$obj['target']->type->name})";

		$obj['message'] = &$msg;
		$instance = &$this->iface->node->instance;

		if($severe && $instance->throwOnError)
			throw new \Exception($msg."\n\n");

		$temp = (object) $obj;
		$instance->emit($name, $temp);
	}

	public function connectCable(Cable &$cable){
		if($cable->isRoute){
			$this->_cableConnectError('cable.not_route_port', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cable->owner
			]);

			$cable->disconnect();
			return false;
		}

		$cableOwner = &$cable->owner;

		if($cableOwner === $this){ // It's referencing to same port
			$cable->disconnect();
			return false;
		}

		if(($this->onConnect !== false && ($this->onConnect)($cable, $cableOwner))
		|| ($cableOwner->onConnect !== false && ($cableOwner->onConnect)($cable, $this)))
			return false;

		// Remove cable if ...
		if(($cable->source === 'output' && $this->source !== 'input') // Output source not connected to input
			|| ($cable->source === 'input' && $this->source !== 'output')  // Input source not connected to output
			// || ($cable->source === 'property' && $this->source !== 'property')  // Property source not connected to property
		){
			$this->_cableConnectError('cable.wrong_pair', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cableOwner
			]);
			$cable->disconnect();
			return false;
		}

		if($cableOwner->source === 'output'){
			if(($this->feature === PortType::ArrayOf && !PortFeature::ArrayOf_validate($this->type, $cableOwner->type))
			   || ($this->feature === PortType::Union && !PortFeature::Union_validate($this->type, $cableOwner->type))){
				$this->_cableConnectError('cable.wrong_type', [
					"cable" => &$cable,
					"iface" => &$this->iface,
					"port" => &$cableOwner,
					"target" => &$this
				]);

				$cable->disconnect();
				return false;
			}
		}

		elseif($this->source === 'output'){
			if(($cableOwner->feature === PortType::ArrayOf && !PortFeature::ArrayOf_validate($cableOwner->type, $this->type))
			   || ($cableOwner->feature === PortType::Union && !PortFeature::Union_validate($cableOwner->type, $this->type))){
				$this->_cableConnectError('cable.wrong_type', [
					"cable" => &$cable,
					"iface" => &$this->iface,
					"port" => &$this,
					"target" => &$cableOwner
				]);

				$cable->disconnect();
				return false;
			}
		}

		// ToDo: recheck why we need to check if the constructor is a function
		$isInstance = true;
		if($cableOwner->type !== $this->type
		   && $cableOwner->type === Types::Function
		   && $this->type === Types::Function){
			if($cableOwner->source === 'output')
				$isInstance = $cableOwner->type instanceof $this->type;
			else $isInstance =  $this->type instanceof $cableOwner->type;
		}

		// Remove cable if type restriction
		if(!$isInstance || (
			   $cableOwner->type === Types::Function && $this->type !== Types::Function
			|| $cableOwner->type !== Types::Function && $this->type === Types::Function
		)){
			$this->_cableConnectError('cable.wrong_type_pair', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cableOwner
			]);

			$cable->disconnect();
			return false;
		}

		// Restrict connection between function input/output node with variable node
		// Connection to similar node function IO or variable node also restricted
		// These port is created on runtime dynamically
		if($this->iface->_dynamicPort && $cableOwner->iface->_dynamicPort){
			$this->_cableConnectError('cable.unsupported_dynamic_port', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cableOwner
			]);

			$cable->disconnect();
			return false;
		}

		$sourceCables = &$cableOwner->cables;

		// Remove cable if there are similar connection for the ports
		foreach ($sourceCables as &$_cable) {
			if(in_array($_cable, $this->cables, true)){
				$this->_cableConnectError('cable.duplicate_removed', [
					"cable" => &$cable,
					"port" => &$this,
					"target" => &$cableOwner
				], false);

				$cable->disconnect();
				return false;
			}
		}

		// Put port reference to the cable
		$cable->target = &$this;

		if($cable->target->source === 'input'){
			/** @var Port */
			$inp = &$cable->target;
			$out = &$cableOwner;
		}
		else {
			/** @var Port */
			$inp = &$cableOwner;
			$out = &$cable->target;
		}

		// Remove old cable if the port not support array
		if($inp->feature !== PortType::ArrayOf && $inp->type !== Types::Function){
			$cables = &$inp->cables; // Cables in input port

			if(!empty($cables)){
				$temp = $cables[0];

				if($temp === $cable)
					$temp = $cables[1] ?? null;

				if($temp !== null){
					$inp->_cableConnectError('cable.replaced', [
						"cable" => &$cable,
						"oldCable" => &$temp,
						"port" => &$inp,
						"target" => &$out,
					], false);
					$temp->disconnect();
				}
			}
		}

		// Connect this cable into port's cable list
		$this->cables[] = &$cable;
		// $cable->connecting();
		$cable->_connected();

		return true;
	}

	public function connectPort(Port &$port){
		$cable = new Cable($port, $this);
		if($port->_ghost) $cable->_ghost = true;

		$port->cables[] = &$cable;
		return $this->connectCable($cable);
	}
}

function createCallablePort($port){
	return function() use(&$port) {
		if($port->iface->node->disablePorts) return;

		$cables = &$port->cables;
		foreach ($cables as &$cable) {
			$target = &$cable->input;
			if($target === null)
				continue;

			if($target->_name != null)
				($target->iface->_funcMain->node->output[$target->_name->name])();
			else ($target->iface->input[$target->name]->default)();
		}

		$port->emit('call');
	};
}

function createCallableRoutePort($port){
	$port->isRoute = true;
	$port->iface->node->routes->disableOut = true;

	return function() use(&$port) {
		$cable = &$port->cables[0];
		if($cable === null) return;

		$cable->input->routeIn();
	};
}