<?php
namespace BPNode\Example\Input;

use \Blackprint\{
	Types,
	Port,
};

class Simple extends \Blackprint\Node {
	public static $Output = [
		'Changed'=> Types::Trigger,
		'Value'=> Types::String,
	];

	/** @var InputIFace */
	public $iface;

	function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/Example/Input');
		$iface->title = "Input";
	}

	// Bring value from imported iface to node output
	function imported($data) {
		if($data == null) return;

		$val = $data['value'];
		\App\colorLog("Input/Simple:", "Old data: {$this->iface->data->value}");
		if($val) \App\colorLog("Input/Simple:", "Imported data: {$val}");

		$this->iface->data->value = $val;
	}

	// Remote sync in
	function syncIn($id, &$data, $isRemote = false){
		if($id === 'data'){
			$this->iface->data->value = &$data->value;
		}
		else if($id === 'value'){
			$this->iface->data->value = $data;
		}
	}
}

// Getter and setter should be changed with basic property accessor
// After this draft was merged to PHP https://github.com/php/php-src/pull/6873
class InputIFaceData {
    // Constructor promotion, $iface as private InputIFaceData property
	function __construct(private $iface){}

	private $data = ["value"=> '...'];
	function __get($key) {
		return $this->data[$key];
	}

	function __set($key, $val) {
		$this->data[$key] = &$val;

		if($key === 'value'){
			$this->iface->changed($val);
			$this->iface->node->routes->routeOut();
		}
	}
}

\Blackprint\registerInterface('BPIC/Example/Input', InputIFace::class);
class InputIFace extends \Blackprint\Interfaces {
	function __construct(&$node){
		parent::__construct($node);
		$this->data = new InputIFaceData($this);
	}

	function changed(&$val) {
		$node = &$this->node;

		// This node still being imported
		if($this->importing !== false)
			return;

		\App\colorLog("Input/Simple:", "The input box have new value: $val");
		$node->output['Value'] = $val;
		$node->syncOut('data', ['value' => $this->data->value]);

		// This will call every connected node
		$node->output['Changed']();
	}
}