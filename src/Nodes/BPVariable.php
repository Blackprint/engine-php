<?php
namespace Blackprint\Nodes;

\Blackprint\registerNode('BP\Var\Set', VarSet::class);
class VarSet extends \Blackprint\Node {
	public static $input = [];
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Var/Set');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = [
			"name" => '',
			"scope" => 'public'
		];

		$iface->title = 'VarSet';
		$iface->type = 'bp-var-set';
		$iface->enum = Enums::BPVarSet;
		$iface->_dynamicPort = true; // Port is initialized dynamically
	}
	public function update($cable){
		$this->iface->_bpVarRef->value = $this->input['Val'];
	}
};

\Blackprint\registerNode('BP\Var\Get', VarGet::class);
class VarGet extends \Blackprint\Node {
	public static $output = [];
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Var/Get');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = [
			"name" => '',
			"scope" => 'public'
		];

		$iface->title = 'VarGet';
		$iface->type = 'bp-var-get';
		$iface->enum = Enums::BPVarGet;
		$iface->_dynamicPort = true; // Port is initialized dynamically
	}
};


class BPVarTemp {
	public static $typeNotSet = ['typeNotSet' => true]; // Flag that a port is not set
}

// used for instance.createVariable
class BPVariable extends \Blackprint\Constructor\CustomEvent {
	public $type = null;
	public $used = [];
	// this->totalSet = 0;
	// this->totalGet = 0;

	public function __construct($id, $options=null){
		// this.rootInstance = instance;
		$this->id = $this->title = $id;
		$this->type = &BPVarTemp::$typeNotSet;
		
		// The type need to be defined dynamically on first cable connect
	}

	private $_value = null;
	public function value($val=null){
		if($val == null) return $this->_value;
		$this->_value = &$val;
		$this->emit('value');
	}

	public function destroy(){
		$map = &$this->used;
		foreach ($map as &$iface) {
			$iface->node->_instance->deleteNode($iface);
		}

		array_splice($map, 0);
	}
}

class BPVarGetSet extends \Blackprint\Interfaces {
	public function imported($data){
		if($data->scope == null || $data->name == null)
			throw new \Exception("'scope' and 'name' options is required for creating variable node");

		$this->changeVar($data->name, $data->scope);
		$temp = &$this->_bpVarRef;
		$temp->used->add($this);
	}
	public function &changeVar($name, $scopeName){
		if($this->data->name !== '')
			throw new \Exception(`Can't change variable node that already be initialized`);
			
		$this->data->name = &$name;
		$this->data->scope = &$scopeName;

		$_funcInstance = &$this->node->_instance->_funcMain;
		if($_funcInstance !== null)
			$_funcInstance = &$_funcInstance->node->_funcInstance;

		if($scopeName === 'public'){
			if($_funcInstance !== null)
				$scope = &$_funcInstance->rootInstance->variables;
			else $scope = &$this->node->_instance->variables;
		}
		else if($scopeName === 'shared')
			$scope = &$_funcInstance->variables;
		else // private
			$scope = &$this->node->_instance->variables;

		if(!isset($scope[$name]))
			throw new \Exception(`'{$name}' variable was not defined on the '{$scopeName}' instance`);

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

		$targetPort = &$this->_reinitPort();
		$targetPort->connectPort($port);

		// Also create port for other node that using $this variable
		$used = &$temp->used;
		foreach ($used as &$item)
			$item->_reinitPort();
	}
	public function destroy(){
		$temp = &$this->_bpVarRef;
		if($temp === null) return;

		$temp->used->delete($this);

		$listener = &$this->_bpVarRef->listener;
		if($listener == null) return;

		$i = array_search($this, $listener);
		if($i !== false) array_splice($listener, $i, 1);
	}
}

\Blackprint\registerInterface('BPIC/BP/Var/Get', IVarGet::class);
class IVarGet extends BPVarGetSet {
	public function changeVar($name, $scopeName){
		if($this->data->name !== '')
			throw new \Exception(`Can't change variable node that already be initialized`);

		if($this->_onChanged != null)
			$this->_scope[$this->data->name]?->off('value', $this->_onChanged);

		$scope = $this->_scope = parent::changeVar($name, $scopeName);
		$this->title = "Get $name";

		$temp = $this->_bpVarRef = &$scope[$this->data->name];
		if($temp->type === BPVarTemp::$typeNotSet) return;

		$this->_reinitPort();
	}

	public function _reinitPort(){
		$temp = $this->_bpVarRef;
		$node = $this->node;
		if($this->output->Val !== null)
			$node->deletePort('output', 'Val');

		$ref = $this->node->output;
		if($temp->type === \Blackprint\Types::Function){
			$node->createPort('output', 'Val', $temp->type);

			$this->_eventListen = 'call';
			$this->_onChanged = function() use(&$ref) { $ref['Val'](); };
		}
		else{
			$node->createPort('output', 'Val', $temp->type);

			$this->_eventListen = 'value';
			$this->_onChanged = function() use(&$ref, &$temp) { $ref['Val'] = &$temp->_value; };
		}

		$temp->on($this->_eventListen, $this->_onChanged);
		return $this->output->Val;
	}
	public function destroy(){
		if($this->_eventListen != null)
			$this->_bpVarRef->off($this->_eventListen, $this->_onChanged);

		parent::destroy();
	}
}

\Blackprint\registerInterface('BPIC/BP/Var/Set', IVarSet::class);
class IVarSet extends BPVarGetSet {
	public function changeVar($name, $scopeName){
		$scope = parent::changeVar($name, $scopeName);
		$this->title = "Set $name";

		$temp = $this->_bpVarRef = &$scope[$this->data->name];
		if($temp->type === BPVarTemp::$typeNotSet) return;

		$this->_reinitPort();
	}

	public function _reinitPort(){
		$input = &$this->input;
		$node = &$this->node;
		$temp = &$this->_bpVarRef;

		if($input['Val'] !== null)
			$node->deletePort('input', 'Val');

		if($temp->type === \Blackprint\Types::Function){
			$node->createPort('input', 'Val', \Blackprint\Port::Trigger(function() use(&$temp) {
				$temp->emit('call');
			}));
		}
		else $node->createPort('input', 'Val', $temp->type);

		return $this->input['Val'];
	}
}