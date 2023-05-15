<?php
namespace Blackprint;

use Blackprint\Nodes\VarScope;

require_once __DIR__."/Internal.php";
require_once __DIR__."/Types.php";
require_once __DIR__."/Port/_PortTypes.php";
require_once __DIR__."/PortGhost.php";
require_once __DIR__."/Nodes/BPVariable.php";
require_once __DIR__."/Nodes/BPEvent.php";
require_once __DIR__."/Nodes/Environments.php";

class Engine extends Constructor\CustomEvent {
	/** @var array<\Blackprint\Interfaces|\Blackprint\Nodes\FnMain|\Blackprint\Nodes\BPVarGetSet|\Blackprint\Nodes\BPFnInOut> */
	public $ifaceList = [];
	protected $settings = [];
	public $disablePorts = false;
	public $throwOnError = true;

	// Private or function node's instance only
	public $sharedVariables;

	public $variables = []; // { category => BPVariable{ name, value, type }, category => { category } }
	public $functions = []; // { category => BPFunction{ name, variables, input, output, used: [], node, description }, category => { category } }

	public $iface = []; // { id => IFace }
	public $ref = []; // { id => Port references }

	/** @var Constructor\OrderedExecution */
	public $executionOrder;
	public $events;

	/** @var Nodes/FnMain */
	public $_funcMain = null;
	public $_mainInstance = null;
	public $_importing = false;
	public $_remote = false;
	public $_locked_ = false;
	public $_eventsInsNew = false;
	public $_destroyed_ = false;

	public function __construct(){
		$this->executionOrder = new Constructor\OrderedExecution($this);
		$this->events = new Constructor\InstanceEvents($this);
	}

	public function deleteNode($iface){
		$list = &$this->ifaceList;
		$i = array_search($iface, $list);

		if($i !== -1)
			array_splice($list, $i, 1);
		else{
			// if($this->throwOnError)
				throw new \Exception("Node to be deleted was not found");

			// $temp = [
			// 	"type" => 'node_delete_not_found',
			// 	"data" => new EvIface($iface)
			// ];
			// return $this->_emit('error', $temp);
		}

		// $iface->_bpDestroy = true;

		$eventData = new EvIface($iface);
		$this->_emit('node.delete', $eventData);

		$iface->node->destroy();
		$iface->destroy();

		$check = \Blackprint\Temp::$list;
		foreach ($check as &$val) {
			$portList = &$iface[$val];
			foreach ($portList as &$port) {
				$portList[$port]->disconnectAll($this->_remote != null);
			}
		}

		$routes = &$iface->node->routes;
		if(count($routes->in) !== 0){
			$inp = &$routes->in;
			foreach ($inp as &$cable) {
				$cable->disconnect();
			}
		}

		if($routes->out !== null) $routes->out->disconnect();

		// Delete reference
		unset($this->iface[$iface->id]);
		unset($this->ref[$iface->id]);

		$parent = &$iface->node->_funcInstance;
		if($parent != null)
			unset($parent->rootInstance->ref[$iface->id]);

		$this->_emit('node.deleted', $eventData);
	}

	public function clearNodes(){
		$list = &$this->ifaceList;
		foreach ($list as &$iface) {
			if($iface == null) continue;

			$iface->node->destroy();
			$iface->destroy();
		}

		$this->ifaceList = [];
		$this->iface = [];
		$this->ref = [];
	}

