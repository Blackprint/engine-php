<?php
namespace Blackprint;

enum PortType {
	case ArrayOf;
	case Default;
	case Trigger;
	case Union;
}

class Port {
	/* This port can contain multiple cable as input
	 * and the value will be array of 'type'
	 * it's only one type, not union
	 * for union port, please split it to different port to handle it
	 */
	static function ArrayOf($type){
		return [
			'feature'=>PortType::ArrayOf,
			'type'=>&$type
		];
	}

	static function ArrayOf_validate(&$type, &$target){
		if($type === Types::Any || $target === Types::Any || $type === $target)
			return true;

		if(is_array($type) && in_array($target, $type))
			return true;

		return false;
	}

	/* This port can have default value if no cable was connected
	 * type = Type Data that allowed for the Port
	 * value = default value for the port
	 */
	static function Default($type, $val){
		return [
			'feature'=>PortType::Default,
			'type'=>&$type,
			'value'=>&$val
		];
	}

	/* This port will be used as a trigger or callable input port
	 * func = callback when the port was being called as a function
	 */
	static function Trigger($func){
		return [
			'feature'=>PortType::Trigger,
			'func'=>&$func
		];
	}

	/* This port can allow multiple different types
	 * like an 'any' port, but can only contain one value
	 */
	static function Union($types){
		return [
			'feature'=>PortType::Union,
			'type'=>&$types
		];
	}

	static function Union_validate(&$types, &$target){
		if(is_array($types) && is_array($target)){
			if(count($types) !== count($target)) return false;
	
			foreach ($types as &$type) {
				if(!in_array($type, $target))
					return false;
			}
	
			return true;
		}
	
		return $target === Types::Any || in_array($target, $types);
	}
}