<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;

class Port{
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
		if($this->type === Types\Functions){
			return function(){
				$cables = $this->cables;
				foreach ($cables as &$cable) {
					$target = $cable->owner === $this ? $cable->target : $cable->owner;
					// $cable->_print();

					$target->iface->node->input[$target->name]($this, $cable);
				}
			};
		}

		return function($val=null){
			// Getter value
			if($val === null){
				// This port must use values from connected output
				if($this->source === 'input'){
					if(count($this->cables) === 0)
						return $this->default;

					// Flag current iface is requesting value to other iface
					$this->iface->_requesting = true;

					// Return single data
					if(count($this->cables) === 1){
						$target = $this->cables[0]->owner === $this ? $this->cables[0]->$target : $this->cables[0]->owner;

						// Request the data first
						if($target->iface->node->request)
							($target->iface->node->request)($target, $this->iface);

						// echo "\n1. {$this->name} -> {$target->name} ({$target->value})";

						$this->iface->_requesting = false;

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
						if($target->iface->node->request)
							$target->iface->node->request($target, $this->iface);

						// echo "\n2. {$this->name} -> {$target->name} ({$target->value})";

						if($target->value === null)
							$data[] = $target->default;
						else
							$data[] = $target->value;
					}

					$this->iface->_requesting = false;
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

			if($target->feature === \Blackprint\Port\Listener)
				($target->_call)($cable->owner === $this ? $cable->owner : $cable->target, $this->value);

			if($target->iface->_requesting === false && $target->iface->node->update !== false)
				($target->iface->node->update)($cable);
		}
	}
}