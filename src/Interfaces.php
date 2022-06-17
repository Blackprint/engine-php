<?php
namespace Blackprint;

class Temp {
	public static $list = ['input', 'output', 'property'];
}

class Interfaces extends Constructor\CustomEvent {
	/** @var string */
	public $id; // Named ID (String)
	/** @var int */
	public $i; // Generated Index
	public $title = 'No title';
	public $interface = 'BP/default';
	public $importing = true;
	public $_dynamicPort = false;

	/** @var Node */
	public $node;
	/** @var string */
	public $namespace;
	public $_requesting = false;

	/** @var Constructor\References */
	public $ref;

	public function __construct(&$node){
		$this->node = &$node;
	}

	public function _prepare_($clazz){
		$node = &$this->node;
		$ref = new Constructor\References();
		$node->ref = &$ref;
		$this->ref = &$ref;

		if(isset($clazz::$output)){
			$node->output = new Constructor\PortLink($node, 'output', $clazz::$output);
			$ref->IOutput = &$this->output;
			$ref->Output = &$node->output;
		}

		if(isset($clazz::$input)){
			$node->input = new Constructor\PortLink($node, 'input', $clazz::$input);
			$ref->IInput = &$this->input;
			$ref->Input = &$node->input;
		}

		if(isset($clazz::$property))
			throw new \Exception("'node.property', 'iface.property', and 'public static \$property' is reserved field for Blackprint");
	}

	public function _newPort($portName, $type, $def, $which, $haveFeature){
		return new Constructor\Port($portName, $type, $def, $which, $this, $haveFeature);
	}

	public function init(){}
	public function destroy(){}
	public function imported($data){}
}