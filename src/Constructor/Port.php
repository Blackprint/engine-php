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

	public function __construct(&$portName, &$type, &$def, &$which, &$node){
		$this->name = $portName;
		$this->type = $type;
		$this->default = $def;
		$this->source = $which;
		$this->node = &$node;
	}

	public function createLinker(){
		// Only for outputs
		if($this->type === Types\Functions){
			return function(){
				$cables = $this->cables;
				foreach ($cables as &$cable) {
					$target = $cable->owner === $this ? $cable->target : $cable->owner;
					if($target === null)
						continue;$cable->_print();
echo "\n0. {$this->name} -> {$target->name}";
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
						if($target === null)
							return;

						// Request the data first
						if($target->node->handle->request)
							($target->node->handle->request)($target, $this->node);
echo "\n1. {$this->name} -> {$target->name}";
						$this->node->_requesting = false;
						return $target->value || $target->default;
					}

					// Return multiple data as an array
					$cables = $this->cables;
					$data = [];
					foreach ($cables as &$cable) {
						$target = $cable->owner === $this ? $cable->$target : $cable->owner;
						if($target === null)
							continue;

						// Request the data first
						if($target->node->handle->request)
							$target->node->handle->request($target, $this->node);
echo "\n2. {$this->name} -> {$target->name}";
						$data[] = $target->value || $target->default;
					}

					$this->node->_requesting = false;
					return $data;
				}

				return $this->value;
			}

			// Data type validation
			if($this->type === Types\Numbers)
				$val = Types\Numbers($val);
			elseif($this->type === Types\Booleans)
				$val = Types\Booleans($val);
			elseif($this->type === Types\Strings)
				$val = Types\Strings($val);
			elseif($this->type === Types\Arrays)
				$val = Types\Arrays($val);

			$this->value = &$val;
			$this->sync();
		};
	}

	public function sync(){
		$cables = &$this->cables;
		foreach ($cables as &$cable) {
			$target = $cable->owner === $this ? $cable->target : $cable->owner;
			if($target === null)
				continue;

			if($target->feature === \Blackprint\PortListener)
				$target->_call($cable->owner === $this ? $cable->owner : $cable->target, $this->value);

			if($target->node->_requesting === false && $target->node->handle->update !== false)
				($target->node->handle->update)($cable);
		}
	}
}