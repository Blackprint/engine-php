<?php
namespace Blackprint\Nodes;

use Blackprint\PortType;
use Blackprint\Types;

class FnVarInput extends \Blackprint\Node {
	public static $Output = [];
	public function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/FnVar/Input');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'FnInput';

		$iface->_enum = Enums::BPFnVarInput;
		$iface->_dynamicPort = true; // Port is initialized dynamically
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
}
\Blackprint\registerNode('BP/FnVar/Input', FnVarInput::class);

class FnVarOutput extends \Blackprint\Node {
	public static $Input = [];
	public function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/FnVar/Output');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'FnOutput';

		$iface->_enum = Enums::BPFnVarOutput;
		$iface->_dynamicPort = true; // Port is initialized dynamically
	}
	public function update($cable){
		$id = &$this->iface->data['name'];
		$this->refOutput->setByRef($id, $this->ref->Input["Val"]);

		// Also update the cache on the proxy node
		$this->iface->_funcMain->_proxyOutput->ref->IInput[$id]->_cache = &$this->ref->Input['Val'];
	}
}
\Blackprint\registerNode('BP/FnVar/Output', FnVarOutput::class);

class BPFnVarInOut extends \Blackprint\Interfaces {
	public function imported($data){
		if(!$data['name']) throw new \Exception("Parameter 'name' is required");
		$this->data['name'] = &$data['name'];
		$this->_funcMain = &$this->node->instance->_funcMain;
	}
};

class FnVarInputIface extends BPFnVarInOut {
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
			$iPort = $node->createPort('output', 'Val', Types::Any);
			$proxyIface = &$this->_proxyIface;

			// Run when $this node is being connected with other node
			$iPort->onConnect = function(&$cable, &$port) use(&$iPort, &$proxyIface, &$name, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				unset($iPort->onConnect);
				$proxyIface->off("_add.{$name}", $this->_waitPortInit);
				$this->_waitPortInit = null;

				$cable->disconnect();
				$node->deletePort('output', 'Val');

				$portName = new RefPortName($name);
				$portType = getFnPortType($port, 'input', $this->_funcMain, $portName);
				$newPort = $node->createPort('output', 'Val', $portType);
				$newPort->_name = &$portName;
				$newPort->connectPort($port);

				$proxyIface->addPort($port, $name);
				$this->_addListener();
				return true;
			};

			// Run when main node is the missing port
			$this->_waitPortInit = function(&$port) use(&$iPort, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				unset($iPort->onConnect);
				$this->_waitPortInit = null;

				$backup = [];
				$cables = &$this->output['Val']->cables;
				foreach ($cables as &$cable) {
					$backup[] = &$cable->input;
				}

				$node->deletePort('output', 'Val');

				$portType = getFnPortType($port, 'input', $this->_funcMain, $port->_name);
				$newPort = $node->createPort('output', 'Val', $portType);
				$this->_addListener();

				foreach ($backup as &$val)
					$newPort->connectPort($val);
			};

			$proxyIface->once("_add.{$name}", $this->_waitPortInit);
		}
		else{
			if(!isset($this->output['Val'])){
				$port = &$ports[$name];
				$portType = getFnPortType($port, 'input', $this->_funcMain, $port->_name);
				$node->createPort('output', 'Val', $portType);
			}

			$this->_addListener();
		}
	}
	public function _addListener(){
		$port = &$this->_proxyIface->output[$this->data['name']];

		if($port->feature === PortType::Trigger){
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
	public function destroy(){
		parent::destroy();

		if($this->_listener == null) return;

		$port = &$this->_proxyIface->output[$this->data['name']];
		if($port->feature === PortType::Trigger)
			$port->off('call', $this->_listener);
		else $port->off('value', $this->_listener);
	}
}
\Blackprint\registerInterface('BPIC/BP/FnVar/Input', FnVarInputIface::class);

class FnVarOutputIface extends BPFnVarInOut {
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
			$iPort = $node->createPort('input', 'Val', Types::Any);
			$proxyIface = &$this->_funcMain->_proxyOutput->iface;

			// Run when this node is being connected with other node
			$iPort->onConnect = function(&$cable, &$port) use(&$iPort, &$proxyIface, &$name, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				unset($iPort->onConnect);
				$proxyIface->off("_add.${name}", $this->_waitPortInit);
				$this->_waitPortInit = null;

				$cable->disconnect();
				$node->deletePort('input', 'Val');

				$portName = new RefPortName($name);
				$portType = getFnPortType($port, 'output', $this->_funcMain, $portName);
				$newPort = $node->createPort('input', 'Val', $portType);
				$newPort->_name = &$portName;
				$newPort->connectPort($port);

				$proxyIface->addPort($port, $name);
				return true;
			};

			// Run when main node is the missing port
			$this->_waitPortInit = function(&$port) use(&$iPort, &$node) {
				// Skip port with feature: ArrayOf
				if($port->feature === PortType::ArrayOf) return;

				unset($iPort->onConnect);
				$this->_waitPortInit = null;

				$backup = [];
				$cables = &$this->input['Val']->cables;
				foreach ($cables as &$cable) {
					$backup[] = &$cable->output;
				}

				$node->deletePort('input', 'Val');

				$portType = getFnPortType($port, 'output', $this->_funcMain, $port->_name);
				$newPort = $node->createPort('input', 'Val', $portType);

				foreach ($backup as &$value)
					$newPort->connectPort($value);
			};

			$proxyIface->once("_add.{$name}", $this->_waitPortInit);
		}
		else {
			$port = &$ports[$name];
			$portType = getFnPortType($port, 'output', $this->_funcMain, $port->_name);
			$node->createPort('input', 'Val', $portType);
		}
	}
}
\Blackprint\registerInterface('BPIC/BP/FnVar/Output', FnVarOutputIface::class);

function getFnPortType(&$port, $which, &$parentNode, &$ref){
	if($port->feature === \Blackprint\PortType::Trigger){
		if($which === 'input') // Function Input (has output port inside, and input port on main node)
			return Types::Function;
		else return \Blackprint\Port::Trigger($parentNode->output[$ref->name]->_callAll);
	}
	else return $port->feature != null ? $port->feature($port->type) : $port->type;
}