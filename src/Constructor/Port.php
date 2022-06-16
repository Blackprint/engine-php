<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;
use \Blackprint\Port as PortType;

$NOOP = function(){};

class Port extends CustomEvent {
	public $name;
	public $type;
	public $cables = [];
	public $source;
	public $iface;
	public $default;
	public $value = null;
	public $sync = false;
	public $_ghost = false;
	public $feature = null;
	public $onConnect = false;

	public function __construct(&$portName, &$type, &$def, &$which, &$iface, &$feature){
		$this->name = $portName;
		$this->type = $type;
		$this->source = $which;
		$this->iface = &$iface;

		if($feature === false){
			$this->default = &$def;
			return;
		}

		// this.value;
		if($feature === \Blackprint\Port::Trigger_){
			$this->default = function() use(&$def) {
				$def($this);
				$this->iface->node->routes->routeOut();
			};
		}
		else $this->default = &$def;

		$this->feature = &$feature;
	}

	public function disconnectAll($hasRemote){
		$cables = &$this->cables;
		foreach ($cables as &$cable) {
			if($hasRemote)
				$cable->_evDisconnected = true;

			$cable->disconnect();
		}
	}

	public function createLinker(){
		if($this->type === Types::Function){
			if($this->source === 'output'){
				return function(){
					$cables = $this->cables;
					foreach ($cables as &$cable) {
						// $cable->_print();
						$target = $cable->owner === $this ? $cable->target : $cable->owner;
						$target->default($this);
					}
				};
			}

			return $this->default;
		}

		return function&($val = null){
			// Getter value
			if($val === null){
				// This port must use values from connected output
				if($this->source === 'input'){
					if(count($this->cables) === 0){
						if($this->feature === \Blackprint\Port::ArrayOf_){
							$temp = [];
							return $temp;
						}

						return $this->default;
					}

					// Flag current iface is requesting value to other iface
					$this->iface->_requesting = true;

					// Return single data
					if(count($this->cables) === 1){
						$temp = $this->cables[0]; # Don't use pointer

						if($temp->owner === $this)
							$target = &$temp->target;
						else $target = &$temp->owner;

						// Request the data first
						$target->iface->node->request($target, $this->iface);

						// echo "\n1. {$this->name} -> {$target->name} ({$target->value})";

						$this->iface->_requesting = false;

						if($this->feature === \Blackprint\Port::ArrayOf_){
							$temp = [$target->value ?? $target->default];
							return $temp;
						}

						if($target->value === null)
							return $target->default;
						return $target->value;
					}

					// Return multiple data as an array
					$cables = &$this->cables;
					$data = [];
					foreach ($cables as &$cable) {
						if($cable->owner === $this)
							$target = &$cable->target;
						else $target = &$cable->owner;

						// Request the data first
						$target->iface->node->request($target, $this->iface);

						// echo "\n2. {$this->name} -> {$target->name} ({$target->value})";

						if($target->value === null)
							$data[] = $target->default;
						else
							$data[] = $target->value;
					}

					$this->iface->_requesting = false;

					if($this->feature !== \Blackprint\Port::ArrayOf_)
						return $data[0];

					return $data;
				}

				if($this->value === null)
					return $this->default;
				return $this->value;
			}
			// else setter (only for output port)

			if($this->source === 'input')
				throw new \Exception("Can't set data to input port");

			$type = gettype($val);

			// Type check
			$pass = match($this->type){
				Types::Number => ($type === 'integer' || $type === 'double' || $type === 'float'),
				Types::Boolean => $type === 'boolean',
				Types::String => $type === 'string',
				Types::Array => $type === 'array',
				Types::Function => is_callable($val),
				Types::Object => $type === 'object',
				Types::Any => true,
				default => null,
			};

			if($pass === null) {
				if($type === 'object' && $this->type === $val::class){}
				else $pass = false;
			}

			if($pass === false) {
				$bpType = \Blackprint\getTypeName($this->type);
				throw new \Exception("Can't validate type of ID: $bpType == $type");
			}

			// Data type validation (ToDo: optimize)

			// echo "\n3. {$this->name} = {$val}";

			$this->value = &$val;
			$this->emit('value', $this);
			$this->sync();

			return $val;
		};
	}

