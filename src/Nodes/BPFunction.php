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
			'_bpStale' => false,
			'instance' => [
				'BP/Fn/Input' => [['i' => 0]],
				'BP/Fn/Output' => [['i' => 1]],
			],
		];

		// Event listeners for environment, variable, function, and event renaming
		$this->_envNameListener = function($ev) { return $this->_onEnvironmentRenamed($ev); };
		$this->_varNameListener = function($ev) { return $this->_onVariableRenamed($ev); };
		$this->_funcNameListener = function($ev) { return $this->_onFunctionRenamed($ev); };
		$this->_funcPortNameListener = function($ev) { return $this->_onFunctionPortRenamed($ev); };
		$this->_eventNameListener = function($ev) { return $this->_onEventRenamed($ev); };

		// Register event listeners
		$this->rootInstance->on('environment.renamed', $this->_envNameListener);
		$this->rootInstance->on('variable.renamed', $this->_varNameListener);
		$this->rootInstance->on('function.renamed', $this->_funcNameListener);
		$this->rootInstance->on('function.port.renamed', $this->_funcPortNameListener);
		$this->rootInstance->on('event.renamed', $this->_eventNameListener);

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

	public function _onEnvironmentRenamed($ev){
		/** Handle environment name changes */
		$instance = &$this->structure['instance'];
		$list = [];
		if (isset($instance['BP/Env/Get']))
			$list = array_merge($list, $instance['BP/Env/Get']);
		if (isset($instance['BP/Env/Set']))
			$list = array_merge($list, $instance['BP/Env/Set']);

		foreach ($list as &$item) {
			if ($item['data']['name'] === $ev->old) {
				$item['data']['name'] = $ev->now;
				$item['data']['title'] = $ev->now;
			}
		}
	}

	public function _onVariableRenamed($ev){
		/** Handle variable name changes */
		$instance = &$this->structure['instance'];
		if (in_array($ev->scope, [VarScope::Public, VarScope::Shared])) {
			$list = [];
			if (isset($instance['BP/Var/Get']))
				$list = array_merge($list, $instance['BP/Var/Get']);
			if (isset($instance['BP/Var/Set']))
				$list = array_merge($list, $instance['BP/Var/Set']);

			foreach ($list as &$item) {
				if ($item['data']['scope'] === $ev->scope && $item['data']['name'] === $ev->old) {
					$item['data']['name'] = $ev->now;
				}
			}
		}
	}

	public function _onFunctionRenamed($ev){
		/** Handle function name changes */
		$instance = &$this->structure['instance'];
		if (!isset($instance['BPI/F/'.$ev->old]))
			return;

		$instance['BPI/F/'.$ev->now] = $instance['BPI/F/'.$ev->old];
		unset($instance['BPI/F/'.$ev->old]);
	}

	public function _onFunctionPortRenamed($ev){
		/** Handle function port name changes */
		$instance = &$this->structure['instance'];
		$funcs = $instance['BPI/F/'.$ev->reference->id] ?? null;
		if ($funcs === null)
			return;

		foreach ($funcs as &$item) {
			if ($ev->which === 'output') {
				if (isset($item['output_sw']) && isset($item['output_sw'][$ev->old])) {
					$item['output_sw'][$ev->now] = $item['output_sw'][$ev->old];
					unset($item['output_sw'][$ev->old]);
				}
			} elseif ($ev->which === 'input') {
				if (isset($item['input_d']) && isset($item['input_d'][$ev->old])) {
					$item['input_d'][$ev->now] = $item['input_d'][$ev->old];
					unset($item['input_d'][$ev->old]);
				}
			}
		}
	}

	public function _onEventRenamed($ev){
		/** Handle event name changes */
		$instance = &$this->structure['instance'];
		$list = [];
		if (isset($instance['BP/Event/Listen']))
			$list = array_merge($list, $instance['BP/Event/Listen']);
		if (isset($instance['BP/Event/Emit']))
			$list = array_merge($list, $instance['BP/Event/Emit']);

		foreach ($list as &$item) {
			if ($item['data']['namespace'] === $ev->old) {
				$item['data']['namespace'] = $ev->now;
			}
		}
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
							$targetInput = &$inputIface->addPort($targetOutput, $output->name);
						}
						else throw new \Exception("Output port was not found");
					}

					if($targetOutput === null){
						if($outputIface->_enum === Enums::BPFnInput){
							$targetOutput = &$outputIface->addPort($targetInput, $input->name);
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

	public function renameVariable($from_, $to, $scopeId){
		if ($scopeId === null)
			throw new \Exception("Third parameter couldn't be null");
		if (str_contains($to, '/'))
			throw new \Exception("Slash symbol is reserved character and currently can't be used for creating path");

		$to = preg_replace('/^\/|\/$/m', '', $to);
		$to = preg_replace('/[`~!@#$%^&*()\-_+={}\[\]:"|;\'\\\\,.<>?]+/', '_', $to);

		if ($scopeId === VarScope::Private) {
			$privateVars = &$this->privateVars;
			$i = array_search($from_, $privateVars);
			if ($i === false)
				throw new \Exception("Private variable with name '$from_' was not found on '{$this->id}' function");
			$privateVars[$i] = $to;
		} elseif ($scopeId === VarScope::Shared) {
			$varObj = $this->variables[$from_] ?? null;
			if ($varObj === null)
				throw new \Exception("Shared variable with name '$from_' was not found on '{$this->id}' function");

			$varObj->id = $varObj->title = $to;
			$this->variables[$to] = $varObj;
			if (isset($this->variables[$from_]))
				unset($this->variables[$from_]);

			$this->rootInstance->emit('variable.renamed', new \Blackprint\EvVariableRenamed($scopeId, $from_, $to, $this, $varObj));
		} else {
			throw new \Exception("Can't rename variable from scopeId: $scopeId");
		}

		// Update references in all function instances
		$lastInstance = null;
		if ($scopeId === VarScope::Shared) {
			$used = $this->variables[$to]->used;
			foreach ($used as &$iface) {
				$iface->title = $iface->data['name'] = $to;
				$lastInstance = $iface->node->instance;
			}
		} else {
			foreach ($this->used as &$iface) {
				$lastInstance = $iface->bpInstance;
				$lastInstance->renameVariable($from_, $to, $scopeId);
			}
		}
	}

	public function deleteVariable($namespace, $scopeId){
		if ($scopeId === VarScope::Public)
			return $this->rootInstance->deleteVariable($namespace, $scopeId);

		$used = &$this->used;
		$path = explode('/', $namespace);

		if ($scopeId === VarScope::Private) {
			$privateVars = &$this->privateVars;
			$i = array_search($namespace, $privateVars);
			if ($i === false)
				return;
			array_splice($privateVars, $i, 1);

			$used[0]->bpInstance->deleteVariable($namespace, $scopeId);

			// Delete from all function node instances
			for ($i = 1; $i < count($used); $i++) {
				$instance = &$used[$i];
				$varsObject = &$instance->variables;
				$oldObj = \Blackprint\Utils::getDeepProperty($varsObject, $path);
				if ($oldObj === null)
					continue;
				if ($scopeId === VarScope::Private)
					$oldObj->destroy();
				\Blackprint\Utils::deleteDeepProperty($varsObject, $path, true);
				$eventData = new \Blackprint\EvVariableDeleted($oldObj->_scope, $oldObj->id, $this);
				$instance->emit('variable.deleted', $eventData);
			}
		} elseif ($scopeId === VarScope::Shared) {
			$oldObj = \Blackprint\Utils::getDeepProperty($this->variables, $path);
			$used[0]->bpInstance->deleteVariable($namespace, $scopeId);

			// Delete from all function node instances
			$eventData = new \Blackprint\EvVariableDeleted($oldObj->_scope, $oldObj->id, $this);
			for ($i = 1; $i < count($used); $i++) {  // Skip the first element and iterate directly over the rest
				$used[$i]->bpInstance->emit('variable.deleted', $eventData);
			}
		}
	}

	public function createNode(&$instance, &$options){
		return $instance->createNode($this->node, $options);
	}

	public function &createVariable($id, $options){
		if(str_contains($id, '/'))
			throw new \Exception("Slash symbol is reserved character and currently can't be used for creating path");

		if($options['scope'] === VarScope::Private){
			if(!in_array($id, $this->privateVars, true)){
				$this->privateVars[] = $id;
				$_null = null;
				$eventData = new \Blackprint\EvVariableNew(VarScope::Private, $id, $this, $_null);
				$this->emit('variable.new', $eventData);
				$this->rootInstance->emit('variable.new', $eventData);
			}

			// Add private variable to all function instances
			foreach ($this->used as &$iface) {
				$vars = &$iface->bpInstance->variables;
				$vars[$id] = new BPVariable($id);
			}
			$null = null;
			return $null;
		}
		elseif($options['scope'] === VarScope::Public){
			throw new \Exception("Can't create public variable from a function");
		}

		// Shared variable
		if(isset($this->variables[$id]))
			throw new \Exception("Variable id already exist: $id");

		$temp = new BPVariable($id, $options);
		$temp->bpFunction = $this;
		$temp->_scope = $options['scope'];
		$this->variables[$id] = $temp;

		$eventData = new \Blackprint\EvVariableNew($temp->_scope, $temp->id, $this, $temp);
		$this->emit('variable.new', $eventData);
		$this->rootInstance->emit('variable.new', $eventData);
		return $temp;
	}

	public function addPrivateVars($id){
		if(str_contains($id, '/'))
			throw new \Exception("Slash symbol is reserved character and currently can't be used for creating path");

		if(!in_array($id, $this->privateVars, true)){
			$this->privateVars[] = &$id;

			$temp = new \Blackprint\EvVariableNew(VarScope::Private, $id, $this, $null=null);
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

		$this->rootInstance->emit('function.port.renamed', new \Blackprint\EvFunctionPortRenamed($fromName, $toName, $this, $which));
	}

	public function deletePort($which, $portName){
		$used = &$this->used;
		if (count($used) == 0) {
			throw new \Exception("One function node need to be placed to the instance before deleting port");
		}

		$main = &$this->{$which};
		unset($main[$portName]);

		$hasDeletion = false;
		foreach ($used as &$iface) {
			if ($which == 'output') {
				$list_ = &$iface->_proxyOutput;
				foreach ($list_ as &$item) {
					$item->iface->deletePort($portName);
				}
				$hasDeletion = true;
			} elseif ($which == 'input') {
				$iface->_proxyInput->iface->deletePort($portName);
				$hasDeletion = true;
			}
		}

		if ($hasDeletion) {
			$used[0]->_save(false, false, true);
			$this->rootInstance->emit('function.port.deleted', new \Blackprint\EvFunctionPortDeleted($which, $portName, $this));
		}
	}

	public function invoke($input){
		$iface = $this->directInvokeFn;
		if ($iface === null) {
			$iface = $this->directInvokeFn = $this->createNode($this->rootInstance);
			$iface->bpInstance->executionOrder->stop = true;  // Disable execution order and force to use route cable
			$iface->bpInstance->pendingRender = true;
			$iface->isDirectInvoke = true;  // Mark this node as direct invoke, for some optimization

			// For sketch instance, we will remove it from sketch visibility
			$sketchScope = $iface->node->instance->scope;
			if ($sketchScope !== null) {
				$list_ = $sketchScope('nodes')->list;
				if (in_array($iface, $list_)) {
					$key = array_search($iface, $list_);
					array_splice($list_, $key, 1);
				}
			}

			// Wait until ready - using event listener instead of Promise
			$ready_event = new \Blackprint\Utils\Event();

			$on_ready = function() use(&$ready_event, &$iface) {
				$iface->off('ready', $on_ready);
				$ready_event->set();
			};

			$iface->once('ready', $on_ready);
			$ready_event->wait();
		}

		$proxyInput = $iface->_proxyInput;
		if ($proxyInput->routes->out === null) {
			throw new \Exception("{$this->id}: Blackprint function node must have route port that connected from input node to the output node");
		}

		$inputPorts = $proxyInput->iface->output;
		foreach ($inputPorts as $key => &$port) {
			$val = $input[$key];

			if ($port->value === $val)
				continue;  // Skip if value is the same

			// Set the value if different, and reset cache and emit value event after this line
			$port->value = $val;

			// Check all connected cables, if any node need to synchronize
			$cables = $port->cables;
			foreach ($cables as &$cable) {
				if ($cable->hasBranch)
					continue;
				$inp = $cable->input;
				if ($inp === null)
					continue;

				$inp->_cache = null;
				$inp->emit('value', new \Blackprint\EvPortValue($inp, $iface, $cable));
			}
		}

		$proxyInput->routes->routeOut();

		$ret = [];
		$outputs = $iface->node->output;
		foreach ($outputs as $key => &$value) {
			$ret[$key] = $value;
		}

		return $ret;
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
		$iface->parentInterface = &$funcMain;
		$funcMain->_proxyInput = &$this;
	}
	public function imported($data){
		$input = &$this->iface->parentInterface->node->bpFunction->input;

		foreach ($input as $key => &$value)
			$this->createPort('output', $key, $value);
	}
	public function request($cable){
		$name = &$cable->output->name;

		// This will trigger the port to request from outside and assign to this node's port
		$this->output->setByRef($name, $this->iface->parentInterface->node->input[$name]);
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
		$iface->parentInterface = &$funcMain;
		$funcMain->_proxyOutput ??= [];
		$funcMain->_proxyOutput[] = &$this;
	}

	public function imported($data){
		$output = &$this->iface->parentInterface->node->bpFunction->output;

		foreach ($output as $key => &$value)
			$this->createPort('input', $key, $value);
	}

	public function update($cable){
		$iface = &$this->iface->parentInterface;
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

		if($bpFunction->structure['_bpStale'] ?? false) {
			print_r($node->iface->namespace + ": Function structure was stale, this maybe get modified or not re-synced with remote sketch on runtime");
			throw new \Exception("Unable to create stale function structure");
		}

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

		$iface = $this;
		$this->_save = function(&$ev, &$eventName=false, $force=false) use(&$bpFunction, &$newInstance, &$iface) {
			$eventName = $newInstance->_currentEventName;

			// $this->bpInstance._emit('_fn.structure.update', { iface: this });
			if($force || $bpFunction->_syncing) return;
			if($iface->_bpDestroy) return;

			# This will be synced by remote sketch as this engine dont have exportJSON
			$bpFunction->structure['_bpStale'] = true;
			# bpFunction.structure = this.bpInstance.exportJSON({
			# 	toRawObject: true,
			# 	exportFunctions: false,
			# 	exportVariables: false,
			# 	exportEvents: false,
			# });

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
	public function &addPort(\Blackprint\Constructor\Port $port, $customName){
		if($port === null) return;

		if(str_starts_with($port->iface->namespace, "BP/Fn"))
			throw new \Exception("Function Input can't be connected directly to Output");

		$portType = null;
		$inputPortType = null;

		$name = $port->_name?->name ?? $customName ?? $port->name;
		$nodeA = null; $nodeB = null; // Main (input) -> Input (output), Output (input) -> Main (output)
		if($this->type === 'bp-fn-input'){ // Main (input) -> Input (output)
			$inc = 1;
			while(isset($this->output[$name])){
				if(isset($this->output[$name + $inc])) $inc++;
				else {
					$name .= $inc;
					break;
				}
			}

			$nodeA = &$this->parentInterface->node;
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
			$nodeB = &$this->parentInterface->node;
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
		$bpFunction = &$this->parentInterface->node->bpFunction;

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
		$funcMainNode = &$this->parentInterface->node;
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