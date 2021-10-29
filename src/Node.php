<?php
namespace Blackprint;

class Node extends Constructor\CustomEvent{
	/** @var array Constructor\Port */
	public $output = [];
	public $input = [];
	public $property = [];

	// public $init = false;
	// public $request = false;
	// public $update = false;

	/** @var Interfaces */
	public $iface = false;

	function __construct(public $instance){}

	public function &setInterface($namespace='BP/default'){
		if(isset(Internal::$interface[$namespace]) === false)
			throw new \Exception("Node interface for '{$namespace}' was not found, maybe .registerInterface() haven't being called?");

		$iface = new Internal::$interface[$namespace]($this);
		$this->iface = &$iface;

		return $iface;
	}
}