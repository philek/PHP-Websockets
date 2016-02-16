<?php

namespace Gpws\Core;

define('MAX_HANDSHAKE_SIZE', 8 * 1024);
define('MAX_BUFFER_SIZE', 24 * 1024 * 1024);
define('MAGIC_GUID', "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
define('SERVER_NAME', 'ghedipunk/PHP-Websockets-0.0~fr1');

class ClientSocket extends \Gpws\Core\Socket {
	private $_closing = false;

	private $_handshakeComplete = false;

	private $_read_buffer = '';

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

	private $_partialFrame = NULL;
	private $_partialMessage = NULL;

	private function parseBuffer() {
		if (!$this->_handshakeComplete) {
			$this->handleHandshake();
			return;
		}

		while ($this->_read_buffer) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Loop%s', PHP_EOL);

			if ($this->_partialFrame === NULL) {
				$this->_partialFrame = new \Gpws\Core\Frame;
			}

			$numBytes = $this->_partialFrame->addData($this->_read_buffer);

			if ($numBytes === 0) {
				break; // Not enough data to do anything.
			}

			if ($this->_partialFrame->isInvalid()) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Invalid Frame. Must Close. %s', PHP_EOL);

				$this->raise('onError');

				$this->close(false);

				break;
			}

			assert($numBytes > 0);

			$this->_read_buffer = substr($this->_read_buffer, $numBytes);

			if ($this->_partialFrame->isReady()) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Proper Frame Parsed%s', PHP_EOL);

				$this->handleFrame($this->_partialFrame);

				$this->_partialFrame = NULL;
			}
		}
	}


	private function handleFrame(\Gpws\Interfaces\Frame $frame) {
		switch ($frame->getType()) {
			case 0:
			case 1:
			case 2:
				if ($this->_partialMessage === NULL) {
					$this->_partialMessage = new \Gpws\Message\InboundMessage;
				}

				if (!$this->_partialMessage->addFrame($frame)) {
					$this->close(false);

					return;
				}

				if ($this->_partialMessage->isReady()) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Proper Message Parsed%s', PHP_EOL);

					$this->raise('onRead', $this->_partialMessage);

					$this->_partialMessage = NULL;
				}
				break;

			case 8:
				$this->close();
				return;

			case 9:
				$message = new \Gpws\Message\PongMessage($frame->getPayload());
				$this->sendMessage($message);
				return;

			case 10:
				// NOOP
		}
	}


	private function handleHandshake() {
		$clientHeaders = $this->parseHandshake();

// Header not complete yet.
		if ($clientHeaders === false) {
			return;
		}

		$handshakeResponse = $this->getHandshakeResponse($clientHeaders);

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

		$this->sendRaw($data);


		if ($error) {
			$this->close(false);
		}

		$this->raise('onHandshakeComplete', $clientHeaders);

		$this->_handshakeComplete = true;
	}

	private function parseHandshake() {
		if (strpos($this->_read_buffer, "\r\n\r\n") === FALSE) {
			if (strlen($this->_read_buffer >= MAX_HANDSHAKE_SIZE)) {
				return array();
			}

			return false;
		}

// TODO only use the part up to \r\n\r\n... Or check that nothing after that?
if (!defined('NOOUTPUT')) printf('[ReadLoop] New Client: %s%s', str_replace("\r\n", "  ", $this->_read_buffer), PHP_EOL);

		$lines = explode("\r\n", $this->_read_buffer);
		$this->_read_buffer = '';

		$clientHeaders = array();

		$requestLine = array_shift($lines);

		if (preg_match("/GET (.*) HTTP/i", $requestLine, $reqResource)) {
			$clientHeaders['get'] = trim($reqResource[1]);
		} else {
			return $clientHeaders;
		}


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
			}
		}

		return $clientHeaders;
	}

	private function getHandshakeResponse($clientHeaders) {
		if (!$clientHeaders) {
			$handshakeResponse = array();
			$handshakeResponse['StatusLine'] = 'HTTP/1.1 413 Request Entity Too Large';
			$handshakeResponse['error'] = true;
			return $handshakeResponse;
		}


		$handshakeResponse = array('StatusLine' => 'HTTP/1.1 101 Switching Protocols', 'error' => false);

		if (!isset($clientHeaders['get'])) {
			$handshakeResponse['StatusLine'] = "HTTP/1.1 405 Method Not Allowed";
			$handshakeResponse['error'] = true;
			return $handshakeResponse;
		}

		if (!isset($clientHeaders['host'])) {
			$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
			$handshakeResponse['error'] = true;
			return $handshakeResponse;
		}
		if (!isset($clientHeaders['upgrade']) || strtolower($clientHeaders['upgrade']) != 'websocket') {
			$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
			$handshakeResponse['error'] = true;
			return $handshakeResponse;
		}
		if (!isset($clientHeaders['connection']) || strpos(strtolower($clientHeaders['connection']), 'upgrade') === FALSE) {
			$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
			$handshakeResponse['error'] = true;
			return $handshakeResponse;
		}
		if (!isset($clientHeaders['sec-websocket-key'])) {
			$handshakeResponse['StatusLine'] = "HTTP/1.1 400 Bad Request";
			$handshakeResponse['error'] = true;
			return $handshakeResponse;
		}
		if (!isset($clientHeaders['sec-websocket-version']) || strtolower($clientHeaders['sec-websocket-version']) != 13) {
			$handshakeResponse['StatusLine'] = "HTTP/1.1 426 Upgrade Required";
			$handshakeResponse['Sec-WebSocketVersion'] = "13";
			$handshakeResponse['error'] = true;
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

		$handshakeResponse['Server'] = SERVER_NAME;
		$handshakeResponse['Upgrade'] = 'websocket';
		$handshakeResponse['Connection'] = 'Upgrade';
		$handshakeResponse['Sec-WebSocket-Accept'] = $handshakeToken;

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

	public function sendMessage(\Gpws\Interfaces\OutboundMessage $message) {
		$frame = $message->getFramedContent();
		$this->sendRaw($frame);
	}

	protected function sendRaw(string $buffer) {

if (!defined('NOOUTPUT')) printf('[NetBuffer] Adding to send buffer %d%s', strlen($buffer), PHP_EOL);
assert(strlen($buffer) > 0);

		$buffer_empty = !$this->_write_buffer;

		$this->_write_buffer[] = array('data' => $buffer, 'offset' => 0);

		if ($buffer_empty) {
			$this->raise('onStateChanged');
		}
	}


	private function close($send_message = true) {
		$this->_partialFrame = NULL;
		$this->_partialMessage = NULL;

		$this->_read_buffer = NULL;

		$this->_closing = true;
		$this->raise('onStateChanged');

		if ($this->_write_buffer && $this->_write_buffer[0]['offset'] == 0) {
			$this->_write_buffer = array();
		}

		if ($send_message) {
			$message = new \Gpws\Message\CloseMessage();
			$this->sendMessage($message);
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

		$this->_partialFrame = NULL;
		$this->_partialMessage = NULL;

		$this->_read_buffer = NULL;
		$this->_write_buffer = NULL;

		$this->_closing = true;
		$this->raise('onStateChanged');
	}

}
