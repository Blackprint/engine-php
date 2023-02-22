<?php
namespace App;
require_once('../vendor/autoload.php');

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
$instance->importJSON('{"instance":{"BP/Event/Listen":[{"i":0,"x":454,"y":177,"z":1,"data":{"namespace":"TestEvent"},"input_d":{"Limit":0},"output":{"test":[{"i":4,"name":"Any"}],"other":[{"i":4,"name":"Any"}]}}],"BP/Event/Emit":[{"i":1,"x":316,"y":177,"z":0,"data":{"namespace":"TestEvent"}}],"Example/Button/Simple":[{"i":2,"x":42,"y":143,"z":3,"id":"myButton","output":{"Clicked":[{"i":1,"name":"Emit"}]}}],"Example/Input/Simple":[{"i":3,"x":43,"y":285,"z":4,"id":"myInput","data":{"value":"123"},"output":{"Value":[{"i":1,"name":"other"}]}}],"Example/Display/Logger":[{"i":4,"x":713,"y":184,"z":2,"id":"myLogger","input":{"Any":[{"i":0,"name":"test"},{"i":0,"name":"other"}]}}]},"moduleJS":["http://localhost:6789/dist/nodes-example.mjs"],"events":{"TestEvent":{"schema":["test","other"]}}}');

// Let's to run something
$button = $instance->iface['myButton'];

echo "\n\n>> I'm clicking the button";
$button->clicked();

$logger = $instance->iface['myLogger'];
echo "\n\n>> I got the output value: ".$logger->log();

echo "\n\n>> I'm writing something to the input box";
$input = $instance->iface['myInput'];
$input->data->value = 'hello wrold';

echo "\n\n>> I'm clicking the button";
$button->clicked();

// you can also use getNodes if you haven't set the ID
$logger = $instance->getNodes('Example/Display/Logger')[0]->iface;
echo "\n\n>> I got the output value: ".$logger->log();

echo "\n\n>> I'm emitting event";
$data = ['test' => 123, 'other' => 234];
$instance->events->emit("TestEvent", $data);
echo "\n\n>> I got the output value: ".$logger->log();