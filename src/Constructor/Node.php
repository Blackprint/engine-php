<?php
namespace Blackprint\Constructor;
use \Blackprint\Blackprint;
use \Blackprint\Constructor;

class Node{
	/** @var array Port */
	public $output = [];
	public $input = [];
	public $property = [];

	public $init = false;
	public $request = false;
	public $update = false;

	/** @var NodeInterface */
	public $iface = false;

	public function setInterface($namespace='BP/default'){
		if(isset(Blackprint::$interface[$namespace]) === false)
			throw new \Exception("Node interface for '{$namespace}' was not found, maybe .registerInterface() haven't being called?");

		$this->iface = $iface = new Constructor\NodeInterface($this, $namespace);

		Blackprint::$interface[$namespace]($iface, function($property) use($iface) {
			foreach ($property as $key => &$val) {
				$iface->{$key} = $val;
			}
		});

		return $iface;
	}
}