<?php
namespace Blackprint;
require_once __DIR__."/Types.php";
require_once __DIR__."/PortDefault.php";
require_once __DIR__."/PortListener.php";
require_once __DIR__."/PortValidator.php";

class Engine{
	public $iface = [];
	public $ifaceList = [];
	public $nodes = [];
	public $settings = [];
	public $interface = [];

	public function __construct(){
		$this->interface['default'] = &Utils::$NoOperation;
	}

	public function registerInterface($ifaceType, $options=null, $func=null){
		if($func === null)
			$func = &$options;

		$this->interface[$ifaceType] = $func;
	}

	public function registerNode($namespace, $func){
		Utils::deepProperty($this->nodes, explode('/', $namespace), $func);
	}

	public function importJSON($json){
		if(is_string($json))
			$json = json_decode($json, true);

		$version = &$json['version'];
		unset($json['version']);

		$inserted = &$this->ifaceList;
		$nodes = [];

		// Prepare all ifaces depend on the namespace
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

		// Create cable only from outputs and properties
		// > Important to be separated from above, so the cable can reference to loaded ifaces
		foreach($json as $namespace => &$ifaces){
			// Every ifaces that using this namespace name
			foreach ($ifaces as &$iface) {
				$current = &$inserted[$iface['i']];

				// If have outputs connection
				if(isset($iface['outputs'])){
					$out = &$iface['outputs'];

					// Every outputs port that have connection
					foreach($out as $portName => &$ports){
						$linkPortA = &$current->outputs[$portName];
						if($linkPortA === null)
							throw new \Exception("Node port not found for iface (index: $iface[i]), with name: $portName");

						// Current outputs's available targets
						foreach ($ports as &$target) {
							$targetNode = &$inserted[$target['i']];

							// Outputs can only meet input port
							$linkPortB = &$targetNode->inputs[$target['name']];
							if($linkPortB === null)
								throw new \Exception("Node port not found for $targetNode with name: $target[name]");

							// echo "\n{$current->title}.{$linkPortA->name} => {$linkPortB->name}.{$targetNode->title}";

							$cable = new Constructor\Cable($linkPortA, $linkPortB);
							$linkPortA->cables[] = $linkPortB->cables[] = $cable;

							// $cable->_print();
						}
					}
				}
			}
		}

		// Call nodes init after creation processes was finished
		foreach ($nodes as &$val)
			$val->init !== false && ($val->init)();
	}

	public function settings($which, $val){
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
		$func = Utils::deepProperty($this->nodes, explode('/', $namespace));
		if($func === null)
			throw new \Exception("Node nodes for $namespace was not found, maybe .registerNode() haven't being called?");

		// Processing scope is different with iface scope
		$node = new Constructor\Node;
		$iface = new Constructor\NodeInterface($node, $namespace);

		// Call the registered func (from this.registerNode)
		$func($node, $iface);

		if(isset($this->interface[$iface->interface]) === false)
			throw new \Exception("Node interface for '{$iface->interface}' was not found, maybe .registerInterface() haven't being called?");

		// Initialize for interface
		$iface->interfacing($this->interface[$iface->interface]);

		// Assign the saved options if exist
		// Must be called here to avoid port trigger
		if(isset($iface->data) && isset($options['data']))
			deepMerge($iface->data, $options['data']);

		// Create the linker between the nodes and the iface
		$iface->prepare();

		if(isset($iface->id))
			$this->iface[$iface->id] = &$iface;

		if(isset($iface->i))
			$this->ifaceList[$iface->i] = &$iface;
		else $this->ifaceList[] = &$iface;

		$iface->importing = false;

		isset($node->imported) && ($node->imported)();

		if($nodes !== null)
			$nodes[] = $node;
		elseif($node->init)
			($node->init)();

		return $iface;
	}
}

function deepMerge(&$real, &$opt){
	foreach ($opt as $key => &$val) {
		if(is_array($val)){
			deepMerge($real[$key], $val);
			continue;
		}

		$real[$key]($val);
	}
}