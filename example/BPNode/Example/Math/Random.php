<?php
namespace BPNode\Example\Math;

use \Blackprint\{
	Types,
	Port,
};

Random::$input['Re-seed'] = Port::Trigger(function() {
	$this->executed = true;
	$this->output['Out'](random_int(0, 100));
});

class Random extends \Blackprint\Node {
	public static $output = [
		'Out'=> Types::Number
	];

	public static $input = [
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