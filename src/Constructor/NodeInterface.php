<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;

class Temp{
	public static $list = [];
}
Temp::$list = ['inputs', 'outputs', 'properties'];

class NodeInterface extends CustomEvents{
	public $title = 'No title';
	public $interface = 'default';
	public $importing = true;

	/** @var Node */
	public $node;
	public $namespace;
	public $_requesting = false;

	// Deprecated
	public $inputs;
	public $outputs;
	public $properties;

	public function __construct(&$node, &$namespace){
		$this->node = &$node;
		$this->namespace = &$namespace;
	}

	public function interfacing(&$interFunc){
		$interFunc($this, function($properties){
			foreach ($properties as $key => &$val) {
				$this->{$key} = $val;
			}
		});
	}

	public function prepare(){
		$node = &$this->node;
		foreach (Temp::$list as &$which) {
			$localPorts = &$node->{$which};

			foreach ($localPorts as $portName => &$port) {
				$type = $port;
				$def = null;
				$feature = false;

				if(is_callable($port)){
					$def = $port;
					$type = Types\Functions;
				}
				elseif(is_array($port)){
					if($port['type'] === null || strpos($port['type'], '>?><') === 0){
						$type = &$port['type'];

						if(isset($port['value']))
							$def = &$port['value'];

						$feature = $port;
					}
					else{
						$def = $port;
						$type = Types\Arrays;
					}
				}
				elseif(is_string($port)){
					if($type === Types\Numbers)
						$def = 0;
					elseif($type === Types\Booleans)
						$def = false;
					elseif($type === Types\Strings)
						$def = '';
					elseif($type === Types\Arrays)
						$def = [];
					elseif($type === null)
						0;
					elseif($type === Types\Functions)
						0;
					else{
						$def = $port;
						$type = Types\Strings;
					}
				}

				$linkedPort = $this->{$which}[$portName] = new Port($portName, /* the types */ $type, $def, $which, $this, $feature);
				if(is_callable($port) && $which !== 'outputs')
					continue;

				$localPorts[$portName] = $linkedPort->createLinker();
			}
		}
	}
}