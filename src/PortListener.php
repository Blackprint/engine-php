<?php
namespace Blackprint;

const PortListener = 2;
function PortListener($type, $func = null){
	if($func === null)
		return [
			'feature'=>PortListener,
			'type'=>null,
			'func'=>&$type
		];

	return [
		'feature'=>PortListener,
		'type'=>&$type,
		'func'=>&$func
	];
}