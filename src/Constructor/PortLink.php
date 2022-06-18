<?php
namespace Blackprint\Constructor;
use Blackprint\Utils;

class PortLink {
	private $iface;
	private $which;
	private $nodePort;

	public function __construct(&$node, $which, $portMeta){
		$iface = &$node->iface;
		$this->iface = &$iface;
		$this->which = &$which;

		$iface->{$which} = [];

		$link = [];
		$node->{$which} = &$link;
		$this->nodePort = &$link;

		// Create linker for all port
		foreach($portMeta as $portName => &$val){
			if(substr($portName, 0, 1) === '_') continue;
			$this->_add($portName, $val);
		}
	}

	public function &_add(&$portName, $val){
		$iPort = &$this->iface->{$this->which};
		$exist = &$iPort[$portName];

		if(isset($iPort[$portName]))
			return $exist;

		// Determine type and add default value for each type
		[ $type, $def, $haveFeature ] = Utils::determinePortType($val, $this);

		$linkedPort = $this->iface->_newPort($portName, $type, $def, $this->which, $haveFeature);
		$iPort[$portName] = &$linkedPort;

		$this->nodePort[$portName] = $linkedPort->createLinker();

		return $linkedPort; // IFace Port
	}

	public function _delete(&$portName){
		$iPort = &$this->iface[$this->which];
		if(!isset($iPort)) return;

		// Destroy cable first
		$port = &$iPort[$portName];
		$port->disconnectAll();

		unset($iPort[$portName]);
		unset($this->nodePort[$portName]);
	}
}