	public function &importJSON($json, $options=[]){
		if(is_string($json))
			$json = json_decode($json, true);

		// Throw if no instance data in the JSON
		if(!isset($json['instance']))
			throw new \Exception("Instance was not found in the JSON data");

		if(!isset($options['appendMode'])) $options['appendMode'] = false;
		if(!isset($options['clean'])) $options['clean'] = true;
		if(!isset($options['noEnv'])) $options['noEnv'] = false;

		$appendMode = isset($options['appendMode']) && $options['appendMode'];
		if(!$appendMode) $this->clearNodes();
		$reorderInputPort = [];

		$this->_importing = true;

		if($options['clean'] !== false && !$options['appendMode']){
			$this->clearNodes();
			$this->functions = [];
			$this->variables = [];
			$this->events->list = [];
		}
		elseif(!$options['appendMode']) $this->clearNodes();

		// Do we need this?
		// $this->emit("json.importing", {appendMode: options.appendMode, raw: json});
		
		if(isset($json['environments']) && !$options['noEnv'])
			\Blackprint\Environment::import($json['environments']);

		if(isset($json['functions'])){
			$functions = &$json['functions'];
	
			foreach ($functions as $key => &$value)
				$this->createFunction($key, $value);
		}
	
		if(isset($json['variables'])){
			$variables = &$json['variables'];
	
			foreach ($variables as $key => &$value)
				$this->createVariable($key, $value);
		}

		if(isset($json['events'])){
			$events = &$json['events'];

			foreach ($events as $path => &$value)
				$this->events->createEvent($path, $value);
		}

		$inserted = &$this->ifaceList;
		$nodes = [];
		$appendLength = $appendMode ? count($inserted) : 0;
		$instance = &$json['instance'];

		// Prepare all ifaces based on the namespace
		// before we create cables for them
		foreach($instance as $namespace => &$ifaces){
			// Every ifaces that using this namespace name
			foreach ($ifaces as &$conf) {
				$conf['i'] += $appendLength;
				$confOpt = [
					'id' => isset($conf['id']) ? $conf['id'] : null,
					'i' => $conf['i']
				];

				if(isset($conf['data']))
					$confOpt['data'] = &$conf['data'];
				if(isset($conf['input_d']))
					$confOpt['input_d'] = &$conf['input_d'];
				if(isset($conf['output_sw']))
					$confOpt['output_sw'] = &$conf['output_sw'];

				/** @var Interfaces | Nodes\FnMain */
				$iface = $this->createNode($namespace, $confOpt, $nodes);
				$inserted[$conf['i']] = $iface; // Don't add & as it's already reference

				if(isset($conf['input'])){
					$reorderInputPort[] = [
						"iface"=> $iface,
						"config"=> $conf,
					];
				}

				// For custom function node
				if(method_exists($iface, '_BpFnInit'))
					$iface->_BpFnInit();
			}
		}

		// Create cable only from output and property
		// > Important to be separated from above, so the cable can reference to loaded ifaces
		foreach($instance as $namespace => &$ifaces){
			// Every ifaces that using this namespace name
			foreach ($ifaces as &$ifaceJSON) {
				/** @var \Blackprint\Interfaces|\Blackprint\Nodes\FnMain|\Blackprint\Nodes\BPVarGetSet|\Blackprint\Nodes\BPFnInOut */
				$iface = &$inserted[$ifaceJSON['i']];

				if(isset($ifaceJSON['route']))
					$iface->node->routes->routeTo($inserted[$ifaceJSON['route']['i'] + $appendLength]);

				// If have output connection
				if(isset($ifaceJSON['output'])){
					$out = &$ifaceJSON['output'];

					// Every output port that have connection
					foreach($out as $portName => &$ports){
						/** @var Constructor\Port */
						$linkPortA = &$iface->output[$portName];

						if($linkPortA === null){
							if($iface->_enum === Nodes\Enums::BPFnInput){
								$target = $this->_getTargetPortType($iface->node->instance, 'input', $ports);

								/** @var Constructor\Port */
								$linkPortA = $iface->addPort($target, $portName);

								if($linkPortA === null)
									throw new \Exception("Can't create output port ({$portName}) for function ({$iface->_funcMain->node->_funcInstance->id})");
							}
							elseif($iface->_enum === Nodes\Enums::BPVarGet){
								$target = $this->_getTargetPortType($this, 'input', $ports);
								$iface->useType($target);

								/** @var Constructor\Port */
								$linkPortA = $iface->output[$portName];
							}
							else throw new \Exception("Node port not found for iface (index: $ifaceJSON[i], title: $iface->title), with port name: $portName");
						}

						// Current output's available targets
						foreach ($ports as &$target) {
							$target['i'] += $appendLength;

							/** @var \Blackprint\Interfaces|\Blackprint\Nodes\BPFnInOut|\Blackprint\Nodes\BPVarGetSet */
							$targetNode = &$inserted[$target['i']]; // iface

							if($linkPortA->isRoute){
								$nul = null;
								$cable = new Constructor\Cable($linkPortA, $nul);
								$cable->isRoute = true;
								$cable->output = $linkPortA;
								$linkPortA->cables[] = $cable;

								$targetNode->node->routes->connectCable($cable);
								continue;
							}

							// output can only meet input port
							$linkPortB = &$targetNode->input[$target['name']];
							if($linkPortB === null){
								if($targetNode->_enum === Nodes\Enums::BPFnOutput){
									$linkPortB = &$targetNode->addPort($linkPortA, $target['name']);

									if($linkPortB === null)
										throw new \Exception("Can't create output port ({$target['name']}) for function ({$targetNode->_funcMain->node->_funcInstance->id})");
								}
								elseif($targetNode->_enum === Nodes\Enums::BPVarSet){
									$targetNode->useType($linkPortA);
									$linkPortB = &$targetNode->input[$target['name']];
								}
								elseif($linkPortA->type === \Blackprint\Types::Route){
									$linkPortB = &$targetNode->node->routes;
								}
								else throw new \Exception("Node port not found for $targetNode->title with name: $target[name]");
							}

							// echo "\n{$current->title}.{$linkPortA->name} => {$targetNode->title}.{$linkPortB->name}";

							$linkPortA->connectPort($linkPortB);
							// $cable->_print();
						}
					}
				}
			}
		}

		// Fix input port cable order
		foreach ($reorderInputPort as &$value) {
			$iface = &$value['iface'];
			$cInput = &$value['config']['input'];

			foreach ($cInput as $key => &$conf) {
				$port = &$iface->input[$key];
				$cables = &$port->cables;
				$temp = [];

				for ($a=0, $n=count($conf); $a < $n; $a++) {
					$ref = &$conf[$a];
					$name = $ref['name'];
					$targetIface = $inserted[$ref['i'] + $appendLength];

					foreach ($cables as &$cable) {
						if($cable->output->name !== $name || $cable->output->iface !== $targetIface) continue;

						$temp[$a] = &$cable;
						break;
					}
				}

				foreach ($temp as &$ref) {
					if($ref == null) echo("Some cable failed to be ordered for ({$iface->title}: {$key})");
				}

				$port->cables = $temp;
			}
		}

		// Call nodes init after creation processes was finished
		foreach ($nodes as &$val){
			$val->init();
		}

		$this->_importing = false;
		// $this->emit("json.imported", {appendMode: options.appendMode, nodes: inserted, raw: json});
		$this->executionOrder->next();

		return $inserted;
	}

