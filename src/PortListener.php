<?php
namespace Blackprint;

const PortListener = 0;
function PortListener($type, $func = null){
	if($func === null)
		return [
			'feature'=>0,
			'type'=>null,
			'func'=>&$type
		];

	return [
		'feature'=>0,
		'type'=>&$type,
		'func'=>&$func
	];
}