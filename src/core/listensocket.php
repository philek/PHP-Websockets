<?php

namespace Gpws\Core;

class ListenSocket extends \Gpws\Core\Socket {
	private $_onAccept = NULL;

	public function __construct(string $addr, int $port) {
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)  or die("Failed: socket_create()");
		socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
		socket_bind($socket, $addr, $port)                      or die("Failed: socket_bind()");
		socket_listen($socket, 1024)                             or die("Failed: socket_listen()");
		socket_set_nonblock($socket)                                        or die("Failed: socket_set_nonblock()");

		parent::__construct($socket);
	}

	public function read() {
		$this->raise('onConnectionReady', $this);
	}

	public function write() {
		// Never write.
	}

	public function getState() : int {
		return self::SOCKET_READ;
	}

	public function getWaitingConnection() {
		return socket_accept($this->_socket);
	}

}
