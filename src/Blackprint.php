<?php
namespace Blackprint;
class Blackprint {
	public static $nodes = [];
	public static function registerNode($namespace, $func){
		\Blackprint\Utils::deepProperty(Blackprint::$nodes, explode('/', $namespace), $func);
	}

	public static $interface = [];
	public static function registerInterface($ifaceType, $options=null, $func=null){
		if($func === null)
			$func = &$options;

		Blackprint::$interface[$ifaceType] = $func;
	}
}

Blackprint::$interface['default'] = &\Blackprint\Utils::$NoOperation;