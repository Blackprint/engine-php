<?php
namespace Blackprint\Nodes;

class FnInput extends \Blackprint\Node {
	public static $Output = [];
	public function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/FnVar/Input');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'FnInput';

		$iface->enum = Enums::BPFnVarInput;
		$iface->_dynamicPort = true; // Port is initialized dynamically
	}
	public function imported($data){
		$this->routes->disabled = true;
	}
}

class FnOutput extends \Blackprint\Node {
	public static $Input = [];
	public function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/FnVar/Output');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'FnOutput';

		$iface->enum = Enums::BPFnVarOutput;
		$iface->_dynamicPort = true; // Port is initialized dynamically
	}
	public function update($cable){
		$id = &$this->iface->data->name;
		$this->refOutput[$id] = &$this->ref->Input["Val"];
	}
}

class BPFnVarInOut extends \Blackprint\Interfaces {
	public function imported($data){
		if(!$data->name) throw new \Exception("Parameter 'name' is required");
		$this->data->name = &$data->name;
		$this->_parentFunc = &$this->node->_instance->_funcMain;
	}
};

\Blackprint\registerInterface('BPIC/BP/FnVar/Input', FnVarInput::class);
class FnVarInput extends BPFnVarInOut {
	public function __construct($node){
		parent::__construct($node);
		$this->type = 'bp-fnvar-input';
	}
	public function imported($data){
		parent::imported($data);
		$ports = &$this->_parentFunc->ref->IInput;
		$node = &$this->node;

		$this->_proxyIface = &$this->_parentFunc->_proxyInput->iface;

		// Create temporary port if the main function doesn't have the port
		$name = $data->name;
		if(!isset($ports[$name])){
			$iPort = $node->createPort('output', 'Val', null); // null = any type
			$proxyIface = $this->_proxyIface;

			// Run when $this node is being connected with other node
			$iPort->onConnect = function($cable, $port) use(&$iPort, &$proxyIface, &$name) {
				unset($iPort->onConnect);
				$proxyIface->off(`_add.{$name}`, $this->_waitPortInit);

				$node = &$this->node;

				$cable->disconnect();
				$node->deletePort('output', 'Val');

				$portType = $port->feature != null ? $port->feature($port->type) : $port->type;
				$newPort = $node->createPort('output', 'Val', $portType);
				$newPort->connectPort($port);

				$proxyIface->addPort($port, $name);
				$this->_addListener();
				return true;
			};

			// Run when main node is the missing port
			$this->_waitPortInit = function($port) use(&$iPort) {
				unset($iPort->onConnect);

				$backup = [];
				$cables = &$this->output->Val->cables;
				foreach ($cables as &$cable) {
					$backup[] = &$cable->output;
				}

				$node = &$this->node;
				$node->deletePort('output', 'Val');

				$portType = $port->feature != null ? $port->feature($port->type) : $port->type;
				$newPort = $node->createPort('output', 'Val', $portType);
				$this->_addListener();

				foreach ($backup as &$val)
					$newPort->connectPort($val);
			};

			$proxyIface->once(`_add.{$name}`, $this->_waitPortInit);
		}
		else{
			if(!isset($this->output['Val'])){
				$port = &$ports[$name];
				$portType = $port->feature != null ? $port->feature($port->type) : $port->type;
				$node->createPort('output', 'Val', $portType);
			}

			$this->_addListener();
		}
	}
	public function _addListener(){
		$this->_listener = function($dat) {
			$port = &$dat->port;

			if($port->iface->node->routes->out != null){
				$Val = &$this->ref->IOutput['Val'];
				$Val->value = &$port->value; // Change value without trigger node.update

				$list = &$Val->cables;
				foreach ($list as &$temp) {
					if($temp->hasBranch) continue;

					// Clear connected cable's cache
					$temp->input->_cache = null;
				}
				return;
			}

			$this->ref->Output['Val'] = &$port->value;
		};

		$this->_proxyIface->output[$this->data->name]->on('value', $this->_listener);
	}
	public function destroy(){
		parent::destroy();

		if($this->_listener == null) return;
		$this->_proxyIface->output[$this->data->name]->off('value', $this->_listener);
	}
}

\Blackprint\registerInterface('BPIC/BP/FnVar/Output', FnVarOutput::class);
class FnVarOutput extends BPFnVarInOut {
	public function __construct($node){
		parent::__construct($node);
		$this->type = 'bp-fnvar-output';
	}
	public function imported($data){
		parent::imported($data);
		$ports = &$this->_parentFunc->ref->IOutput;
		$node = &$this->node;

		$node->refOutput = &$this->_parentFunc->ref->Output;

		// Create temporary port if the main function doesn't have the port
		$name = $data->name;
		if(!isset($ports[$name])){
			$iPort = $node->createPort('input', 'Val', null); // null = any type
			$proxyIface = &$this->_parentFunc->_proxyOutput->iface;

			// Run when this node is being connected with other node
			$iPort->onConnect = function($cable, $port) use(&$iPort, &$proxyIface, &$name) {
				unset($iPort->onConnect);
				$proxyIface->off(`_add.${name}`, $this->_waitPortInit);

				$node = &$this->node;
				$cable->disconnect();
				$node->deletePort('input', 'Val');

				$portType = $port->feature != null ? $port->feature($port->type) : $port->type;
				$newPort = $node->createPort('input', 'Val', $portType);
				$newPort->connectPort($port);

				$proxyIface->addPort($port, $name);
				return true;
			};

			// Run when main node is the missing port
			$this->_waitPortInit = function($port) use(&$iPort) {
				unset($iPort->onConnect);

				$backup = [];
				$cables = &$this->input->Val->cables;
				foreach ($cables as &$cable) {
					$backup[] = &$cable->output;
				}

				$node = &$this->node;
				$node->deletePort('input', 'Val');

				$portType = $port->feature != null ? $port->feature($port->type) : $port->type;
				$newPort = $node->createPort('input', 'Val', $portType);

				foreach ($backup as &$value)
					$newPort->connectPort($value);
			};

			$proxyIface->once(`_add.{$name}`, $this->_waitPortInit);
		}
		else {
			$port = $ports[$name];
			$portType = $port->feature != null ? $port->feature($port->type) : $port->type;
			$node->createPort('input', 'Val', $portType);
		}
	}
}