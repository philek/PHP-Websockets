<?php

namespace Gpws\Core;

define('MAX_BUFFER_SIZE', 64 * 1024);

class ClientSocket extends \Gpws\Core\Socket {
	private $_open = true;

	private $_read_buffer = '';
	private $_write_buffer = '';

	public function read() {
		$buffer = '';
		$numBytes = socket_recv($this->_socket, $buffer, MAX_BUFFER_SIZE - strlen($this->_read_buffer), 0); 
		if ($numBytes === false) {
			trigger_error('Socket error: ' . socket_strerror(socket_last_error($this->_socket)));
			return;
		}

		if ($numBytes == 0) {
			$this->onClose();

			trigger_error("Client disconnected. TCP connection lost: " . $this->_socket);
			return;
		}

		$this->_read_buffer .= $buffer;

		$this->parseBuffer();
	}

	private function parseBuffer() {
		if ($this->_handshakeComplete) {
			$this->parseFrame();
		} else {
			$this->parseHandshake();
		}
	}

	private function parseFrame() {

	}

	private function parseHandshake() {
		if (strpos($this->_read_buffer, "\r\n\r\n") === FALSE) {
			if (strlen($this->_read_buffer >= MAX_HANDSHAKE_SIZE) {
				$handshakeResponse = "HTTP/1.1 413 Request Entity Too Large"; 
				$this->ws_write($user,$handshakeResponse);
				$this->disconnect($user);
			}
			return;
		}

		$magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
		$headers = array();
		$lines = explode("\r\n",$buffer);
		//protocol and extensions can be sent on multiple line.
		//$headers['sec-websocket-protocol']='';
		//$headers['sec-websocket-extensions']='';
		foreach ($lines as $line) {
			if (strpos($line,":") !== false) {
				$header = explode(":",$line,2);
				switch ($header) {
					case 'sec-websocket-protocol':
						$headers[strtolower(trim($header[0]))] .= trim($header[1]).', ';
						break;
					case 'sec-websocket-extensions':
						$headers[strtolower(trim($header[0]))] .= trim($header[1]).'; ';
						break;
					default:
						$headers[strtolower(trim($header[0]))] = trim($header[1]);
						break;
				}
			} else if (stripos($line,"get ") !== false) {
				preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
				$headers['get'] = trim($reqResource[1]);
			}
		}

		if (isset($headers['get'])) {
			$user->requestedResource = $headers['get'];
		} else {
			// todo: fail the connection
			$handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";     
		}

		if (!isset($headers['host']) || !$this->checkHost($headers['host'])) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		}
		if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket') {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		} 
		if (!isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		}
		if (!isset($headers['sec-websocket-key'])) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		} 
		if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
			$handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
		}
		if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
			$handshakeResponse = "HTTP/1.1 403 Forbidden";
		}
		
		// Protocol work on message level. So you can enforce it
		$protocol = $this->checkProtocol(explode(', ',$headers['sec-websocket-protocol']));
		if (($this->headerProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerProtocolRequired && !$protocol)) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		} else if ($protocol){
			$user->headers["protocol"] = $protocol;
			$subProtocol = "Sec-WebSocket-Protocol: ".$protocol."\r\n";
		}
		
		// Done verifying the _required_ headers and optionally required headers.

		if (isset($handshakeResponse)) {
			$this->ws_write($user,$handshakeResponse);
			$this->disconnect($user);

			return;
		}

		// Keeping only relevant token that can be of use after handshake
		// protocol get host extensions
		$user->headers["get"] = $headers["get"];
		$user->headers["host"] = $headers["host"];

		// Not all browser support same extensions.
		// extensions work on frame level
		$extensionslist = $this->checkExtensions(explode('; ',$headers['sec-websocket-extensions']));

		if ($this->willSupportExtensions && !$extensions) {
			$user->headers["extensions"] = $extensionslist;
			$extensions = "Sec-WebSocket-Extensions: ".$extensionslist."\r\n";
		}

		$user->handshaked = TRUE;

		$webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);

		$rawToken = "";
		for ($i = 0; $i < 20; $i++) {
			$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
		}
		$handshakeToken = base64_encode($rawToken) . "\r\n";

		$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
		$this->ws_write($user,$handshakeResponse);

		$this->dispatchEvent('onOpen', array('user' => $user));     	  	
	}


	public function write() {
		$numBytes = socket_write($this->_socket, $this->_write_buffer);

		if ($numBytes > 0) {
			$this->_write_buffer = substr($this->_write_buffer, $numBytes);
		}

		if (!$this->_write_buffer) {
			call_user_func($this->onWriteComplete);
		}

		if (!$this->_write_buffer) {
			call_user_func($this->onStateChanged);
		}
	}

	public function getState() : int {
		return ($this->_open ? self::SOCKET_READ : 0) | ($this->_write_buffer ? self::SOCKET_WRITE : 0);
	}

	public function send($buffer) {
		$buffer_empty = !$this->_write_buffer;

		$this->_write_buffer .= $buffer;

		if ($buffer_empty) {
			call_user_func($this->onStateChanged);
		}
	}

}
