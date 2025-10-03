<?php
namespace Blackprint\Nodes;

use Blackprint\PortType;

include_once(__DIR__."/FnPortVar.php");

/** For internal library use only */
class RefPortName {
	function __construct(
		public &$name,
	){ }
}

// used for instance.createFunction
class BPFunction extends \Blackprint\Constructor\CustomEvent { // <= _funcInstance
	// {name: BPVariable}
	public $variables = []; // shared between function

	// ['id', ...]
	public $privateVars = []; // private variable (different from other function)

	public $id;
	public $title;
	public $description = '';
	public $structure;
	public $rootInstance;
	public $input = [];
	public $output = [];
	public $used = [];
	public $node = null; // Node constructor
	public $directInvokeFn = null;
	public $_destroying = false;
	public $_ready = false;
	public $_readyResolve = null;
	public $_readyPromise = null;
	public $_envNameListener = null;
	public $_varNameListener = null;
	public $_funcNameListener = null;
	public $_funcPortNameListener = null;
	public $_eventNameListener = null;
	public $_syncing = false;

	public function __construct($id, $options, $instance){
		$this->rootInstance = &$instance; // root instance

		$id = preg_replace('/^\/|\/$/m', '', $id);
		$id = preg_replace('/[`~!@#$%^&*()\-_+={}\[\]:"|;\'\\\\,.<>?]+/', '_', $id);
		$this->id = &$id;
		$this->title = $options['title'] ?? $id;
		// $this->description = $options['description'] ?? ''; // Future reference

		$input = &$this->input;
		$output = &$this->output;
		$this->used = []; // [\Blackprint\Interfaces, ...]

		// This will be updated if the function sketch was modified
		$this->structure = $options['structure'] ?? [
			'instance' => [
				'BP/Fn/Input' => [['i' => 0]],
				'BP/Fn/Output' => [['i' => 1]],
			],
		];

		$temp = &$this;
		$uniqId = 0;

		$this->node = function&($instance) use(&$input, &$output, &$id, &$temp, &$uniqId) {
			BPFunctionNode::$Input = &$input;
			BPFunctionNode::$Output = &$output;
			BPFunctionNode::$namespace = &$id;

			$node = new BPFunctionNode($instance);
			$iface = &$node->iface;

			$instance->bpFunction = &$temp;
			$node->bpFunction = &$temp;
			// $iface->description = &$temp->description; // Future reference
			$iface->title = &$temp->title;
			$iface->uniqId = $uniqId++;

			$iface->_enum = Enums::BPFnMain;
			$iface->_prepare_(BPFunctionNode::class);
			return $node;
		};
	}

