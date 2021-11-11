<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;

class Port extends CustomEvent {
	public $name;
	public $type;
	public $cables = [];
	public $source;
	public $iface;
	public $default;
	public $value = null;
	public $sync = false;
	public $feature = null;

	public function __construct(&$portName, &$type, &$def, &$which, &$iface, &$feature){
		$this->name = $portName;
		$this->type = $type;
		$this->default = $def;
		$this->source = $which;
		$this->iface = &$iface;

		if($feature === false)
			return;

		$this->feature = &$feature;
	}

	public function createLinker(){
		if($this->type === Types::Function){
			if($this->source === 'output'){
				return function($data = null){
					$cables = $this->cables;
					foreach ($cables as &$cable) {
						$target = $cable->owner === $this ? $cable->target : $cable->owner;
						// $cable->_print();

						($target->default)($data);
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
						if(method_exists($target->iface->node, 'request'))
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
						if(method_exists($target->iface->node, 'request'))
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

				if($this->feature === \Blackprint\Port::ArrayOf_){
					$temp = [$target->value ?? $target->default];
					return $temp;
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
			$this->_trigger('value', $this);
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

			if($target->iface->_requesting === false && method_exists($target->iface->node, 'update'))
				$target->iface->node->update($cable);

			$target->_trigger('value', $this);
		}
	}
}