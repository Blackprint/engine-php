<?php
namespace Blackprint\Port;

const Validator = 6;
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