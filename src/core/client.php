<?php

namespace Gpws\Core;

class Client implements \Gpws\Interfaces\Client {
	private $_socket;

	public function __construct(\Gpws\Core\ClientSocket $socket) {
		$this->_socket = $socket;

		$this->_socket->onRead = array($this, 'onReadCallback');
		$this->_socket->onWriteComplete = array($this, 'onWriteCompleteCallback');
	}

	private $_messageQueue = array();

	public function onReadCallback($frameContent) {
		printf('GOT FRAME: %s%s', $frameContent, PHP_EOL);

	}

	public function onWriteCompleteCallback() {
		if ($this->_messageQueue) {
			$message = array_shift($this->_messageQueue);
			$this->_socket->send($message->getContent());
		}
	}

	public function queueMessage(\Gpws\Interfaces\OutboundMessage $message) : int {
		if (!$this->_socket->writeBuffer()) {
			$this->_socket->send($message->getContent());
			return;
		}

		$this->_messageQueue[] = $message;

		return count($this->_messageQueue);
	}

}
