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
				$feature = false;

				if(is_callable($port)){
					$def = $port;
					$type = Types::Function;
				}
				elseif(is_array($port)){
					if($port['type'] === null){
						$type = &$port['type'];

						if(isset($port['value']))
							$def = &$port['value'];

						$feature = $port;
					}
					else{
						$def = $port;
						$type = Types::Array;
					}
				}
				elseif(is_string($port)){
					if($type === Types::Number)
						$def = 0;
					elseif($type === Types::Boolean)
						$def = false;
					elseif($type === Types::String)
						$def = '';
					elseif($type === Types::Array)
						$def = [];
					elseif($type === null)
						0;
					elseif($type === Types::Function)
						0;
					else{
						$def = $port;
						$type = Types::String;
					}
				}

				$linkedPort = $this->{$which}[$portName] = new Port($portName, /* the types */ $type, $def, $which, $this, $feature);
				if(is_callable($port) && $which !== 'output')
					continue;

				$localPorts[$portName] = $linkedPort->createLinker();
			}
		}
	}
}