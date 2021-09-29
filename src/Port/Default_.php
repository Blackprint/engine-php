<?php
namespace Blackprint\Port;

const Default_ = 1;
function Default_($type, $val){
	return [
		'feature'=>Default_,
		'type'=>&$type,
		'value'=>&$val
	];
}