<?php
namespace Blackprint;
$_noEvent = false;

class Environment {
	public static $map = [];

	// arr = ["KEY": "value"]
	public static function import($arr){
		$_noEvent = true;
		foreach ($arr as $key => &$value) {
			Environment::set($key, $value);
		}

		$_noEvent = false;
		// Blackprint.emit('environment-imported');
	}

	public static function set($key, string $val){
		if(preg_match("/[^A-Z_][^A-Z0-9_]/", $key) !== false)
			throw new \Exception("Environment must be uppercase and not contain any symbol except underscore, and not started by a number. But got: $key");

		$map = &Environment::$map;
		$map[$key] = &$val;

		// if(!$_noEvent)
		// 	Blackprint.emit('environment-added', temp);
	}

	public static function delete($key){
		$map = &Environment::$map;
		unset($map[$key]);

		// Blackprint.emit('environment-deleted', {key});
	}
}