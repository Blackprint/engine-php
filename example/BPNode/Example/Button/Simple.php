<?php
namespace BPNode\Example\Button;

use \Blackprint\{
	Types,
	Port,
};

class Simple extends \Blackprint\Node {
	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/Example/Button');
		$iface->title = "Button";

		$this->output = [
			'Clicked'=> Types::Function
		];
	}
}

\Blackprint\registerInterface('BPIC\Example\Button', ButtonIFace::class);
class ButtonIFace extends \Blackprint\Interfaces {
	function clicked($ev = null){
		\App\colorLog("Button\Simple:", "I got '$ev', time to trigger to the other node");

		$this->node->output['Clicked']($ev);
	}
}