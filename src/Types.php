<?php
namespace Blackprint;

enum Types {
	// Port will accept any data type
	case Any;
	case Function;
	case Number;
	case Array;
	case String;
	case Boolean;
	case Object;

	/**
	 * [Experimental] May get deleted/changed anytime
	 * Port's type can be assigned and validated later
	 * This port will accept any port for initial connection
	 * Currently only for output port
	 */
	case Slot;

	// Can only be applicable for output port's type
	case Route;
}

function getTypeName(&$type){
	return $type->name;

	// return match($type){
	// 	Types::Any => 'Any',
	// 	Types::Function => 'Function',
	// 	Types::Number => 'Number',
	// 	Types::Array => 'Array',
	// 	Types::String => 'String',
	// 	Types::Boolean => 'Boolean',
	// 	Types::Object => 'Object',
	// 	Types::Route => 'Route',
	// };
}

class _Types{
	static $typeList;
}
_Types::$typeList = Types::cases();

function isTypeExist(&$type){
	return in_array($type, _Types::$typeList);
}