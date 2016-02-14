<?php 
namespace Gpws\Eventloop;

// Default method 
class SelectLoop implements \Gpws\Interfaces\EventLoop {

	public function run() {
		while (true) {
			$read = $this->_read_sockets;
			$write = $this->_write_sockets;
			$except = null;

			socket_select($read, $write, $except, $this->_nextTimer);

			foreach ($write as $socket) {
				$this->_socketList[(int)$socket]->write();
			}

			foreach ($read as $socket) {
				$this->_socketList[(int)$socket]->read();
			}
		}
	}

	private $_read_cb = array();
	private $_write_cb = array();

	private $_read_sockets = array();
	private $_write_sockets = array();

	public function addSocket(\Gpws\Interfaces\Socket $socket) {
		$this->_socketList[$socket->getId()] = $socket;

		$socket->addListener('onStateChanged', array($this, 'socketStateChangedCallback'));

		$this->socketStateChangedCallback($socket);
	}

	public function socketStateChangedCallback(\Gpws\Interfaces\Socket $socket) {
		$status = $socket->getState();

		$socket_handle = $socket->getHandle();

		if ($status & \Gpws\Interfaces\Socket::SOCKET_READ) {
			$this->_read_sockets[(int)$socket_handle] = $socket_handle;
		} else {
			unset($this->_read_sockets[(int)$socket_handle]);
		}

		if ($status & \Gpws\Interfaces\Socket::SOCKET_WRITE) {
			$this->_write_sockets[(int)$socket_handle] = $socket_handle;
		} else {
			unset($this->_write_sockets[(int)$socket_handle]);
		}
	}

	public function delSocket(\Gpws\Interfaces\Socket $socket) {
		$socket_id = $socket->getId();
		
		unset($this->_socketList[$socket_id]);

		unset($this->_read_sockets[$socket_id]);
		unset($this->_write_sockets[$socket_id]);
	}


	private $_timerList = array();
	private $_nextTimer = NULL;

	public function addTimer(int $interval, callable $callback) : int {
		// Not Implemented.
	}

	public function delTimer(int $timer_id) {

	}

	private function runTimers() {

	}
}

