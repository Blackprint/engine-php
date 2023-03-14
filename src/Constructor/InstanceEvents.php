<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;

class InstanceEvents extends CustomEvent {
	public $list = [];
	function __construct(public &$instance){ }

	// No need to override like engine-js as it's already performant
	// public function emit($eventName, &$obj){ }

	public function createEvent($namespace, $options=[]){
		if(in_array($namespace, $this->list)) return;
		if(preg_match('/\s/', $namespace) !== 0)
			throw new \Exception("Namespace can't have space character: '$namespace'");

		$schema = [];
		$list = &$options['schema'];
		if($list !== null){
			foreach ($list as &$value) {
				$schema[$value] = Types::Any;
			}
		}

		$this->list[$namespace] = new InstanceEvent([ 'schema' => &$schema ]);
	}

	public function _renameFields($namespace, $name, $to){
		$schema = $this->list[$namespace]?->schema;
		if($schema === null) return;

		$schema[$to] = $schema[$name];
		unset($schema[$name]);

		$this->refreshFields($namespace, $name, $to);
	}

	// second and third parameter is only be used for renaming field
	public function refreshFields($namespace, $_name=null, $_to=null){
		$schema = $this->list[$namespace]?->schema;
		if($schema === null) return;

		$refreshPorts = function($iface, $target) use(&$schema, &$_name, &$_to) {
			$ports = $iface[$target];
			$node = $iface->node;

			if($_name !== null){
				$node->renamePort($target, $_name, $_to);
				return;
			}

			// Delete port that not exist or different type first
			$isEmitPort = $target === 'input' ? true : false;
			foreach ($ports as $name => &$val) {
				if($isEmitPort) { $isEmitPort = false; continue; }
				if($schema[$name] != $ports[$name]->_config){
					$node->deletePort($target, $name);
				}
			}

			// Create port that not exist
			foreach ($schema as $name => &$val) {
				if($ports[$target] == null)
					$node->createPort($target, $name, $schema[$name]);
			}
		};

		$iterateList = function($ifaceList) use(&$refreshPorts, &$namespace, &$iterateList) {
			foreach ($ifaceList as &$iface) {
				if($iface->_enum === \Blackprint\Nodes\Enums::BPEventListen){
					if($iface->data['namespace'] === $namespace)
						$refreshPorts($iface, 'output');
				}
				elseif($iface->_enum === \Blackprint\Nodes\Enums::BPEventEmit){
					if($iface->data['namespace'] === $namespace)
						$refreshPorts($iface, 'input');
				}
				elseif($iface->_enum === \Blackprint\Nodes\Enums::BPFnMain){
					$iterateList($iface->bpInstance->ifaceList);
				}
			}
		};

		$iterateList($this->instance->ifaceList);
	}
}

class InstanceEvent {
	public $schema;
	function __construct($options){
		$this->schema = &$options['schema'];
	}
}