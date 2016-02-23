<?php

namespace Gpws\Socket;

abstract class Socket implements \Gpws\Interfaces\Socket, \Gpws\Interfaces\EventEmitter {
	use \Gpws\Core\EventEmitter;

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

public static $opencounter = 0; // DEBUGONLY
}
