<?php
namespace Blackprint\Nodes;
use \Blackprint\Types;
use \Blackprint\Environment;

class BPEnvGet extends \Blackprint\Node {
	public static $Output = ["Val" => Types::String];
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Env/Get');

		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'EnvGet';
		$iface->type = 'bp-env-get';

		$iface->_enum = Enums::BPEnvGet;
	}
}
\Blackprint\registerNode('BP/Env/Get', BPEnvGet::class);

class BPEnvSet extends \Blackprint\Node {
	public static $Input = ["Val" => Types::String];
	public function __construct($instance){
		parent::__construct($instance);
		$iface = $this->setInterface('BPIC/BP/Env/Set');
		
		// Specify data field from here to make it enumerable and exportable
		$iface->data = ["name" => ''];
		$iface->title = 'EnvSet';
		$iface->type = 'bp-env-set';

		$iface->_enum = Enums::BPEnvSet;
	}
	public function update($cable){
		Environment::set($this->iface->data['name'], $this->input["Val"]);
	}
}
\Blackprint\registerNode('BP/Env/Set', BPEnvSet::class);

class BPEnvGetSet extends \Blackprint\Interfaces {
	public function imported($data){
		if(!$data['name']) throw new \Exception("Parameter 'name' is required");
		$this->data['name'] = $data['name'];

		// Create new environment if not exist
		if(!isset(Environment::$map[$data['name']])){
			Environment::set($data['name'], '');
		}
	}
};

class IEnvGet extends BPEnvGetSet {
	public function imported($data){
		parent::imported($data);
		$this->_listener = function(&$v) {
			if($v->key !== $this->data['name']) return;
			$this->ref->Output->setByRef("Val", $v->value);
		};

		\Blackprint\Event->on('environment.changed environment.added', $this->_listener);
		$this->ref->Output->setByRef("Val", Environment::$map[$this->data['name']]);
	}
	public function destroy(){
		if($this->_listener == null) return;
		\Blackprint\Event->off('environment.changed environment.added', $this->_listener);
	}
}
\Blackprint\registerInterface('BPIC/BP/Env/Get', IEnvGet::class);

class IEnvSet extends BPEnvGetSet { }
\Blackprint\registerInterface('BPIC/BP/Env/Set', IEnvSet::class);