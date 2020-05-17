<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;

class Port{
	public $name;
	public $type;
	public $cables = [];
	public $source;
	public $node;
	public $default;
	public $value = null;
	public $sync = false;
	public $feature = null;
	public $_call = null;

	public function __construct(&$portName, &$type, &$def, &$which, &$node, &$feature){
		$this->name = $portName;
		$this->type = $type;
		$this->default = $def;
		$this->source = $which;
		$this->node = &$node;

		if($feature === false)
			return;

		$this->feature = &$feature['feature'];

		if(isset($feature['func']))
			$this->_call = &$feature['func'];
	}

	public function createLinker(){
		// Only for outputs
		if($this->type === Types\Functions){
			return function(){
				$cables = $this->cables;
				foreach ($cables as &$cable) {
					$target = $cable->owner === $this ? $cable->target : $cable->owner;
					$cable->_print();

					$target->node->handle->inputs[$target->name]($this, $cable);
				}
			};
		}

		return function($val=null){
			// Getter value
			if($val === null){
				// This port must use values from connected outputs
				if($this->source === 'inputs'){
					if(count($this->cables) === 0)
						return $this->default;

					// Flag current node is requesting value to other node
					$this->node->_requesting = true;

					// Return single data
					if(count($this->cables) === 1){
						$target = $this->cables[0]->owner === $this ? $this->cables[0]->$target : $this->cables[0]->owner;

						// Request the data first
						if($target->node->handle->request)
							($target->node->handle->request)($target, $this->node);

						// echo "\n1. {$this->name} -> {$target->name} ({$target->value})";

						$this->node->_requesting = false;

						if($target->value === null)
							return $target->default;
						return $target->value;
					}

					// Return multiple data as an array
					$cables = $this->cables;
					$data = [];
					foreach ($cables as &$cable) {
						$target = $cable->owner === $this ? $cable->$target : $cable->owner;

						// Request the data first
						if($target->node->handle->request)
							$target->node->handle->request($target, $this->node);

						// echo "\n2. {$this->name} -> {$target->name} ({$target->value})";

						if($target->value === null)
							$data[] = $target->default;
						else
							$data[] = $target->value;
					}

					$this->node->_requesting = false;
					return $data;
				}

				if($this->value === null)
					return $this->default;
				return $this->value;
			}

			$type = gettype($val);

			// Data type validation
			if($this->type === Types\Numbers)
				($type !== 'integer' || $type !== 'double' || $type !== 'float') && Types\Numbers($val);
			elseif($this->type === Types\Booleans)
				$type !== 'boolean' && Types\Booleans($val);
			elseif($this->type === Types\Strings)
				$type !== 'string' && Types\Strings($val);
			elseif($this->type === Types\Arrays)
				$type !== 'array' && Types\Arrays($val);
			else
				throw new \Exception("Can't validate type of ID: {$this->type}");

			$this->value = &$val;
			$this->sync();
		};
	}

	public function sync(){
		$cables = &$this->cables;
		foreach ($cables as &$cable) {
			$target = $cable->owner === $this ? $cable->target : $cable->owner;

			if($target->feature === \Blackprint\PortListener)
				($target->_call)($cable->owner === $this ? $cable->owner : $cable->target, $this->value);

			if($target->node->_requesting === false && $target->node->handle->update !== false)
				($target->node->handle->update)($cable);
		}
	}
}