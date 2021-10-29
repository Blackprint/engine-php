<?php
namespace Blackprint\Port;

const Trigger = 4;
function Trigger($type, $func = null){
	return $func === null ? $type : $func;

	if($func === null)
		return [
			'feature'=>Trigger,
			'type'=>null,
			'func'=>&$type
		];

	return [
		'feature'=>Trigger,
		'type'=>&$type,
		'func'=>&$func
	];
}