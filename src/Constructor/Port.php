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
	public $_isSlot = false;
	public $struct = null;
	public $isRoute = false;

	public $_ghost = false;
	public $_name = null;
	public $__call = null;
	public $_callDef = null;
	public $_cache = null;
	public $_config = null;
	public $_func = null;
	public $_hasUpdate = false;
	public $_hasUpdateCable = null;
	public $_cable = null;
	public $_calling = false;

	/** @var \Blackprint\Node */
	public $_node = null;

	public function __construct(&$portName, &$type, &$def, &$which, &$iface, &$feature){
		$this->name = &$portName;
		$this->type = &$type;
		$this->source = &$which;
		$this->iface = &$iface;
		$this->_node = &$iface->node;

		$this->_isSlot = $type === Types::Slot;

		if($feature === false){
			$this->default = &$def;
			return;
		}

		// $this->value;
		if($feature === PortType::Trigger){
			// if($def === $this->_callAll) throw new \Exception("Logic error");

			$this->_callDef = &$def;
			$this->default = fn()=> $this->_call();
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

	public function _call(&$cable=null){
		$iface = $this->iface;

		if($cable == null){
			if($this->_cable == null)
				$this->_cable = new Cable($this, $this);

			$cable = &$this->_cable;
		}

		if($this->_calling){
			$input = &$cable->input;
			$output = &$cable->output;
			throw new \Exception("Circular call stack detected:\nFrom: {$output->iface->title}->{$output->name}\nTo: {$input->iface->title}->{$input->name})");
		}

		$this->_calling = $cable->_calling = true;
		try {
			($this->_callDef)($this);
		}
		finally {
			$this->_calling = $cable->_calling = false;
		}

		if($iface->_enum !== \Blackprint\Nodes\Enums::BPFnMain)
			$iface->node->routes->routeOut();
	}

	public function _callAll(){
		if($this->type === Types::Route){
			$cables = &$this->cables;
			$cable = &$cables[0];

			if($cable === null) return;
			if($cable->hasBranch) $cable = &$cables[1];

			// if(Blackprint->settings->visualizeFlow)
			// 	$cable->visualizeFlow();

			if($cable->input == null) return;
			$cable->input->routeIn($cable);
		}
		else {
			$node = $this->iface->node;
			if($node->disablePorts) return;
			$executionOrder = &$node->instance->executionOrder;

			foreach ($this->cables as &$cable) {
				$target = &$cable->input;
				if($target === null) continue;

				// if(Blackprint->settings->visualizeFlow && !executionOrder->stepMode)
				// 	$cable->visualizeFlow();

				if($target->_name != null)
					$target->iface->parentInterface->node->iface->output[$target->_name->name]->_callAll();
				else {
					if($executionOrder->stepMode){
						$executionOrder->_addStepPending($cable, 2);
						continue;
					}

					$target->iface->input[$target->name]->_call($cable);
				}
			}

			$this->emit('call');
		}
	}

	public function createLinker(){
		// Callable port
		if($this->source === 'output' && ($this->type === Types::Trigger || $this->type === Types::Route)){
			// Disable sync
			$this->_sync = false;

			if($this->type !== Types::Trigger){
				$this->isRoute = true;
				$this->iface->node->routes->disableOut = true;
			}

			return fn() => $this->_callAll();
		}

		// "var prepare = " is in PortLink.php (offsetGet)
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
		   && $thisNode->iface->bpInstance->executionOrder->isPending()){
			$skipSync = true;
		}

		foreach ($cables as &$cable) {
			$inp = &$cable->input;
			if($inp === null) continue;
			$inp->_cache = null;

			if($inp->_cache != null && $instance->executionOrder->stepMode)
				$inp->_oldCache = &$inp->_cache;

			$inpIface = &$inp->iface;
			$inpNode = &$inpIface->node;
			$temp = new \Blackprint\EvPortValue($inp, $this, $cable);
			$inp->emit('value', $temp);
			$inpIface->emit('port.value', $temp);

			$nextUpdate = $inpIface->_requesting === false && count($inpNode->routes->in) === 0;
			if($skipSync === false && $thisNode->_bpUpdating){
				if($inpNode->partialUpdate){
					if($inp->feature === \Blackprint\PortType::ArrayOf){
						$inp->_hasUpdate = true;
						$cable->_hasUpdate = true;
					}
					else $inp->_hasUpdateCable = $cable;
				}

				if($nextUpdate)
					$instance->executionOrder->add($inp->_node, $cable);
			}

			// Skip sync if the node has route cable
			if($skipSync || $thisNode->_bpUpdating) continue;

			// echo "\n4. {$inp->name} = {$inpIface->title}, {$inpIface->_requesting}";

			if($inpNode->update && $nextUpdate)
				$inpNode->_bpUpdate($cable);
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
		$instance->_emit($name, $temp);
	}

	public function assignType($type){
		if($type == null) throw new \Exception("Can't set type with undefined");

		if($this->type !== Types::Slot){
			var_dump($this->type);
			throw new \Exception("Can only assign type to port with 'Slot' type, this port already has type");
		}

		// Skip if the assigned type is also Slot type
		if($type === Types::Slot) return;

		if($type === Types::Trigger && $this->source === 'input') {
			throw new \Exception("Assigning Trigger type must use PortFeatures, and not only Types.Trigger");
		}

		// Check current output value type
		if($this->value != null){
			$gettype = \Blackprint\_getValueType($this->value);
			$pass = false;

			if($gettype === Types::Object){
				if($this->value instanceof $type) $pass = true;
			}
			else if($type === Types::Any || $type === $gettype){
				$pass = true;
			}

			if($pass === false) throw new \Exception("The output value of this port is not instance of type that will be assigned: {$gettype->name} is not instance of {$type->name}");
		}

		// Check connected cable's type
		foreach ($this->cables as &$cable) {
			$inputPort = &$cable->input;
			if($inputPort == null) continue;

			$portType = &$inputPort->type;
			if($portType === Types::Any) 1; // pass
			elseif($portType === $type) 1; // pass
			elseif($portType === Types::Slot) 1; // pass
			elseif(\Blackprint\isTypeExist($portType) || \Blackprint\isTypeExist($type)){
				throw new \Exception("The target port's connection of this port is not instance of type that will be assigned: {$portType->name} is not instance of {$type->name}");
			}
			else {
				$clazz = (is_array($type) && $type['type'] != null ? $type['type'] : $type)::class;
				if(!(is_subclass_of($portType, $clazz))){
					throw new \Exception("The target port's connection of this port is not instance of type that will be assigned: {$portType} is not instance of {$clazz}");
				}
			}
		}

		if(is_array($type) && isset($type['feature'])){
			if($this->source === 'output'){
				if($type['feature'] === PortType::Union)
					$type = Types::Any;
				elseif($type['feature'] === PortType::Trigger)
					$type = $type['type'];
				elseif($type['feature'] === PortType::ArrayOf)
					$type = Types::Array;
				elseif($type['feature'] === PortType::Default)
					$type = &$type['type'];
			}
			else {
				if($type['type'] == null) throw new \Exception("Missing type for port feature");

				$this->feature = &$type['feature'];
				$this->type = &$type['type'];

				if($type['feature'] === PortType::StructOf){
					$this->struct = &$type['value'];
					// $this->classAdd .= "BP-StructOf ";
				}
			}

			// if($type->virtualType != null)
			// 	$this->virtualType = &$type->virtualType;
		}
		else $this->type = &$type;

		// Trigger `connect` event for every connected cable
		foreach ($this->cables as &$cable) {
			if($cable->disabled || $cable->target == null) continue;
			$cable->_connected();
		}

		$this->_config = &$type;
		$this->emit('type.assigned');
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
			   $cableOwner->type === Types::Trigger && $this->type !== Types::Trigger
			|| $cableOwner->type !== Types::Trigger && $this->type === Types::Trigger
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
		if($inp->feature !== PortType::ArrayOf && $inp->type !== Types::Trigger){
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
		$cable->connecting();

		return true;
	}

	public function connectPort(Port &$port){
		if($this->_node->instance->_locked_)
			throw new \Exception("This instance was locked");

		if($port instanceof Port){
			$cable = new Cable($port, $this);
			if($port->_ghost) $cable->_ghost = true;

			$port->cables[] = &$cable;
			if($this->connectCable($cable))
				return true;

			return false;
		}
		elseif($port instanceof \Blackprint\RoutePort){
			if($this->source === 'output'){
				$cable = new Cable($this, $this);
				$this->cables[] = &$cable;
				return $port->connectCable($cable);
			}
			throw new \Exception("Unhandled connection for RoutePort");
		}
		throw new \Exception("First parameter must be instance of Port or RoutePort");
	}
}