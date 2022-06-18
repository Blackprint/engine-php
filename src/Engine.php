<?php
namespace Blackprint;
require_once __DIR__."/Internal.php";
require_once __DIR__."/Types.php";
require_once __DIR__."/Port/_PortTypes.php";
require_once __DIR__."/PortGhost.php";

class Engine extends Constructor\CustomEvent {
	public $iface = [];
	public $ifaceList = [];
	protected $settings = [];
	public $disablePorts = false;
	public $throwOnError = true;

	public $variables = []; // { category => { name, value, type, childs:{ category } } }
	public $functions = []; // { category => { name, variables, input, output, used: [], node, description, childs:{ category } } }
	public $ref = []; // { id => Port references }

	// public function __construct(){ }

	public function deleteNode($iface){
		$list = $this->ifaceList;
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

		// $iface._bpDestroy = true;

		$eventData = new EvIface($iface);
		$this->emit('node.delete', $eventData);

		$iface->node->destroy();
		$iface->destroy();

		$check = \Blackprint\Temp::$list;
		foreach ($check as &$val) {
			$portList = &$iface[$val];
			foreach ($portList as &$port) {
				if(substr($port, 0, 1) === '_') continue;
				$portList[$port]->disconnectAll($this->_remote != null);
			}
		}

		// Delete reference
		unset($this->iface[$iface->id]);
		unset($this->ref[$iface->id]);

		$this->emit('node.deleted', $eventData);
	}

	public function clearNodes(){
		$list = $this->ifaceList;
		foreach ($list as &$iface) {
			$iface->node->destroy();
			$iface->destroy();
		}

		$this->ifaceList = [];
		$this->iface = [];
		$this->ref = [];
	}

	// ToDo: sync with js
	public function importJSON($json){
		if(is_string($json))
			$json = json_decode($json, true);

		$metaData = &$json['_'];
		unset($json['_']);

		if(isset($metaData['env']))
			\Blackprint\Environment::import($metaData['env']);

		$inserted = &$this->ifaceList;
		$nodes = [];

		// Prepare all ifaces based on the namespace
		// before we create cables for them
		foreach($json as $namespace => &$ifaces){
			// Every ifaces that using this namespace name
			foreach ($ifaces as &$iface) {
				$ifaceOpt = [
					'id' => isset($iface['id']) ? $iface['id'] : null,
					'i' => $iface['i']
				];

				if(isset($iface['data']))
					$ifaceOpt['data'] = &$iface['data'];

				$inserted[$iface['i']] = $this->createNode($namespace, $ifaceOpt, $nodes);
			}
		}

		// Create cable only from output and property
		// > Important to be separated from above, so the cable can reference to loaded ifaces
		foreach($json as $namespace => &$ifaces){
			// Every ifaces that using this namespace name
			foreach ($ifaces as &$iface) {
				$current = &$inserted[$iface['i']];

				// If have output connection
				if(isset($iface['output'])){
					$out = &$iface['output'];

					// Every output port that have connection
					foreach($out as $portName => &$ports){
						$linkPortA = &$current->output[$portName];
						if($linkPortA === null)
							throw new \Exception("Node port not found for iface (index: $iface[i]), with name: $portName");

						// Current output's available targets
						foreach ($ports as &$target) {
							$targetNode = &$inserted[$target['i']];

							// output can only meet input port
							$linkPortB = &$targetNode->input[$target['name']];
							if($linkPortB === null)
								throw new \Exception("Node port not found for $targetNode->title with name: $target[name]");

							// echo "\n{$current->title}.{$linkPortA->name} => {$targetNode->title}.{$linkPortB->name}";

							$linkPortA->connectPort($linkPortB);
							// $cable = new Constructor\Cable($linkPortA, $linkPortB);
							// $linkPortA->cables[] = &$cable;
							// $linkPortB->cables[] = &$cable;

							// $cable->_connected();
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
	}

	public function settings($which, $val){
		if($val === null)
			return $this->settings[$which];

		$this->settings[$which] = &$val;
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

	public function &createNode($namespace, $options=null, &$nodes=null){
		$func = Utils::deepProperty(Internal::$nodes, explode('/', $namespace));

		// Try to load from registered namespace folder if exist
		if($func === null){
			Internal::_loadNamespace($namespace);
			$func = Utils::deepProperty(Internal::$nodes, explode('/', $namespace));

			if($func === null)
				throw new \Exception("Node nodes for $namespace was not found, maybe .registerNode() haven't being called?");
		}

		/** @var Node */
		$node = new $func($this);
		$iface = &$node->iface;

		// Disable data flow on any node ports
		if($this->disablePorts) $node->disablePorts = true;

		if($iface === null)
			throw new \Exception("Node interface was not found, do you forget to call \$node->setInterface() ?");

		// Create the linker between the nodes and the iface
		$iface->_prepare_($func, $iface);

		$iface->namespace = &$namespace;
		if(isset($options['id'])){
			$iface->id = &$options['id'];
			$this->iface[$iface->id] = &$iface;
		}

		if(isset($options['i'])){
			$iface->i = &$options['i'];
			$this->ifaceList[$iface->i] = &$iface;
		}
		else $this->ifaceList[] = &$iface;

		$iface->importing = false;

		$savedData = $options['data'] ?? null;
		$iface->imported($savedData);
		$node->imported($savedData);

		if($nodes !== null)
			$nodes[] = &$node;

		$iface->init();
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

		if($options->vars != null){
			$vars = $options->vars;
			foreach ($vars as &$val) {
				$temp->createVariable($val, ["scope" => 'shared']);
			}
		}

		if($options->privateVars != null){
			$privateVars = $options->privateVars;
			foreach ($privateVars as &$val) {
				$temp->addPrivateVars($val);
			}
		}

		$this->emit('function.new', $temp);
		return $temp;
	}

	public function destroy(){
		$this->iface = [];
		$this->ifaceList = [];
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