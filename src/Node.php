<?php
namespace Blackprint;

class Node {
	/** @var Constructor\PortLink */
	public $output = null;

	/** @var Constructor\PortLink */
	public $input = null;

	/** @var Interfaces */
	public $iface = null;
	public $routes = null;
	public $disablePorts = false;
	public $partialUpdate = false;

	private $contructed = false;
	public $_bpUpdating = false;

	/** @var Constructor\References */
	public $ref;

	// Reserved for future
	/** @param Engine $instance */
	function __construct(public &$instance){
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

	public function createPort($which, $name, $type){
		if($which !== 'input' && $which !== 'output')
			throw new \Exception("Can only create port for 'input' and 'output'");

		return $this->{$which}->_add($name, $type);
	}

	public function renamePort($which, $name, $to){
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

	public function deletePort($which, $name){
		if($which !== 'input' && $which !== 'output')
			throw new \Exception("Can only delete port for 'input' and 'output'");

		return $this->{$which}->_delete($name);
	}

	public function log($message){
		$this->instance->_log(new NodeLog($this->iface, $message));
	}

	public function _bpUpdate(){
		$this->_bpUpdating = true;
		$this->update(\Blackprint\Utils::$_null);
		$this->_bpUpdating = false;

		if($this->routes->out == null){
			$this->instance->executionOrder->next();
		}
		else{
			if($this->iface->_enum !== \Blackprint\Nodes\Enums::BPFnMain)
				$this->routes->routeOut();
			else $this->iface->_proxyInput->routes->routeOut();
		}
	}

	// ToDo: remote-control PHP
	public function syncOut($id, $data){}

	// To be overriden by module developer
	public function imported(&$data){}
	public function update(&$cable){}
	public function request(&$cable){
		$this->update($cable); // Default behaviour
	}
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