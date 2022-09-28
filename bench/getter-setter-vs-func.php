<?php
// Middle 0.00023293495178223ms
class Data1 {
	private $data = 0;
	function &data($val=null){
		if($val !== null) $this->data = &$val;
		return $this->data;
	}
}
$Data1 = new Data1();

// This was fastest 0.00016498565673828ms
class Data2 {
	private $data = 0;
	function &data(){ return $this->data; }
	function setData($val){ $this->data = &$val; }
}
$Data2 = new Data2();

// This was slowest than Data1 or Data2 0.0004429817199707ms
class Data3 {
	private $data = ['data'=>0];
	function &__get($prop){ return $this->data[$prop]; }
	function __set($prop, $val){ $this->data[$prop] = &$val; }
}
$Data3 = new Data3();

$time = microtime(true);
for ($i=0; $i < 1000; $i++) { 
	$Data1->data($i);
	$temp = $Data1->data();
}
$time = microtime(true) - $time;
echo "Data1 -> $time\n";

$time = microtime(true);
for ($i=0; $i < 1000; $i++) { 
	$Data2->setData($i);
	$temp = $Data2->data();
}
$time = microtime(true) - $time;
echo "Data2 -> $time\n";

$time = microtime(true);
for ($i=0; $i < 1000; $i++) { 
	$Data3->data = $i;
	$temp = $Data3->data;
}
$time = microtime(true) - $time;
echo "Data3 -> $time\n";