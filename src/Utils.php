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
}

Utils::$NoOperation = function(){};