	// Only for output port
	public function sync(){
		$cables = &$this->cables;
		foreach ($cables as &$cable) {
			if($cable->owner === $this)
				$target = &$cable->target;
			else $target = &$cable->owner;

			if($target->iface->_requesting === false)
				$target->iface->node->update($cable);

			$target->emit('value', $this);
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

	public function _cableConnectError($name, $obj){
		$msg = "Cable notify: {$name}";
		if($obj->iface) $msg += "\nIFace: {$obj->iface->namespace}";

		if($obj->port)
			$msg += "\nFrom port: {$obj->port->name}\n - Type: {$obj->port->source} ({$obj->port->type->name})";

		if($obj->target)
			$msg += "\nTo port: {$obj->target->name}\n - Type: {$obj->target->source} ({$obj->target->type->name})";

		$obj->message = $msg;
		$this->iface->node->_instance->emit($name, $obj);
	}

	public function connectCable(Cable $cable){
		if($cable->isRoute){
			$this->_cableConnectError('cable.not_route_port', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cable->owner
			]);

			$cable->disconnect();
			return;
		}

		if($cable->owner === $this) // It's referencing to same port
			return $cable->disconnect();

		// Remove cable if ...
		if(($cable->source === 'output' && $this->source !== 'input') // Output source not connected to input
			|| ($cable->source === 'input' && $this->source !== 'output')  // Input source not connected to output
			|| ($cable->source === 'property' && $this->source !== 'property')  // Property source not connected to property
		){
			$this->_cableConnectError('cable.wrong_pair', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cable->owner
			]);
			$cable->disconnect();
			return;
		}

		if($cable->owner->source === 'output'){
			if(($this->feature === PortType::ArrayOf_ && !PortType::ArrayOf_validate($this->type, $cable->owner->type))
			   || ($this->feature === PortType::Union_ && !PortType::Union_validate($this->type, $cable->owner->type))){
				$this->_cableConnectError('cable.wrong_type', [
					"cable" => &$cable,
					"iface" =>$this->iface,
					"port" => &$cable->owner,
					"target" => &$this
				]);
				return $cable->disconnect();
			}
		}

		else if($this->source === 'output'){
			if(($cable->owner->feature === PortType::ArrayOf_ && !PortType::ArrayOf_validate($cable->owner->type, $this->type))
			   || ($cable->owner->feature === PortType::Union_ && !PortType::Union_validate($cable->owner->type, $this->type))){
				$this->_cableConnectError('cable.wrong_type', [
					"cable" => &$cable,
					"iface" =>$this->iface,
					"port" => &$this,
					"target" => &$cable->owner
				]);
				return $cable->disconnect();
			}
		}

		// ToDo: recheck why we need to check if the constructor is a function
		$isInstance = true;
		if($cable->owner->type !== $this->type
		   && $cable->owner->type === Types::Function
		   && $this->type === Types::Function){
			if($cable->owner->source === 'output')
				$isInstance = $cable->owner->type instanceof $this->type;
			else $isInstance =  $this->type instanceof $cable->owner->type;
		}

		// Remove cable if type restriction
		if(!$isInstance || (
			   $cable->owner->type === Types::Function && $this->type !== Types::Function
			|| $cable->owner->type !== Types::Function && $this->type === Types::Function
		)){
			$this->_cableConnectError('cable.wrong_type_pair', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cable->owner
			]);
			$cable->disconnect();
			return;
		}

		// Restrict connection between function input/output node with variable node
		// Connection to similar node function IO or variable node also restricted
		// These port is created on runtime dynamically
		if($this->iface->_dynamicPort && $cable->owner->iface->_dynamicPort){
			$this->_cableConnectError('cable.unsupported_dynamic_port', [
				"cable" => &$cable,
				"port" => &$this,
				"target" => &$cable->owner
			]);
			$cable->disconnect();
			return;
		}

		$sourceCables = $cable->owner->cables;

		// Remove cable if there are similar connection for the ports
		foreach ($sourceCables as &$_cable) {
			if(in_array($_cable, $this->cables)){
				$this->_cableConnectError('cable.duplicate_removed', [
					"cable" => &$cable,
					"port" => &$this,
					"target" => &$cable->owner
				]);

				$cable->disconnect();
				return;
			}
		}

		if(($this->onConnect !== false && ($this->onConnect)($cable, $cable->owner))
		|| ($cable->owner->onConnect !== false && ($cable->owner->onConnect)($cable, $this)))
			return;

		// Put port reference to the cable
		$cable->target = &$this;

		if($cable->target->source === 'input'){
			/** @var Port */
			$inp = $cable->target;
			$out = $cable->owner;
		}
		else {
			/** @var Port */
			$inp = $cable->owner;
			$out = $cable->target;
		}

		// Remove old cable if the port not support array
		if($inp->feature !== PortType::ArrayOf_ && $inp->type !== Types::Function){
			$_cables = $inp->cables; // Cables in input port

			if(!empty($_cables)){
				$_cables = $_cables[0];

				if($_cables === $cable)
					$_cables = $_cables[1];

				if($_cables !== null){
					$inp->_cableConnectError('cable.replaced', [
						"cable" => &$cable,
						"oldCable" => &$_cables,
						"port" => &$inp,
						"target" => &$out,
					]);
					$_cables->disconnect();
				}
			}
		}

		// Connect this cable into port's cable list
		$this->cables[] = &$cable;
		// $cable->connecting();
		$cable->_connected();

		return true;
	}

	public function connectPort(Port $port){
		$cable = new Cable($this, $port);
		if($port->_ghost) $cable->_ghost = true;

		$port->cables[] = &$cable;
		if($this->connectCable($cable))
			return true;

		return false;
	}
}