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
	private $contructed = false;

	// Reserved for future
	function __construct(public $instance){
		$this->contructed = true;
	}

	public function &setInterface($namespace='BP/default'){
		if($this->contructed === false)
			throw new \Exception("Make sure you have call 'parent::__construct(\$instance);' when constructing nodes before '->setInterface'");

		if(isset(Internal::$interface[$namespace]) === false)
			throw new \Exception("Node interface for '{$namespace}' was not found, maybe .registerInterface() haven't being called?");

		$iface = new Internal::$interface[$namespace]($this);
		$this->iface = &$iface;

		return $iface;
	}
}