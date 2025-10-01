<?php
namespace Blackprint;

class Utils{
	public static $NoOperation;
	public static $_null = null;

	// ToDo: recheck if reference &$obj will change the original data
	public static function setDeepProperty(&$obj, $path, &$value, $onCreate=null){
		$last = array_pop($path);
		foreach ($path as &$key) {
			if(isset($obj[$key]) === false)

			if(!is_string($key) && !is_numeric($key))
				throw new \Exception("Object field must be Number or String, but found: " . json_encode($key));

			if(!isset($obj[$key])) {
				$obj[$key] = [];
				if($onCreate !== null) $onCreate($obj[$key]);
			}

			$obj = &$obj[$key];
		}

		if(!is_string($last) && !is_numeric($last))
			throw new \Exception("Object field must be Number or String, but found: " . json_encode($last));

		$obj[$last] = &$value;
	}

	public static function &getDeepProperty(&$obj, $path, $reduceLen=0){
		// use foreach for more performance
		if($reduceLen === 0){
			foreach ($path as &$key) {
				$obj = &$obj[$key];
				if($obj === null)
					return $obj;
			}
		}
		else for ($i=0, $n=count($path)-$reduceLen; $i < $n; $i++) {
			$obj = &$obj[$path[$i]];
			if($obj === null)
				return $obj;
		}

		return $obj;
	}

	public static function deleteDeepProperty(&$obj, $path, $deleteEmptyParent=false){
		$lastPath = $path[count($path) - 1];
		$parents = [];

		for($i = 0, $n = count($path)-1; $i < $n; $i++){
			$parents[$i] = &$obj;
			if(($obj = &$obj[$path[$i]]) === null)
				return;
		}

		unset($obj[$lastPath]);

		if($deleteEmptyParent) for($a=count($parents)-1; $a >= 0; $a--) {
			$checkName = $path[$a];
			$check = &$parents[$a];
			foreach ($check[$checkName] as $key => $val) {
				break; // object is not empty
			}

			unset($check[$checkName]);
		}
	}

	public static function _combineArray($A, $B){
		$list = [];
		if($A != null) $list = array_merge($list, $A);
		if($B != null) $list = array_merge($list, $B);
		return $list;
	}

	public static function determinePortType($val, $that){
		if($val === null)
			throw new \Exception("Port type can't be null, error when processing: {$that->_iface->namespace}, {$that->_which} port");

		$type = $val;
		$def = null;
		$feature = is_array($val) ? $val['feature'] : false;

		if($feature === \Blackprint\PortType::Trigger){
			$def = &$val['func'];
			$type = &$val['type'];
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
		// elseif($type === Types::Trigger) 0;
		// elseif($type === Types::Any) 0; // Any
		// elseif($type === Types::Slot) 0;
		// elseif($type === Types::Route) 0;
		// elseif($feature === false){
		// 	var_dump($type);
		// 	throw new \Exception("Unrecognized port type or port feature", 1);
		// }

		return [ &$type, &$def, &$feature ];
	}

	public static function log($value, $return=FALSE){
		ob_start();
		var_dump($value);
		$dump = ob_get_clean();
		$dump = preg_replace("/=>\n[ ]+/m", '=> ', $dump);
		if ((bool)$return) return $dump; else echo $dump;
	}
}

Utils::$NoOperation = function(){};