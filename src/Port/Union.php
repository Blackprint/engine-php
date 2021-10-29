<?php
namespace Blackprint\Port;

const Union = 5;
function Union($type, $func = null){
	if($func === null)
		return [
			'feature'=>Union,
			'type'=>null,
			'func'=>&$type
		];

	return [
		'feature'=>Union,
		'type'=>&$type,
		'func'=>&$func
	];
}