<?php
namespace BPNode\Example\Math;

use \Blackprint\{
	Types,
	Port,
};

class Multiply extends \Blackprint\Node {
	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface(); // default interface
		$iface->title = "Multiply";

		$this->input = [
			'Exec'=> Port::Trigger(function(){
				$this->output['Result']($this->multiply());
				\App\colorLog("Result has been set: ".$this->output['Result']());
			}),
			'A'=> Types::Number,
			'B'=> Port::Validator(Types::Number, function($val) {
				// Executed when input.B is being obtained
				// And the output from other node is being assigned
				// as current port value in this node
				\App\colorLog("{$this->iface->title} - Port B got input: $val");
				return $val+0;
			})
		];

		$this->output = [
			'Result'=> Types::Number,
		];

		$this->on('cable.connect', function($ev){
			\App\colorLog("Cable connected from {$ev->port->node->title} ({$ev->port->name}) to {$ev->target->node->title} ({$ev->target->name})");
		});
	}

	// Your own processing mechanism
	function multiply(){
		\App\colorLog("Multiplying {$this->input['A']()} with {$this->input['B']()}");
		return $this->input['A']() * $this->input['B']();
	}

	// When any output value from other node are updated
	// Let's immediately change current node result
	function update($cable){
		$this->output['Result']($this->multiply());
	}
}