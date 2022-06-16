<?php
namespace Blackprint\Constructor;

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

	// For remote-control
	public $_evDisconnected = false;

	public function __construct(&$owner, &$target){
		$this->type = &$owner->type;
		$this->owner = &$owner;
		$this->target = &$target;

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

		$temp = ['cable'=> &$this, 'port'=> &$owner, 'target'=> &$target];
		$owner->emit('cable.connect', $temp);

		$temp2 = ['cable'=> &$this, 'port'=> &$owner, 'target'=> &$target];
		$target->emit('cable.connect', $temp2);

		if($this->output->value === null) return;
		$this->input->emit('value', $this->output);
	}

	// For debugging
	public function _print(){
		echo "\nCable: {$this->owner->iface->title}.{$this->owner->name} -> {$this->target->name}.{$this->target->iface->title}";
	}

	public function disconnect($which=false){ // which = port
		if($this->isRoute){ // ToDo: simplify, use 'which' instead of check all
			$input = &$this->input;
			$output = &$this->output;

			if($output->cables != null) array_splice($output->cables, 0);
			else if($output->out === $this) $output->out = null;
			else if($input->out === $this) $input->out = null;

			$i = $output->in ? array_search($this, $output->in) : -1;
			if($i !== -1){
				array_splice($output->in, $i, 1);
			}
			else if($input != null) {
				$i = array_search($this, $input->in);
				if($i !== -1)
					array_splice($input->in, $i, 1);
			}

			// $this->connected = false;
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
				$temp = [
					"cable" => &$this,
					"port" => &$owner,
					"target" => &$target
				];

				$owner->emit('disconnect', $temp);
				$owner->iface->emit('cable.disconnect', $temp);
				$owner->iface->node->_instance->emit('cable.disconnect', $temp);

				$alreadyEmitToInstance = true;
			}
			else{
				$temp = ["port" => &$owner, "cable" => &$this];
				$owner->iface->emit('cable.cancel', $temp);
				// $owner->iface->node->_instance->emit('cable.cancel', temp);
			}
		}

		// Remove from connected target
		if($target && $this->connected && (!$which || $target === $which)){
			$i = array_search($this, $target->cables);
			if($i !== -1)
				array_splice($target->cables, $i, 1);

			$temp = [
				"cable" => &$this,
				"port" => &$target,
				"target" => &$owner
			];

			$target->emit('disconnect', $temp);
			$target->iface->emit('cable.disconnect', $temp);

			if(!$alreadyEmitToInstance)
				$target->iface->node->_instance->emit('cable.disconnect', $temp);
		}

		// if(hasOwner || hasTarget) this.connected = false;
	}
}