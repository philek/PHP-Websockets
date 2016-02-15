<?php

namespace Gpws\Core;

define('MAX_HANDSHAKE_SIZE', 8 * 1024);
define('MAX_BUFFER_SIZE', 24 * 1024 * 1024);
define('MAGIC_GUID', "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");

class ClientSocket extends \Gpws\Core\Socket {
	private $_closing = false;

	private $_handshakeComplete = false;

	private $_read_buffer = '';
	private $_read_buffer_header = NULL;

	private $_write_buffer = array();

	public function read() {
		if ($this->_closing) {
trigger_error('This should not happen?');
			return;
		}

		$buffer = '';
		$numBytes = socket_recv($this->_socket, $buffer, MAX_BUFFER_SIZE - strlen($this->_read_buffer), 0);

// Handle Errors.
if (!defined('NOOUTPUT')) printf('[Network] Read %d bytes%s', $numBytes, PHP_EOL);

		if ($numBytes === false) {
			trigger_error('Socket error: ' . socket_strerror(socket_last_error($this->_socket)));

			$this->raise('onError');
			$this->abort();

			return;
		}

		if ($numBytes == 0) {
			trigger_error("Client disconnected. TCP connection lost: " . $this->_socket);

			$this->_closing = true;

			$this->raise('onStateChanged');

			$this->raise('onClose');

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
		while ($this->_read_buffer) {
if (!defined('NOOUTPUT')) printf("[ReadLoop] Current read buffer length: %d%s", strlen($this->_read_buffer), PHP_EOL);

			if (!$this->_read_buffer_header) {
				$frameHeader = $this->extractHeader($this->_read_buffer);

if (!defined('NOOUTPUT')) printf("[ReadLoop] Frame Detected: Type %d Length: %d Offset: %d Payload: %d%s", $frameHeader['opcode'], $frameHeader['offset']+$frameHeader['length'], $frameHeader['offset'], $frameHeader['length'], PHP_EOL);

				if (!$frameHeader) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Frame Header not ready.%s', PHP_EOL);
					return;
				}
			} else {
				$frameHeader = $this->_read_buffer_header;
			}


			if ($frameHeader['offset'] + $frameHeader['length'] > strlen($this->_read_buffer)) {
				$this->_read_buffer_header = $frameHeader;
if (!defined('NOOUTPUT')) printf('[ReadLoop] Frame data not complete.'.PHP_EOL);
				return;
			}

			$frame = substr($this->_read_buffer, $frameHeader['offset'], $frameHeader['length']);

			$message = $this->deFrame($frame, $frameHeader);
			if ($message !== false) {

if (!defined('NOOUTPUT')) printf('[ReadLoop] Proper Message Parsed%s', PHP_EOL);

				$this->raise('onRead', $message['data'], $message['binary']);
			}

			$this->_read_buffer = substr($this->_read_buffer, $frameHeader['offset'] + $frameHeader['length']);
			$this->_read_buffer_header = '';
		}
	}

	private $_partialMessage = NULL;
	private $_partialMessageBinary = NULL;

	protected function deframe($payload, $headers) {
		if ($this->checkRSVBits($headers)) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Invalid rsv bits (%d%d%d) received. Aborting connection.'.PHP_EOL, $headers['rsv1'], $headers['rsv2'], $headers['rsv3']);

			$this->raise('onError');

			$this->close(false);

			return false;
		}



		switch ($headers['opcode']) {
			case 0:
if (!defined('NOOUTPUT')) printf("[DeFrame] Fragmented Packet Received.%s", PHP_EOL);
				if ($this->_partialMessage === NULL) {
if (!defined('NOOUTPUT')) printf("[DeFrame] Fragmented Packet with nothing to continue. Aborting.%s", PHP_EOL);
					$this->close(false);
					return false;
				}
				break;

			case 1:
			case 2:
				if ($this->_partialMessage !== NULL) {
if (!defined('NOOUTPUT')) printf("[DeFrame] Expecting continuation packet. Aborting.%s", PHP_EOL);
					$this->close(false);
					return false;
				}
				break;

			case 8:
				$this->close();
if (!defined('NOOUTPUT')) printf('[DeFrame] Disconnect packet received.' . PHP_EOL);

				return false;

			case 9:
				if ($headers['length'] > 125) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Invalid ping - too long.' . PHP_EOL);
					$this->close();

					return false;
				}

				if (!$headers['fin']) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Ping cannot be fragmented.'.PHP_EOL);
					$this->close(false);

					return false;
				}

				$message = new \Gpws\Message\PongMessage($this->applyMask($headers, $payload));
				$this->send($message->getContent());

				return false;

			case 10:
// A Pong frame MAY be sent unsolicited. This serves as a unidirectional heartbeat. A response to an unsolicited Pong frame is not expected.
// IE 11 default behavior send PONG ~30sec between them. Just ignore them.
				if (!$headers['fin']) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Pong cannot be fragmented.'.PHP_EOL);
					$this->close(false);

					return false;
				}

				return false;  

			default:
