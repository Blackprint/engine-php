<?php
namespace App;

require_once('../vendor/autoload.php');
use \Blackprint\Engine;

function colorLog($bright, $dark=''){
	echo "\n\x1b[1m\x1b[33m$bright\x1b[0m \x1b[33m$dark\x1b[0m";
}

// The nodes and interface is almost similar with the engine-js example
// When creating your own interface please use specific interface naming
// 'BPIC/LibraryName/FeatureName/NodeName'

// Because PHP support namespace system we can register all of
// our nodes in BPNode folders like this.. only used nodes that will be imported
\Blackprint\registerNamespace(__DIR__.'/BPNode');

// === Import JSON after all nodes was registered ===
// You can import the JSON to Blackprint Sketch if you want to view the nodes visually
$instance = new \Blackprint\Engine;
$instance->importJSON('{"Example/Math/Random":[{"i":0,"x":298,"y":73,"output":{"Out":[{"i":2,"name":"A"}]}},{"i":1,"x":298,"y":239,"output":{"Out":[{"i":2,"name":"B"}]}}],"Example/Math/Multiply":[{"i":2,"x":525,"y":155,"output":{"Result":[{"i":3,"name":"Any"}]}}],"Example/Display/Logger":[{"i":3,"id":"myLogger","x":763,"y":169}],"Example/Button/Simple":[{"i":4,"id":"myButton","x":41,"y":59,"output":{"Clicked":[{"i":2,"name":"Exec"}]}}],"Example/Input/Simple":[{"i":5,"id":"myInput","x":38,"y":281,"data":{"value":"saved input"},"output":{"Changed":[{"i":1,"name":"Re-seed"}],"Value":[{"i":3,"name":"Any"}]}}]}');

// Because PHP lack of getter and setter, We need to get or set like calling a function
// Anyway.. lets to run something :)
$button = $instance->iface['myButton'];

echo "\n\n>> I'm clicking the button";
$button->clicked(123);

$logger = $instance->iface['myLogger'];
echo "\n\n>> I got the output value: ".$logger->log();

echo "\n\n>> I'm writing something to the input box";
$input = $instance->iface['myInput'];
$input->data['value']('hello wrold');

// you can also use getNodes if you haven't set the ID
$logger = $instance->getNodes('Example/Display/Logger')[0]->iface;
echo "\n\n>> I got the output value: ".$logger->log();