<?php
namespace Blackprint\Constructor;
use \Blackprint\Types;

class InstanceEvents extends CustomEvent {
	public $list = [];
	function __construct(public &$instance){ }

	// No need to override like engine-js as it's already performant
	// public function emit($eventName, &$obj){ }

	public function createEvent($namespace, $options=[]){
		if(isset($this->list[$namespace])) return; // throw new \Exception(`Event with name '${namespace}' already exist`);
		if(preg_match('/\s/', $namespace) !== 0)
			throw new \Exception("Namespace can't have space character: '$namespace'");

		if(isset($options['schema']) && !is_array($options['schema'])){
			$options['fields'] = $options['schema'];
			unset($options['schema']);
			error_log(".createEvent: schema options need to be object, please re-export this instance and replace your old JSON");
		}

		$schema = $options['schema'] ?? [];
		$list = $options['fields'] ?? null;
		if($list !== null){
			foreach ($list as $value) {
				$schema[$value] = Types::Any;
			}
		}

		$obj = $this->list[$namespace] = new InstanceEvent([ 'schema' => &$schema, 'namespace' => $namespace, '_root' => $this ]);
		$this->instance->_emit('event.created', new EvEventCreated($obj));
	}

	public function renameEvent($from, $to){
		if(isset($this->list[$to])) throw new \Exception("Event with name '$to' already exist");
		if(preg_match('/\s/', $to) !== 0)
			throw new \Exception("Namespace can't have space character: '$to'");

		$oldEvInstance = $this->list[$from];
		$used = $oldEvInstance->used;
		$oldEvInstance->namespace = $to;

		foreach ($used as $iface) {
			if($iface->_enum === \Blackprint\Nodes\Enums::BPEventListen){
				$this->off($iface->data['namespace'], $iface->_listener);
				$this->on($to, $iface->_listener);
			}

			$iface->data['namespace'] = $to;
			$iface->title = implode(' ', array_slice(explode('/', $to), -2));
		}

		$this->list[$to] = $this->list[$from];
		unset($this->list[$from]);
		$this->instance->_emit('event.renamed', new EvEventRenamed($from, $to, $oldEvInstance));
	}

	public function deleteEvent($namespace){
		if(!isset($this->list[$namespace])) return;

		$exist = $this->list[$namespace];
		$map = $exist->used; // This list can be altered multiple times when deleting a node
		while (!empty($map)) {
			$iface = array_pop($map);
			$iface->node->instance->deleteNode($iface);
		}

		unset($this->list[$namespace]);
		$this->instance->_emit('event.deleted', new EvEventDeleted($exist));
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
		$evInstance = $this->list[$namespace] ?? null;
		if($evInstance === null) return;
		$schema = &$evInstance->schema;

		$refreshPorts = function($iface, $target) use(&$schema, &$_name, &$_to) {
			$ports = &$iface[$target];
			$node = $iface->node;

			if($_name !== null){
				$node->renamePort($target, $_name, $_to);
				return;
			}

			// Delete port that not exist or different type first
			$isEmitPort = $target === 'input' ? true : false;
			foreach ($ports as $name => &$val) {
				if($isEmitPort) { $isEmitPort = false; continue; }
				if(!isset($schema[$name]) || $schema[$name] != $ports[$name]->_config){
					$node->deletePort($target, $name);
				}
			}

			// Create port that not exist
			foreach ($schema as $name => $val) {
				if(!isset($ports[$target]))
					$node->createPort($target, $name, $schema[$name]);
			}
		};

		$used = &$evInstance->used;
		foreach ($used as &$iface) {
			if($iface->_enum === \Blackprint\Nodes\Enums::BPEventListen){
				if($iface->data['namespace'] === $namespace)
					$refreshPorts($iface, 'output');
			}
			elseif($iface->_enum === \Blackprint\Nodes\Enums::BPEventEmit){
				if($iface->data['namespace'] === $namespace)
					$refreshPorts($iface, 'input');
			}
			else throw new \Exception("Unrecognized node in event list's stored nodes");
		}
	}
}

class InstanceEvent {
	public $schema;
	public $_root;
	public $namespace;
	public $used = [];
	function __construct($options){
		$this->schema = &$options['schema'];
		$this->_root = $options['_root'];
		$this->namespace = $options['namespace'];
	}
}

// Don't put below to Internal.php script to avoid circular import
class EvEventCreated {
	function __construct(
		public &$reference
	){}
}

class EvEventRenamed {
	function __construct(
		public $old,
		public $now,
		public &$reference
	){}
}

class EvEventDeleted {
	function __construct(
		public &$reference
	){}
}