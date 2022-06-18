<?php
namespace Blackprint\Nodes;
include_once("./FnPortVar.php");

// used for instance.createFunction
class BPFunction extends \Blackprint\Constructor\CustomEvent { // <= _funcInstance
	// {name: BPVariable}
	public $variables = []; // shared between function

	// ['id', ...]
	public $privateVars = []; // private variable (different from other function)

	public $input = [];
	public $output = [];

	public function __construct($id, $options, $instance){
		$this->rootInstance = &$instance; // root instance
		$this->id = $this->title = &$id;
		$this->description = $options?->description ?? '';

		$input = &$this->input;
		$output = &$this->output;
		$this->used = []; // [\Blackprint\Node, ...]

		// This will be updated if the function sketch was modified
		$this->structure = $options->structure || [
			'BP/Fn/Input' => [[]],
			'BP/Fn/Output' => [[]],
		];

		$temp = &$this;
		$uniqId = 0;

		$this->node = function(&$instance) use(&$input, &$output, &$id, &$temp, &$uniqId) {
			BPFunctionNode::$Input = &$input;
			BPFunctionNode::$Output = &$output;
			BPFunctionNode::$namespace = &$id;

			$node = new BPFunctionNode($instance);
			$iface = &$node->iface;

			$instance->_funcInstance = &$temp;
			$node->_funcInstance = &$temp;
			$iface->description = &$temp->description;
			$iface->title = &$temp->title;
			$iface->uniqId = $uniqId++;
		};
	}

