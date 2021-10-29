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
	public $_call = null;

	public function __construct(&$portName, &$type, &$def, &$which, &$iface, &$feature){
		$this->name = $portName;
		$this->type = $type;
		$this->default = $def;
		$this->source = $which;
		$this->iface = &$iface;

		if($feature === false)
			return;

		$this->feature = &$feature['feature'];

		if(isset($feature['func']))
			$this->_call = &$feature['func'];
	}

	public function createLinker(){
		// Only for output
		if($this->type === Types::Function){
			return function(){
				$cables = $this->cables;
				foreach ($cables as &$cable) {
					$target = $cable->owner === $this ? $cable->target : $cable->owner;
					// $cable->_print();

					$target->iface->node->input[$target->name]($this, $cable);
				}
			};
		}

		return function&($val=null){
			// Getter value
			if($val === null){
				// This port must use values from connected output
				if($this->source === 'input'){
					if(count($this->cables) === 0){
						if($this->feature === \Blackprint\Port\ArrayOf){
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

						if($this->feature === \Blackprint\Port\ArrayOf){
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

					if($this->feature !== \Blackprint\Port\ArrayOf)
						return $data[0];

					return $data;
				}

				if($this->feature === \Blackprint\Port\ArrayOf){
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

			// Data type validation
			if($this->type === Types::Number && ($type === 'integer' || $type === 'double' || $type === 'float')){}
			elseif($this->type === Types::Boolean && $type === 'boolean'){}
			elseif($this->type === Types::String && $type === 'string'){}
			elseif($this->type === Types::Array && $type === 'array'){}
			elseif($this->type === Types::Function && is_callable($val)){}
			elseif($this->type === null){}
			else{
				$bpType = \Blackprint\getTypeName($this->type);
				throw new \Exception("Can't validate type of ID: $bpType == $type");
			}

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