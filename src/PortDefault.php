<?php
namespace Blackprint;

const PortDefault = 2;
function PortDefault($type, $val){
	return [
		'feature'=>2,
		'type'=>&$type,
		'value'=>&$val
	];
}