<?php
namespace Blackprint\Types;

const Functions = '>?><0';
function Functions(){}

const Numbers = '>?><1';
function Numbers($val){
	if(!is_numeric($val))
		throw new \Exception("Can't validate number");

	return $val+0;
}

const Arrays = '>?><2';
function &Arrays($val){
	if(is_array($val) === false)
		$val = [$val];

	return $val;
}

const Strings = '>?><3';
function Strings($val){
	return $val.'';
}

const Booleans = '>?><4';
function Boolean($val){
	return $val.'';
}