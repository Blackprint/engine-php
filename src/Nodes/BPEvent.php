<?php
namespace Blackprint\Nodes;
use \Blackprint\Types;
use \Blackprint\Port;
use \Blackprint\Environment;

class BPEventListen extends \Blackprint\Node {
	// Defined below this class
	public static $Input;
	public static $Output = [];

	/** @var IEventListen */
	public $iface;
	public $_limit = -1; // -1 = no limit
	public $_off = false;

	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Event/Listen');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["namespace" => ''];
		$iface->title = 'EventListen';
		// $iface->type = 'event';

		$iface->_enum = Enums::BPEventListen;
	}
	public function initPorts($data){ $this->iface->initPorts($data); }
	public function resetLimit(){
		$limit = &$this->input['Limit'];
		$this->_limit = $limit === 0 ? -1 : $limit;

		if($this->_off){
			$iface = &$this->iface;
			$this->instance->events->on($iface->data['namespace'], $iface->_listener);
		}
	}
	public function eventUpdate($obj){
		if($this->_off || $this->_limit === 0) return;
		if($this->_limit > 0) $this->_limit--;

		// Don't use object assign as we need to re-assign null/undefined field
		$output = &$this->iface->output;
		foreach ($output as $key => &$port) {
			$port->value = &$obj[$key];
			$port->sync();
		}

		$this->routes->routeOut();
	}
	public function offEvent(){
		if($this->_off === false){
			$iface = $this->iface;
			$this->instance->events->off($iface->data['namespace'], $iface->_listener);

			$this->_off = true;
		}
	}
	public function destroy(){
		$iface = $this->iface;
		$iface->_removeFromList();

		if($iface->_listener == null) return;
		$iface->_insEventsRef->off($iface->data['namespace'], $iface->_listener);
	}
}
BPEventListen::$Input = [
	"Limit" => Port::Default(Types::Number, 0),
	"Reset" => Port::Trigger(fn($port) => $port->iface->node->resetLimit()),
	"Off" => Port::Trigger(fn($port) => $port->iface->node->offEvent()),
];
\Blackprint\registerNode('BP/Event/Listen', BPEventListen::class);

class BPEventEmit extends \Blackprint\Node {
	// Defined below this class
	public static $Input;

	/** @var IEnvEmit */
	public $iface;

	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Event/Emit');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["namespace" => ''];
		$iface->title = 'EventEmit';
		// $iface->type = 'event';

		$iface->_enum = Enums::BPEventEmit;
	}
	public function initPorts($data){ $this->iface->initPorts($data); }
	public function trigger(){
		$data = []; // Copy data from input ports
		$IInput = &$this->iface->input;
		$Input = &$this->input;

		foreach ($IInput as $key => &$value) {
			if($key === 'Emit') continue;
			$data[$key] = $Input[$key]; // Obtain data by triggering the offsetGet (getter)
		}

		$this->instance->events->emit($this->iface->data['namespace'], $data);
	}

	public function destroy(){
		$this->iface->_removeFromList();
	}
}
BPEventEmit::$Input = [
	"Emit" => Port::Trigger(fn($port) => $port->iface->node->trigger()),
];
\Blackprint\registerNode('BP/Event/Emit', BPEventEmit::class);

class BPEventListenEmit extends \Blackprint\Interfaces {
	// public $_nameListener;
	public $_insEventsRef;
	public $_eventRef;
	public function __construct($node){
		parent::__construct($node);
		$this->_insEventsRef = &$this->node->instance->events;
	}
	public function initPorts($data){
		$namespace = $data['namespace'];
		if(!$namespace) throw new \Exception("Parameter 'namespace' is required");

		$this->data['namespace'] = $namespace;
		$this->title = explode('/', $namespace);
		if(count($this->title) >= 2) array_splice($this->title, -2);
		$this->title = implode(' ', $this->title);

		$this->_eventRef = $this->node->instance->events->list[$namespace];
		if($this->_eventRef == null) throw new \Exception("Events ($namespace) is not defined");

		$schema = &$this->_eventRef->schema;
		if($this->_enum === \Blackprint\Nodes\Enums::BPEventListen){
			$createPortTarget = 'output';
		}
		else $createPortTarget = 'input';

		foreach ($schema as &$key) {
			$this->node->createPort($createPortTarget, $key, Types::Any);
		}

		$this->_addToList();
	}
	public function createField($name, $type=Types::Any){
		$schema = &$this->_eventRef->schema;
		if($schema[$name] != null) return;

		$schema[$name] = &$type;
		$this->_insEventsRef->refreshFields($this->data['namespace']);
		$this->node->instance->_emit('event.field.created', new \Blackprint\EvEFieldCreated(
			$name,
			$this->data['namespace']
		));
	}
	public function renameField($name, $to){
		$schema = &$this->_eventRef->schema;
		if($schema[$name] == null || $schema[$to] != null) return;

		$this->_insEventsRef->_renameFields($this->data['namespace'], $name, $to);
		$this->node->instance->_emit('event.field.renamed', new \Blackprint\EvEFieldRenamed(
			$name,
			$to,
			$this->data['namespace']
		));
	}
	public function deleteField($name){
		$schema = &$this->_eventRef->schema;
		if($schema[$name] == null) return;

		unset($schema[$name]);
		$this->_insEventsRef->refreshFields($this->data['namespace']);
		$this->node->instance->_emit('event.field.deleted', new \Blackprint\EvEFieldDeleted(
			$name,
			$this->data['namespace']
		));
	}

	public function _addToList(){
		$used = &$this->_insEventsRef->list[$this->data['namespace']]->used;
		if(!in_array($this, $used)) $used[] = $this;
		else \trigger_error("Tried to adding this node to the InstanceEvents more than once", E_USER_ERROR);
	}

	public function _removeFromList(){
		$used = &$this->_insEventsRef->list[$this->data['namespace']]->used;
		$usedIndex = array_search($this, $used);
		if($usedIndex !== false) unset($used[$usedIndex]);
	}
};

class IEventListen extends BPEventListenEmit {
	public $_listener;
	/** @var BPEventListen */
	public $node;
	public function initPorts($data){
		parent::initPorts($data);

		if($this->_listener) throw new \Exception("This node already listen to an event");
		$this->_listener = fn($ev) => $this->node->eventUpdate($ev);

		$this->_insEventsRef->on($data['namespace'], $this->_listener);
	}
}
\Blackprint\registerInterface('BPIC/BP/Event/Listen', IEventListen::class);

class IEnvEmit extends BPEventListenEmit { }
\Blackprint\registerInterface('BPIC/BP/Event/Emit', IEnvEmit::class);