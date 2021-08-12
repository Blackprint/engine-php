<?php
require_once('../vendor/autoload.php');

use \Blackprint\{
	Blackprint,
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

// These comment can be collapsed depend on your IDE

// === Register Node Interface ===
	// When creating your own interface please use specific interface naming
	// 'LibraryName/FeatureName/NodeName'
	// Example below is using 'i-' to make it easier to understand
	Blackprint::registerInterface('i-button', function($iface){
		// Will be used for 'Example/Button/Simple' node
		$iface->clicked = function($ev=null) use($iface) {
			colorLog("Engine: 'Trigger' button clicked, going to run the handler");

			isset($iface->node->clicked) && ($iface->node->clicked)($ev);
		};
	});

	Blackprint::registerInterface('i-input', function($iface, $bind){
		$theValue = '';
		$bind([
			'data'=>[
				'value'=> function ($val = null) use(&$theValue, $iface) {
					if($val === null)
						return $theValue;

					$theValue = $val; // Don't use &$val or the data will not being saved
					isset($iface->node->changed) && ($iface->node->changed)($val, null);
				}
			]
		]);
	});

	Blackprint::registerInterface('i-logger', function($iface, $bind){
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
	Blackprint::registerNode('Example/Math/Multiply', function($node, $iface){
		$iface->title = "Multiply";
		// $iface->interface = undefined; // default interface

		// Your own processing mechanism
		$multiply = function() use($node) {
			colorLog("Multiplying {$node->input['A']()} with {$node->input['B']()}");
			return $node->input['A']() * $node->input['B']();
		};

		$node->input = [
			'Exec'=>function() use($node, $multiply) {
				$node->output['Result']($multiply());
				colorLog("Result has been set: ".$node->output['Result']());
			},
			'A'=> Types\Numbers,
			'B'=> PortValidator(Types\Numbers, function($val) use($iface) {
				// Executed when input.B is being obtained
				// And the output from other node is being assigned
				// as current port value in this node
				colorLog("{$iface->title} - Port B got input: $val");
				return $val+0;
			})
		];

		$node->output = [
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
			$node->output['Result']($multiply());
		};
	});

	Blackprint::registerNode('Example/Math/Random', function($node, $iface){
		$iface->title = "Random";
		// $iface->interface = undefined; // default interface

		$node->output = [
			'Out'=> Types\Numbers
		];

		$executed = false;
		$node->input = [
			'Re-seed'=>function() use(&$executed, $node) {
				$executed = true;
				$node->output['Out'](random_int(0,100));
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
			$node->input['Re-seed']();
		};
	});

	Blackprint::registerNode('Example/Display/Logger', function($node, $iface){
		$iface->title = "Logger";
		$iface->interface = 'i-logger';

		$refreshLogger = function($val) use($iface) {
			if($val === null)
				($iface->log)('null');
			else if(is_string($val) || is_numeric($val))
				($iface->log)($val);
			else
				($iface->log)(json_encode($val));
		};

		$node->input = [
			'Any'=> PortListener(function($port, $val) use($refreshLogger, $node) {
				colorLog("I connected to {$port->name} (port {$port->iface->title}), that have new value: $val");

				// Let's take all data from all connected nodes
				// Instead showing new single data-> val
				$refreshLogger($node->input['Any']());
			})
		];

		$node->init = function() use($node, $iface, $refreshLogger) {
			// Let's show data after new cable was connected or disconnected
			$iface->on('cable.connect cable.disconnect', function() use($refreshLogger, $node) {
				colorLog("A cable was changed on Logger, now refresing the input element");
				$refreshLogger($node->input['Any']());
			});
		};
	});

	Blackprint::registerNode('Example/Button/Simple', function($node, $iface){
		// iface = under ScarletsFrame element control
		$iface->title = "Button";
		$iface->interface = 'i-button';

		// node = under Blackprint node flow control
		$node->output = [
			'Clicked'=> Types\Functions
		];

		// Proxy event object from: iface.clicked -> node.clicked -> output.Clicked
		$node->clicked = function($ev) use($node) {
			colorLog("Example/Button/Simple: got $ev, time to trigger to the other node");
			$node->output['Clicked']($ev);
		};
	});

	Blackprint::registerNode('Example/Input/Simple', function($node, $iface){
		// iface = under ScarletsFrame element control
		$iface->title = "Input";
		$iface->interface = 'i-input';

		// node = under Blackprint node flow control
		$node->output = [
			'Changed'=> Types\Functions,
			'Value'=> 'wer', // Default to empty string
		];

		// Bring value from imported iface to node output
		$node->imported = function() use($node, $iface) {
			if($iface->data['value']())
				colorLog("Saved data as output: {$iface->data['value']()}");

			$node->output['Value']($iface->data['value']());
		};

		// Proxy string value from: iface.changed -> node.changed -> output.Value
		// And also call output.Changed() if connected to other node
		$node->changed = function($text, $ev) use($node, $iface) {
			// This node still being imported
			if($iface->importing !== false)
				return;

			colorLog("The input box have new value: $text");
			$node->output['Value']($text);

			// This will call every connected node
			$node->output['Changed']();
		};
	});


// === Import JSON after all nodes was registered ===
// You can import the JSON to Blackprint Sketch if you want to view the nodes visually
$instance = new Engine;
$instance->importJSON('{"Example/Math/Random":[{"i":0,"x":298,"y":73,"output":{"Out":[{"i":2,"name":"A"}]}},{"i":1,"x":298,"y":239,"output":{"Out":[{"i":2,"name":"B"}]}}],"Example/Math/Multiply":[{"i":2,"x":525,"y":155,"output":{"Result":[{"i":3,"name":"Any"}]}}],"Example/Display/Logger":[{"i":3,"x":763,"y":169}],"Example/Button/Simple":[{"i":4,"x":41,"y":59,"output":{"Clicked":[{"i":2,"name":"Exec"}]}}],"Example/Input/Simple":[{"i":5,"x":38,"y":281,"data":{"value":"saved input"},"output":{"Changed":[{"i":1,"name":"Re-seed"}],"Value":[{"i":3,"name":"Any"}]}}]}');


// Time to run something :)
$button = $instance->getNodes('Example/Button/Simple')[0]->iface;

echo "\n\n>> I'm clicking the button";
($button->clicked)();

$logger = $instance->getNodes('Example/Display/Logger')[0]->iface;
echo "\n\n>> I got the output value: ".($logger->log)();

echo "\n\n>> I'm writing something to the input box";
$input = $instance->getNodes('Example/Input/Simple')[0]->iface;
$input->data['value']('hello wrold');

$logger = $instance->getNodes('Example/Display/Logger')[0]->iface;
echo "\n\n>> I got the output value: ".($logger->log)();