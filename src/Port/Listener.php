<?php
namespace Blackprint\Port;

const Listener = 2;
function Listener($type, $func = null){
	if($func === null)
		return [
			'feature'=>Listener,
			'type'=>null,
			'func'=>&$type
		];

	return [
		'feature'=>Listener,
		'type'=>&$type,
		'func'=>&$func
	];
}