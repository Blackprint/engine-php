<?php
namespace BPNode\Example\Math;

use \Blackprint\{
	Types,
	Port,
};

class Multiply extends \Blackprint\Node {
	// Define input port here
	public static $Input = [
		// 'Exec'=> Port::Trigger,
		'A'=> Types::Number,
		'B'=> Types::Any,
	];

	// Define output port here
	public static $Output = [
		'Result'=> Types::Number,
	];

	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface(); // default interface
		$iface->title = "Multiply";
	}

	function init(){
		$iface = &$this->iface;

		$iface->on('cable.connect', function($ev){
			\App\colorLog("Math\Multiply:", "Cable connected from {$ev->port->iface->title} ({$ev->port->name}) to {$ev->target->iface->title} ({$ev->target->name})");
		});
	}

	// When any output value from other node are updated
	// Let's immediately change current node result
	function update($cable){
		$this->output['Result']($this->multiply());
	}

	// Your own processing mechanism
	function multiply(){
		$input = &$this->input;

		\App\colorLog("Math\Multiply:", "Multiplying {$input['A']()} with {$input['B']()}");
		return $input['A']() * $input['B']();
	}
}

Multiply::$Input['Exec'] = Port::Trigger(function($self){
	$node = &$self->iface->node;

	$node->output['Result']($node->multiply());
	\App\colorLog("Math\Multiply:", "Result has been set: ".$node->output['Result']());
});