<?php
namespace Blackprint\Port;

const Default_ = 2;
function Default_($type, $val){
	return [
		'feature'=>Default_,
		'type'=>&$type,
		'value'=>&$val
	];
}