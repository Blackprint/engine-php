<?php
namespace BPNode\Example\Display;

use \Blackprint\{
	Types,
	Port,
};

class Logger extends \Blackprint\Node {
	public static $Input = [
		// 'Any'=> Port::ArrayOf(Types::Any) // Defined on bottom
	];

	function __construct(&$instance){
		parent::__construct($instance);

		$iface = $this->setInterface('BPIC/Example/Logger');
		$iface->title = "Logger";
	}

	function init() {
		/** @var LoggerIFace */
		$iface = &$this->iface;

		$refreshLogger = function($val) use(&$iface) {
			if($val === null){
				$val = 'null';
				$iface->log($val);
			}
			elseif(is_string($val) || is_numeric($val))
				$iface->log($val);
			else{
				$val = json_encode($val);
				$iface->log($val);
			}
		};

		// Let's show data after new cable was connected or disconnected
		$iface->on('cable.connect cable.disconnect', function() use(&$refreshLogger) {
			\App\colorLog("Display/Logger:", "A cable was changed on Logger, now refresing the input element");
			$refreshLogger($this->input['Any']);
		});

		$iface->input['Any']->on('value', function(&$ev) use(&$refreshLogger) {
			$target = &$ev->target;

			\App\colorLog("Display/Logger:", "I connected to {$target->name} (port {$target->iface->title}), that have new value: $target->value");

			// Let's take all data from all connected nodes
			// Instead showing new single data-> val
			$refreshLogger($this->input['Any']);
		});
	}
}

Logger::$Input['Any'] = Port::ArrayOf(Types::Any);

\Blackprint\registerInterface('BPIC/Example/Logger', LoggerIFace::class);
class LoggerIFace extends \Blackprint\Interfaces {
	private $_log = null;
	public function log(&$val = null){ // getter (if first arg is null), setter (if not null)
		if($val === null) return $this->_log;

		$this->_log = &$val;
		\App\colorLog("Example/Logger log =>", $val);
	}
}