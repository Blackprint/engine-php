<?php
namespace Blackprint\Constructor;
use Blackprint\Utils;

class PortLink {
	private $_iface;
	private $_which;

	public function __construct($node, $which, $portMeta){
		$iface = $this->_iface = &$node->iface;
		$this->_which = &$which;

		$iface[$which] = [];

		// Create linker for all port
		foreach($portMeta as $portName){
			if(substr($portName, 0, 1) === '_') continue;
			$this->_add($portName, $portMeta[$portName]);
		}
	}

	public function &_add($portName, $val){
		$iPort = &$this->_iface[$this->_which];
		$exist = &$iPort[$portName];

		if(isset($iPort[$portName]))
			return $exist;

		// Determine type and add default value for each type
		[ $type, $def, $haveFeature ] = Utils::determinePortType($val, $this);

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