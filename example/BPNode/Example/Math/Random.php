<?php
namespace BPNode\Example\Math;

use \Blackprint\{
	Types,
	Port,
};

class Random extends \Blackprint\Node {
	public static $Output = [
		'Out'=> Types::Number
	];

	public static $Input = [
		// 'Re-seed'=> Port::Trigger,
	];

	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface(); // default interface
		$iface->title = "Random";

		$this->executed = false;
	}

	// When the connected node is requesting for the output value
	function request($port, $iface) {
		// Only run once this node never been executed
		// Return false if no value was changed
		if($this->executed === true)
			return false;

		\App\colorLog("Math\Random:", "Value request for port: {$port->name}, from node: {$iface->title}");

		// Let's create the value for him
		$this->input['Re-seed']();
	}
}

Random::$Input['Re-seed'] = Port::Trigger(function(&$self) {
	$node = &$self->iface->node;

	$node->executed = true;
	$node->output['Out'](random_int(0, 100));
});