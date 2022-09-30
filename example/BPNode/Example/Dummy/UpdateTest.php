<?php
namespace BPNode\Example\Dummy;

use \Blackprint\{
	Types,
	Port,
};

class UpdateTest extends \Blackprint\Node {
	public static $Input = [
		"A1"=> Types::String,
		"A2"=> Types::String,
	];

	public static $Output = [
		"B1"=> Types::String,
		"B2"=> Types::String,
	];

	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface(); // Let's use default node interface
		$iface->title = "Pass data only";

		// $iface->on('port.value', ({ port, target }) => {
		// 	if($port->source !== 'input') return;
		// 	$this[$port->name] = $target->value;
		// });
	}

	function update(&$cable){
		// $index = $this->iface->id || array_search($this->iface, $this->instance->ifaceList);
		// echo("UpdateTest "+index+"> Updating ports");

		// if($this->input->A1 !== $this->A1) echo("A1 from event listener value was mismatched");
		// if($this->input->A2 !== $this->A2) echo("A2 from event listener value was mismatched");

		$this->output->B1 = $this->input->A1;
		$this->output->B2 = $this->input->A2;
		// echo("UpdateTest "+index+"> Updated");
	}
}