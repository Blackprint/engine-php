<?php
require_once('../vendor/autoload.php');

use \Blackprint\{
	Interpreter,
	Types,
};
use function \Blackprint\{
	PortListener,
	PortValidator,
};

/* Because PHP lack of getter and setter
 * We need to get or set like calling a function
 */

$instance = new Interpreter;
// These comment can be collapsed depend on your IDE

// === Register Node Interface ===
	$instance->registerInterface('button', function($self){
		$self->clicked = function($ev=null) use($self) {
			colorLog("Interpreter: 'Trigger' button clicked, going to run the handler");

			isset($self->handle->clicked) && ($self->handle->clicked)($ev);
		};
	});

	$instance->registerInterface('input', function($self, $bind){
		$theValue = '';
		$bind([
			'options'=>[
				'value'=> function ($val = null) use(&$theValue, $self) {
					if($val === null)
						return $theValue;

					$theValue = $val;
					isset($self->handle->changed) && ($self->handle->changed)($val, null);
				}
			]
		]);
	});

	$instance->registerInterface('logger', function($self, $bind){
		$log = '...';
		$bind([
			'log'=> function ($val = null) use(&$log) {
				if($val !== null)
					return $val;

				$log = $val;
				echo "\nLogger:".$val;
			}
		]);
	});

// Mask the console color, to separate the console.log call from Register Node Handler
	function colorLog($val){
		echo "\n\x1b[33m$val\x1b[0m";
	}

// === Register Node Handler ===
// Almost similar with the interpreter-js example version
	$instance->registerNode('math/multiply', function($handle, $node){
		$node->title = "Multiply";

		// Your own processing mechanism
		$multiply = function() use($handle) {
			colorLog("Multiplying {$handle->inputs['A']()} with {$handle->inputs['B']()}");
			return $handle->inputs['A']() * $handle->inputs['B']();
		};

		$handle->inputs = [
			'Exec'=>function() use($handle, $multiply) {
				$handle->outputs['Result']($multiply());
				colorLog("Result has been set: ".$handle->outputs['Result']());
			},
			'A'=> Types\Numbers,
			'B'=> PortValidator(Types\Numbers, function($val) use($node) {
				// Executed when inputs.B is being obtained
				// And the output from other node is being assigned
				// as current port value in this node
				colorLog("{$node->title} - Port B got input: $val");
				return $val+0;
			})
		];

		$handle->outputs = [
			'Result'=> Types\Numbers,
		];

		// Event listener can only be registered after init
		$handle->init = function() use($node) {
			$node->on('cable.connect', function($cable){
				colorLog("Cable connected from {$cable->owner->node->title} ({$cable->owner->name}) to {$cable->target->node->title} ({$cable->target->name})");
			});
		};

		// When any output value from other node are updated
		// Let's immediately change current node result
		$handle->update = function($cable) use($multiply, $handle) {
			$handle->outputs['Result']($multiply());
		};
	});

	$instance->registerNode('math/random', function($handle, $node){
		$node->title = "Random";

		$handle->outputs = [
			'Out'=> Types\Numbers
		];

		$executed = false;
		$handle->inputs = [
			'Re-seed'=>function() use(&$executed, $handle) {
				$executed = true;
				$handle->outputs['Out'](random_int(0,100));
			}
		];

		// When the connected node is requesting for the output value
		$handle->request = function($port, $node) use(&$executed, $handle) {
			// Only run once this node never been executed
			// Return false if no value was changed
			if($executed === true)
				return false;

			colorLog("Value request for port: {$port->name}, from node: {$node->title}");

			// Let's create the value for him
			$handle->inputs['Re-seed']();
		};
	});

	$instance->registerNode('display/logger', function($handle, $node){
		$node->title = "Logger";
		$node->type = 'logger';

		$refreshLogger = function($val) use($node) {
			if($val === null)
				($node->log)('null');
			else if(is_string($val) || is_numeric($val))
				($node->log)($val);
			else
				($node->log)(json_encode($val));
		};

		$handle->inputs = [
			'Any'=> PortListener(function($port, $val) use($refreshLogger, $handle) {
				colorLog("I connected to {$port->name} (port {$port->node->title}), that have new value: $val");

				// Let's take all data from all connected nodes
				// Instead showing new single data-> val
				$refreshLogger($handle->inputs['Any']());
			})
		];

		$handle->init = function(){
			// Let's show data after new cable was connected or disconnected
			$node->on('cable.connect cable.disconnect', function() use($refreshLogger) {
				colorLog("A cable was changed on Logger, now refresing the input element");
				$refreshLogger($handle->inputs['Any']());
			});
		};
	});

	$instance->registerNode('button/simple', function($handle, $node){
		// node = under ScarletsFrame element control
		$node->title = "Button";
		$node->type = 'button';

		// handle = under Blackprint node flow control
		$handle->outputs = [
			'Clicked'=> Types\Functions
		];

		// Proxy event object from: node.clicked -> handle.clicked -> outputs.Clicked
		$handle->clicked = function($ev) use($handle) {
			colorLog("button/simple: got $ev, time to trigger to the other node");
			$handle->outputs['Clicked']($ev);
		};
	});

	$instance->registerNode('input/simple', function($handle, $node){
		// node = under ScarletsFrame element control
		$node->title = "Input";
		$node->type = 'input';

		// handle = under Blackprint node flow control
		$handle->outputs = [
			'Changed'=> Types\Functions,
			'Value'=> 'wer', // Default to empty string
		];

		// Bring value from imported node to handle output
		$handle->imported = function(){
			if($node->options['value']())
				colorLog("Saved options as outputs: {$node->options->value}");

			$handle->outputs['Value']($node->options['value']());
		};

		// Proxy string value from: node.changed -> handle.changed -> outputs.Value
		// And also call outputs.Changed() if connected to other node
		$handle->changed = function($text, $ev) use($handle, $node) {
			// This node still being imported
			if($node->importing !== false)
				return;

			colorLog("The input box have new value: $text");
			$handle->outputs['Value']($text);

			// This will call every connected node
			$handle->outputs['Changed']();
		};
	});


// === Import JSON after all nodes was registered ===
// You can import this to Blackprint Sketch if you want to view the nodes visually
$instance->importJSON('{"math/random":[{"id":0,"x":298,"y":73,"outputs":{"Out":[{"id":2,"name":"A"}]}},{"id":1,"x":298,"y":239,"outputs":{"Out":[{"id":2,"name":"B"}]}}],"math/multiply":[{"id":2,"x":525,"y":155,"outputs":{"Result":[{"id":3,"name":"Any"}]}}],"display/logger":[{"id":3,"x":763,"y":169}],"button/simple":[{"id":4,"x":41,"y":59,"outputs":{"Clicked":[{"id":2,"name":"Exec"}]}}],"input/simple":[{"id":5,"x":38,"y":281,"options":{"value":"saved input"},"outputs":{"Changed":[{"id":1,"name":"Re-seed"}],"Value":[{"id":3,"name":"Any"}]}}]}');


// Time to run something :)
$button = $instance->getNodes('button/simple')[0];

echo "\n>> I'm clicking the button";
($button->clicked)();

$logger = $instance->getNodes('display/logger')[0];
echo "\n>> I got the output value:", ($logger->log)();

echo "\n>> I'm writing something to the input box";
$input = $instance->getNodes('input/simple')[0];
$input->options['value']('hello wrold');

$logger = $instance->getNodes('display/logger')[0];
echo "\n>> I got the output value:", ($logger->log)();