	public function _onFuncChanges($eventName, $obj, $fromNode){
		$list = &$this->used;

		foreach ($list as &$iface_) {
			if($iface_->node === $fromNode) continue;

			$nodeInstance = &$iface_->bpInstance;
			$nodeInstance->pendingRender = true; // Force recalculation for cable position

			if($eventName === 'cable.connect' || $eventName === 'cable.disconnect'){
				$input = &$obj->cable->input;
				$output = &$obj->cable->output;
				$ifaceList = &$fromNode->iface->bpInstance->ifaceList;

				// Skip event that also triggered when deleting a node
				if($input->iface->_bpDestroy || $output->iface->_bpDestroy) continue;

				$inputIface = &$nodeInstance->ifaceList[array_search($input->iface, $ifaceList)];
				if($inputIface === null)
					throw new \Exception("Failed to get node input iface index");

				$outputIface = &$nodeInstance->ifaceList[array_search($output->iface, $ifaceList)];
				if($outputIface === null)
					throw new \Exception("Failed to get node output iface index");

				if($inputIface->namespace !== $input->iface->namespace){
					echo $inputIface->namespace.' !== '.$input->iface->namespace;
					throw new \Exception("Input iface namespace was different");
				}

				if($outputIface->namespace !== $output->iface->namespace){
					echo $outputIface->namespace.' !== '.$output->iface->namespace;
					throw new \Exception("Output iface namespace was different");
				}

				if($eventName === 'cable.connect'){
					$targetInput = &$inputIface->input[$input->name];
					$targetOutput = &$outputIface->output[$output->name];

					if($targetInput === null){
						if($inputIface->_enum === Enums::BPFnOutput){
							$targetInput = &$inputIface->createPort($targetOutput, $output->name);
						}
						else throw new \Exception("Output port was not found");
					}

					if($targetOutput === null){
						if($outputIface->_enum === Enums::BPFnInput){
							$targetOutput = &$outputIface->createPort($targetInput, $input->name);
						}
						else throw new \Exception("Input port was not found");
					}

					$targetInput->connectPort($targetOutput);
				}
				elseif($eventName === 'cable.disconnect'){
					$cables = &$inputIface->input[$input->name]->cables;
					$outputPort = &$outputIface->output[$output->name];

					foreach ($cables as &$cable) {
						if($cable->output === $outputPort){
							$cable->disconnect();
							break;
						}
					}
				}
			}
			elseif($eventName === 'node.created'){
				$iface = &$obj->iface;
				$nodeInstance->createNode($iface->namespace, [
					"data" => &$iface->data
				]);
			}
			elseif($eventName === 'node.delete'){
				$index = array_search($obj->iface, $fromNode->iface->bpInstance->ifaceList);
				if($index === false)
					throw new \Exception("Failed to get node index");

				$iface = &$nodeInstance->ifaceList[$index];
				if($iface->namespace !== $obj->iface->namespace){
					echo $iface->namespace.' '.$obj->iface->namespace;
					throw new \Exception("Failed to delete node from other function instance");
				}

				if($eventName === 'node.delete')
					$nodeInstance->deleteNode($iface);
			}
		}
	}

	public function createNode(&$instance, &$options){
		return $instance->createNode($this->node, $options);
	}

	public function &createVariable($id, $options){
		if(isset($this->variables[$id]))
			throw new \Exception("Variable id already exist: $id");

		if(str_contains($id, '/'))
			throw new \Exception("Slash symbol is reserved character and currently can't be used for creating path");

		// setDeepProperty

		$temp = new BPVariable($id, $options);
		$temp->_scope = &$options['scope'];

		if($options['scope'] === VarScope::Shared)
			$this->variables[$id] = &$temp;
		else {
			return $temp2 = $this->addPrivateVars($id);
		}

		$this->emit('variable.new', $temp);
		$this->rootInstance->emit('variable.new', $temp);
		return $temp;
	}

	public function addPrivateVars($id){
		if(str_contains($id, '/'))
			throw new \Exception("Slash symbol is reserved character and currently can't be used for creating path");

		if(!in_array($id, $this->privateVars, true)){
			$this->privateVars[] = &$id;

			$temp = new \Blackprint\EvVariableNew($this, VarScope::Private, $id);
			$this->emit('variable.new', $temp);
			$this->rootInstance->emit('variable.new', $temp);
		}
		else return;

		$list = &$this->used;
		foreach ($list as &$iface) {
			$vars = &$iface->bpInstance->variables;
			$vars[$id] = new BPVariable($id);
		}
	}

	public function refreshPrivateVars(&$instance){
		$vars = &$instance->variables;

		$list = &$this->privateVars;
		foreach ($list as &$id) {
			$vars[$id] = new BPVariable($id);
		}
	}

	public function renamePort($which, $fromName, $toName){
		$main = &$this->{$which};
		$main->setByRef($toName, $main[$fromName]);
		unset($main[$fromName]);

		$used = &$this->used;
		$proxyPort = $which === 'output' ? 'input' : 'output';

		foreach($used as &$iface){
			$iface->node->renamePort($which, $fromName, $toName);

			$temp = $which === 'output' ? $iface->_proxyOutput : $iface->_proxyInput;
			$temp->iface[$proxyPort][$fromName]->_name->name = $toName;
			$temp->renamePort($proxyPort, $fromName, $toName);

			$ifaces = &$iface->bpInstance->ifaceList;
			foreach($ifaces as &$proxyVar){
				if(($which === 'output' && $proxyVar->namespace !== "BP/FnVar/Output")
					|| ($which === 'input' && $proxyVar->namespace !== "BP/FnVar/Input"))
					continue;

				if($proxyVar->data['name'] !== $fromName) continue;
				$proxyVar->data['name'] = &$toName;

				if($which === 'output')
					$proxyVar[$proxyPort]['Val']->_name->name = &$toName;
			}
		}
	}

