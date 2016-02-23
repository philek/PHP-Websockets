<?php

namespace Gpws\Core;

trait EventEmitter {
	private $_events = [];

	protected function raise($event) {
		if (!isset($this->_events[$event]) || !$this->_events[$event]) return false;

		$args = func_get_args();
		array_shift($args);
		array_unshift($args, $this);

		foreach ($this->_events[$event] as $handler) {
			call_user_func_array($handler, $args);
		}

		return true;
	}


	protected function raise_array($event, $args) {
		if (!isset($this->_events[$event]) || !$this->_events[$event]) return false;

		array_unshift($args, $this);

		foreach ($this->_events[$event] as $handler) {
			call_user_func_array($handler, $args);
		}

		return true;
	}


	public function addListener(string $event, callable $handler) {
		if (!isset($this->_events[$event])) {
			$this->_events[$event] = [];
		}

		$this->_events[$event][] = $handler;

		return $this;
	}

	public function removeListener(string $event, callable $handler) {
		if (isset($this->_events[$event])) {
			$key = array_search($handler, $this->_events[$event]);
			if ($key !== false) {
				array_splice($this->_events[$event], $key, 1);
			}
		}

		return $this;
	}

	public function clearListeners() {
		$this->_events = array();
	}

	public function hasListeners() {
		$c = 0; foreach ($this->_events AS $ev=>$list) $c += count($list);
		return $c;
	}
}