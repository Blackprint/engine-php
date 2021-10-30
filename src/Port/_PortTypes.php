<?php
namespace Blackprint;

class Port {
	/* This port can contain multiple cable as input
	 * and the value will be array of 'type'
	 * it's only one type, not union
	 * for union port, please split it to different port to handle it
	 */
	static function ArrayOf($type){
		return [
			'feature'=>Port::ArrayOf_,
			'type'=>&$type
		];
	} const ArrayOf_ = 1;

	/* This port can have default value if no cable was connected
	 * type = Type Data that allowed for the Port
	 * value = default value for the port
	 */
	static function Default($type, $val){
		return [
			'feature'=>Port::Default_,
			'type'=>&$type,
			'value'=>&$val
		];
	} const Default_ = 2;

	/* Allow many cable connected to a port
	 * But only the last value that will used as value
	 */
	static function Switch($type){
		return [
			'feature'=>Port::Switch_,
			'type'=>&$type
		];
	} const Switch_ = 3;

	/* This port will be used as a trigger or callable input port
	 * func = callback when the port was being called as a function
	 */
	static function Trigger($func){
		return [
			'feature'=>Port::Trigger_,
			'func'=>&$func
		];
	} const Trigger_ = 4;

	/* This port can allow multiple different types
	 * like an 'any' port, but can only contain one value
	 */
	static function Union($types){
		return [
			'feature'=>Port::Union_,
			'type'=>&$types
		];
	} const Union_ = 5;

	/* This port will allow any value to be passed to a function
	 * then you can write custom data validation in the function
	 * the value returned by your function will be used as the input value
	 */
	static function Validator($type, $func = null){
		return [
			'feature'=>Port::Validator_,
			'type'=>&$type,
			'func'=>&$func
		];
	} const Validator_ = 6;
}