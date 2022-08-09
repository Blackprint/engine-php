<?php
namespace Blackprint;
use Blackprint\Utils;

class PortGhost extends Constructor\Port {
	public static $fakeIface = null;
	public function destroy(){
		$this->disconnectAll(false);
	}
}

$fakeIface = (object)[
	"title" => "Blackprint.PortGhost",
	"isGhost" => true,
	"node" => (object)["instance" => (object)["emit" => function($data){}]],
	"emit" => function($data){},
	"input" => [],
	"output" => [],
];

$fakeIface->_iface = &$fakeIface;
PortGhost::$fakeIface = &$fakeIface;

// These may be useful for testing or creating custom port without creating nodes when scripting
class OutputPort extends PortGhost {
	public $_ghost = true;
	public function __construct(&$type){
		[ $type, $def, $haveFeature ] = Utils::determinePortType($type, PortGhost::$fakeIface);

		parent::__construct('Blackprint.OutputPort', $type, $def, 'output', $fakeIface, $haveFeature);
	}
}

class InputPort extends PortGhost {
	public $_ghost = true;
	public function __construct(&$type){
		[ $type, $def, $haveFeature ] = Utils::determinePortType($type, PortGhost::$fakeIface);

		parent::__construct('Blackprint.InputPort', $type, $def, 'input', $fakeIface, $haveFeature);
	}
}