if (!defined('NOOUTPUT')) printf('[DeFrame] Invalid opcode %d received. Aborting connection.%s', $headers['opcode'], PHP_EOL);

				$this->raise('onError');

				$this->close(false);
				break;
		}

		if ($headers['opcode'] !== 0) {
			$this->_partialMessage = '';
			$this->_partialMessageBinary = $headers['opcode'] == 2;
		}

		$this->_partialMessage .= $this->applyMask($headers, $payload);



		if ($headers['fin']) {
			if (!$this->_partialMessageBinary && !$this->isValidUTF8($this->_partialMessage)) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Invalid UTF8 received %s. Aborting connection.%s', $this->_partialMessage, PHP_EOL);

				$this->close(false);

				return false;
			}



			$frame = array(
				'data' => $this->_partialMessage,
				'binary' => $this->_partialMessageBinary
			);

			$this->_partialMessage = NULL;
			$this->_partialMessageBinary = NULL;

			return $frame;
		}

		return false;
	}

	private function isValidUTF8($string) {
		return preg_match('//u', $string);

		return preg_match('%^(?:
      [\x09\x0A\x0D\x20-\x7E]            # ASCII
    | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
    | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
    | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
    | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
    | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
    | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
    | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
)*$%xs', $string);
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
		$handshakeResponse = $this->getHandshakeResponse();

		if (!$handshakeResponse) return;

		// Send Response.

		$error = $handshakeResponse['error'];
		unset($handshakeResponse['error']);

		$data = $handshakeResponse['StatusLine'] . "\r\n";
		unset($handshakeResponse['StatusLine']);

		foreach ($handshakeResponse AS $headerName => $headerValue) {
			$data .= sprintf("%s: %s\r\n", $headerName, $headerValue);
		}

//		$data .= "\r\n";

		$this->send($data);


		if ($error) {
			$this->close(false);
		}

		$this->raise('onHandshakeComplete', $this->_clientHeaders);

		$this->_handshakeComplete = true;
	}

	private function getHandshakeResponse() {
		$handshakeResponse = array('StatusLine' => 'HTTP/1.1 101 Switching Protocols', 'error' => false);

		if (strpos($this->_read_buffer, "\r\n\r\n") === FALSE) {
			if (strlen($this->_read_buffer >= MAX_HANDSHAKE_SIZE)) {
				$handshakeResponse['StatusLine'] = 'HTTP/1.1 413 Request Entity Too Large';
				$handshakeResponse['error'] = true;

				return $handshakeResponse;
			}

			return false;
		}

// TODO only use the part up to \r\n\r\n... Or check that nothing after that?
if (!defined('NOOUTPUT')) printf('[ReadLoop] New Client: %s%s', str_replace("\r\n", "  ", $this->_read_buffer), PHP_EOL);

		$clientHeaders = array();
		$lines = explode("\r\n", $this->_read_buffer);
		$this->_read_buffer = '';

		//protocol and extensions can be sent on multiple line.
		//$clientHeaders['sec-websocket-protocol']='';
		//$clientHeaders['sec-websocket-extensions']='';
		foreach ($lines as $line) {
			if (strpos($line,":") !== false) {
				$header = explode(":",$line,2);
				switch ($header) {
					case 'sec-websocket-protocol':
						$clientHeaders[strtolower(trim($header[0]))] .= trim($header[1]).', ';
						break;
					case 'sec-websocket-extensions':
						$clientHeaders[strtolower(trim($header[0]))] .= trim($header[1]).'; ';
						break;
					default:
						$clientHeaders[strtolower(trim($header[0]))] = trim($header[1]);
						break;
				}
			} else if (stripos($line,"get ") !== false) {
				if (preg_match("/GET (.*) HTTP/i", $line, $reqResource)) {
					$clientHeaders['get'] = trim($reqResource[1]);
				} else {
					$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
					$handshakeResponse['error'] = true;
					return $handshakeResponse;
				}
			}
		}


		do {
			if (!isset($clientHeaders['get'])) {
				$handshakeResponse['StatusLine'] = "HTTP/1.1 405 Method Not Allowed";
				$handshakeResponse['error'] = true;
				break;
			}

			if (!isset($clientHeaders['host'])) {
				$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
				$handshakeResponse['error'] = true;
				break;
			}
			if (!isset($clientHeaders['upgrade']) || strtolower($clientHeaders['upgrade']) != 'websocket') {
				$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
				$handshakeResponse['error'] = true;
				break;
			}
			if (!isset($clientHeaders['connection']) || strpos(strtolower($clientHeaders['connection']), 'upgrade') === FALSE) {
				$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
				$handshakeResponse['error'] = true;
				break;
			}
			if (!isset($clientHeaders['sec-websocket-key'])) {
				$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
				$handshakeResponse['error'] = true;
				break;
			}
			if (!isset($clientHeaders['sec-websocket-version']) || strtolower($clientHeaders['sec-websocket-version']) != 13) {
				$handshakeResponse['StatusLine'] = "HTTP/1.1 426 Upgrade Required";
				$handshakeResponse['Sec-WebSocketVersion'] = "13";
				$handshakeResponse['error'] = true;
				break;
			}
		} while (false);

		if ($handshakeResponse['error']) {
			return $handshakeResponse;
		}


		// Not all browser support same extensions.
		// extensions work on frame level
/*
		$extensionslist = $this->checkExtensions(explode('; ',$clientHeaders['sec-websocket-extensions']));

		if ($this->willSupportExtensions && !$extensions) {
			$user->headers["extensions"] = $extensionslist;
			$extensions = "Sec-WebSocket-Extensions: ".$extensionslist."\r\n";
		}
*/


		$this->raise_array('onHandshake', array($clientHeaders, &$handshakeResponse));

		if ($handshakeResponse['error']) {
			return $handshakeResponse;
		}

		$webSocketKeyHash = sha1($clientHeaders['sec-websocket-key'] . MAGIC_GUID);

		$rawToken = "";
		for ($i = 0; $i < 20; $i++) {
			$rawToken .= chr(hexdec(substr($webSocketKeyHash,$i*2, 2)));
		}
		$handshakeToken = base64_encode($rawToken) . "\r\n";


		$handshakeResponse['Upgrade'] = 'websocket';
		$handshakeResponse['Connection'] = 'Upgrade';
		$handshakeResponse['Sec-WebSocket-Accept'] = $handshakeToken;

		$this->_clientHeaders = $clientHeaders;

		return $handshakeResponse;
	}

	public function writeBufferEmpty() {
		return !$this->_write_buffer;
	}

	public function write() {
		if (!$this->_write_buffer) return false;

		if (!$this->_socket) {
			trigger_error('Attempting to write to closed socket.');
			return;
		}

		if ($this->_write_buffer[0]['offset'] > 0) {
// TODO Possibly put a limit here to cut out ~32kb out of the string. An amount that is close to the max that socket_write is likely to accept. Limit the useless memory usage in case of gigantic messages
			$buffer = substr($this->_write_buffer[0]['data'], $this->_write_buffer[0]['offset']);
		} else {
			$buffer = $this->_write_buffer[0]['data'];
		}

if (!defined('NOOUTPUT')) printf("[Network] Sending %d bytes %s", strlen($buffer), PHP_EOL);

		$numBytes = socket_write($this->_socket, $buffer);

		if ($numBytes === false) {
if (!defined('NOOUTPUT')) printf('[Network] Write Socket error: ' . socket_strerror(socket_last_error($this->_socket)));

			$this->raise('onError');
			$this->abort();

			return;
		}

		unset($buffer); // Free ASAP just because we can.

// TODO Handle Errors.


		if ($numBytes > 0) {
			$this->_write_buffer[0]['offset'] += $numBytes;
			if ($this->_write_buffer[0]['offset'] >= strlen($this->_write_buffer[0]['data'])) {
				array_shift($this->_write_buffer);
			}
		}

		if (!$this->_write_buffer) {
			if ($this->_closing) {
				$this->closeFinished();
				return;
			}

			$this->raise('onWriteComplete');
		}

		if (!$this->_write_buffer) {
			$this->raise('onStateChanged');
		}
	}

	public function getState() : int {
		return ((!$this->_closing) ? self::SOCKET_READ : 0) | ($this->_write_buffer ? self::SOCKET_WRITE : 0);
	}

	public function send($buffer) {
if (!defined('NOOUTPUT')) printf('[NetBuffer] Adding to send buffer %d%s', strlen($buffer), PHP_EOL);

		$buffer_empty = !$this->_write_buffer;

		$this->_write_buffer[] = array('data' => $buffer, 'offset' => 0);

		if ($buffer_empty) {
			$this->raise('onStateChanged');
		}
	}


	private function close($send_message = true) {
		$this->_partialMessage = NULL;
		$this->_partialMessageBinary = NULL;

		$this->_read_buffer = NULL;
		$this->_read_buffer_header = NULL;

		$this->_closing = true;
		$this->raise('onStateChanged');

		if ($send_message) {
			$message = new \Gpws\Message\CloseMessage();
			$this->send($message->getContent());
		}

		if (!$this->_write_buffer) {
			$this->closeFinished();
		}
	}


	private function closeFinished() {
		$this->abort();
	}


	private function abort() {
if (!defined('NOOUTPUT')) printf('[Network] CLOSE FINISHED.'.PHP_EOL);

		$this->raise('onClose');

		socket_close($this->_socket);

		$this->_socket = NULL;

		$this->_partialMessage = NULL;
		$this->_partialMessageBinary = NULL;

		$this->_read_buffer = NULL;
		$this->_read_buffer_header = NULL;

		$this->_write_buffer = NULL;

		$this->_closing = true;

		$this->raise('onStateChanged');
	}

}
