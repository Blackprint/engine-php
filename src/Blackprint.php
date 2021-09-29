<?php
namespace Blackprint;
class Blackprint {
	public static $nodes = [];
	public static function registerNode($namespace, $func){
		\Blackprint\Utils::deepProperty(Blackprint::$nodes, explode('/', $namespace), $func);
	}

	public static $interface = [];
	public static function registerInterface($templatePath, $options=null, $func=null){
		if(strpos($templatePath, 'BPIC/') !== 0)
			throw new Exception("The first parameter of 'registerInterface' must be started with BPIC to avoid name conflict. Please name the interface similar with 'templatePrefix' for your module that you have set on 'blackprint.config.js'.", 1);

		if($func === null)
			$func = &$options;

		Blackprint::$interface[$templatePath] = &$func;
	}
}

Blackprint::$interface['BP/default'] = &\Blackprint\Utils::$NoOperation;