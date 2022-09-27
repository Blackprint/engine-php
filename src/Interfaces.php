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
	public $_enum = null;
	public $isGhost = false;

	/** @var Node */
	public $node;
	/** @var string */
	public $namespace;
	public $_requesting = false;

	/** @var Nodes/FnMain */
	public $_funcMain = null;

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

		$node->routes = new \Blackprint\RoutePort($this);

		if(isset($clazz::$Output)){
			$node->output = new Constructor\PortLink($node, 'output', $clazz::$Output);
			$ref->IOutput = &$this->output;
			$ref->Output = &$node->output;
		}

		if(isset($clazz::$Input)){
			$node->input = new Constructor\PortLink($node, 'input', $clazz::$Input);
			$ref->IInput = &$this->input;
			$ref->Input = &$node->input;
		}

		if(isset($clazz::$property))
			throw new \Exception("'node.property', 'iface.property', and 'public static \$property' is reserved field for Blackprint");
	}

	public function _newPort($portName, $type, $def, $which, $haveFeature){
		return new Constructor\Port($portName, $type, $def, $which, $this, $haveFeature);
	}

	public function _initPortSwitches(&$portSwitches){
		foreach ($portSwitches as $key => &$value) {
			$ref = &$this->output[$key];

			if(($value | 1) === 1)
				\Blackprint\Port::StructOf_split($ref);

			if(($value | 2) === 2){
				$ref->allowResync = true;
			}
		}
	}

	public function _importInputs(&$ports){
		// Load saved port data value
		$inputs = &$this->input;
		foreach($ports as $key => &$val) {
			if(isset($inputs[$key])){
				$port = &$inputs[$key];
				$port->default = $val;
			}
		}
	}

	public function init(){}
	public function destroy(){}
	public function imported(&$data){}
}