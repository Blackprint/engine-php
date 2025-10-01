<?php
namespace Blackprint\Constructor;

use Blackprint\EvPortValue;
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

	public $_hasUpdate = false;
	public $_ghost = false;
	public $_disconnecting = false;
	public $_calling = false;

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

	public function connecting(){
		if($this->disabled || $this->input->type === \Blackprint\Types::Slot || $this->output->type === \Blackprint\Types::Slot){
			// inp.iface.node.instance.emit('cable.connecting', {
			// 	port: input, target: output
			// });
			return;
		}

		$this->_connected();
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

		$input = &$this->input;
		$tempEv = new \Blackprint\EvPortValue($input, $this->output, $this);
		$input->emit('value', $tempEv);
		$input->iface->emit('port.value', $tempEv);

		$node = &$input->iface->node;
		if($node->instance->_importing)
			$node->instance->executionOrder->add($node, $this);
		elseif(count($node->routes->in) === 0)
			$node->_bpUpdate($this);
	}

	// For debugging
	public function _print(){
		echo "\nCable: {$this->output->iface->title}.{$this->output->name} -> {$this->input->name}.{$this->input->iface->title}";
	}

	// ToDo: redesign after https://github.com/php/php-src/pull/6873 been merged
	public function &__get($key){
		if($key !== 'value') throw new Exception("'$key' property was not found on this object");

		if($this->_disconnecting) return $this->input->default;
		return $this->output->value;
	}

	public function disconnect($which=false){ // which = port
		$owner = &$this->owner;
		$target = &$this->target;
		if($this->isRoute){ // ToDo: simplify, use 'which' instead of check all
			$input = &$this->input;
			$output = &$this->output;

			if($output == null) return;

			if($output->out === $this) $output->out = null;
			elseif($input->out === $this) $input->out = null;

			$i = $output->in ? array_search($this, $output->in) : -1;
			if($i !== -1){
				array_splice($output->in, $i, 1);
			}
			else if($input != null) {
				$i = array_search($this, $input->in);
				if($i !== -1)
					array_splice($input->in, $i, 1);
			}

			$this->connected = false;

			if($target == null) return; // Skip disconnection event emit

			$temp1 = new \Blackprint\EvPortValue($owner, $target, $this);
			$owner->emit('disconnect', $temp1);
			$owner->iface->emit('cable.disconnect', $temp1);
			$owner->iface->node->instance->emit('cable.disconnect', $temp1);

			if($target == null) return;
			$temp2 = new \Blackprint\EvPortValue($target, $owner, $this);
			$target->emit('disconnect', $temp2);
			$target->iface->emit('cable.disconnect', $temp2);

			return;
		}

		$alreadyEmitToInstance = false;
		$this->_disconnecting = true;

		$inputPort = &$this->input;
		if($inputPort !== null){
			$oldVal = &$this->output->value;
			$inputPort->_cache = null;

			$defaultVal = $inputPort->default;
			if($defaultVal != null && $defaultVal !== $oldVal){
				$iface = &$inputPort->iface;
				$node = &$iface->node;
				$routes = &$node->routes; // PortGhost's node may not have routes

				if($iface->_bpDestroy !== true && $routes !== null && count($routes->in) === 0){
					$temp = new EvPortValue($inputPort, $this->output, $this);
					$inputPort->emit('value', $temp);
					$iface->emit('port.value', $temp);
					$node->instance->executionOrder->add($node);
				}
			}

			$inputPort->_hasUpdateCable = null;
		}

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

		if($owner || $target) $this->connected = false;
		$this->_disconnecting = false;
	}
}