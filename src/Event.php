<?php
namespace Blackprint;

const _Event = new Constructor\CustomEvent();
class Event {
	public static function on($eventName, $func, $once = false){
		_Event->on($eventName, $func, $once);
	}
	public static function once($eventName, $func){
		_Event->once($eventName, $func);
	}
	public static function waitOnce($eventName){
		_Event->waitOnce($eventName);
	}
	public static function off($eventName, $func = null){
		_Event->off($eventName, $func);
	}
	public static function emit($eventName, &$data=null){
		_Event->emit($eventName, $data);
	}
}