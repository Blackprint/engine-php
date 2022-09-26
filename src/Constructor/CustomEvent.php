<?php
namespace Blackprint\Constructor;

class CustomEvent {
	private $events = [];
	private $once = [];

	public function on($eventName, $func, $once = false){
		if(str_contains($eventName, ' ')){
			$eventName = explode(' ', $eventName);

			foreach ($eventName as &$val)
				$this->on($val, $func, $once);

			return;
		}

		if($once === false)
			$events = &$this->events;
		else
			$events = &$this->once;

		if(isset($events[$eventName]) === false)
			$events[$eventName] = [];

		$events[$eventName][] = &$func;
	}

	public function once($eventName, $func){
		$this->on($eventName, $func, true);
	}

	public function off($eventName, $func = null){
		if(str_contains($eventName, ' ')){
			$eventName = explode(' ', $eventName);

			foreach ($eventName as &$val)
				$this->off($val, $func);

			return;
		}

		if($func === null){
			unset($this->events[$eventName]);
			unset($this->once[$eventName]);
			return;
		}

		if(!isset($this->events[$eventName])) return;

		$i = array_search($this->events[$eventName], $func);
		if($i !== false)
			array_splice($this->events[$eventName], $i, 1);

		$i = array_search($this->once[$eventName], $func);
		if($i !== false)
			array_splice($this->once[$eventName], $i, 1);
	}

	public function emit($eventName, &$data=null){
		$events = &$this->events;
		$once = &$this->once;

		if(isset($events[$eventName])){
			$evs = &$events[$eventName];
			foreach ($evs as &$val)
				$val($data);
		}

		if(isset($once[$eventName])){
			$evs = &$once[$eventName];
			foreach ($evs as &$val)
				$val($data);

			unset($once[$eventName]);
		}
	}
}