<?php

namespace Gpws\Core;

class Client implements \Gpws\Interfaces\Client, \Gpws\Interfaces\EventEmitter {
	use \Gpws\Core\EventEmitter;

	private $_socket;

	public function __construct(\Gpws\Core\ClientSocket $socket) {
		$this->_socket = $socket;

		$this->_socket->addListener('onRead', array($this, 'onReadCallback'));

		$this->_socket->addListener('onWriteComplete', array($this, 'onWriteCompleteCallback'));
	}

	private $_messageQueue = array();

	public function onReadCallback(\Gpws\Interfaces\Socket $socket, \Gpws\Interfaces\InboundMessage $message) {
//		printf('GOT FRAME: %s%s', $frameContent, PHP_EOL);

		$this->raise('onMessage', $message);
	}

	public function onWriteCompleteCallback(\Gpws\Interfaces\Socket $socket) {
		if ($this->_messageQueue) {
			$message = array_shift($this->_messageQueue);
			$this->_socket->sendMessage($message);
		}
	}

	public function queueMessage(\Gpws\Interfaces\OutboundMessage $message) : int {
		if ($this->_socket->writeBufferEmpty()) {
			$this->_socket->sendMessage($message);
			return 0;
		}

		$this->_messageQueue[] = $message;

		return count($this->_messageQueue);
	}

}