	public function destroy(){
		$map = &$this->used;
		foreach ($map as &$iface) {
			$iface->node->instance->deleteNode($iface);
		}
	}
}

// Main function node
class BPFunctionNode extends \Blackprint\Node { // Main function node -> BPI/F/{FunctionName}
	public static $Input = null;
	public static $Output = null;
	public static $namespace = null;

	public static $type = 'function';
	public function __construct($instance){
		parent::__construct($instance);
		$this->partialUpdate = true;
		$iface = $this->setInterface("BPIC/BP/Fn/Main");
		$iface->type = 'function';
		$iface->_enum = Enums::BPFnMain;
	}

	/** @var FnMain */
	public $iface = null;

	public function init(){
		// This is required when the node is created at runtime (maybe from remote or Sketch)
		if(!$this->iface->_importOnce) $this->iface->_BpFnInit();
	}

	public function imported($data){
		$instance = &$this->bpFunction;
		$instance->used[] = &$this->iface;
	}

	public function update($cable){
		$iface = &$this->iface->_proxyInput->iface;
		$Output = &$iface->node->output;

		if($cable === null){ // Triggered by port route
			$IOutput = &$iface->output;
			$thisInput = &$this->input;

			// Sync all port value
			foreach ($IOutput as $key => &$value){
				if($value->type === \Blackprint\Types::Trigger) continue;
				$Output->setByRef($key, $thisInput[$key]);
			}

			return;
		}

		// Update output value on the input node inside the function node
		$Output->setByRef($cable->input->name, $cable->value);
	}

	public function destroy(){
		$used = &$this->bpFunction->used;

		$i = array_search($this->iface, $used);
		if($i !== false) array_splice($used, $i, 1);

		$this->iface->bpInstance->destroy();
	}
}

class NodeInput extends \Blackprint\Node {
	public static $Output = [];
	public function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/Fn/Input');
		$iface->_enum = Enums::BPFnInput;
		$iface->_proxyInput = true; // Port is initialized dynamically

		$funcMain = &$this->instance->parentInterface;
		$iface->_funcMain = &$funcMain;
		$funcMain->_proxyInput = &$this;
	}
	public function imported($data){
		$input = &$this->iface->_funcMain->node->bpFunction->input;

		foreach ($input as $key => &$value)
			$this->createPort('output', $key, $value);
	}
	public function request($cable){
		$name = &$cable->output->name;

		// This will trigger the port to request from outside and assign to this node's port
		$this->output->setByRef($name, $this->iface->_funcMain->node->input[$name]);
	}
}
\Blackprint\registerNode('BP/Fn/Input', NodeInput::class);

class NodeOutput extends \Blackprint\Node {
	public static $Input = [];
	public function __construct($instance){
		parent::__construct($instance);
		$this->partialUpdate = true; // Trigger this.update(cable) function everytime this node connected to any port that have update

		$iface = $this->setInterface('BPIC/BP/Fn/Output');
		$iface->_enum = Enums::BPFnOutput;
		$iface->_dynamicPort = true; // Port is initialized dynamically

		$funcMain = &$this->instance->parentInterface;
		$iface->_funcMain = &$funcMain;
		$funcMain->_proxyOutput = &$this;
	}

	public function imported($data){
		$output = &$this->iface->_funcMain->node->bpFunction->output;

		foreach ($output as $key => &$value)
			$this->createPort('input', $key, $value);
	}

