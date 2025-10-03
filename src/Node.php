<?php
namespace Blackprint;

use Blackprint\Nodes\Enums;
use Blackprint\Types;
use Blackprint\PortType;

class Node {
	/** @var Constructor\PortLink */
	public $output = null;

	/** @var Constructor\PortLink */
	public $input = null;

	/** @var Interfaces|\Blackprint\Nodes\FnMain|\Blackprint\Nodes\BPFnInOut */
	public $iface = null;
	public $routes = null;
	public $disablePorts = false;
	public $partialUpdate = false;

	// If enabled, syncIn will have 3 parameter, and syncOut will be send to related node in other function instances
	public $allowSyncToAllFunction = false;

	/** @var \Blackprint\Engine|null */
	public $parentInterface = null;

	/** @var \Blackprint\Engine|null */
	public $rootInstance = null;

	/** @var \Blackprint\Nodes\BPFunction|null */
	public $bpFunction = null;

	/** @var int Automatically call `.update` on node init depends on this flag rules */
	public static $initUpdate = 0;

	private $contructed = false;
	public $_bpUpdating = false;

	/** @var Constructor\References */
	public $ref;

	// Reserved for future
	/** @param Engine $instance */
	function __construct(public $instance){
		$this->contructed = true;
	}

	public function &setInterface($namespace='BP/default'){
		if($this->iface !== null)
			throw new \Exception('node->setInterface() can only be called once');

		if($this->contructed === false)
			throw new \Exception("Make sure you have call 'parent::__construct(\$instance);' when constructing nodes before '->setInterface'");

		if(isset(Internal::$interface[$namespace]) === false)
			throw new \Exception("Node interface for '{$namespace}' was not found, maybe .registerInterface() haven't being called?");

		$iface = new Internal::$interface[$namespace]($this);
		$this->iface = &$iface;

		return $iface;
	}

	public function createPort($which, string $name, $type){
		if($this->instance->_locked_)
			throw new \Exception("This instance was locked");

		if($which !== 'input' && $which !== 'output')
			throw new \Exception("Can only create port for 'input' and 'output'");

		if($type === null)
			throw new \Exception("Type is required for creating new port");

		if(!is_string($name)) $name = strval($name);

		if(
			# Types from Blackprint
			   $type === Types::Slot
			|| $type === Types::Any
			|| $type === Types::Slot
			|| $type === Types::Route
			|| $type === Types::Trigger

			# Types from PHP
			|| $type === Types::Function
			|| $type === Types::Number
			|| $type === Types::Array
			|| $type === Types::String
			|| $type === Types::Boolean
			|| $type === Types::Object

			# PortFeature
			|| (is_array($type) && isset($type['feature']) && (
				   $type['feature'] === PortType::ArrayOf
				|| $type['feature'] === PortType::Default
				|| $type['feature'] === PortType::Trigger
				|| $type['feature'] === PortType::Union
				|| $type['feature'] === PortType::StructOf
			))
		){
			return $this->{$which}->_add($name, $type);
		}

		print_r("Get type: ");
		var_dump($type);
		throw new \Exception("Type must be a class object or from Blackprint.Port.{feature}");
	}

	public function renamePort($which, string $name, $to){
		if($this->instance->_locked_)
			throw new \Exception("This instance was locked");

		$iPort = &$this->iface[$which];

		if(!isset($iPort[$name]))
			throw new \Exception("$which port with name '$name' was not found");

		if(isset($iPort[$to]))
			throw new \Exception("$which port with name '$to' already exist");

		$temp = &$iPort[$name];
		$iPort[$to] = &$temp;
		unset($iPort[$name]);

		$temp->name = &$to;
		$this[$which]->setByRef($to, $this[$which][$name]);
		unset($this[$which][$name]);
	}

	public function deletePort($which, string $name){
		if($this->instance->_locked_)
			throw new \Exception("This instance was locked");

		if($which !== 'input' && $which !== 'output')
			throw new \Exception("Can only delete port for 'input' and 'output'");

		if(!is_string($name)) $name = strval($name);
		return $this->{$which}->_delete($name);
	}

	public function log($message){
		$this->instance->_log(new NodeLog($this->iface, $message));
	}

	public function _bpUpdate($cable = null){
		$thisIface = $this->iface;
		$isMainFuncNode = $thisIface->_enum === Enums::BPFnMain;
		$ref = &$this->instance->executionOrder;

		$this->_bpUpdating = true;
		try {
			$this->update($cable);
		}
		finally {
			$this->_bpUpdating = false;
		}
		$thisIface->emit('updated');

		if($this->routes->out == null){
			if($isMainFuncNode && $thisIface->_proxyInput->routes->out != null){
				$thisIface->_proxyInput->routes->routeOut();
			}
		}
		else{
			if(!$isMainFuncNode)
				$this->routes->routeOut();
			else $thisIface->_proxyInput->routes->routeOut();
		}

		$ref->next();
	}

	// ToDo: remote-control PHP
	public function syncOut($id, $data, $force = false){
		if($this->allowSyncToAllFunction) $this->_syncToAllFunction($id, $data);

		$instance = $this->instance;
		if($instance->rootInstance !== null) $instance->rootInstance = $instance->rootInstance; // Ensure rootInstance is set

		$remote = $instance->_remote ?? null;
		if($remote !== null)
			$remote->nodeSyncOut($this, $id, $data, $force);
	}

	// Check into main instance if this instance is created inside of a function
	private function _isInsideFunction($fnNamespace){
		if($this->instance->rootInstance == null) return false;
		if($this->instance->parentInterface->namespace === $fnNamespace) return true;
		return $this->instance->parentInterface->node->instance->_isInsideFunction($fnNamespace);
	}

	// Sync data to all function instances
	private function _syncToAllFunction($id, $data){
		$parentInterface = &$this->instance->parentInterface;
		if($parentInterface == null) return; // This is not in a function node

		$list = $parentInterface->node->bpFunction->used;
		$nodeIndex = $this->iface->i;
		$namespace = $parentInterface->namespace;

		foreach ($list as &$iface) {
			if($iface === $parentInterface) continue; // Skip self
			$target = $iface->bpInstance->ifaceList[$nodeIndex];

			if($target == null) {
				// console.log(12, iface.bpInstance.ifaceList, target, nodeIndex, this.iface)
				throw new \Exception("Target node was not found on other function instance, maybe the node was not correctly synced/saved? (" . str_replace('BPI/F/', '', $namespace) . ");");
			}
			$target->node->syncIn($id, $data, false);
		}
	}

	// To be overriden by module developer
	public function imported($data){}
	public function update($cable){}
	public function request($cable){
		// $this->update($cable); // Default behaviour
	}
	public function initPorts($data){}
	public function destroy(){}
	public function init(){}
	public function syncIn($id, &$data, $isRemote = false){}
}

class NodeLog {
	function __construct(
		public &$iface,
		public &$message,
	) { }
}