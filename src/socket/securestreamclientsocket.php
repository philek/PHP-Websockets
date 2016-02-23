<?php

namespace Gpws\Socket;

class SecureStreamClientSocket extends ClientSocket {

	public function __construct($socket) {
		stream_set_blocking($socket, true);
 printf('ENABLE CRYPTO'.PHP_EOL);	
 $x =	stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
 var_dump($x);
// stream_set_blocking($socket, false);
		parent::__construct($socket);
	}



	protected function _rawRead(&$buffer, $maxBytes = NULL) : int {
		$buffer = fread($this->_socket, $maxBytes ?? MAX_BUFFER_SIZE);
		$numBytes = strlen($buffer);

		return $numBytes;
	}

	protected function _rawWrite(string $buffer) : int {
		return fwrite($this->_socket, $buffer) ?? 0;
	}

	protected function _rawClose() {
		fclose($this->_socket);
	}

}
