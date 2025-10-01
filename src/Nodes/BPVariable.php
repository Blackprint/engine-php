<?php
namespace Blackprint\Nodes;

use Blackprint\PortType;

/** For internal library use only */
class VarScope {
	const Public = 0;
	const Private = 1;
	const Shared = 2;
};

class VarSet extends \Blackprint\Node {
	public static $Input = [];

	/** @var IVarSet */
	public $iface;
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Var/Set');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = [
			"name" => '',
			"scope" => VarScope::Public
		];

		$iface->title = 'VarSet';
		$iface->type = 'bp-var-set';
		$iface->_enum = Enums::BPVarSet;
	}
	public function update($cable){
		$this->iface->_bpVarRef->value = $this->input['Val'];
	}
	public function destroy(){ $this->iface->destroyIface(); }
};
\Blackprint\registerNode('BP/Var/Set', VarSet::class);

class VarGet extends \Blackprint\Node {
	public static $Output = [];

	/** @var IVarGet */
	public $iface;
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Var/Get');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = [
			"name" => '',
			"scope" => VarScope::Public
		];

		$iface->title = 'VarGet';
		$iface->type = 'bp-var-get';
		$iface->_enum = Enums::BPVarGet;
	}
	public function destroy(){ $this->iface->destroyIface(); }
};
\Blackprint\registerNode('BP/Var/Get', VarGet::class);


class BPVarTemp {
	public static $typeNotSet = ['typeNotSet' => true]; // Flag that a port is not set
}

// used for instance.createVariable
class BPVariable extends \Blackprint\Constructor\CustomEvent {
	public $type = null;
	public $id = null;
	public $title = null;
	public $used = []; // [Interface, Interface, ...]
	// this->totalSet = 0;
	// this->totalGet = 0;
	public $bpFunction = null; // Only exist for function node's variable (shared/private)
	public $_value = null;
	public $_scope = null;
	public $isShared = false;

	public function __construct($id, $options=null){
		$id = preg_replace('/^\/|\/$/m', '', $id);
		$id = preg_replace('/[`~!@#$%^&*()\-_+={}\[\]:"|;\'\\\\,.<>?]+/', '_', $id);

		// $this->rootInstance = instance;
		$this->id = &$id;
		$this->title = $options['title'] ?? $id;
		$this->type = \Blackprint\Types::Slot;

		// The type need to be defined dynamically on first cable connect
	}

	function &__get($key){
		if($key !== 'value') throw new \Exception("Property '$key' was not found");;
		return $this->_value;
	}

	function __set($key, $val){
		if($key !== 'value') throw new \Exception("Property '$key' was not found");
		if($this->_value === $val) return;

		$this->_value = &$val;
		$this->emit('value');
	}

	public function destroy(){
		$map = &$this->used; // This list can be altered multiple times when deleting a node
		foreach ($map as &$iface) {
			$iface->node->instance->deleteNode($iface);
		}
		$this->emit('destroy');
	}
}

class BPVarGetSet extends \Blackprint\Interfaces {
	public $_onChanged = null;
	public $_destroyWaitType;
	public $_waitTypeChange;
	public $_bpVarRef;
	public $_dynamicPort = true; // Port is initialized dynamically
	public $type;

	public function imported($data){
		if(!isset($data['scope']) || !isset($data['name']))
			throw new \Exception("'scope' and 'name' options is required for creating variable node");

		$this->changeVar($data['name'], $data['scope']);
		$temp = &$this->_bpVarRef;
		$temp->used[] = &$this;
	}
	public function changeVar($name, $scopeId){
		if($this->data['name'] !== '')
			throw new \Exception("Can't change variable node that already be initialized");

		$this->data['name'] = &$name;
		$this->data['scope'] = &$scopeId;

		$bpFunction = &$this->node->instance->parentInterface->node->bpFunction;

		if($scopeId === VarScope::Public){
			if($bpFunction !== null)
				$scope = &$bpFunction->rootInstance->variables;
			else $scope = &$this->node->instance->variables;
		}
		elseif($scopeId === VarScope::Shared)
			$scope = &$bpFunction->variables;
		else // private
			$scope = &$this->node->instance->variables;

		$construct = \Blackprint\Utils::getDeepProperty($scope, explode('/', $name));

		if($construct === null){
			if($scopeId === VarScope::Public) $_scopeName = 'public';
			elseif($scopeId === VarScope::Private) $_scopeName = 'private';
			elseif($scopeId === VarScope::Shared) $_scopeName = 'shared';
			else throw new \Exception("Unrecognized scopeId: $scopeId");

			throw new \Exception("'{$name}' variable was not defined on the '{$_scopeName} (scopeId: $scopeId)' instance");
		}

		return $construct;
	}

	public function _reinitPort(){
		throw new \Exception("It should only call child method and not the parent");
	}

