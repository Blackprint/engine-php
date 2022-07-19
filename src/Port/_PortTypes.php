<?php
namespace Blackprint;

enum PortType {
	case ArrayOf;
	case Default;
	case Trigger;
	case Union;
	case StructOf;
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

	/* This port can allow multiple different types
	 * like an 'any' port, but can only contain one value
	 */
	static function StructOf($type, $struct){
		return [
			'feature'=>PortType::StructOf,
			'type'=>&$type,
			'value'=>&$struct
		];
	}
	
	static function StructOf_split(&$port){
		if($port->source === 'input')
			throw new \Exception("Port with feature 'StructOf' only supported for output port");

		$node = &$port->iface->node;
		$struct = &$port->struct;
		$port->structList ??= array_keys($port->struct);

		foreach ($struct as $key => &$val) {
			$val->_name ??= $port->name.$key;

			$newPort = $node->createPort('output', $val->_name, $val->type);
			$newPort->_parent = &$port;
			$newPort->_structSplitted = true;
		}

		$port->splitted = true;
		$port->disconnectAll();

		$data = &$node->output[$port->name];
		if($data != null) Port::StructOf_handle($port, $data);
	}

	static function StructOf_unsplit(&$port){
		$parent = &$port->_parent;
		if($parent === null && $port->struct !== null)
			$parent = $port;

		$parent->splitted = false;

		$struct = &$parent->struct;
		$node = &$port->iface->node;

		foreach ($struct as &$val) {
			$node->deletePort('output', $val->_name);
		}
	}

	static function StructOf_handle(&$port, &$data){
		$struct = &$port->struct;
		$output = &$port->iface->node;

		$structList = &$port->structList;
		foreach ($structList as &$val) {
			$ref = &$struct[$val];

			if($ref->field != null)
				$output[$ref->_name] = &$data[$ref->field];
			else
				$output[$ref->_name] = $ref->handle($data);
		}
	}
}