	public function settings($which, $val){
		if($val === null)
			return $this->settings[$which];

		$which = str_replace('.', '_', $which);
		$this->settings[$which] = &$val;
	}

	public function &_getTargetPortType(&$instance, $whichPort, &$targetNodes){
		$target = &$targetNodes[0]; // ToDo: check all target in case if it's supporting Union type
		$targetIface = &$instance->ifaceList[$target['i']];
		return $targetIface->{$whichPort}[$target['name']];
	}

	public function &getNodes($namespace){
		$ifaces = &$this->ifaceList;
		$got = [];

		foreach ($ifaces as &$val) {
			if($val->namespace === $namespace)
				$got[] = &$val->node;
		}

		return $got;
	}

	public function &createNode($namespace, $options=null, &$nodes=null){
		$func = Utils::getDeepProperty(Internal::$nodes, explode('/', $namespace));

		// Try to load from registered namespace folder if exist
		$funcNode = null;
		if($func === null){
			if(str_starts_with($namespace, "BPI/F/")){
				$func = Utils::getDeepProperty($this->functions, explode('/', substr($namespace, 6)));

				if($func !== null)
					$funcNode = ($func->node)($this);
			}
			else {
				Internal::_loadNamespace($namespace);
				$func = Utils::getDeepProperty(Internal::$nodes, explode('/', $namespace));
			}

			if($func === null)
				throw new \Exception("Node nodes for $namespace was not found, maybe .registerNode() haven't being called?");
		}

		/** @var Node */
		$node = $funcNode ?? new $func($this);
		$iface = &$node->iface;

		// Disable data flow on any node ports
		if($this->disablePorts) $node->disablePorts = true;

		if($iface === null)
			throw new \Exception("Node interface was not found, do you forget to call \$node->setInterface() ?");

		// Create the linker between the nodes and the iface
		if($funcNode === null)
			$iface->_prepare_($func);

		$iface->namespace = &$namespace;
		if(isset($options['id'])){
			$iface->id = &$options['id'];
			$this->iface[$iface->id] = &$iface;
			$this->ref[$iface->id] = &$iface->ref;

			$parent = &$iface->node->_funcInstance;
			if($parent != null)
				$parent->rootInstance->ref[$iface->id] = &$iface->ref;
		}

		$savedData = &$options['data'];
		$portSwitches = &$options['output_sw'];

		if(isset($options['i'])){
			$iface->i = &$options['i'];
			$this->ifaceList[$iface->i] = &$iface;
		}
		else $this->ifaceList[] = &$iface;

		$node->initPorts($savedData);

		$defaultInputData = &$options['input_d'];
		if($defaultInputData != null)
			$iface->_importInputs($defaultInputData);

		if($portSwitches != null){
			foreach($portSwitches as $key => &$val) {
				$ref = &$iface->output[$key];

				if(($val | 1) === 1)
					Port::StructOf_split($ref);

				if(($val | 2) === 2)
					$ref->allowResync = true;
			}
		}

		$iface->importing = false;

		$iface->imported($savedData);
		$node->imported($savedData);

		if($nodes !== null)
			$nodes[] = &$node;
		else {
			$node->init();
			$iface->init();
		}

		return $iface;
	}

