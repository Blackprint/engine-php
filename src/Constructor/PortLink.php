<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;
use \Blackprint\Utils;
use \Blackprint\PortType;
use \Blackprint\Port as PortFeature;

class PortLink extends \ArrayObject {
	/** @var \Blackprint\Interfaces */
	public $_iface;
	public $_which;

	/** @var array<Port> */
	private $ifacePort;

	/** @param object $portMeta */
	public function __construct(&$node, $which, $portMeta){
		$iface = &$node->iface;
		$this->_iface = &$iface;
		$this->_which = &$which;

		$link = [];
		$iface->{$which} = &$link;
		$this->ifacePort = &$link;

		// $node->{$which} = &$link;

		// Create linker for all port
		foreach($portMeta as $portName => &$val){
			$this->_add($portName, $val);
		}
	}

	public function &offsetGet($key){
		$port = &$this->ifacePort[$key];
		if($port === null) throw new \Exception("Port '$key' was not found");

		// This port must use values from connected output
		if($port->source === 'input'){
			if($port->_cache !== null) return $port->_cache;

			$cableLen = count($port->cables);
			if($cableLen === 0)
				return $port->default;

			$portIface = &$port->iface;

			// Flag current iface is requesting value to other iface
			$portIface->_requesting = true;

			// Return single data
			if($cableLen === 1){
				$cable = $port->cables[0]; # Don't use pointer

				if($cable->connected === false || $cable->disabled){
					$portIface->_requesting = false;
					if($port->feature === PortType::ArrayOf)
						$port->_cache = [];
					else $port->_cache = $port->default;

					return $port->_cache;
				}

				$output = &$cable->output;

				// Request the data first
				if($output->value === null){
					$node = &$output->iface->node;
					$executionOrder = &$node->instance->executionOrder;

					if($executionOrder->stepMode && $node->request != null){
						$executionOrder->_addStepPending($cable, 3);
						return;
					}

					$output->iface->node->request($cable);
				}

				// echo "\n1. {$port->name} -> {$output->name} ({$output->value})";

				$portIface->_requesting = false;

				if($port->feature === PortType::ArrayOf){
					$port->_cache = [];

					if($output->value != null)
						$port->_cache[] = &$output->value;

					return $port->_cache;
				}

				$port->_cache = $output->value ?? $port->default;
				return $port->_cache;
			}

			$isNotArrayPort = $port->feature !== PortType::ArrayOf;

			// Return multiple data as an array
			$cables = &$port->cables;
			$data = [];
			foreach ($cables as &$cable) {
				if($cable->connected === false || $cable->disabled)
					continue;

				$output = &$cable->output;

				// Request the data first
				if($output->value === null)
					$output->iface->node->request($cable);

				// echo "\n2. {$port->name} -> {$output->name} ({$output->value})";

				if($isNotArrayPort){
					$portIface->_requesting = false;
					$port->_cache = $output->value ?? $port->default;
					return $port->_cache;
				}

				$data[] = $output->value;
			}

			$portIface->_requesting = false;

			$port->_cache = &$data;
			return $data;
		}
		// else output ports

		// This may get called if the port is lazily assigned with Slot port feature
		if($port->type === Types::Trigger){
			if($port->__call === null)
				$port->__call = fn() => $port->_callAll();

			return $port->__call;
		}

		return $port->value;
	}

	public function offsetSet($key, $val): void{
		$this->setByRef($key, $val);
	}

	public function setByRef($key, &$val) {
		$port = &$this->ifacePort[$key];

		if($port === null)
			throw new \Exception("Port {$this->_which} ('$key') was not found on node with namespace '{$this->_iface->namespace}'");

		// setter (only for output port)
		if($port->iface->node->disablePorts || (!($port->splitted || $port->allowResync) && $port->value === $val))
			return;
		
		if($port->source === 'input')
			throw new \Exception("Can't set data to input port");

		if($val == null)
			$val = &$port->default;
		else{
			// Data type validation (ToDo: optimize)
			$type = gettype($val);

			// Type check
			$pass = match($port->type){
				Types::Number => ($type === 'integer' || $type === 'double' || $type === 'float'),
				Types::Boolean => $type === 'boolean',
				Types::String => $type === 'string',
				Types::Array => $type === 'array',
				Types::Function => is_callable($val),
				Types::Object => $type === 'object',
				Types::Any => true,
				Types::Slot => throw new \Exception("Port type need to be assigned before giving any value"),
				default => null,
			};

			if($pass === null) {
				if($type === 'object' && $port->type === $val::class){} // pass
				else $pass = false;
			}

			if($pass === false) {
				$bpType = \Blackprint\getTypeName($port->type);
				throw new \Exception("Can't validate type of ID: $bpType == $type");
			}
		}

		// echo "\n3. {$port->name} = {$val}";

		$port->value = $val;

		$temp = new \Blackprint\EvPortSelf($port);
		$port->emit('value', $temp);

		if($port->feature === PortType::StructOf && $port->splitted){
			PortFeature::StructOf_handle($port, $val);
			return;
		}

		if($port->_sync == false) return;

		$port->sync();
	}

	public function offsetExists($key): bool {
		return isset($this->ifacePort[$key]);
	}

	public function serialize(): string {
		$ports = &$this->ifacePort;
		$temp = [];

		foreach($ports as $key => &$port){
			if($port->source === 'input')
				$temp[$key] = $port->_cache ?? $port->default;
			else $temp[$key] = $port->value ?? $port->default;
		}

		return serialize($temp);
	}

	public function &_add(&$portName, $val){
		if(preg_match('/([~!@#$%^&*()_\-+=[]{};\'\\:"|,.\/<>?]|\s)/', $portName))
			throw new \Exception("Port name can't include symbol character except underscore");

		if($portName === '')
			throw new \Exception("Port name can't be empty");

		if($this->_which === 'output' && (is_array($val) && isset($val['feature']))){
			if($val['feature'] === PortType::Union)
				$val = Types::Any;
			elseif($val['feature'] === PortType::Trigger)
				$val = Types::Trigger;
			elseif($val['feature'] === PortType::ArrayOf)
				$val = Types::Array;
			elseif($val['feature'] === PortType::Default)
				$val = &$val['type'];
		}

		$iPort = &$this->ifacePort;
		$exist = &$iPort[$portName];

		if(isset($iPort[$portName]))
			return $exist;

		// Determine type and add default value for each type
		[ $type, $def, $haveFeature ] = Utils::determinePortType($val, $this);

		$linkedPort = $this->_iface->_newPort($portName, $type, $def, $this->_which, $haveFeature);
		$iPort[$portName] = &$linkedPort;
		$linkedPort->_config = &$val;

		if(!($haveFeature == PortType::Trigger && $this->_which === 'input'))
			$linkedPort->createLinker();

		return $linkedPort; // IFace Port
	}

	public function _delete(&$portName){
		$iPort = &$this->ifacePort;
		if($iPort === null) return;

		// Destroy cable first
		$port = &$iPort[$portName];
		$port->disconnectAll();

		unset($iPort[$portName]);
	}
}