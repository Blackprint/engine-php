<?php
namespace Blackprint\Port;

const ArrayOf = 1;
function ArrayOf($type, $func = null){
	if($func === null)
		return [
			'feature'=>ArrayOf,
			'type'=>null,
			'func'=>&$type
		];

	return [
		'feature'=>ArrayOf,
		'type'=>&$type,
		'func'=>&$func
	];
}