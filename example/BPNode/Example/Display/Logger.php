<?php
namespace BPNode\Example\Display;

use \Blackprint\{
	Types,
	Port,
};

class Logger extends \Blackprint\Node {
	/** @var \Blackprint\Nodes\BPFunction|null */
	public $bpFunction = null;

	public static $Input = [
		// 'Any'=> Port::ArrayOf(Types::Any) // Defined on bottom
	];

	/** @var LoggerIFace */
	public $iface;

	function __construct($instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/Example/Logger');
		$iface->title = "Logger";
	}

	function refreshLogger($val) {
		if($val === null){
			$val = 'null';
			$this->iface->log($val);
		}
		elseif(is_string($val) || is_numeric($val))
			$this->iface->log($val);
		else{
			$val = json_encode($val);
			$this->iface->log($val);
		}
	}

	function init() {
		// Let's show data after new cable was connected or disconnected
		$this->iface->on('cable.connect cable.disconnect', function() {
			\App\colorLog("Logger (".($this->iface->id ?? '')."):", "A cable was changed on Logger, now refresing the input element");
			$this->update(null);
		});

		$this->iface->input['Any']->on('value', function(&$ev) {
			$target = &$ev->target;

			\App\colorLog("Logger (".($this->iface->id ?? '')."):", "I connected to port {$target->name} ({$target->iface->namespace}), that have new value: $target->value");
		});
	}

	function update($cable){
		// Let's take all data from all connected nodes
		// Instead showing new single data-> val
		$this->refreshLogger($this->input['Any']);
	}

	// Remote sync in
	function syncIn($id, &$data, $isRemote = false){
		if($id === 'log') $this->iface->log($data);
	}
}

Logger::$Input['Any'] = Port::ArrayOf(Types::Any);

\Blackprint\registerInterface('BPIC/Example/Logger', LoggerIFace::class);
class LoggerIFace extends \Blackprint\Interfaces {
	private $_log = null;
	public function log(&$val = null){ // getter (if first arg is null), setter (if not null)
		if($val === null) return $this->_log;

		$this->_log = &$val;
		\App\colorLog("Logger (".($this->id ?? '').") Data:", $val);
		$this->node->syncOut('log', $val);
	}
}