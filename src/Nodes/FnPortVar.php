<?php
namespace Blackprint\Nodes;

use Blackprint\PortType;
use Blackprint\Types;

class FnVarInput extends \Blackprint\Node {
	public static $Output = [];
	/** @var FnVarInputIface */
	public $iface;
	public function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/FnVar/Input');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'FnInput';

		$iface->_enum = Enums::BPFnVarInput;
	}
	public function imported($data){
		if($this->routes !== null)
			$this->routes->disabled = true;
	}
	public function request($cable){
		$iface = &$this->iface;

		// This will trigger the port to request from outside and assign to this node's port
		$this->output->setByRef('Val', $iface->_funcMain->node->input[$iface->data['name']]);
	}
	public function destroy(){
		$iface = &$this->iface;
		if($iface->_listener == null) return;

		$port = &$iface->_proxyIface->output[$iface->data['name']];
		if($port->feature === PortType::Trigger)
			$port->off('call', $iface->_listener);
		else $port->off('value', $iface->_listener);
	}
}
\Blackprint\registerNode('BP/FnVar/Input', FnVarInput::class);

class FnVarOutput extends \Blackprint\Node {
	public static $Input = [];
	public $refOutput;

	public function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/FnVar/Output');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'FnOutput';

		$iface->_enum = Enums::BPFnVarOutput;
	}
	public function update($cable){
		$iface = &$this->iface;
		$id = &$iface->data['name'];
		$this->refOutput->setByRef($id, $this->ref->Input["Val"]);

		$mainNodeIFace = &$iface->_funcMain;
		$proxyOutputNode = &$mainNodeIFace->_proxyOutput;

		// Also update the cache on the proxy node
		$proxyOutputNode->ref->IInput[$id]->_cache = &$this->ref->Input['Val'];

		// If main node has route and the output proxy doesn't have input route
		// Then trigger out route on the main node
		$mainNodeRoutes = &$mainNodeIFace->node->routes;
		if($mainNodeRoutes->out !== null && count($proxyOutputNode->routes->in) === 0){
			$mainNodeRoutes->routeOut();
		}
	}
}
\Blackprint\registerNode('BP/FnVar/Output', FnVarOutput::class);

class BPFnVarInOut extends \Blackprint\Interfaces {
	public $_dynamicPort = true; // Port is initialized dynamically

	public function imported($data){
		if(!$data['name']) throw new \Exception("Parameter 'name' is required");
		$this->data['name'] = &$data['name'];
		$this->_funcMain = &$this->node->instance->_funcMain;
	}
};

class FnVarInputIface extends BPFnVarInOut {
	public $_listener;
	public $_proxyIface;
	public $_waitPortInit;
	public $type;

	public function __construct(&$node){
		parent::__construct($node);
		$this->type = 'bp-fnvar-input';
	}
	public function imported($data){
		parent::imported($data);
		$ports = &$this->_funcMain->ref->IInput;
		$node = &$this->node;

		$this->_proxyIface = &$this->_funcMain->_proxyInput->iface;

		// Create temporary port if the main function doesn't have the port
		$name = $data['name'];
		if(!isset($ports[$name])){
			$iPort = $node->createPort('output', 'Val', Types::Slot);
			$proxyIface = &$this->_proxyIface;

			// Run when $this node is being connected with other node
			$iPort->onConnect = function(&$cable, &$port) use(&$iPort, &$proxyIface, &$name, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				$iPort->onConnect = false;
				$proxyIface->off("_add.{$name}", $this->_waitPortInit);
				$this->_waitPortInit = null;

				$portName = new RefPortName($name);
				$portType = getFnPortType($port, 'input', $this, $portName);
				$iPort->assignType($portType);
				$iPort->_name = $portName;

				$proxyIface->addPort($port, $name);
				($cable->owner === $iPort ? $port : $iPort)->connectCable($cable);

				$this->_addListener();
				return true;
			};

			// Run when main node is the missing port
			$this->_waitPortInit = function(&$port) use(&$iPort, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				$iPort->onConnect = false;
				$this->_waitPortInit = null;

				$portType = getFnPortType($port, 'input', $this, $port->_name);
				$iPort->assignType($portType);
				$this->_addListener();
			};

			$proxyIface->once("_add.{$name}", $this->_waitPortInit);
		}
		else{
			if(!isset($this->output['Val'])){
				$port = &$this->_funcMain->_proxyInput->iface->output[$name];
				$portType = getFnPortType($port, 'input', $this, $port->_name);

				$newPort = $node->createPort('output', 'Val', $portType);
				$newPort->_name = &$port->_name;
			}

			$this->_addListener();
		}
	}
	public function _addListener(){
		$port = &$this->_proxyIface->output[$this->data['name']];

		if($port->type === Types::Trigger){
			$this->_listener = function() {
				$this->ref->Output['Val']();
			};

			$port->on('call', $this->_listener);
		}
		else{
			$this->_listener = function(&$dat) {
				$port = &$dat->port;

				if($port->iface->node->routes->out != null){
					$Val = &$this->ref->IOutput['Val'];
					$Val->value = &$port->value; // Change value without trigger node.update

					$list = &$Val->cables;
					foreach ($list as &$temp) {
						// Clear connected cable's cache
						$temp->input->_cache = null;
					}
					return;
				}

				$this->ref->Output->setByRef('Val', $port->value);
			};

			$port->on('value', $this->_listener);
		}
	}
}
\Blackprint\registerInterface('BPIC/BP/FnVar/Input', FnVarInputIface::class);

