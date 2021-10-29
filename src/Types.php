<?php
namespace Blackprint;

enum Types {
	case Function;
	case Number;
	case Array;
	case String;
	case Boolean;
}

function getTypeName(&$type){
	return match($type){
		Types::Function => 'Function',
		Types::Number => 'Number',
		Types::Array => 'Array',
		Types::String => 'String',
		Types::Boolean => 'Boolean',
	};
}