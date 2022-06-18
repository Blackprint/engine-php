<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;
use \Blackprint\PortType;
use \Blackprint\Port as PortFeature;

enum Args {
	case NoArgs;
}

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
	public $_callAll = null;
	public $_cache = null;

	public function __construct(&$portName, &$type, &$def, &$which, &$iface, &$feature){
		$this->name = &$portName;
		$this->type = &$type;
		$this->source = &$which;
		$this->iface = &$iface;

		if($feature === false){
			$this->default = &$def;
			return;
		}

		// this.value;
		if($feature === PortType::Trigger){
			$this->default = function() use(&$def) {
				$def($this);
				$this->iface->node->routes->routeOut();
			};
		}
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
			return PortFeature::Trigger($this->type);
		}
		elseif($this->feature === PortType::Union){
			return PortFeature::Union($this->type);
		}

		throw new \Exception("Port feature not recognized");
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
		// Callable port
		if($this->source === 'output' && ($this->type === Types::Function || $this->type === Types::Route)){
			$this->sync = false;

			if($this->type === Types::Function)
				return $this->_callAll = createCallablePort($this);
			else return $this->_callAll = createCallableRoutePort($this);
		}

		if($this->feature === PortType::Trigger)
			return $this->default;

		// Getter/Setter (no args == getter, with args == setter)
		return function&($val = Args::NoArgs) {
			// Getter value
			if($val === Args::NoArgs){
				// This port must use values from connected output
				if($this->source === 'input'){
					$cableLen = count($this->cables);

					if($cableLen === 0)
						return $this->default;

					if($this->_cache !== null) return $this->_cache;

					// Flag current iface is requesting value to other iface
					$this->iface->_requesting = true;

					// Return single data
					if($cableLen === 1){
						$cable = $this->cables[0]; # Don't use pointer

						if($cable->connected === false || $cable->disabled){
							$this->iface->_requesting = null;
							if($this->feature === PortType::ArrayOf)
								return $this->_cache = [];

							return $this->_cache = $this->default;
						}

						$output = &$cable->output;

						// Request the data first
						$output->iface->node->request($output, $this->iface);

						// echo "\n1. {$this->name} -> {$output->name} ({$output->value})";

						$this->iface->_requesting = false;

						if($this->feature === PortType::ArrayOf){
							$this->_cache = [];

							if($output->value != null)
								$this->_cache[] = &$output->value;

							return $this->_cache;
						}

						$this->_cache = $output->value ?? $this->default;
						return $this->_cache;
					}

					$isNotArrayPort = $this->feature !== PortType::ArrayOf;

					// Return multiple data as an array
					$cables = &$this->cables;
					$data = [];
					foreach ($cables as &$cable) {
						if($cable->connected === false || $cable->disabled)
							continue;

						$output = &$cable->output;

						// Request the data first
						$output->iface->node->request($output, $this->iface);

						// echo "\n2. {$this->name} -> {$output->name} ({$output->value})";

						if($isNotArrayPort){
							$this->iface->_requesting = null;
							return $this->_cache = $output->value ?? $this->default;
						}

						$data[] = $output->value ?? $this->default;
					}

					$this->iface->_requesting = false;

					$this->_cache = &$data;
					return $data;
				}

				return $this->value;
			}
			// else setter (only for output port)

			if($this->value === $val || $this->iface->node->disablePorts)
				return $val;

			if($this->source === 'input')
				throw new \Exception("Can't set data to input port");

			if($val == null)
				$val = $this->default;
			else{
				// Data type validation (ToDo: optimize)
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
					if($type === 'object' && $this->type === $val::class){} // pass
					else $pass = false;
				}

				if($pass === false) {
					$bpType = \Blackprint\getTypeName($this->type);
					throw new \Exception("Can't validate type of ID: $bpType == $type");
				}
			}

			// echo "\n3. {$this->name} = {$val}";

			$this->value = &$val;

			$temp = new \Blackprint\EvPortSelf($this);
			$this->emit('value', $temp);
			$this->sync();

			return $val;
		};
	}

	// Only for output port
	public function sync(){
		$cables = &$this->cables;
		$skipSync = $this->iface->node->routes->out !== null;

		foreach ($cables as &$cable) {
			$inp = &$cable->input;
			if($inp === null) continue;
			$inp->_cache = null;

			$temp = new \Blackprint\EvPortValue($inp, $this, $cable);
			$inp->emit('value', $temp);
			$inp->iface->emit('port.value', $temp);

			// Skip sync if the node has route cable
			if($skipSync) return;

			$node = $inp->iface->node;
			if($inp->iface->_requesting === null){
				$node->update($inp, $this, $cable);

				if($node->iface->enum !== \Blackprint\Nodes\Enums::BPFnMain){
					$node->routes->routeOut();
				}
				else {
					$node->iface->_proxyInput->routes->routeOut();
				}
			}
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
		if($obj['iface']) $msg += "\nIFace: {$obj['iface']->namespace}";

		if($obj['port'])
			$msg += "\nFrom port: {$obj['port']->name}\n - Type: {$obj['port']->source} ({$obj['port']->type->name})";

		if($obj['target'])
			$msg += "\nTo port: {$obj['target']->name}\n - Type: {$obj['target']->source} ({$obj['target']->type->name})";

		$obj['message'] = $msg;
		$instance = &$this->iface->node->_instance;

		if($severe && $instance->throwOnError)
			throw new \Exception($msg);

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

		if($cable->owner === $this){ // It's referencing to same port
			$cable->disconnect();
			return false;
		}

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
			return false;
		}

		if($cable->owner->source === 'output'){
			if(($this->feature === PortType::ArrayOf && !PortFeature::ArrayOf_validate($this->type, $cable->owner->type))
			   || ($this->feature === PortType::Union && !PortFeature::Union_validate($this->type, $cable->owner->type))){
				$this->_cableConnectError('cable.wrong_type', [
					"cable" => &$cable,
					"iface" => &$this->iface,
					"port" => &$cable->owner,
					"target" => &$this
				]);

				$cable->disconnect();
				return false;
			}
		}

		else if($this->source === 'output'){
			if(($cable->owner->feature === PortType::ArrayOf && !PortFeature::ArrayOf_validate($cable->owner->type, $this->type))
			   || ($cable->owner->feature === PortType::Union && !PortFeature::Union_validate($cable->owner->type, $this->type))){
				$this->_cableConnectError('cable.wrong_type', [
					"cable" => &$cable,
					"iface" => &$this->iface,
					"port" => &$this,
					"target" => &$cable->owner
				]);

				$cable->disconnect();
				return false;
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
			return false;
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
			return false;
		}

		$sourceCables = $cable->owner->cables;

		// Remove cable if there are similar connection for the ports
		foreach ($sourceCables as &$_cable) {
			if(in_array($_cable, $this->cables)){
				$this->_cableConnectError('cable.duplicate_removed', [
					"cable" => &$cable,
					"port" => &$this,
					"target" => &$cable->owner
				], false);

				$cable->disconnect();
				return false;
			}
		}

		if(($this->onConnect !== false && ($this->onConnect)($cable, $cable->owner))
		|| ($cable->owner->onConnect !== false && ($cable->owner->onConnect)($cable, $this)))
			return false;

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
		if($this->connectCable($cable))
			return true;

		return false;
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

			($target->iface->input[$target->name]->default)();
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