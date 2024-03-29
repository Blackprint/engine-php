<?php
namespace Blackprint;

use Blackprint\Nodes\Enums;

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

	private $contructed = false;
	public $_bpUpdating = false;
	public $_funcInstance = null;

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

		return $this->{$which}->_add($name, $type);
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

		return $this->{$which}->_delete($name);
	}

	public function log($message){
		$this->instance->_log(new NodeLog($this->iface, $message));
	}

	public function _bpUpdate(){
		$thisIface = $this->iface;
		$isMainFuncNode = $thisIface->_enum === Enums::BPFnMain;
		$ref = &$this->instance->executionOrder;

		$this->_bpUpdating = true;
		try {
			$this->update(\Blackprint\Utils::$_null);
		}
		finally {
			$this->_bpUpdating = false;
		}
		$this->iface->emit('updated');

		if($this->routes->out == null){
			if($isMainFuncNode && $thisIface->node->routes->out != null){
				$thisIface->node->routes->routeOut();
				$ref->next();
			}
			else $ref->next();
		}
		else{
			if(!$isMainFuncNode)
				$this->routes->routeOut();
			else $thisIface->_proxyInput->routes->routeOut();

			$ref->next();
		}
	}

	// ToDo: remote-control PHP
	public function syncOut($id, $data){}

	// To be overriden by module developer
	public function imported($data){}
	public function update($cable){}
	public function request($cable){
		// $this->update($cable); // Default behaviour
	}
	public function initPorts($data){}
	public function destroy(){}
	public function init(){}
	public function syncIn($id, &$data){}
}

class NodeLog {
	function __construct(
		public &$iface,
		public &$message,
	) { }
}