	public function _onFuncChanges($eventName, $obj, $fromNode){
		$list = &$this->used;

		foreach ($list as &$node) {
			if($node === $fromNode) continue;

			$nodeInstance = &$node->iface->bpInstance;
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
						if($inputIface->enum === Enums::BPFnOutput){
							$targetInput = &$inputIface->addPort($targetOutput, $output->name);
						}
						else throw new \Exception("Output port was not found");
					}

					if($targetOutput === null){
						if($outputIface->enum === Enums::BPFnInput){
							$targetOutput = &$outputIface->addPort($targetInput, $input->name);
						}
						else throw new \Exception("Input port was not found");
					}

					$targetInput->connectPort($targetOutput);
				}
				else if($eventName === 'cable.disconnect'){
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
			else if($eventName === 'node.created'){
				$iface = &$obj->iface;
				$nodeInstance->createNode($iface->namespace, [
					"data" => &$iface->data
				]);
			}
			else if($eventName === 'node.delete'){
				$index = array_search($obj->iface, $fromNode->iface->bpInstance->ifaceList);
				if($index === false)
					throw new \Exception("Failed to get node index");

				$iface = $nodeInstance->ifaceList[$index];
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

		// deepProperty

		// BPVariable = ./Var.js
		$temp = new BPVariable($id, $options);
		$temp->funcInstance = &$this;

		if($options->scope === 'shared')
			$this->variables[$id] = &$temp;
		else return $this->addPrivateVars($id);

		$this->emit('variable.new', $temp);
		$this->rootInstance->emit('variable.new', $temp);
		return $temp;
	}

	public function addPrivateVars($id){
		if(!in_array($id, $this->privateVars)){
			$this->privateVars[] = &$id;

			$temp = new \Blackprint\EvVariableNew('private', $id);
			$this->emit('variable.new', $temp);
			$this->rootInstance->emit('variable.new', $temp);
		}
		else return;

		$list = &$this->used;
		foreach ($list as &$node) {
			$vars = &$node->iface->bpInstance->variables;
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

	public function destroy(){
		$map = &$this->used;
		foreach ($map as &$iface) {
			$iface->node->_instance->deleteNode($iface);
		}
	}
}

class BPFunctionNode extends \Blackprint\Node { // Main function node -> BPI/F/{FunctionName}
	public static $Input = null;
	public static $Output = null;
	public static $namespace = null;

	public static $type = 'function';
	public function __construct(&$instance){
		parent::__construct($instance);
		$iface = $this->setInterface("BPIC/BP/Fn/Main");
		$iface->type = 'function';
		$iface->enum = Enums::BPFnMain;
	}

	/** @var FnMain */
	public $iface = null;

	public function init(){
		if(!$this->iface->_importOnce) $this->iface->_BpFnInit();
	}

	public function imported($data){
		$instance = $this->_funcInstance;
		$instance->used[] = &$this;
	}

	public function update($cable){
		$port = &$cable->input;

		$iface = $this->iface->_proxyInput->iface;
		if($port === null){ // Triggered by port route
			$IOutput = &$iface->output;
			$Output = &$iface->node->output;
			$thisInput = &$this->input;

			// Sync all port value
			foreach ($IOutput as &$key)
				$Output[$key]($thisInput[$key]());

			return;
		}

		// port => input port from current node
		$iface->node->output[$port->name] = $cable->value;
	}

	public function destroy(){
		$used = &$this->_funcInstance->used;

		$i = array_search($this, $used);
		if($i !== false) array_splice($used, $i, 1);
	}
}

\Blackprint\registerInterface('BP/Fn/Input', NodeInput::class);
class NodeInput extends \Blackprint\Node {
	public static $Output = [];
	public function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/Fn/Input');
		$iface->enum = Enums::BPFnInput;
		$iface->_proxyInput = true; // Port is initialized dynamically

		$funcMain = &$this->_instance->_funcMain;
		$iface->_funcMain = &$funcMain;
		$funcMain->_proxyInput = &$this;
	}
	public function imported($data){
		$input = &$this->iface->_funcMain->node->_funcInstance;

		foreach ($input as &$key)
			$this->createPort('output', $key, $input[$key]);
	}
}

\Blackprint\registerInterface('BP/Fn/Output', NodeOutput::class);
class NodeOutput extends \Blackprint\Node {
	public static $Input = [];
	public function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/BP/Fn/Output');
		$iface->enum = Enums::BPFnOutput;
		$iface->_dynamicPort = true; // Port is initialized dynamically

		$funcMain = &$this->_instance->_funcMain;
		$iface->_funcMain = &$funcMain;
		$funcMain->_proxyOutput = &$this;
	}

	public function imported($data){
		$output = &$this->iface->_funcMain->node->_funcInstance;

		foreach ($output as &$key)
			$this->createPort('input', $key, $output[$key]);
	}

	public function update($cable){
		$port = &$cable->input;

		$iface = $this->iface->_funcMain;
		if($port === null){ // Triggered by port route
			$IOutput = &$iface->output;
			$Output = &$iface->node->output;
			$thisInput = &$this->input;

			foreach ($IOutput as &$key)
				$Output[$key]($thisInput[$key]());

			return;
		}

		$iface->node->output[$port->name] = &$cable->value;
	}
}

\Blackprint\registerInterface('BPIC/BP/Fn/Main', FnMain::class);
class FnMain extends \Blackprint\Interfaces {
	public function _BpFnInit(){
		if($this->_importOnce)
			throw new \Exception("Can't import function more than once");

		$this->_importOnce = true;
		$node = &$this->node;

		$this->bpInstance = new \Blackprint\Engine();

		$bpFunction = &$node->_funcInstance;

		$newInstance = &$this->bpInstance;
		$newInstance->variables = []; // private for one function
		$newInstance->sharedVariables = &$bpFunction->variables; // shared between function
		$newInstance->functions = &$node->_instance->functions;
		$newInstance->_funcMain = &$this;
		$newInstance->_mainInstance = &$bpFunction->rootInstance;

		$bpFunction->refreshPrivateVars($newInstance);

		$swallowCopy = array_slice($bpFunction->structure, 0);
		$this->bpInstance->importJSON($swallowCopy);

		$this->bpInstance->on('cable.connect cable.disconnect node.created node.delete node.move', function($ev, $eventName) use(&$bpFunction, &$newInstance) {
			if($bpFunction->_syncing) return;

			$ev->bpFunction = &$bpFunction;
			$newInstance->_mainInstance->emit($eventName, $ev);

			$bpFunction->_syncing = true;
			$bpFunction->_onFuncChanges($eventName, $ev, $this->node);
			$bpFunction->_syncing = false;
		});
	}
}

class BPFnInOut extends \Blackprint\Interfaces {
	public function &addPort(\Blackprint\Constructor\Port $port, $customName){
		if($port === null) throw new \Exception("Can't set type with null");

		if($port->iface->namespace->startsWith("BP/Fn"))
			throw new \Exception("Function Input can't be connected directly to Output");

		$name = $customName || $port->name;
		$portType = $port->feature != null ? $port->_getPortFeature() : $port->type;

		// $nodeA, $nodeB; // Main (input) -> Input (output), Output (input) -> Main (output)
		if($this->type === 'bp-fn-input'){ // Main (input) -> Input (output)
			$inc = 1;
			while(isset($this->output[$name])){
				if(isset($this->output[$name + $inc])) $inc++;
				else {
					$name += $inc;
					break;
				}
			}

			$nodeA = &$this->_funcMain->node;
			$nodeB = &$this->node;
			$nodeA->_funcInstance->input[$name] = &$portType;
		}
		else { // Output (input) -> Main (output)
			$inc = 1;
			while(isset($this->input[$name])){
				if(isset($this->input[$name + $inc])) $inc++;
				else {
					$name += $inc;
					break;
				}
			}

			$nodeA = &$this->node;
			$nodeB = &$this->_funcMain->node;
			$nodeB->_funcInstance->output[$name] = &$portType;
		}

		$outputPort = $nodeB->createPort('output', $name, $portType);

		if($portType === \Blackprint\Types::Function)
			$inputPort = $nodeA->createPort('input', $name, \Blackprint\Port::Trigger($outputPort->_callAll));
		else $inputPort = $nodeA->createPort('input', $name, $portType);

		if($this->type === 'bp-fn-input'){
			$this->emit(`_add.{$name}`, $outputPort);
			return $outputPort;
		}

		$this->emit(`_add.{$name}`, $inputPort);
		return $inputPort;
	}
	public function renamePort($fromName, $toName){
		$funcMainNode = &$this->_funcMain->node;

		if($this->type === 'bp-fn-input'){ // Main (input) -> Input (output)
			$funcMainNode->renamePort('input', $fromName, $toName);
			$this->node->renamePort('output', $fromName, $toName);

			$main = &$funcMainNode->_funcInstance->input;
			$main[$toName] = &$main[$fromName];
			unset($main[$fromName]);
		}
		else { // Output (input) -> Main (output)
			$funcMainNode->renamePort('output', $fromName, $toName);
			$this->node->renamePort('input', $fromName, $toName);

			$main = &$funcMainNode->_funcInstance->output;
			$main[$toName] = &$main[$fromName];
			unset($main[$fromName]);
		}
	}
	public function deletePort($name){
		$funcMainNode = &$this->_funcMain->node;
		if($this->type === 'bp-fn-input'){ // Main (input) -> Input (output)
			$funcMainNode->deletePort('input', $name);
			$this->node->deletePort('output', $name);

			unset($funcMainNode->_funcInstance->input[$name]);
		}
		else { // Output (input) -> Main (output)
			$funcMainNode->deletePort('output', $name);
			$this->node->deletePort('input', $name);

			unset($funcMainNode->_funcInstance->output[$name]);
		}
	}
}

\Blackprint\registerInterface('BPIC/BP/Fn/Input', FnInput::class);
class FnInput extends BPFnInOut {
	public static $Output = [];
	public function __construct(&$node){
		parent::__construct($node);
		$this->title = 'Input';
		$this->type = 'bp-fn-input';
	}
}

\Blackprint\registerInterface('BPIC/BP/Fn/Output', FnOutput::class);
class FnOutput extends BPFnInOut {
	public static $Input = [];
	public function __construct(&$node){
		parent::__construct($node);
		$this->title = 'Output';
		$this->type = 'bp-fn-output';
	}
}