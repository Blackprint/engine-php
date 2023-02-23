<?php
namespace Blackprint;
use Blackprint\Utils;

class PortGhost extends Constructor\Port {
	public static $fakeIface = null;
	public function destroy(){
		$this->disconnectAll(false);
	}
}

class fakeIface {
	public $title = "Blackprint.PortGhost";
	public $isGhost = true;
	public $node;
	public $emit;
	public $_iface;
	public $input = [];
	public $output = [];
	function __construct(){
		$this->node = (object)["instance" => new fakeInstance()];
	}
	function emit(){}
}
class fakeInstance {
	public function emit(){}
}

PortGhost::$fakeIface = new fakeIface();
PortGhost::$fakeIface->_iface = PortGhost::$fakeIface;

// These may be useful for testing or creating custom port without creating nodes when scripting
class OutputPort extends PortGhost {
	public $_ghost = true;
	public function __construct($type){
		[ $type, $def, $haveFeature ] = Utils::determinePortType($type, PortGhost::$fakeIface);
		$this->iface = PortGhost::$fakeIface;

		$title = 'Blackprint.OutputPort';
		$source = 'output';
		parent::__construct($title, $type, $def, $source, $this->iface, $haveFeature);
	}
}

class InputPort extends PortGhost {
	public $_ghost = true;
	public function __construct($type){
		[ $type, $def, $haveFeature ] = Utils::determinePortType($type, PortGhost::$fakeIface);
		$this->iface = PortGhost::$fakeIface;

		$title = 'Blackprint.InputPort';
		$source = 'input';
		parent::__construct($title, $type, $def, $source, PortGhost::$fakeIface, $haveFeature);
	}
}