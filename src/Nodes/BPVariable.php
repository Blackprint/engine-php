<?php
namespace Blackprint\Nodes;

/** For internal library use only */
class VarScope {
	const public = 0;
	const private = 1;
	const shared = 2;
};

class VarSet extends \Blackprint\Node {
	public static $Input = [];
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Var/Set');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = [
			"name" => '',
			"scope" => VarScope::public
		];

		$iface->title = 'VarSet';
		$iface->type = 'bp-var-set';
		$iface->_enum = Enums::BPVarSet;
		$iface->_dynamicPort = true; // Port is initialized dynamically
	}
	public function update($cable){
		$this->iface->_bpVarRef->value = $this->input['Val'];
	}
};
\Blackprint\registerNode('BP/Var/Set', VarSet::class);

class VarGet extends \Blackprint\Node {
	public static $Output = [];
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Var/Get');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = [
			"name" => '',
			"scope" => VarScope::public
		];

		$iface->title = 'VarGet';
		$iface->type = 'bp-var-get';
		$iface->_enum = Enums::BPVarGet;
		$iface->_dynamicPort = true; // Port is initialized dynamically
	}
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
	public $used = [];
	// this->totalSet = 0;
	// this->totalGet = 0;
	public $funcInstance = null;
	public $_value = null;

	public function __construct($id, $options=null){
		$id = preg_replace('/[`~!@#$%^&*()\-_+={}\[\]:"|;\'\\\\,.\/<>?]+/', '_', $id);
		
		// this.rootInstance = instance;
		$this->id = $this->title = &$id;
		$this->type = &BPVarTemp::$typeNotSet;
		
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
		$map = &$this->used;
		foreach ($map as &$iface) {
			$iface->node->instance->deleteNode($iface);
		}

		array_splice($map, 0);
	}
}

class BPVarGetSet extends \Blackprint\Interfaces {
	public $_onChanged = null;

	public function imported($data){
		if(!isset($data['scope']) || !isset($data['name']))
			throw new \Exception("'scope' and 'name' options is required for creating variable node");

		$this->changeVar($data['name'], $data['scope']);
		$temp = &$this->_bpVarRef;
		$temp->used[] = &$this;
	}
	public function &changeVar($name, $scopeId){
		if($this->data['name'] !== '')
			throw new \Exception("Can't change variable node that already be initialized");
			
		$this->data['name'] = &$name;
		$this->data['scope'] = &$scopeId;

		$_funcInstance = &$this->node->instance->_funcMain;
		if($_funcInstance !== null)
			$_funcInstance = &$_funcInstance->node->_funcInstance;

		if($scopeId === VarScope::public){
			if($_funcInstance !== null)
				$scope = &$_funcInstance->rootInstance->variables;
			else $scope = &$this->node->instance->variables;
		}
		elseif($scopeId === VarScope::shared)
			$scope = &$_funcInstance->variables;
		else // private
			$scope = &$this->node->instance->variables;

		if(!isset($scope[$name])){
			if($scopeId === VarScope::public) $_scopeName = 'public';
			elseif($scopeId === VarScope::private) $_scopeName = 'private';
			elseif($scopeId === VarScope::shared) $_scopeName = 'shared';
			else $_scopeName = 'unknown';

			throw new \Exception("'{$name}' variable was not defined on the '{$_scopeName} (scopeId: $scopeId)' instance");
		}

		return $scope;
	}

	public function _reinitPort(){
		throw new \Exception("It should only call child method and not the parent");
	}
	
	public function useType(\Blackprint\Constructor\Port $port){
		$temp = &$this->_bpVarRef;
		if($temp->type !== BPVarTemp::$typeNotSet){
			if($port === null) $temp->type = BPVarTemp::$typeNotSet;
			return;
		}

		if($port === null) throw new \Exception("Can't set type with null");
		$temp->type = &$port->type;

		$targetPort = $this->_reinitPort();
		$targetPort->connectPort($port);

		// Also create port for other node that using $this variable
		$used = &$temp->used;
		foreach ($used as &$item)
			$item->_reinitPort();
	}
	public function destroy(){
		$temp = &$this->_bpVarRef;
		if($temp === null) return;

		$i = array_search($this, $temp->used);
		if($i !== false) array_splice($temp->used, $i, 1);

		$listener = &$this->_bpVarRef->listener;
		if($listener == null) return;

		$i = array_search($this, $listener);
		if($i !== false) array_splice($listener, $i, 1);
	}
}

class IVarGet extends BPVarGetSet {
	public function &changeVar($name, $scopeId){
		if($this->data['name'] !== '')
			throw new \Exception("Can't change variable node that already be initialized");

		if($this->_onChanged != null)
			$this->_bpVarRef?->off('value', $this->_onChanged);

		$scope = parent::changeVar($name, $scopeId);
		$this->title = "Get $name";

		$temp = &$scope[$this->data['name']];
		$this->_bpVarRef = &$temp;
		if($temp->type === BPVarTemp::$typeNotSet) return $scope;

		$this->_reinitPort();
	}

	public function _reinitPort(){
		$temp = &$this->_bpVarRef;
		$node = &$this->node;
		if(isset($this->output['Val']))
			$node->deletePort('output', 'Val');

		$ref = &$this->node->output;
		$node->createPort('output', 'Val', $temp->type);

		if($temp->type === \Blackprint\Types::Function){
			$this->_eventListen = 'call';
			$this->_onChanged = function() use(&$ref) { $ref['Val'](); };
		}
		else{
			$this->_eventListen = 'value';
			$this->_onChanged = function() use(&$ref, &$temp) { $ref->setByRef('Val', $temp->_value); };
		}

		$temp->on($this->_eventListen, $this->_onChanged);
		return $this->output['Val'];
	}
	public function destroy(){
		if($this->_eventListen != null)
			$this->_bpVarRef->off($this->_eventListen, $this->_onChanged);

		parent::destroy();
	}
}
\Blackprint\registerInterface('BPIC/BP/Var/Get', IVarGet::class);

class IVarSet extends BPVarGetSet {
	public function &changeVar($name, $scopeId){
		$scope = parent::changeVar($name, $scopeId);
		$this->title = "Set $name";

		$temp = &$scope[$this->data['name']];
		$this->_bpVarRef = &$temp;
		if($temp->type === BPVarTemp::$typeNotSet) return $scope;

		$this->_reinitPort();
	}

	public function _reinitPort(){
		$input = &$this->input;
		$node = &$this->node;
		$temp = &$this->_bpVarRef;

		if(isset($input['Val']))
			$node->deletePort('input', 'Val');

		if($temp->type === \Blackprint\Types::Function){
			$node->createPort('input', 'Val', \Blackprint\Port::Trigger(function() use(&$temp) {
				$temp->emit('call');
			}));
		}
		else $node->createPort('input', 'Val', $temp->type);

		return $input['Val'];
	}
}
\Blackprint\registerInterface('BPIC/BP/Var/Set', IVarSet::class);