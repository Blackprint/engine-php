<?php
namespace Blackprint\Port;

const Switch_ = 3;
function Switch_($type, $func = null){
	if($func === null)
		return [
			'feature'=>Switch_,
			'type'=>null,
			'func'=>&$type
		];

	return [
		'feature'=>Switch_,
		'type'=>&$type,
		'func'=>&$func
	];
}