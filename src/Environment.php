<?php
namespace Blackprint;

class Environment {
	public static $_noEvent = false;
	public static $map = [];
	public static $_rules = [];

	// arr = ["KEY": "value"]
	public static function import($arr){
		Environment::$_noEvent = true;
		foreach ($arr as $key => &$value) {
			Environment::set($key, $value);
		}

		Environment::$_noEvent = false;
		Event->emit('environment.imported');
	}

	public static function set($key, string $val){
		if(preg_match("/[^A-Z_][^A-Z0-9_]/", $key) !== 0)
			throw new \Exception("Environment must be uppercase and not contain any symbol except underscore, and not started by a number. But got: '$key'");

		$map = &Environment::$map;
		$map[$key] = &$val;

		if(!Environment::$_noEvent){
			$temp = new EvEnv($key, $val);
			Event->emit('environment.added', $temp);
		}
	}

	public static function delete($key){
		$map = &Environment::$map;
		unset($map[$key]);

		$temp = new EvEnv($key);
		Event->emit('environment.deleted', $temp);
	}

	/**
	 * options = {allowGet: {}, allowSet: {}}
	 */
	public static function rule($name, $options){
		if(Environment::$map[$name] == null)
			throw new \Exception("'$name' was not found on Blackprint\Environment, maybe it haven't been added or imported");

		if(Environment::$_rules[$name] != null)
			throw new \Exception("'rule' only allow first registration");

		if($options === null)
			throw new \Exception("Second parameter is required");

		Environment::$_rules[$name] = $options;
	}
}