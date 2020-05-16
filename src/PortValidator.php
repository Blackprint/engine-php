<?php
namespace Blackprint;

const PortValidator = 3;
function PortValidator($type, $func = null){
	if($func === null)
		return [
			'feature'=>PortValidator,
			'type'=>&$type
		];

	return [
		'feature'=>PortValidator,
		'type'=>&$type,
		'func'=>&$func
	];
}