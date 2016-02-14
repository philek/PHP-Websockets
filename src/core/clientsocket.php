<?php

namespace Gpws\Core;

define('MAX_HANDSHAKE_SIZE', 8 * 1024);
define('MAX_BUFFER_SIZE', 64 * 1024);
define('MAGIC_GUID', "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");

class ClientSocket extends \Gpws\Core\Socket {
	private $_open = true;

	private $_handshakeComplete = false;

	private $_read_buffer = '';
	private $_read_buffer_header = NULL;

	private $_write_buffer = array();

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
		printf("Current read buffer: %s%s", $this->_read_buffer, PHP_EOL);

		if ($this->_handshakeComplete) {
			$this->parseFrame();
		} else {
			$this->parseHandshake();
		}
	}

	private function parseFrame() {
		while ($this->_read_buffer) {
			if (!$this->_read_buffer_header) {
				$frameHeader = $this->extractHeader($this->_read_buffer);
				if (!$frameHeader) {
					printf('Not enough for header.%s', PHP_EOL);
					return;
				}
			} else {
				$frameHeader = $this->_read_buffer_header;
			}

			echo ("msglen : ".$frameHeader['length']." offset : ".$frameHeader['offset']." = framesize of ".($frameHeader['offset']+$frameHeader['length']) . PHP_EOL);

			if ($frameHeader['offset'] + $frameHeader['length'] > strlen($this->_read_buffer)) {
				$this->_read_buffer_header = $frameHeader;
				printf('Frame not finished yet.');
				return;
			}

			$frame = substr($this->_read_buffer, $frameHeader['offset'], $frameHeader['length']);

			$message = $this->deFrame($frame, $frameHeader);
			if ($message) {
				call_user_func($this->onRead, $message);
			}

			$this->_read_buffer = substr($this->_read_buffer, $frameHeader['offset'] + $frameHeader['length']);
			$this->_read_buffer_header = '';
		}
	}

	private $_partialMessage = '';

	protected function deframe($payload, $headers) {
		$pongReply = false;
		$willClose = false;

		switch ($headers['opcode']) {
			case 0:
				printf("chrome fragmenting payload over 128K.%s", PHP_EOL);
			case 1:
			case 2:
				break;
			case 8:
				// todo: close the connection
				$user->hasSentClose = true;
				return "";

			case 9:
				$pongReply = true;
			case 10:
// A Pong frame MAY be sent unsolicited. This serves as a unidirectional heartbeat. A response to an unsolicited Pong frame is not expected.
// IE 11 default behavior send PONG ~30sec between them. Just ignore them.
				return false;  

			default:
				//$this->disconnect($user); // todo: fail connection
				$willClose = true;
				break;
		}

		if ($this->checkRSVBits($headers)) {
			return false;
		}

		if ($willClose) {
			// todo: fail the connection
			return false;
		}
 
		if ($pongReply) {
			$reply = $this->frame($payload, $user, 'pong');
			$this->send($user, $reply);

			return false;
		}

		//add unmask payload to partialMessage who handle continuous message allready unmasked
		$payload = $this->_partialMessage . $this->applyMask($headers, $payload);

		if ($headers['fin']) {
			$this->_partialMessage = '';

			return $payload;
		}

		$this->_partialMessage = $payload;

		return false;
	}


	protected function extractHeader($message) {
		if (strlen($message) < 2) return false;

		$header = array(
			'fin'       => ord($message[0])>>7 & 1,
			'rsv1'      => ord($message[0])>>6 & 1,
			'rsv2'      => ord($message[0])>>5 & 1,
			'rsv3'      => ord($message[0])>>4 & 1,
			'opcode'    => ord($message[0] & chr(15)),
			'hasmask'   => ord($message[1])>>7 & 1,
			'length'    => 0,
			'indexMask' => 2,
			'offset'    => 0,
			'mask'      => ""
		);

		$header['length'] = ord($message[1] & chr(127)) ;
	
		if ($header['length'] == 126) {
			if (strlen($message) < 4) return false;

			$header['indexMask'] = 4;
			$header['length'] =  (ord($message[2])<<8) | (ord($message[3])) ;
		} else if ($header['length'] == 127) {
			if (strlen($message) < 10) return false;

			$header['indexMask'] = 10;
			$header['length'] = (ord($message[2]) << 56 ) | ( ord($message[3]) << 48 ) | ( ord($message[4]) << 40 ) | (ord($message[5]) << 32 ) | ( ord($message[6]) << 24 ) | ( ord($message[7]) << 16 ) | (ord($message[8]) << 8  ) | ( ord($message[9]));
		} 

		$header['offset'] = $header['indexMask'];
		if ($header['hasmask']) {
			if (strlen($message) < $header['indexMask'] + 4) return false;

			$header['mask'] = $message[$header['indexMask']] . $message[$header['indexMask']+1] . $message[$header['indexMask']+2] . $message[$header['indexMask']+3];
			$header['offset'] += 4;
		}

		return $header;
	}

	protected function applyMask($headers, $payload) {
		$effectiveMask = "";
		if ($headers['hasmask']) {
			$mask = $headers['mask'];
		} else {
			return $payload;
		}

		$effectiveMask = str_repeat($mask, ($headers['length'] / 4) + 1);
		$over = $headers['length'] - strlen($effectiveMask);
		$effectiveMask = substr($effectiveMask, 0, $over);
		
		return $effectiveMask ^ $payload;
	}

