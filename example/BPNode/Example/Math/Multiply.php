<?php
namespace BPNode\Example\Math;

use \Blackprint\{
	Types,
	Port,
};

Multiply::$input['Exec'] = Port::Trigger(function($self){
	$self->output['Result']($self->multiply());
	\App\colorLog("Math\Multiply:", "Result has been set: ".$self->output['Result']());
});

class Multiply extends \Blackprint\Node {
	public static $input = [
		// 'Exec'=> Port::Trigger,
		'A'=> Types::Number,
		'B'=> Types::Any,
	];

	public static $output = [
		'Result'=> Types::Number,
	];

	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface(); // default interface
		$iface->title = "Multiply";

		$this->on('cable.connect', function($ev){
			\App\colorLog("Math\Multiply:", "Cable connected from {$ev->port->node->title} ({$ev->port->name}) to {$ev->target->node->title} ({$ev->target->name})");
		});
	}

	// Your own processing mechanism
	function multiply(){
		\App\colorLog("Math\Multiply:", "Multiplying {$this->input['A']()} with {$this->input['B']()}");
		return $this->input['A']() * $this->input['B']();
	}

	// When any output value from other node are updated
	// Let's immediately change current node result
	function update($cable){
		$this->output['Result']($this->multiply());
	}
}