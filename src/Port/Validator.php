<?php
namespace Blackprint\Port;

const Validator = 3;
function Validator($type, $func = null){
	if($func === null)
		return [
			'feature'=>Validator,
			'type'=>&$type
		];

	return [
		'feature'=>Validator,
		'type'=>&$type,
		'func'=>&$func
	];
}