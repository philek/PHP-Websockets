<?php

namespace Gpws\Core;

class GenericClient implements \Gpws\Interfaces\EventEmitter {
	use \Gpws\Core\EventEmitter;

	private $_socket;


public static $counter = 0; //DEBUGONLY
	public function __construct(\Gpws\Interfaces\ClientSocket $socket) {
		$this->_socket = $socket;
		$this->_socket->addListener('onReadWaiting', array($this, 'readWaitingCallback'));

++self::$counter; // DEBUGONLY
	}

	public function __destruct() {
--self::$counter; // DEBUGONLY
	}

	public function readWaitingCallback(\Gpws\Interfaces\Socket $socket) {
		if ($buffer = $socket->read()) {
			$this->addHandshakeData($buffer);
		}
	}


	private function close() {
		$this->raise('onClose');

		$this->_socket->close();
	}

	private $_handshakeData = '';
	private $_handshakeComplete = false;


	public function getSocket() : \Gpws\Interfaces\ClientSocket {
		return $this->_socket;
	}

	private function addHandshakeData(string $buffer) {
		$this->_handshakeData .= $buffer;
if (!defined('NOOUTPUT')) printf('[ReadLoop] Got New Data: %d%s', strlen($buffer), PHP_EOL);

		$clientHeaders = $this->parseHandshake();

// Header not complete yet.
		if ($clientHeaders === false) {
			return;
		}

		$this->_handshakeData = '';

		$handshakeResponse = $this->getHandshakeResponse($clientHeaders);

		$error = $handshakeResponse['error'];
		unset($handshakeResponse['error']);

		$data = $handshakeResponse['StatusLine'] . "\r\n";
		unset($handshakeResponse['StatusLine']);

		foreach ($handshakeResponse AS $headerName => $headerValue) {
			$data .= sprintf("%s: %s\r\n", $headerName, $headerValue);
		}

		$this->_socket->send($data);

		if ($error) {
			$this->close();
		}

		$this->raise('onHandshakeComplete', $clientHeaders);
	}

	private function parseHandshake() {
		if (strpos($this->_handshakeData, "\r\n\r\n") === FALSE) {
			if (strlen($this->_handshakeData >= MAX_HANDSHAKE_SIZE)) {
				$this->raise('onError');

				$this->close();

				return false;
			}

			if (!defined('NOOUTPUT')) printf('[ReadLoop] Incomplete Handshake. Waiting: %s %s', str_replace("\r\n", "  ", $this->_handshakeData), PHP_EOL);
		

			return false;
		}

		$this->_socket->removeListener('onReadWaiting', array($this, 'readWaitingCallback'));

// TODO only use the part up to \r\n\r\n... Or check that nothing after that?
if (!defined('NOOUTPUT')) printf('[ReadLoop] New Client: %s%s', str_replace("\r\n", "  ", $this->_handshakeData), PHP_EOL);

		$lines = explode("\r\n", $this->_handshakeData);
		$this->_handshakeData = '';

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

	private function getHandshakeResponse($clientHeaders) : array {
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

}