	public function update($cable){
		$iface = &$this->iface->_funcMain;
		$Output = &$iface->node->output;

		if($cable === null){ // Triggered by port route
			$IOutput = &$iface->output;
			$thisInput = &$this->input;

			// Sync all port value
			foreach ($IOutput as $key => &$value){
				if($value->type === \Blackprint\Types::Trigger) continue;
				$Output->setByRef($key, $thisInput[$key]);
			}

			return;
		}

		$Output->setByRef($cable->input->name, $cable->value);
	}
}
\Blackprint\registerNode('BP/Fn/Output', NodeOutput::class);

class FnMain extends \Blackprint\Interfaces {
	public $_importOnce = false;
	public $_save = null;
	public $_portSw_ = null;
	public $_proxyInput = null;
	public $_proxyOutput = null;
	public $uniqId = null;
	public $bpInstance = null;
	public $type = false;

	// We won't internally mark this node for having dynamic port
	// The port was defined after the node is imported, the outer port
	// will already have a type

	public function _BpFnInit(){
		if($this->_importOnce)
			throw new \Exception("Can't import function more than once");

		$this->_importOnce = true;
		$node = &$this->node;

		$this->bpInstance = new \Blackprint\Engine();
		if($this->data?->pause) $this->bpInstance->executionOrder->pause = true;

		$bpFunction = &$node->bpFunction;

		$newInstance = &$this->bpInstance;
		$newInstance->variables = []; // private for one function
		$newInstance->sharedVariables = &$bpFunction->variables; // shared between function
		$newInstance->functions = &$node->instance->functions;
		$newInstance->events = &$node->instance->events;
		$newInstance->parentInterface = &$this;
		$newInstance->rootInstance = &$bpFunction->rootInstance;

		$bpFunction->refreshPrivateVars($newInstance);

		$swallowCopy = array_slice($bpFunction->structure, 0);
		$newInstance->importJSON($swallowCopy, ['clean'=> false]);

		// Init port switches
		if($this->_portSw_ != null){
			$this->_initPortSwitches($this->_portSw_);
			$this->_portSw_ = null;

			$InputIface = &$this->_proxyInput->iface;
			if($InputIface->_portSw_ != null){
				$InputIface->_initPortSwitches($InputIface->_portSw_);
				$InputIface->_portSw_ = null;
			}
		}

		$this->_save = function(&$ev, &$eventName, $force=false) use(&$bpFunction, &$newInstance) {
			// $this->bpInstance._emit('_fn.structure.update', { iface: this });
			if($force || $bpFunction->_syncing) return;

			// $ev->bpFunction = &$bpFunction;
			$newInstance->rootInstance->emit($eventName, $ev);

			$bpFunction->_syncing = true;
			try {
				$bpFunction->_onFuncChanges($eventName, $ev, $this->node);
			}
			finally {
				$bpFunction->_syncing = false;
			}
		};

		$this->bpInstance->on('cable.connect cable.disconnect node.created node.delete node.id.changed port.default.changed _port.split _port.unsplit _port.resync.allow _port.resync.disallow', $this->_save);
	}
	public function imported($data){ $this->data = &$data; }
	public function renamePort($which, $fromName, $toName){
		$this->node->bpFunction->renamePort($which, $fromName, $toName);
		($this->_save)(false, false, true);

		// $this->node.instance._emit('_fn.rename.port', {
		// 	iface: this,
		// 	which, fromName, toName,
		// });
	}
}
\Blackprint\registerInterface('BPIC/BP/Fn/Main', FnMain::class);