class FnVarOutputIface extends BPFnVarInOut {
	public $_waitPortInit;
	public $type;

	/** @var FnVarOutput */
	public $node;

	public function __construct(&$node){
		parent::__construct($node);
		$this->type = 'bp-fnvar-output';
	}
	public function imported($data){
		parent::imported($data);
		$ports = &$this->_funcMain->ref->IOutput;
		$node = &$this->node;

		$node->refOutput = &$this->_funcMain->ref->Output;

		// Create temporary port if the main function doesn't have the port
		$name = $data['name'];
		if(!isset($ports[$name])){
			$iPort = $node->createPort('input', 'Val', Types::Slot);
			$proxyIface = &$this->_funcMain->_proxyOutput->iface;

			// Run when this node is being connected with other node
			$iPort->onConnect = function(&$cable, &$port) use(&$iPort, &$proxyIface, &$name, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				$iPort->onConnect = false;
				$proxyIface->off("_add.$name", $this->_waitPortInit);
				$this->_waitPortInit = null;

				$portName = new RefPortName($name);
				$portType = getFnPortType($port, 'output', $this, $portName);
				$iPort->assignType($portType);
				$iPort->_name = $portName;

				$this->_recheckRoute();

				// echo $name;
				// debug_print_backtrace();

				$proxyIface->addPort($port, $name);
				($cable->owner === $iPort ? $port : $iPort)->connectCable($cable);

				return true;
			};

			// Run when main node is the missing port
			$this->_waitPortInit = function(&$port) use(&$iPort, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				$iPort->onConnect = false;
				$this->_waitPortInit = null;

				$portType = getFnPortType($port, 'output', $this, $port->_name);
				// echo $port->_name->name;
				// debug_print_backtrace();
				$iPort->assignType($portType);
			};

			$proxyIface->once("_add.{$name}", $this->_waitPortInit);
		}
		else {
			$port = &$this->_funcMain->_proxyOutput->iface->input[$name];
			$portType = getFnPortType($port, 'output', $this, $port->_name);
			$newPort = $node->createPort('input', 'Val', $portType);
			$newPort->_name = &$port->_name;
		}
	}
	public function _recheckRoute(){
		if($this->input->Val->type !== Types::Trigger) return;

		$routes = &$this->node->routes;
		$routes->disableOut = true;
		$routes->noUpdate = true;
	}
}
\Blackprint\registerInterface('BPIC/BP/FnVar/Output', FnVarOutputIface::class);

class _Dummy{ public static $PortTrigger;}
_Dummy::$PortTrigger = \Blackprint\Port::Trigger(fn()=> throw new \Exception("This can't be called"));

function getFnPortType(&$port, $which, &$forIface, &$ref){
	if($port->feature === \Blackprint\PortType::Trigger || $port->type === Types::Trigger){
		// Function Input (has output port inside, and input port on main node)

		if($which === 'input')
			return Types::Trigger;
		else
			return _Dummy::$PortTrigger;
	}
	// Skip ArrayOf port feature, and just use the type
	elseif($port->feature === \Blackprint\PortType::ArrayOf){
		return $port->type;
	}
	elseif($port->_isSlot){
		throw new \Exception("Function node's input/output can't use port from an lazily assigned port type (Types.Slot)");
	}
	else return $port->_config;
}