<?php
namespace BPNode\Example\Math;

use \Blackprint\{
	Types,
	Port,
};

class Random extends \Blackprint\Node {
	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface(); // default interface
		$iface->title = "Random";

		$this->output = [
			'Out'=> Types::Number
		];

		$this->executed = false;
		$this->input = [
			'Re-seed'=> Port::Trigger(function() {
				$this->executed = true;
				$this->output['Out'](random_int(0, 100));
			})
		];
	}

	// When the connected node is requesting for the output value
	function request($port, $iface) {
		// Only run once this node never been executed
		// Return false if no value was changed
		if($this->executed === true)
			return false;

		\App\colorLog("Value request for port: {$port->name}, from node: {$iface->title}");

		// Let's create the value for him
		$this->input['Re-seed']();
	}
}