	public function useType(\Blackprint\Constructor\Port $port){
		$temp = &$this->_bpVarRef;
		if($temp->type !== \Blackprint\Types::Slot){
			if($port === null) $temp->type = \Blackprint\Types::Slot;
			return;
		}

		if($port === null) throw new \Exception("Can't set type with null");
		$type = $temp->type = $port->_config ?? $port->type;

		if(is_array($type) && $type['feature'] === PortType::Trigger)
			$temp->type = \Blackprint\Types::Trigger;

		if($port->type === \Blackprint\Types::Slot)
			$this->waitTypeChange($temp, $port);
		else {
			$this->_recheckRoute();
			$temp->emit('type.assigned');
		}

		// Also create port for other node that using $this variable
		$used = &$temp->used;
		foreach ($used as &$item)
			$item->_reinitPort();
	}
	public function waitTypeChange(&$bpVar, &$port=null){
		$this->_waitTypeChange = function() use(&$bpVar, &$port) {
			if($port !== null) {
				$type = $bpVar->type = $port->_config ?? $port->type;
				if(is_array($type) && $type['feature'] === PortType::Trigger)
					$bpVar->type = \Blackprint\Types::Trigger;

				$bpVar->emit('type.assigned');
			}
			else {
				$target = $this->input['Val'] ?? $this->output['Val'];
				$target->assignType($bpVar->type);
			}

			$this->_recheckRoute();
		};

		$this->_destroyWaitType = fn() => $bpVar->off('type.assigned', $this->_waitTypeChange);
		($port ?? $bpVar)->once('type.assigned', $this->_waitTypeChange);
	}
	public function _recheckRoute(){
		if(($this->input['Val']->type ?? null) === \Blackprint\Types::Trigger
		|| ($this->output['Val']->type ?? null) === \Blackprint\Types::Trigger){
			$routes = &$this->node->routes;
			$routes->disableOut = true;
			$routes->noUpdate = true;
		}
	}
	public function destroyIface(){
		$temp = &$this->_destroyWaitType;
		if($temp !== null)
			($this->_destroyWaitType)();

		$temp = &$this->_bpVarRef;
		if($temp === null) return;

		$i = array_search($this, $temp->used);
		if($i !== false) array_splice($temp->used, $i, 1);
	}
}

class IVarGet extends BPVarGetSet {
	private $_eventListen;
	public function changeVar($name, $scopeId){
		if($this->data['name'] !== '')
			throw new \Exception("Can't change variable node that already be initialized");

		if($this->_onChanged != null)
			$this->_bpVarRef?->off('value', $this->_onChanged);

		$varRef = parent::changeVar($name, $scopeId);
		$this->title = str_replace('/', ' / ', $name);

		$this->_bpVarRef = &$varRef;
		if($varRef->type === \Blackprint\Types::Slot) return;

		$this->_reinitPort();
		$this->_recheckRoute();
	}

	public function _reinitPort(){
		$temp = &$this->_bpVarRef;
		$node = &$this->node;

		if($temp->type === \Blackprint\Types::Slot)
			$this->waitTypeChange($temp);

		if(isset($this->output['Val']))
			$node->deletePort('output', 'Val');

		$ref = &$node->output;
		$node->createPort('output', 'Val', $temp->type);

		if($temp->type === \Blackprint\Types::Trigger){
			$this->_eventListen = 'call';
			$this->_onChanged = function() use(&$ref) { $ref['Val'](); };
		}
		else{
			$this->_eventListen = 'value';
			$this->_onChanged = function() use(&$ref, &$temp) { $ref->setByRef('Val', $temp->_value); };
		}

		if($temp->type !== \Blackprint\Types::Trigger)
			$node->output['Val'] = $temp->_value;

		$temp->on($this->_eventListen, $this->_onChanged);
		return $this->output['Val'];
	}
	public function destroyIface(){
		if($this->_eventListen != null)
			$this->_bpVarRef->off($this->_eventListen, $this->_onChanged);

		parent::destroyIface();
	}
}
\Blackprint\registerInterface('BPIC/BP/Var/Get', IVarGet::class);

class IVarSet extends BPVarGetSet {
	public function changeVar($name, $scopeId){
		$varRef = parent::changeVar($name, $scopeId);
		$this->title = str_replace('/', ' / ', $name);

		$this->_bpVarRef = &$varRef;
		if($varRef->type === \Blackprint\Types::Slot) return;

		$this->_reinitPort();
		$this->_recheckRoute();
	}

	public function _reinitPort(){
		$input = &$this->input;
		$node = &$this->node;
		$temp = &$this->_bpVarRef;

		if($temp->type === \Blackprint\Types::Slot)
			$this->waitTypeChange($temp);

		if(isset($input['Val']))
			$node->deletePort('input', 'Val');

		if($temp->type === \Blackprint\Types::Trigger){
			$node->createPort('input', 'Val', \Blackprint\Port::Trigger(function() use(&$temp) {
				$temp->emit('call');
			}));
		}
		else $node->createPort('input', 'Val', $temp->type);

		return $input['Val'];
	}
}
\Blackprint\registerInterface('BPIC/BP/Var/Set', IVarSet::class);