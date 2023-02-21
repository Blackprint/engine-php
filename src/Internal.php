<?php
namespace Blackprint;
class Internal {
	public static $nodes = [];
	public static $interface = [];
	public static $namespace = [];
	public static $events = [];

	public static function _loadNamespace($path){
		$namespace = &Internal::$namespace;

		if(preg_match('/[<>:"\'|?*\\\\]/', $path, $match) || str_contains($path, ".."))
			throw new \Exception("Illegal character detected [$match[0]] when importing nodes!");

		foreach ($namespace as &$value) {
			$temp = "$value/$path.php";

			if(file_exists($temp)){
				include_once $temp;
				$temp = str_replace('/', '\\', "BPNode/$path");
				\Blackprint\Utils::setDeepProperty(Internal::$nodes, explode('/', $path), $temp);
				return;
			}
		}
	}
}

function registerNode($namespace, $claz){
	$namespace = str_replace('\\', '/', $namespace);

	if(gettype($claz) !== 'string' || !class_exists($claz))
		throw new \Exception("$namespace: The second parameter for ->registerNode must be class that already been defined before this registration", 1);

	\Blackprint\Utils::setDeepProperty(Internal::$nodes, explode('/', $namespace), $claz);
}

function registerInterface($templatePath, $claz){
	$templatePath = str_replace('\\', '/', $templatePath);

	if(!str_starts_with($templatePath, 'BPIC/'))
		throw new \Exception("$templatePath: The first parameter of 'registerInterface' must be started with BPIC to avoid name conflict. Please name the interface similar with 'templatePrefix' for your module that you have set on 'blackprint.config.js'.", 1);

	if(gettype($claz) !== 'string' || !class_exists($claz))
		throw new \Exception("$templatePath: The second parameter for ->registerInterface must be class that already been defined before this registration", 1);

	Internal::$interface[$templatePath] = &$claz;
}

function registerNamespace($nodeDirectory){
	if(isset(Internal::$namespace[$nodeDirectory])) return;
	Internal::$namespace[] = &$nodeDirectory;
}

function registerEvent($namespace, $options){
	if(preg_match('/\s/', $namespace) !== 0)
		throw new \Exception("Namespace can't have space character: '$namespace'");

	$schema = &$options['schema'];
	if($schema == null)
		throw new \Exception("Registering an event must have a schema. If the event doesn't have a schema or dynamically created from an instance you may not need to do this registration.");

	foreach ($schema as $key => &$obj) {
		// Must be a data type
		// or type from Blackprint.Port.{Feature}
		if(!class_exists($obj) && (is_array($obj) && $obj['feature'] == null) && !isTypeExist($obj)){
			throw new \Exception("Unsupported schema type for field '$key' in '$namespace'");
		}
	}

	Internal::$events[$namespace] = new Constructor\InstanceEvent($options);
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
		public &$instance,
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