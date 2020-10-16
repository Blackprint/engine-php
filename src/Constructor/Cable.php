<?php
namespace Blackprint\Constructor;

class Cable{
	/** @var String */
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

	// For debugging
	public function _print(){
		echo "\nCable: {$this->owner->node->title}.{$this->owner->name} -> {$this->target->name}.{$this->target->node->title}";
	}
}