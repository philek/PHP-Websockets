<?php

namespace Gpws\Core;

abstract class Socket implements \Gpws\Interfaces\Socket {
	protected $_socket;

	public function __construct($socket) {
		$this->_socket = $socket;
	}

	public function getId() : int {
		return (int)$this->_socket;
	}

	public function getHandle() {
		return $this->_socket;
	}

	private $_onStateChanged = array();
	public function registerOnStateChanged(callable $callback) {
		$this->_onStateChanged[] = $callback;
	}

	protected function onStateChanged() {
		foreach ($this->_onStateChanged AS $callback) {
			call_user_func($callback, $this);
		}
	}
}
