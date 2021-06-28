<?php
require_once('../vendor/autoload.php');

use \Blackprint\{
	Engine,
	Types,
};
use function \Blackprint\{
	PortListener,
	PortValidator,
};

/* Because PHP lack of getter and setter
 * We need to get or set like calling a function
 */

$instance = new Engine;
// These comment can be collapsed depend on your IDE

// === Register Node Interface ===
	$instance->registerInterface('button', function($iface){
		$iface->clicked = function($ev=null) use($iface) {
			colorLog("Engine: 'Trigger' button clicked, going to run the handler");

			isset($iface->node->clicked) && ($iface->node->clicked)($ev);
		};
	});

	$instance->registerInterface('input', function($iface, $bind){
		$theValue = '';
		$bind([
			'options'=>[
				'value'=> function ($val = null) use(&$theValue, $iface) {
					if($val === null)
						return $theValue;

					$theValue = $val;
					isset($iface->node->changed) && ($iface->node->changed)($val, null);
				}
			]
		]);
	});

	$instance->registerInterface('logger', function($iface, $bind){
		$log = '...';
		$bind([
			'log'=> function ($val = null) use(&$log) {
				if($val === null)
					return $log;

				$log = $val;
				colorLog("Logger: ".$val);
			}
		]);
	});

// Mask the console color, to separate the console.log call from Register Node Handler
	function colorLog($val){
		echo "\n\x1b[33m$val\x1b[0m";
	}

// === Register Node Handler ===
// Almost similar with the engine-js example version
	$instance->registerNode('math/multiply', function($node, $iface){
		$iface->title = "Multiply";

		// Your own processing mechanism
		$multiply = function() use($node) {
			colorLog("Multiplying {$node->inputs['A']()} with {$node->inputs['B']()}");
			return $node->inputs['A']() * $node->inputs['B']();
		};

		$node->inputs = [
			'Exec'=>function() use($node, $multiply) {
				$node->outputs['Result']($multiply());
				colorLog("Result has been set: ".$node->outputs['Result']());
			},
			'A'=> Types\Numbers,
			'B'=> PortValidator(Types\Numbers, function($val) use($iface) {
				// Executed when inputs.B is being obtained
				// And the output from other node is being assigned
				// as current port value in this node
				colorLog("{$iface->title} - Port B got input: $val");
				return $val+0;
			})
		];

		$node->outputs = [
			'Result'=> Types\Numbers,
		];

		// Event listener can only be registered after init
		$node->init = function() use($iface) {
			$iface->on('cable.connect', function($cable){
				colorLog("Cable connected from {$cable->owner->node->title} ({$cable->owner->name}) to {$cable->target->node->title} ({$cable->target->name})");
			});
		};

		// When any output value from other node are updated
		// Let's immediately change current node result
		$node->update = function($cable) use($multiply, $node) {
			$node->outputs['Result']($multiply());
		};
	});

	$instance->registerNode('math/random', function($node, $iface){
		$iface->title = "Random";

		$node->outputs = [
			'Out'=> Types\Numbers
		];

		$executed = false;
		$node->inputs = [
			'Re-seed'=>function() use(&$executed, $node) {
				$executed = true;
				$node->outputs['Out'](random_int(0,100));
			}
		];

		// When the connected node is requesting for the output value
		$node->request = function($port, $iface) use(&$executed, $node) {
			// Only run once this node never been executed
			// Return false if no value was changed
			if($executed === true)
				return false;

			colorLog("Value request for port: {$port->name}, from node: {$iface->title}");

			// Let's create the value for him
			$node->inputs['Re-seed']();
		};
	});

	$instance->registerNode('display/logger', function($node, $iface){
		$iface->title = "Logger";
		$iface->interface = 'logger';

		$refreshLogger = function($val) use($iface) {
			if($val === null)
				($iface->log)('null');
			else if(is_string($val) || is_numeric($val))
				($iface->log)($val);
			else
				($iface->log)(json_encode($val));
		};

		$node->inputs = [
			'Any'=> PortListener(function($port, $val) use($refreshLogger, $node) {
				colorLog("I connected to {$port->name} (port {$port->iface->title}), that have new value: $val");

				// Let's take all data from all connected nodes
				// Instead showing new single data-> val
				$refreshLogger($node->inputs['Any']());
			})
		];

		$node->init = function() use($node, $iface, $refreshLogger) {
			// Let's show data after new cable was connected or disconnected
			$iface->on('cable.connect cable.disconnect', function() use($refreshLogger, $node) {
				colorLog("A cable was changed on Logger, now refresing the input element");
				$refreshLogger($node->inputs['Any']());
			});
		};
	});

	$instance->registerNode('button/simple', function($node, $iface){
		// iface = under ScarletsFrame element control
		$iface->title = "Button";
		$iface->interface = 'button';

		// node = under Blackprint node flow control
		$node->outputs = [
			'Clicked'=> Types\Functions
		];

		// Proxy event object from: iface.clicked -> node.clicked -> outputs.Clicked
		$node->clicked = function($ev) use($node) {
			colorLog("button/simple: got $ev, time to trigger to the other node");
			$node->outputs['Clicked']($ev);
		};
	});

	$instance->registerNode('input/simple', function($node, $iface){
		// iface = under ScarletsFrame element control
		$iface->title = "Input";
		$iface->interface = 'input';

		// node = under Blackprint node flow control
		$node->outputs = [
			'Changed'=> Types\Functions,
			'Value'=> 'wer', // Default to empty string
		];

		// Bring value from imported iface to node output
		$node->imported = function() use($node, $iface) {
			if($iface->options['value']())
				colorLog("Saved options as outputs: {$iface->options['value']()}");

			$node->outputs['Value']($iface->options['value']());
		};

		// Proxy string value from: iface.changed -> node.changed -> outputs.Value
		// And also call outputs.Changed() if connected to other node
		$node->changed = function($text, $ev) use($node, $iface) {
			// This node still being imported
			if($iface->importing !== false)
				return;

			colorLog("The input box have new value: $text");
			$node->outputs['Value']($text);

			// This will call every connected node
			$node->outputs['Changed']();
		};
	});


// === Import JSON after all nodes was registered ===
// You can import this to Blackprint Sketch if you want to view the nodes visually
$instance->importJSON('{"math/random":[{"id":0,"x":298,"y":73,"outputs":{"Out":[{"id":2,"name":"A"}]}},{"id":1,"x":298,"y":239,"outputs":{"Out":[{"id":2,"name":"B"}]}}],"math/multiply":[{"id":2,"x":525,"y":155,"outputs":{"Result":[{"id":3,"name":"Any"}]}}],"display/logger":[{"id":3,"x":763,"y":169}],"button/simple":[{"id":4,"x":41,"y":59,"outputs":{"Clicked":[{"id":2,"name":"Exec"}]}}],"input/simple":[{"id":5,"x":38,"y":281,"options":{"value":"saved input"},"outputs":{"Changed":[{"id":1,"name":"Re-seed"}],"Value":[{"id":3,"name":"Any"}]}}]}');


// Time to run something :)
$button = $instance->getNodes('button/simple')[0];

echo "\n\n>> I'm clicking the button";
($button->clicked)();

$logger = $instance->getNodes('display/logger')[0];
echo "\n\n>> I got the output value: ".($logger->log)();

echo "\n\n>> I'm writing something to the input box";
$input = $instance->getNodes('input/simple')[0];
$input->options['value']('hello wrold');

$logger = $instance->getNodes('display/logger')[0];
echo "\n\n>> I got the output value: ".($logger->log)();