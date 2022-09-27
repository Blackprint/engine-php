<?php
namespace Blackprint\Constructor;

use Exception;

class Cable{
	/** @var Blackprint\Types */
	public $type;

	/** @var Blackprint\Port */
	public $owner;

	/** @var Blackprint\Port */
	public $target;

	/** @var Blackprint\Port */
	public $input;

	/** @var Blackprint\Port */
	public $output;
	public $disabled = false;
	public $isRoute = false;
	public $connected = false;

	// For remote-control
	public $_evDisconnected = false;
	public $source = null;

	public function __construct(&$owner, &$target){
		$this->type = &$owner->type;
		$this->owner = &$owner;
		$this->target = &$target;
		$this->source = &$owner->source;

		if($owner->source === 'input'){
			$inp = &$owner;
			$out = &$target;
		}
		else{
			$inp = &$target;
			$out = &$owner;
		}

		$this->input = &$inp;
		$this->output = &$out;
	}

	public function _connected(){
		$owner = &$this->owner;
		$target = &$this->target;
		$this->connected = true;

		// Skip event emit or node update for route cable connection
		if($this->isRoute) return;

		$temp = new \Blackprint\EvPortValue($owner, $target, $this);
		$owner->emit('cable.connect', $temp);
		$owner->iface->emit('cable.connect', $temp);

		$temp2 = new \Blackprint\EvPortValue($target, $owner, $this);
		$target->emit('cable.connect', $temp2);
		$target->iface->emit('cable.connect', $temp2);

		if($this->output->value === null) return;

		$tempEv = new \Blackprint\EvPortSelf($this->output);
		$input = &$this->input;
		$input->emit('value', $tempEv);
		$input->iface->emit('value', $tempEv);
		$input->iface->node->update($this);
	}

	// For debugging
	public function _print(){
		echo "\nCable: {$this->output->iface->title}.{$this->output->name} -> {$this->input->name}.{$this->input->iface->title}";
	}

	// ToDo: redesign after https://github.com/php/php-src/pull/6873 been merged
	public function &__get($key){
		if($key !== 'value') throw new Exception("'$key' property was not found on this object");
		return $this->output->value;
	}

	public function disconnect($which=false){ // which = port
		if($this->isRoute){ // ToDo: simplify, use 'which' instead of check all
			$input = &$this->input;
			$output = &$this->output;

			if($output->cables != null) array_splice($output->cables, 0);
			elseif($output->out === $this) $output->out = null;
			elseif($input->out === $this) $input->out = null;

			$i = $output->in ? array_search($this, $output->in) : -1;
			if($i !== -1){
				array_splice($output->in, $i, 1);
			}
			elseif($input != null) {
				$i = array_search($this, $input->in);
				if($i !== -1)
					array_splice($input->in, $i, 1);
			}

			$this->connected = false;
			return;
		}

		$owner = &$this->owner;
		$target = &$this->target;
		$alreadyEmitToInstance = false;

		if($this->input !== null)
			$this->input->_cache = null;

		// Remove from cable owner
		if($owner && (!$which || $owner === $which)){
			$i = array_search($this, $owner->cables);
			if($i !== -1)
				array_splice($owner->cables, $i, 1);

			if($this->connected){
				$temp = new \Blackprint\EvPortValue($owner, $target, $this);
				$owner->emit('disconnect', $temp);
				$owner->iface->emit('cable.disconnect', $temp);
				$owner->iface->node->instance->emit('cable.disconnect', $temp);

				$alreadyEmitToInstance = true;
			}
			else{
				$nul = null;
				$temp = new \Blackprint\EvPortValue($owner, $nul, $this);
				$owner->iface->emit('cable.cancel', $temp);
				// $owner->iface->node->instance->emit('cable.cancel', temp);
			}
		}

		// Remove from connected target
		if($target && $this->connected && (!$which || $target === $which)){
			$i = array_search($this, $target->cables);
			if($i !== -1)
				array_splice($target->cables, $i, 1);

			$temp = new \Blackprint\EvPortValue($target, $owner, $this);
			$target->emit('disconnect', $temp);
			$target->iface->emit('cable.disconnect', $temp);

			if(!$alreadyEmitToInstance)
				$target->iface->node->instance->emit('cable.disconnect', $temp);
		}

		// if(hasOwner || hasTarget) this.connected = false;
	}
}