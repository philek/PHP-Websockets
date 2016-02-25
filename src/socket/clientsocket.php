<?php

namespace Gpws\Socket;

define('MAX_HANDSHAKE_SIZE', 8 * 1024);
define('MAX_BUFFER_SIZE', 24 * 1024 * 1024);
define('MAGIC_GUID', "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");

abstract class ClientSocket extends Socket implements \Gpws\Interfaces\ClientSocket {
	protected $_closing = false;
	protected $_write_buffer = array();



// DEBUGONLY
public static $cscounter = 0;
	public function __construct($socket) {
		parent::__construct($socket);

++self::$cscounter;
	}

	public function __destruct() {
--self::$cscounter;
	}
// DEBUGONLY


	abstract protected function _rawRead(&$buffer, $maxBytes = NULL) : int;
	abstract protected function _rawWrite(string $buffer) : int;
	abstract protected function _rawClose();







	public function doRead() {
		$this->raise('onReadWaiting');
	}


	public function read($maxBytes = NULL) {
		if ($this->_closing) {
trigger_error('This should not happen?');
			return;
		}

		$buffer = '';
		$readBytes = $maxBytes ?? MAX_BUFFER_SIZE;
		$numBytes = $this->_rawRead($buffer, $readBytes);

// Handle Errors.
if (!defined('NOOUTPUT')) printf('[Network] Read %d bytes%s', $numBytes, PHP_EOL);

		if ($numBytes == 0) {
			trigger_error("Client disconnected. TCP connection lost: " . $this->_socket);

			$this->abort();
			return;
		}

		return $buffer;
	}






	public function writeBufferEmpty() {
		return !$this->_write_buffer;
	}

	public function doWrite() {
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

		$numBytes = $this->_rawWrite($buffer);


		unset($buffer); // Free ASAP just because we can.

// TODO Handle Errors.
		if ($numBytes == 0) {
			$this->abort();
			return;
		}


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


	public function send(string $buffer) {

if (!defined('NOOUTPUT')) printf('[NetBuffer] Adding to send buffer %d%s', strlen($buffer), PHP_EOL);
assert(strlen($buffer) > 0);

		$buffer_empty = !$this->_write_buffer;

		$this->_write_buffer[] = array('data' => $buffer, 'offset' => 0);

		if ($buffer_empty) {
			$this->raise('onStateChanged');
		}
	}



	public function close() {
		$this->_closing = true;
		$this->raise('onStateChanged');

		if (!$this->_write_buffer) {
			$this->closeFinished();
		}
	}


	protected function closeFinished() {
		$this->abort();
	}


	protected function abort() {
if (!defined('NOOUTPUT')) printf('[Network] CLOSE FINISHED.'.PHP_EOL);

socket::$opencounter--; //DEBUGONLY


		$this->raise('onClose');

		$this->_rawClose();

		$this->_socket = NULL;

		$this->_write_buffer = NULL;

		$this->_closing = true;
		$this->raise('onStateChanged');
	}

}
