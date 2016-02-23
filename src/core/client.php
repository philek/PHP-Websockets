<?php

namespace Gpws\Core;

class Client implements \Gpws\Interfaces\Client, \Gpws\Interfaces\EventEmitter {
	use \Gpws\Core\EventEmitter;

	private $_socket;

	private $_builder;
	private $_framer;

	private $_messageQueue = array();


	public function __construct(\Gpws\Interfaces\ClientSocket $socket) {
		$this->_socket = $socket;

		$this->_socket->addListener('onWriteComplete', 
			function(\Gpws\Interfaces\Socket $socket) {
				if ($this->_messageQueue) {
					$message = array_shift($this->_messageQueue);
					$this->sendMessage($message);
				}
			}
		);

		$this->_socket->addListener('onReadWaiting', 
			function(\Gpws\Interfaces\Socket $socket) {
				if ($buffer = $socket->read()) {
					$this->_builder->addData($buffer);
				}
			}
		);

		$this->_socket->addListener('onClose', 
			function(\Gpws\Interfaces\Socket $socket) {
			}
		);


		$this->_framer = new \Gpws\Core\MessageFramer();

		$this->_builder = new \Gpws\Core\MessageBuilder();

		$this->_builder->addListener('onMessageReady',
			function(\Gpws\Core\MessageBuilder $builder) {
				while ($builder->isReady()) {
					$msg = $builder->getMessage();
					$this->handleMessage($msg);
				}
			}	
		);

		$this->_builder->addListener('onError',
			function(\Gpws\Core\MessageBuilder $builder) {
				$this->raise('onError');
				$this->close(false);
			}	
		);

++self::$counter;// DEBUGONLY
	}

	public function __destruct() {
--self::$counter;// DEBUGONLY
	}

public static $counter = 0;// DEBUGONLY

	public function queueMessage(\Gpws\Interfaces\Message $message) : int {
// Check if still open.
		if (!$this->_socket) return 0;

		if ($this->_socket->writeBufferEmpty()) {
			$this->sendMessage($message);
		} else {
			$this->_messageQueue[] = $message;
		}

		return count($this->_messageQueue);
	}


	private function sendMessage(\Gpws\Interfaces\Message $message) {
		$frame = $this->_framer->getFramedMessage($message);
		$this->_socket->send($frame);
	}


	private function handleMessage(\Gpws\Interfaces\Message $msg) {
		switch ($msg->getType()) {
			case \Gpws\Interfaces\Message::TYPE_TEXT:
			case \Gpws\Interfaces\Message::TYPE_BINARY:
				$this->raise('onMessage', $msg);
				return;

			case \Gpws\Interfaces\Message::TYPE_CLOSE:
				$this->close(true);
				return;

			case \Gpws\Interfaces\Message::TYPE_PONG:
				return;

			case \Gpws\Interfaces\Message::TYPE_PING:
				$message = new \Gpws\Message\PongMessage($msg->getContent());
				$this->queueMessage($message);
				return;
		}
	}


	private function close($send_message = true) {
		$this->_builder->clearListeners();
		$this->_builder = NULL;

		$this->_messageQueue = array();

		if ($send_message) {
			$message = new \Gpws\Message\CloseMessage();
			$this->sendMessage($message);
		}

		$this->_socket->close();
		$this->_socket = NULL;

		$this->raise('onDisconnect');
	}
}
