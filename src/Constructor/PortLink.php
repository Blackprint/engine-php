<?php
namespace Blackprint\Constructor;
use Blackprint\Utils;
use \Blackprint\PortType;

class PortLink {
	public $_iface;
	public $_which;
	private $nodePort;

	public function __construct(&$node, $which, $portMeta){
		$iface = &$node->iface;
		$this->_iface = &$iface;
		$this->_which = &$which;

		$iface->{$which} = [];

		$link = [];
		$node->{$which} = &$link;
		$this->nodePort = &$link;

		// Create linker for all port
		foreach($portMeta as $portName => &$val){
			$this->_add($portName, $val);
		}
	}

	public function &_add(&$portName, $val){
		$iPort = &$this->_iface->{$this->_which};
		$exist = &$iPort[$portName];

		if(isset($iPort[$portName]))
			return $exist;

		// Determine type and add default value for each type
		[ $type, $def, $haveFeature ] = Utils::determinePortType($val, $this);

		$linkedPort = $this->_iface->_newPort($portName, $type, $def, $this->_which, $haveFeature);
		$iPort[$portName] = &$linkedPort;

		if($haveFeature == PortType::Trigger && $this->_which === 'input')
			$this->nodePort[$portName] = $linkedPort->default;
		else $this->nodePort[$portName] = $linkedPort->createLinker();

		return $linkedPort; // IFace Port
	}

	public function _delete(&$portName){
		$iPort = &$this->_iface->{$this->_which};
		if($iPort === null) return;

		// Destroy cable first
		$port = &$iPort[$portName];
		$port->disconnectAll();

		unset($iPort[$portName]);
		unset($this->nodePort[$portName]);
	}
}