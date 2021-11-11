<?php
namespace BPNode\Example\Display;

use \Blackprint\{
	Types,
	Port,
};

class Logger extends \Blackprint\Node {
	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/Example/Logger');
		$iface->title = "Logger";

		$this->input = [
			'Any'=> Port::ArrayOf(Types::Any)
		];
	}
}

\Blackprint\registerInterface('BPIC\Example\Logger', LoggerIFace::class);
class LoggerIFace extends \Blackprint\Interfaces {
	private $_log = '...';
	public function log(&$val = null){ // getter (if first arg is null), setter (if not null)
		if($val === null) return $this->_log;

		$this->_log = &$val;
		\App\colorLog("Logger Data =>", $val);
	}

	function init() {
		$refreshLogger = function($val) {
			if($val === null){
				$val = 'null';
				$this->log($val);
			}
			elseif(is_string($val) || is_numeric($val))
				$this->log($val);
			else{
				$val = json_encode($val);
				$this->log($val);
			}
		};

		// Let's show data after new cable was connected or disconnected
		$this->on('cable.connect cable.disconnect', function() use($refreshLogger) {
			\App\colorLog("Display\Logger:", "A cable was changed on Logger, now refresing the input element");
			$refreshLogger($this->node->input['Any']());
		});

		$this->input['Any']->on('value', function(&$port) use($refreshLogger) {
			\App\colorLog("Display\Logger:", "I connected to {$port->name} (port {$port->iface->title}), that have new value: $port->value");

			// Let's take all data from all connected nodes
			// Instead showing new single data-> val
			$refreshLogger($this->node->input['Any']());
		});
	}
}