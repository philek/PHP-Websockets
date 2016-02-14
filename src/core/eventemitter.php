<?php

namespace Gpws\Core;

trait EventEmitter {
	private $_events = [];

	protected function raise($event) {
		if (!isset($this->_events[$event]) || !$this->_events[$event]) return false;

		$args = func_get_args();
		array_shift($args);

		foreach ($this->_events[$event] as $handler) {
			call_user_func_array($handler, $args);
		}

		return true;
	}

	public function addListener($event, callable $handler) {
		if (!isset($this->_events[$event])) {
			$this->_events[$event] = [];
		}

		$this->_events[$event][] = $handler;

		return $this;
	}

	public function removeListener($event, callable $handler) {
		if (isset($this->_events[$event])) {
			$key = array_search($handler, $this->_events[$event]);
			if ($key !== false) {
				array_splice($this->_events[$event], $key, 1);
			}
		}

		return $this;
	}
}