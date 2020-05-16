<?php
namespace Blackprint;
require_once __DIR__."/Types.php";
require_once __DIR__."/PortValidator.php";
require_once __DIR__."/PortListener.php";
require_once __DIR__."/PortDefault.php";

class Interpreter{
	public $nodes = [];
	public $handler = [];
	public $settings = [];
	public $interface = [];

	public function __construct(){
		$this->interface['default'] = &Utils::$NoOperation;
	}

	public function registerInterface($nodeType, $options=null, $func=null){
		if($func === null)
			$func = &$options;

		$this->interface[$nodeType] = $func;
	}

	public function registerNode($namespace, $func){
		Utils::deepProperty($this->handler, explode('/', $namespace), $func);
	}

	public function importJSON($json){
		if(is_string($json))
			$json = json_decode($json, true);

		$version = &$json['version'];
		unset($json['version']);

		$inserted = &$this->nodes;
		$handlers = [];

		// Prepare all nodes depend on the namespace
		// before we create cables for them
		foreach($json as $namespace => &$nodes){
			// Every nodes that using this namespace name
			foreach ($nodes as &$node) {
				$nodeOpt = [];
				if(isset($node['options']))
					$nodeOpt['options'] = &$node['options'];

				$inserted[$node['id']] = $this->createNode($namespace, $nodeOpt, $handlers);
			}
		}

		// Create cable only from outputs and properties
		// > Important to be separated from above, so the cable can reference to loaded nodes
		foreach($json as $namespace => &$nodes){
			// Every nodes that using this namespace name
			foreach ($nodes as &$node) {
				$current = &$inserted[$node['id']];

				// If have outputs connection
				if(isset($node['outputs'])){
					$out = &$node['outputs'];

					// Every outputs port that have connection
					foreach($out as $portName => &$ports){
						$linkPortA = &$current->outputs[$portName];
						if($linkPortA === null)
							throw new \Exception("Node port not found for node id $node[id], with name: $portName");

						// Current outputs's available targets
						foreach ($ports as &$target) {
							$targetNode = &$inserted[$target['id']];

							// Outputs can only meet input port
							$linkPortB = &$targetNode->inputs[$target['name']];
							if($linkPortB === null)
								throw new \Exception("Node port not found for $targetNode with name: $target[name]");

							echo "\n{$current->title}.{$linkPortA->name} => {$linkPortB->name}.{$targetNode->title}";

							$cable = new Constructor\Cable($linkPortA, $linkPortB);
							$linkPortA->cables[] = $linkPortB->cables[] = $cable;
						}
					}
				}
			}
		}

		// Call handler init after creation processes was finished
		foreach ($handlers as &$val) {
			if(isset($val->init))
				($val->init)();
		}
	}

	public function settings($which, $val){
		$this->settings[$which] = &$val;
	}

	public function &getNodes($namespace){
		$nodes = &$this->nodes;
		$got = [];

		foreach ($nodes as &$val) {
			if($val->namespace === $namespace)
				$got[] = $val;
		}

		return $got;
	}

	public function &createNode($namespace, $options=null, $handlers=null){
		$func = Utils::deepProperty($this->handler, explode('/', $namespace));
		if($func === null)
			throw new \Exception("Node handler for $namespace was not found, maybe .registerNode() haven't being called?");

		// Processing scope is different with node scope
		$handle = new Constructor\Handle;
		$node = new Constructor\Node($handle, $namespace);

		// Call the registered func (from this.registerNode)
		$func($handle, $node);

		if(isset($this->interface[$node->type]) === false)
			throw new \Exception("Node type for '{$node->type}' was not found, maybe .registerInterface() haven't being called?");

		// Initialize for interface
		$node->interface($this->interface[$node->type]);

		// Assign the saved options if exist
		// Must be called here to avoid port trigger
		if(isset($node->options) && isset($options['options']))
			deepMerge($node->options, $options['options']);

		// Create the linker between the handler and the node
		$node->prepare();

		$this->nodes[] = &$node;
		$node->importing = false;

		if($handlers !== null)
			$handlers[] = $handle;
		elseif($handle->init)
			($handle->init)();

		return $node;
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