	public function &createVariable($id, $options){
		if($this->_locked_) throw new \Exception("This instance was locked");
		if(preg_match('/\s/', $id) !== 0)
			throw new \Exception("Id can't have space character: '$id'");

		$ids = explode('/', $id);
		$lastId = $ids[count($ids) - 1];
		$parentObj = Utils::getDeepProperty($this->variables, $ids, 1);

		if($parentObj !== null && isset($parentObj[$lastId])){
			$this->variables[$id]->destroy();
			unset($this->variables[$id]);
		}

		// BPVariable = ./nodes/Var.js
		$temp = new Nodes\BPVariable($id, $options);
		Utils::setDeepProperty($this->variables, $ids, $temp);

		$temp->_scope = VarScope::public;
		$this->_emit('variable.new', $temp);

		return $temp;
	}

	public function &createFunction($id, $options){
		if($this->_locked_) throw new \Exception("This instance was locked");
		if(preg_match('/\s/', $id) !== 0)
			throw new \Exception("Id can't have space character: '$id'");

		if(isset($this->functions[$id])){
			$this->functions[$id]->destroy();
			unset($this->functions[$id]);
		}

		$ids = explode('/', $id);
		$lastId = $ids[count($ids) - 1];
		$parentObj = Utils::getDeepProperty($this->functions, $ids, 1);

		if($parentObj != null && isset($parentObj[$lastId])){
			$parentObj[$lastId]->destroy();
			unset($parentObj[$lastId]);
		}

		// BPFunction = ./nodes/Fn.js
		$temp = new Nodes\BPFunction($id, $options, $this);
		Utils::setDeepProperty($this->functions, $ids, $temp);

		if(isset($options['vars'])){
			$vars = &$options['vars'];
			foreach ($vars as &$val) {
				$temp->createVariable($val, ["scope" => Nodes\VarScope::shared]);
			}
		}

		if(isset($options['privateVars'])){
			$privateVars = &$options['privateVars'];
			foreach ($privateVars as &$val) {
				$temp->addPrivateVars($val);
			}
		}

		$this->_emit('function.new', $temp);
		return $temp;
	}

	public function _log($data){
		$data->instance = $this;

		if($this->_mainInstance != null)
			$this->_mainInstance->_emit('log', $data);
		else $this->_emit('log', $data);
	}

	public function destroy(){
		$this->_locked_ = false;
		$this->_destroyed_ = true;
		$this->clearNodes();
		$this->emit('destroy');
	}

	public function _emit($evName, &$data=[]){
		$this->emit($evName, $data);
		if($this->_funcMain == null) return;

		$rootInstance = &$this->_funcMain->node->_funcInstance->rootInstance;
		if($rootInstance->_remote == null) return;

		$data['bpFunction'] = &$rootInstance->_funcInstance;
		$rootInstance->emit($evName, $data);
	}
}

function deepMerge(&$real, &$opt){
	foreach ($opt as $key => &$val) {
		if(is_array($val)){
			deepMerge($real[$key], $val);
			continue;
		}

		$real->{$key} = $val;
	}
}