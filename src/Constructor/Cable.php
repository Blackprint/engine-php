<?php
namespace Blackprint\Constructor;

class Cable{
	/** @var Blackprint\Types|String */
	public $type;

	/** @var NodeInterface */
	public $owner;

	/** @var NodeInterface */
	public $target;

	public function __construct(&$owner, &$target){
		$this->type = &$owner->type;
		$this->owner = &$owner;
		$this->target = &$target;
	}

	public function _connected(){
		$owner = &$this->owner;
		$target = &$this->target;

		$temp = ['cable'=> &$this, 'port'=> &$owner, 'target'=> &$target];
		$owner->_trigger('cable.connect', $temp);

		$temp2 = ['cable'=> &$this, 'port'=> &$owner, 'target'=> &$target];
		$target->_trigger('cable.connect', $temp2);

		if($target->source === 'input'){
			$inp = &$target;
			$out = &$owner;
		}
		else{
			$inp = &$target;
			$out = &$owner;
		}

		if($out->value === null) return;
		$inp->_trigger('value', $out);
	}

	// For debugging
	public function _print(){
		echo "\nCable: {$this->owner->iface->title}.{$this->owner->name} -> {$this->target->name}.{$this->target->iface->title}";
	}
}