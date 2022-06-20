<?php
namespace Blackprint;

class Utils{
	public static $NoOperation;
	private static $null = null;

	public static function &deepProperty(&$obj, $path, &$value = null){
		if($value !== null){
			$last = array_pop($path);
			foreach ($path as &$key) {
				if(isset($obj[$key]) === false)
					$obj[$key] = [];

				$obj = &$obj[$key];
			}

			$obj[$last] = &$value;
			return self::$null;
		}

		foreach ($path as &$key) {
			$obj = &$obj[$key];

			if($obj === null)
				return $obj;
		}

		return $obj;
	}

	public static function determinePortType($val, $that){
		if($val === null)
			throw new \Exception("Port type can't be null, error when processing: {$that->_iface->title}, {$that->_which} port");
	
		$type = $val;
		$def = null;
		if(is_array($val) && !isset($val['feature']))
		debug_print_backtrace();
		$feature = is_array($val) ? $val['feature'] : false;
	
		if($feature === \Blackprint\PortType::Trigger){
			$def = &$val['func'];
			$type = Types::Function;
		}
		elseif($feature === \Blackprint\PortType::ArrayOf){
			$type = &$val['type'];

			if($type === Types::Any)
				$def = null;
			else $def = [];
		}
		elseif($feature === \Blackprint\PortType::Union)
			$type = &$val['type'];
		elseif($feature === \Blackprint\PortType::Default){
			$type = &$val['type'];
			$def = &$val['value'];
		}
		// Give default value for each primitive type
		elseif($type === Types::Number)
			$def = 0;
		elseif($type === Types::Boolean)
			$def = false;
		elseif($type === Types::String)
			$def = '';
		elseif($type === Types::Array)
			$def = [];
		elseif($type === Types::Any) 0; // Any
		elseif($type === Types::Function) 0;
		elseif($type === Types::Route) 0;
		elseif($feature === false)
			throw new \Exception("Port for initialization must be a types", 1);
		// else{
		// 	$def = $port;
		// 	$type = Types::String;
		// }
	
		return [ &$type, &$def, &$feature ];
	}
}

Utils::$NoOperation = function(){};