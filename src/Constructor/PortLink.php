<?php
namespace Blackprint\Constructor;
use Blackprint\Types;

class PortLink {
	private $_iface;
	private $_which;

	public function __construct($node, $which, $portMeta){
		$iface = $this->_iface = &$node->iface;
		$this->_which = &$which;

		$iface[$which] = [];

		// Create linker for all port
		foreach($portMeta as $portName){
			if($portName->slice(0, 1) === '_') continue;
			$this->_add($portName, $portMeta[$portName]);
		}
	}

	public function &_add($portName, $val){
		$iPort = &$this->_iface[$this->_which];
		$exist = &$iPort[$portName];

		if(isset($iPort[$portName]))
			return $exist;

		// Determine type and add default value for each type
		[ $type, $def, $haveFeature ] = determinePortType($val, $this);

		$linkedPort = $this->_iface->_newPort($portName, $type, $def, $this->_which, $haveFeature);
		$iPort[$portName] = &$linkedPort;

		$linkValue = $linkedPort->createLinker();

		if($this->_which === 'output')
			$this[$portName] = &$linkValue;
		else $this[$portName] = &$def;

		return $linkedPort;
	}

	public function _delete($portName){
		$iPort = &$this->_iface[$this->_which];
		if(!isset($iPort)) return;

		// Destroy cable first
		$port = &$iPort[$portName];
		$port->disconnectAll();

		unset($iPort[$portName]);
		unset($this[$portName]);
	}
}

function determinePortType($val, $that){
	if($val === null)
		throw new \Exception("Port type can't be undefined, error when processing: {$that->_iface->title}, {$that->_which} port");

	$type = $val;
	$def = null;
	$feature = is_array($val) ? $val['feature'] : false;

	if($feature === \Blackprint\Port::Trigger_){
		$def = &$val['func'];
		$type = Types::Function;
	}
	elseif($feature === \Blackprint\Port::ArrayOf_)
		$type = &$val['type'];
	elseif($feature === \Blackprint\Port::Union_)
		$type = &$val['type'];
	elseif($feature === \Blackprint\Port::Default_){
		$type = &$val['type'];
		$def = &$val['value'];
	}
	elseif($type === Types::Number)
		$def = 0;
	elseif($type === Types::Boolean)
		$def = false;
	elseif($type === Types::String)
		$def = '';
	elseif($type === Types::Array)
		$def = [];
	elseif($type === Types::Any) 0; // Any
	elseif($type === Types::Function) 0;
	elseif($type === Types::Route) 0;
	elseif($feature === false)
		throw new \Exception("Port for initialization must be a types", 1);
	// else{
	// 	$def = $port;
	// 	$type = Types::String;
	// }

	return [ &$type, &$def, &$feature ];
}