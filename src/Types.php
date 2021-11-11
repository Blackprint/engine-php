<?php
namespace Blackprint;

enum Types {
	case Any;
	case Function;
	case Number;
	case Array;
	case String;
	case Boolean;
	case Object;
}

function getTypeName(&$type){
	return match($type){
		Types::Any => 'Any',
		Types::Function => 'Function',
		Types::Number => 'Number',
		Types::Array => 'Array',
		Types::String => 'String',
		Types::Boolean => 'Boolean',
		Types::Object => 'Object',
	};
}