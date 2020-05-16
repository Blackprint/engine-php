<?php
namespace Blackprint;

const PortDefault = 1;
function PortDefault($type, $val){
	return [
		'feature'=>PortDefault,
		'type'=>&$type,
		'value'=>&$val
	];
}