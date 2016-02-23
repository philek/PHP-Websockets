<?php

namespace Gpws\Socket;

abstract class ListenSocket extends Socket {
	private $_onAccept = NULL;

	abstract protected function _rawCreate(string $addr, int $port);
	abstract protected function _rawAccept();

	public function __construct(string $addr, int $port) {
		parent::__construct($this->_rawCreate($addr, $port));
	}

	public function doRead() {
		$this->raise('onConnectionReady', $this);
	}

	public function doWrite() {
		// Never write.
	}

	public function getState() : int {
		return self::SOCKET_READ;
	}


	public function getWaitingConnection() {
socket::$opencounter++; //DEBUGONLY

		return $this->_rawAccept();
	}

}
