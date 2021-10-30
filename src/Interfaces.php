<?php
namespace Blackprint;
use \Blackprint\Types;
use \Blackprint\Constructor\Port;

class Temp{
	public static $list = ['input', 'output', 'property'];
}

class Interfaces extends Constructor\CustomEvent {
	public $id; // Named ID (String)
	public $i; // Generated Index
	public $title = 'No title';
	public $interface = 'BP/default';
	public $importing = true;

	/** @var Node */
	public $node;
	public $namespace;
	public $_requesting = false;

	public function __construct(&$node){
		$this->node = &$node;
	}

	public function _prepare_(){
		$node = &$this->node;
		foreach (Temp::$list as &$which) {
			$localPorts = &$node->{$which};

			foreach ($localPorts as $portName => &$port) {
				$type = $port;
				$def = null;
				$feature = is_array($port) ? $port['feature'] : false;

				if($feature === \Blackprint\Port::Trigger_){
					$def = &$port['func'];
					$type = Types::Function;
				}
				elseif($feature === \Blackprint\Port::ArrayOf_)
					$type = &$port['type'];
				elseif($type === Types::Number)
					$def = 0;
				elseif($type === Types::Boolean)
					$def = false;
				elseif($type === Types::String)
					$def = '';
				elseif($type === Types::Array)
					$def = [];
				elseif($type === null) 0; // Any
				elseif($type === Types::Function) 0;
				elseif($feature === false && !is_string($port))
					throw new Exception("Port for initialization must be a types", 1);
				// else{
				// 	$def = $port;
				// 	$type = Types::String;
				// }

				$linkedPort = $this->{$which}[$portName] = new Port($portName, /* the types */ $type, $def, $which, $this, $feature);
				$localPorts[$portName] = $linkedPort->createLinker();
			}
		}
	}
}