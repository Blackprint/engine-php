<?php
namespace BPNode\Example\Input;

use \Blackprint\{
	Types,
	Port,
};

class Simple extends \Blackprint\Node {
	function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/Example/Input');
		$iface->title = "Input";

		$this->output = [
			'Changed'=> Types::Function,
			'Value'=> Types::String,
		];
	}

	// Bring value from imported iface to node output
	function imported() {
		$val = $this->iface->data['value']();
		if($val) \App\colorLog("Input\Simple:", "Saved data as output: {$val}");

		$this->output['Value']($val);
	}
}

\Blackprint\registerInterface('BPIC\Example\Input', InputIFace::class);
class InputIFace extends \Blackprint\Interfaces {
	function __construct($node){
		parent::__construct($node);

		$value = '...';
		$this->data = [
			'value'=> function($val = null) use(&$value) {
				if($val === null) return $value;
				$value = $val;
				$this->changed($val);
			}
		];
	}

	function changed(&$val) {
		// This node still being imported
		if($this->importing !== false)
			return;

		\App\colorLog("Input\Simple:", "The input box have new value: $val");
		$this->node->output['Value']($val);

		// This will call every connected node
		$this->node->output['Changed']();
	}
}