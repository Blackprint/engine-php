<?php
namespace Blackprint;
class Internal {
	public static $nodes = [];
	public static $interface = [];
	public static $namespace = [];

	public static function _loadNamespace($path){
		$namespace = &Internal::$namespace;

		if(preg_match('/[<>:"\'|?*\\\\]/', $path, $match))
			throw new \Exception("Illegal character detected [$match[0]] when importing nodes!");

		foreach ($namespace as &$value) {
			$temp = "$value/$path.php";

			if(file_exists($temp)){
				include_once $temp;
				$temp = str_replace('/', '\\', "BPNode/$path");
				\Blackprint\Utils::deepProperty(Internal::$nodes, explode('/', $path), $temp);
				return;
			}
		}
	}
}

function registerNode($namespace, $claz){
	\Blackprint\Utils::deepProperty(Internal::$nodes, explode('/', $namespace), $claz);
}

function registerInterface($templatePath, $claz){
	$templatePath = str_replace('\\', '/', $templatePath);

	if(!str_starts_with($templatePath, 'BPIC/'))
		throw new \Exception("$templatePath: The first parameter of 'registerInterface' must be started with BPIC to avoid name conflict. Please name the interface similar with 'templatePrefix' for your module that you have set on 'blackprint.config.js'.", 1);

	if(gettype($claz) !== 'string' || !class_exists($claz))
		throw new \Exception("$templatePath: The second parameter for ->registerInterface must be class", 1);

	Internal::$interface[$templatePath] = &$claz;
}

function registerNamespace($nodeDirectory){
	if(isset(Internal::$namespace[$nodeDirectory])) return;
	Internal::$namespace[] = &$nodeDirectory;
}

Internal::$interface['BP/default'] = \Blackprint\Interfaces::class;

// Below is for internal only
class EvIface {
	function __construct(
		public &$iface
	){}
}
class EvPort {
	function __construct(
		public &$port
	){}
}
class EvEnv {
	function __construct(
		public &$key,
		public &$value=null,
	){}
}
class EvVariableNew {
	function __construct(
		public $scope,
		public &$id,
	){}
}
class EvPortValue {
	function __construct(
		public &$port,
		public &$target,
		public &$cable,
	){}
}
class EvPortSelf {
	function __construct(
		public &$port
	){}
}