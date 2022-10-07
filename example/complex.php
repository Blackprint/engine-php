<?php
namespace App;

require_once('../vendor/autoload.php');

function colorLog($bright, $dark=''){
	echo "\x1b[1m\x1b[33m$bright\x1b[0m \x1b[33m$dark\x1b[0m\n";
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
$instance->importJSON('{"_":{"moduleJS":[],"functions":{"Test":{"id":"Test","title":"Test","description":"No description","vars":["shared"],"privateVars":["private"],"structure":{"BP/Fn/Input":[{"i":0,"x":389,"y":100,"z":3,"output":{"A":[{"i":2,"name":"A"},{"i":15,"name":"Any"}],"Exec":[{"i":2,"name":"Exec"}],"B":[{"i":15,"name":"Any"}]}}],"BP/Fn/Output":[{"i":1,"x":973,"y":228,"z":13,"input_d":{"Result":0}}],"Example/Math/Multiply":[{"i":2,"x":656,"y":99,"z":8,"output":{"Result":[{"i":3,"name":"Val"},{"i":9,"name":"Val"}]}},{"i":10,"x":661,"y":289,"z":4,"output":{"Result":[{"i":5,"name":"Val"},{"i":1,"name":"Result1"}]}}],"BP/Var/Set":[{"i":3,"x":958,"y":142,"z":9,"data":{"name":"shared","scope":2}},{"i":5,"x":971,"y":333,"z":2,"data":{"name":"private","scope":1},"route":{"i":1}}],"BP/Var/Get":[{"i":4,"x":387,"y":461,"z":5,"data":{"name":"shared","scope":2},"output":{"Val":[{"i":8,"name":"Any"}]}},{"i":6,"x":389,"y":524,"z":0,"data":{"name":"private","scope":1},"output":{"Val":[{"i":8,"name":"Any"}]}}],"BP/FnVar/Input":[{"i":7,"x":387,"y":218,"z":7,"data":{"name":"B"},"output":{"Val":[{"i":2,"name":"B"},{"i":16,"name":"Any"}]}},{"i":11,"x":386,"y":301,"z":6,"data":{"name":"Exec"},"output":{"Val":[{"i":10,"name":"Exec"}]}},{"i":12,"x":386,"y":370,"z":10,"data":{"name":"A"},"output":{"Val":[{"i":10,"name":"A"},{"i":10,"name":"B"},{"i":16,"name":"Any"}]}}],"Example/Display/Logger":[{"i":8,"x":661,"y":474,"z":14,"id":"innerFunc","input":{"Any":[{"i":4,"name":"Val"},{"i":6,"name":"Val"}]}},{"i":15,"x":661,"y":196,"z":15,"id":"mul_inner1","input":{"Any":[{"i":0,"name":"A"},{"i":0,"name":"B"}]}},{"i":16,"x":662,"y":385,"z":16,"id":"mul_inner2","input":{"Any":[{"i":12,"name":"Val"},{"i":7,"name":"Val"}]}}],"BP/FnVar/Output":[{"i":9,"x":956,"y":69,"z":1,"data":{"name":"Result"}},{"i":14,"x":969,"y":629,"z":12,"data":{"name":"Clicked"}}],"Example/Button/Simple":[{"i":13,"x":634,"y":616,"z":11,"output":{"Clicked":[{"i":14,"name":"Val"}]}}]}}}},"Example/Math/Random":[{"i":0,"x":512,"y":76,"z":0,"output":{"Out":[{"i":5,"name":"A"},{"i":7,"name":"Any"}]},"route":{"i":7}},{"i":1,"x":512,"y":242,"z":1,"output":{"Out":[{"i":5,"name":"B"},{"i":7,"name":"Any"}]}}],"Example/Display/Logger":[{"i":2,"x":1089,"y":278,"z":5,"id":"myLogger","input":{"Any":[{"i":4,"name":"Value"},{"i":5,"name":"Result1"},{"i":5,"name":"Result"}]}},{"i":7,"x":780,"y":75,"z":3,"id":"mul_outer","input":{"Any":[{"i":0,"name":"Out"},{"i":1,"name":"Out"}]}}],"Example/Button/Simple":[{"i":3,"x":216,"y":134,"z":4,"id":"myButton","output":{"Clicked":[{"i":0,"name":"Re-seed"},{"i":5,"name":"Exec","parentId":0}]},"_cable":{"Clicked":[{"x":624,"y":170,"branch":[{"id":0}]}]}}],"Example/Input/Simple":[{"i":4,"x":238,"y":279,"z":2,"id":"myInput","data":{"value":"saved input"},"output":{"Changed":[{"i":1,"name":"Re-seed"}],"Value":[{"i":2,"name":"Any"}]}}],"BPI/F/Test":[{"i":5,"x":775,"y":205,"z":7,"output":{"Result1":[{"i":2,"name":"Any"}],"Result":[{"i":2,"name":"Any"}],"Clicked":[{"i":6,"name":"Exec"}]}}],"Example/Math/Multiply":[{"i":6,"x":1094,"y":157,"z":6,"input_d":{"A":0}}]}');

// Let's to run something
// Because PHP is synchronous, we don't need to use await like in JavaScript
$button = $instance->iface['myButton'];
echo "\n>> I'm clicking the button\n";
$button->clicked();

$logger = $instance->iface['myLogger'];
echo "\n>> I got the output value: ".$logger->log()."\n";

echo "\n>> I'm writing something to the input box\n";
$input = $instance->iface['myInput'];
$input->data->value = 'hello wrold';

// You can also use getNodes if you haven't set the ID
$logger = $instance->getNodes('Example/Display/Logger')[0]->iface;
echo "\n>> I got the output value: ".$logger->log()."\n";