// override this method if you are using an extension where the RSV bits are used.
	protected function checkRSVBits($headers) {
		if ($headers['rsv1'] + $headers['rsv2'] + $headers['rsv3'] > 0) {
// $this->disconnect($user); // todo: fail connection
			return true;
		}

		return false;
	}


	private function parseHandshake() {
		if (strpos($this->_read_buffer, "\r\n\r\n") === FALSE) {
			if (strlen($this->_read_buffer >= MAX_HANDSHAKE_SIZE)) {
				$this->send("HTTP/1.1 413 Request Entity Too Large" . "\r\n\r\n");

				$this->disconnect();
			}

			return;
		}

// TODO only use the part up to \r\n\r\n... Or check that nothing after that?

		$headers = array();
		$lines = explode("\r\n", $this->_read_buffer);
		$this->_read_buffer = '';

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
				if (preg_match("/GET (.*) HTTP/i", $line, $reqResource)) {
					$headers['get'] = trim($reqResource[1]);
				} else {
					$handshakeResponse = "HTTP/1.1 400 Bad Request";
				}
			}
		}

		if (isset($handshakeResponse)) {
			$this->send($handshakeResponse . "\r\n\r\n");

			$this->disconnect();

			return;
		}

		do {
			if (!isset($headers['get'])) {
				$handshakeResponse = "HTTP/1.1 405 Method Not Allowed";
				break;
			}

			if (!isset($headers['host'])) {
				$handshakeResponse = "HTTP/1.1 400 Bad Request";
				break;
			}
			if (!isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket') {
				$handshakeResponse = "HTTP/1.1 400 Bad Request";
				break;
			}
			if (!isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE) {
				$handshakeResponse = "HTTP/1.1 400 Bad Request";
				break;
			}
			if (!isset($headers['sec-websocket-key'])) {
				$handshakeResponse = "HTTP/1.1 400 Bad Request";
				break;
			}
			if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
				$handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
				break;
			}
		} while (false);

		if (isset($handshakeResponse)) {
			$this->send($handshakeResponse . "\r\n\r\n");

			$this->disconnect();

			return;
		}


		// Not all browser support same extensions.
		// extensions work on frame level
/*
		$extensionslist = $this->checkExtensions(explode('; ',$headers['sec-websocket-extensions']));

		if ($this->willSupportExtensions && !$extensions) {
			$user->headers["extensions"] = $extensionslist;
			$extensions = "Sec-WebSocket-Extensions: ".$extensionslist."\r\n";
		}
*/
		call_user_func_array($this->onHandshake, array(&$headers));

		if (isset($headers['__handshakeResponse'])) {
			$this->send($headers['__handshakeResponse'] . "\r\n\r\n");

			$this->disconnect();

			return;
		}

		$webSocketKeyHash = sha1($headers['sec-websocket-key'] . MAGIC_GUID);

		$rawToken = "";
		for ($i = 0; $i < 20; $i++) {
			$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
		}
		$handshakeToken = base64_encode($rawToken) . "\r\n";

$subProtocol = '';
$extensions = '';

		$handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";

		$this->send($handshakeResponse);

		call_user_func_array($this->onOpen, array($this, &$headers));

		$this->_handshakeComplete = true;
	}


	public function write() {
		if (!$this->_write_buffer) return false;

		if ($this->_write_buffer[0]['offset'] > 0) {
// TODO Possibly put a limit here to cut out ~32kb out of the string. An amount that is close to the max that socket_write is likely to accept. Limit the useless memory usage in case of gigantic messages
			$buffer = substr($this->_write_buffer[0]['data'], $this->_write_buffer[0]['offset']);
		} else {
			$buffer = $this->_write_buffer[0]['data'];
		}

		$numBytes = socket_write($this->_socket, $buffer);

		unset($buffer); // Free ASAP just because we can.

// TODO Handle Errors.


		if ($numBytes > 0) {
			$this->_write_buffer[0]['offset'] += $numBytes;
			if ($this->_write_buffer[0]['offset'] >= strlen($this->_write_buffer[0]['data'])) {
				array_shift($this->_write_buffer);
			}
		}

		if (!$this->_write_buffer) {
			if (!$this->_open) {
				$this->disconnect(true);
				return;
			}

			call_user_func($this->onWriteComplete);
		}

		if (!$this->_write_buffer) {
			$this->onStateChanged();
		}
	}

	public function getState() : int {
		return ($this->_open ? self::SOCKET_READ : 0) | ($this->_write_buffer ? self::SOCKET_WRITE : 0);
	}

	public function send($buffer) {
		$buffer_empty = !$this->_write_buffer;

		$this->_write_buffer[] = array('data' => $buffer, 'offset' => 0);

		if ($buffer_empty) {
			$this->onStateChanged();
		}
	}


	private function disconnect($immediate = false) {
		if (!$this->_write_buffer) $immediate = true;

		if ($immediate) {
			socket_close($this->_socket);

			call_user_func($this->onClose);
			return;
		}

		$this->_open = false;

// No longer reading, can discard any partial frames.
		$this->_read_buffer = '';


		$this->onStateChanged();
	}

}
