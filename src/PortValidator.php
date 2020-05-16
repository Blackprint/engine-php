<?php
namespace Blackprint;

const PortValidator = 1;
function PortValidator($type, $func = null){
	if($func === null)
		return [
			'feature'=>1,
			'type'=>&$type
		];

	return [
		'feature'=>1,
		'type'=>&$type,
		'func'=>&$func
	];
}