class BPFnInOut extends \Blackprint\Interfaces {
	/** @var \Blackprint\Nodes\NodeOutput|\Blackprint\Nodes\NodeInput */
	public $_proxyInput;
	public $_proxyOutput;
	public $type = false;
	public $_dynamicPort = true; // Port is initialized dynamically
	public function &createPort(\Blackprint\Constructor\Port $port, $customName){
		if($port === null) return;

		if(str_starts_with($port->iface->namespace, "BP/Fn"))
			throw new \Exception("Function Input can't be connected directly to Output");

		$portType = null;
		$inputPortType = null;

		$name = $port->_name?->name ?? $customName ?? $port->name;
		$nodeA, $nodeB; // Main (input) -> Input (output), Output (input) -> Main (output)
		if($this->type === 'bp-fn-input'){ // Main (input) -> Input (output)
			$inc = 1;
			while(isset($this->output[$name])){
				if(isset($this->output[$name + $inc])) $inc++;
				else {
					$name .= $inc;
					break;
				}
			}

			$nodeA = &$this->_funcMain->node;
			$nodeB = &$this->node;
			$refName = new RefPortName($name);

			$portType = getFnPortType($port, 'input', $this, $refName);

			if($portType === \Blackprint\Types::Trigger)
				$inputPortType = \Blackprint\Port::Trigger(fn(&$_port) => $_port->iface->_proxyInput->output[$refName->name]());
			else $inputPortType = $portType;

			$nodeA->bpFunction->input[$name] = &$inputPortType;
		}
		else { // Output (input) -> Main (output)
			$inc = 1;
			while(isset($this->input[$name])){
				if(isset($this->input[$name + $inc])) $inc++;
				else {
					$name .= $inc;
					break;
				}
			}

			$nodeA = &$this->node;
			$nodeB = &$this->_funcMain->node;
			$refName = new RefPortName($name);

			$portType = getFnPortType($port, 'output', $this, $refName);

			if($port->type === \Blackprint\Types::Trigger)
				$inputPortType = \Blackprint\Port::Trigger(fn(&$_port) => $_port->iface->parentInterface->node->output[$refName->name]());
			else $inputPortType = $portType;

			$nodeB->bpFunction->output[$name] = &$inputPortType;
		}

		$outputPort = $nodeB->createPort('output', $name, $portType);
		$inputPort = $nodeA->createPort('input', $name, $inputPortType);

		if($this->type === 'bp-fn-input'){
			$outputPort->_name = $refName; // When renaming port, this also need to be changed
			$this->emit("_add.{$name}", $outputPort);
			return $outputPort;
		}

		$inputPort->_name = $refName; // When renaming port, this also need to be changed
		$this->emit("_add.{$name}", $inputPort);

		// Code below is used when we dynamically modify function output node inside the function node
		// where in a single function we can have multiple output node "BP/Fn/Output"
		$list = &$this->parentInterface->_proxyOutput;
		foreach ($list as &$item) {
			$port_ = $item->createPort('input', $name, $inputPortType);
			$port_->_name = $inputPort->_name;
			$this->emit("_add.$name", $port_);
		}

		return $inputPort;
	}
	public function renamePort($fromName, $toName){
		$bpFunction = &$this->_funcMain->node->bpFunction;

		// Main (input) -> Input (output)
		if($this->type === 'bp-fn-input')
			$bpFunction->renamePort('input', $fromName, $toName);

		// Output (input) -> Main (output)
		else $bpFunction->renamePort('output', $fromName, $toName);

		// $this->node.instance._emit('_fn.rename.port', {
		// 	iface: this,
		// 	which, fromName, toName,
		// });
	}
	public function deletePort($name){
		$funcMainNode = &$this->_funcMain->node;
		if($this->type === 'bp-fn-input'){ // Main (input) -> Input (output)
			$funcMainNode->deletePort('input', $name);
			$this->node->deletePort('output', $name);

			unset($funcMainNode->bpFunction->input[$name]);
		}
		else { // Output (input) -> Main (output)
			$funcMainNode->deletePort('output', $name);
			$this->node->deletePort('input', $name);

			unset($funcMainNode->bpFunction->output[$name]);
		}
	}
}

class FnInput extends BPFnInOut {
	public static $Output = [];
	public function __construct(&$node){
		parent::__construct($node);
		$this->title = 'Input';
		$this->type = 'bp-fn-input';
	}
}
\Blackprint\registerInterface('BPIC/BP/Fn/Input', FnInput::class);

class FnOutput extends BPFnInOut {
	public static $Input = [];
	public function __construct(&$node){
		parent::__construct($node);
		$this->title = 'Output';
		$this->type = 'bp-fn-output';
	}
}
\Blackprint\registerInterface('BPIC/BP/Fn/Output', FnOutput::class);