<?php

namespace Gpws\Socket;

class BasicClientSocket extends ClientSocket {
	protected function _rawRead(&$buffer, $maxBytes = NULL) : int {

		$numBytes = socket_recv($this->_socket, $buffer, $maxBytes, 0);

		if ($numBytes === false) {
printf('[Network] Read Socket error: ' . socket_strerror(socket_last_error($this->_socket)));

			$this->raise('onError');

			return 0;
		}

		return $numBytes;

	}

	protected function _rawWrite(string $buffer) : int {
		$numBytes = socket_write($this->_socket, $buffer);

		if ($numBytes === false || $numBytes === NULL) {
printf('[Network] Write Socket error: ' . socket_strerror(socket_last_error($this->_socket)));

			$this->raise('onError');

			return 0;
		}

		return $numBytes;
	}

	protected function _rawClose() {
		socket_close($this->_socket);
	}

}


