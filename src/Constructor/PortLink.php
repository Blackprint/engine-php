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

		if($port->feature == PortType::Trigger)
			return $port->default;
		
		// This port must use values from connected output
		if($port->source === 'input'){
			$cableLen = count($port->cables);

			if($cableLen === 0)
				return $port->default;

			if($port->_cache !== null) return $port->_cache;

			// Flag current iface is requesting value to other iface
			$port->iface->_requesting = true;

			// Return single data
			if($cableLen === 1){
				$cable = $port->cables[0]; # Don't use pointer

				if($cable->connected === false || $cable->disabled){
					$port->iface->_requesting = false;
					if($port->feature === PortType::ArrayOf)
						return $port->_cache = [];

					return $port->_cache = $port->default;
				}

				$output = &$cable->output;

				// Request the data first
				$output->iface->node->request($cable);

				// echo "\n1. {$port->name} -> {$output->name} ({$output->value})";

				$port->iface->_requesting = false;

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
				$output->iface->node->request($cable);

				// echo "\n2. {$port->name} -> {$output->name} ({$output->value})";

				if($isNotArrayPort){
					$port->iface->_requesting = false;
					return $port->_cache = $output->value ?? $port->default;
				}

				$data[] = $output->value ?? $port->default;
			}

			$port->iface->_requesting = false;

			$port->_cache = &$data;
			return $data;
		}

		# Callable port (for output ports)
		if($port->_callAll != null)
			return $port->_callAll;

		return $port->value;
	}

	public function offsetSet($key, $val): void{
		$this->setByRef($key, $val);
	}

	public function setByRef($key, &$val) {
		$port = &$this->ifacePort[$key];

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

		$port->value = &$val;

		$temp = new \Blackprint\EvPortSelf($port);
		$port->emit('value', $temp);

		if($port->feature === PortType::StructOf && $port->splitted){
			PortFeature::StructOf_handle($port, $val);
			return;
		}

		$port->sync();
		return;
	}

	public function &_add(&$portName, $val){
		$iPort = &$this->ifacePort;
		$exist = &$iPort[$portName];

		if(isset($iPort[$portName]))
			return $exist;

		// Determine type and add default value for each type
		[ $type, $def, $haveFeature ] = Utils::determinePortType($val, $this);

		$linkedPort = $this->_iface->_newPort($portName, $type, $def, $this->_which, $haveFeature);
		$iPort[$portName] = &$linkedPort;

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
		unset($this->ifacePort[$portName]);
	}
}