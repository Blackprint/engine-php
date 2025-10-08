<?php
namespace Blackprint\Constructor;

class CustomEvent {
	private $events = [];
	private $once = [];
	public $_currentEventName = null;

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

	public function waitOnce($eventName){
		throw new \Exception("This method is not implemented yet, feel free to create improvement for this engine.");
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

		if(isset($this->events[$eventName])){
			$i = array_search($func, $this->events[$eventName]);
			if($i !== false)
				array_splice($this->events[$eventName], $i, 1);
		}
		if(isset($this->once[$eventName])){
			$i = array_search($func, $this->once[$eventName]);
			if($i !== false)
				array_splice($this->once[$eventName], $i, 1);
		}
	}

	public function emit($eventName, &$data=null){
		if($data !== null && !is_array($data) && !is_object($data))
			throw new \Exception("Event object must be an Object, but got:" . gettype($data));

		$events = &$this->events;
		$once = &$this->once;

		$this->_currentEventName = $eventName;

		if(isset($events[$eventName])){
			$evs1 = &$events[$eventName];
			foreach ($evs1 as &$val) $val($data);
		}

		if(isset($once[$eventName])){
			$evs2 = $once[$eventName];
			unset($once[$eventName]);
			foreach ($evs2 as &$val) $val($data);
		}

		$this->_currentEventName = null;
	}
}