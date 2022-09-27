<?php
namespace Blackprint;
require_once __DIR__."/Internal.php";
require_once __DIR__."/Types.php";
require_once __DIR__."/Port/_PortTypes.php";
require_once __DIR__."/PortGhost.php";

class Engine extends Constructor\CustomEvent {
	public $iface = [];
	/** @var array<\Blackprint\Interfaces|\Blackprint\Nodes\FnMain|\Blackprint\Nodes\BPVarGetSet|\Blackprint\Nodes\BPFnInOut> */
	public $ifaceList = [];
	protected $settings = [];
	public $disablePorts = false;
	public $throwOnError = true;

	public $variables = [];
	public $functions = [];
	public $ref = [];

	/** @var Nodes/FnMain */
	public $_funcMain = null;

	// public function __construct(){ }

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
			// return $this->emit('error', $temp);
		}

		// $iface->_bpDestroy = true;

		$eventData = new EvIface($iface);
		$this->emit('node.delete', $eventData);

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
		if($routes->in->length !== 0){
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

		$this->emit('node.deleted', $eventData);
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

		$appendMode = isset($options['appendMode']) && $options['appendMode'] === false;
		if(!$appendMode) $this->clearNodes();

		// Do we need this?
		// $this->emit("json.importing", {appendMode: options.appendMode, raw: json});

		$metadata = &$json['_'];
		unset($json['_']);
		
		if($metadata !== null){
			if(isset($metadata['env']))
				\Blackprint\Environment::import($metadata['env']);

			if(isset($metadata['functions'])){
				$functions = &$metadata['functions'];
	
				foreach ($functions as $key => &$value)
					$this->createFunction($key, $value);
			}
	
			if(isset($metadata['variables'])){
				$variables = &$metadata['variables'];
	
				foreach ($variables as $key => &$value)
					$this->createVariable($key, $value);
			}
		}

		$inserted = &$this->ifaceList;
		$nodes = [];
		$appendLength = $appendMode ? count($inserted) : 0;

		// Prepare all ifaces based on the namespace
		// before we create cables for them
		foreach($json as $namespace => &$ifaces){
			// Every ifaces that using this namespace name
			foreach ($ifaces as &$iface) {
				$iface['i'] += $appendLength;
				$ifaceOpt = [
					'id' => isset($iface['id']) ? $iface['id'] : null,
					'i' => $iface['i']
				];

				if(isset($iface['data']))
					$ifaceOpt['data'] = &$iface['data'];
				if(isset($iface['input_d']))
					$ifaceOpt['input_d'] = &$iface['input_d'];
				if(isset($iface['output_sw']))
					$ifaceOpt['output_sw'] = &$iface['output_sw'];

				/** @var Interfaces | Nodes\FnMain */
				$temp = $this->createNode($namespace, $ifaceOpt, $nodes);
				$inserted[$iface['i']] = $temp; // Don't add & as it's already reference

				// For custom function node
				if(method_exists($temp, '_BpFnInit'))
					$temp->_BpFnInit();
			}
		}

		// Create cable only from output and property
		// > Important to be separated from above, so the cable can reference to loaded ifaces
		foreach($json as $namespace => &$ifaces){
			// Every ifaces that using this namespace name
			foreach ($ifaces as &$ifaceJSON) {
				$iface = &$inserted[$ifaceJSON['i']];

				if(isset($ifaceJSON['route']))
					$iface->node->routes->routeTo($inserted[$ifaceJSON['route']['i']]);

				// If have output connection
				if(isset($ifaceJSON['output'])){
					$out = &$ifaceJSON['output'];

					// Every output port that have connection
					foreach($out as $portName => &$ports){
						$linkPortA = &$iface->output[$portName];

						if($linkPortA === null){
							if($iface->_enum === Nodes\Enums::BPFnInput){
								$target = $this->_getTargetPortType($iface->node->instance, 'input', $ports);
								$linkPortA = $iface->addPort($target, $portName);

								if($linkPortA === null)
									throw new \Exception("Can't create output port ({$portName}) for function ({$iface->_funcMain->node->_funcInstance->id})");
							}
							elseif($iface->_enum === Nodes\Enums::BPVarGet){
								$target = $this->_getTargetPortType($this, 'input', $ports);
								$iface->useType($target);
								$linkPortA = $iface->output[$portName];
							}
							else throw new \Exception("Node port not found for iface (index: $ifaceJSON[i], title: $iface->title), with port name: $portName");
						}

						// Current output's available targets
						foreach ($ports as &$target) {
							$target['i'] += $appendLength;
							$targetNode = &$inserted[$target['i']];

							// output can only meet input port
							$linkPortB = &$targetNode->input[$target['name']];
							if($linkPortB === null){
								if($targetNode->_enum === Nodes\Enums::BPFnOutput){
									$linkPortB = $targetNode->addPort($linkPortA, $target['name']);

									if($linkPortB === null)
										throw new \Exception("Can't create output port ({$target['name']}) for function ({$targetNode->_funcMain->node->_funcInstance->id})");
								}
								elseif($targetNode->_enum === Nodes\Enums::BPVarSet){
									$targetNode->useType($linkPortA);
									$linkPortB = $targetNode->input[$target['name']];
								}
								elseif($linkPortA->type === \Blackprint\PortType::Route){
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

		// Call nodes init after creation processes was finished
		foreach ($nodes as &$val){
			$val->init();
		}

		return $inserted;
	}

	public function settings($which, $val){
		if($val === null)
			return $this->settings[$which];

		$this->settings[$which] = &$val;
	}

	public function &_getTargetPortType(&$instance, $whichPort, &$targetNodes){
		$target = &$targetNodes[0]; // ToDo: check all target in case if it's supporting Union type
		$targetIface = &$instance->ifaceList[$target['i']];
		return $targetIface->{$whichPort}[$target['name']];
	}

	public function &getNode($id){
		$ifaces = &$this->ifaceList;

		foreach ($ifaces as &$val) {
			if($val->id === $id || $val->i === $id)
				return $val->node;
		}
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

	// ToDo: sync with JS, when creating function node this still broken
	public function &createNode($namespace, $options=null, &$nodes=null){
		$func = Utils::deepProperty(Internal::$nodes, explode('/', $namespace));

		// Try to load from registered namespace folder if exist
		$funcNode = null;
		if($func === null){
			if(str_starts_with($namespace, "BPI/F/")){
				$func = Utils::deepProperty($this->functions, explode('/', substr($namespace, 6)));

				if($func !== null)
					$funcNode = ($func->node)($this);
			}
			else {
				Internal::_loadNamespace($namespace);
				$func = Utils::deepProperty(Internal::$nodes, explode('/', $namespace));
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

		if(isset($options['i'])){
			$iface->i = &$options['i'];
			$this->ifaceList[$iface->i] = &$iface;
		}
		else $this->ifaceList[] = &$iface;

		$defaultInputData = &$options['input_d'];
		if($defaultInputData != null)
			$iface->_importInputs($defaultInputData);

		$savedData = &$options['data'];
		$portSwitches = &$options['output_sw'];

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

		$iface->init();

		if($nodes === null)
			$node->init();

		return $iface;
	}

	public function &createVariable($id, $options){
		if(isset($this->variables[$id])){
			$this->variables[$id]->destroy();
			unset($this->variables[$id]);
		}

		// deepProperty

		// BPVariable = ./nodes/Var.js
		$temp = new Nodes\BPVariable($id, $options);
		$this->variables[$id] = &$temp;
		$this->emit('variable.new', $temp);

		return $temp;
	}

	public function &createFunction($id, $options){
		if(isset($this->functions[$id])){
			$this->functions[$id]->destroy();
			unset($this->functions[$id]);
		}

		// BPFunction = ./nodes/Fn.js
		$temp = new Nodes\BPFunction($id, $options, $this);
		$this->functions[$id] = &$temp;

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

		$this->emit('function.new', $temp);
		return $temp;
	}

	public function _log($data){
		$data->instance = $this;

		if($this->_mainInstance != null)
			$this->_mainInstance->emit('log', $data);
		else $this->emit('log', $data);
	}

	public function destroy(){
		$this->clearNodes();
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