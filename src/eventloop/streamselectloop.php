<?php 
namespace Gpws\Eventloop;

// Default method 
class StreamSelectLoop implements \Gpws\Interfaces\EventLoop {

public static $socketcount = 0; // DEBUGONLY

	public function run() {
		while (true) {
if (!defined('NOOUTPUT')) printf('[EventLoop] Loop.%s', PHP_EOL);
			$this->runTimers();

			$read = $this->_read_sockets;
			$write = $this->_write_sockets;
			$except = null;
// usleep(1000000);
			stream_select($read, $write, $except, $this->_nextTimer);

			foreach ($write as $socket) {
				$this->_socketList[(int)$socket]->doWrite();
			}

			foreach ($read as $socket) {
				$this->_socketList[(int)$socket]->doRead();
			}
		}
	}

	private $_read_cb = array();
	private $_write_cb = array();

	private $_read_sockets = array();
	private $_write_sockets = array();

	public function addSocket(\Gpws\Interfaces\Socket $socket) {
self::$socketcount++;
if (!defined('NOOUTPUT')) printf('[EventLoop] ADD SOCKET: %s (Total: %d)%s', $socket->getId(), self::$socketcount, PHP_EOL);

		$this->_socketList[$socket->getId()] = $socket;

		$socket->addListener('onStateChanged', array($this, 'socketStateChangedCallback'));

		$this->socketStateChangedCallback($socket);
	}

	public function socketStateChangedCallback(\Gpws\Interfaces\Socket $socket) {
if (!defined('NOOUTPUT')) printf('[EventLoop] UPDATE SOCKET: ' . $socket->getId() . " STATE " . $socket->getState() . PHP_EOL);
		if (!isset($this->_socketList[$socket->getId()])) {
			trigger_error('INVALID SOCKET UPDATE', E_USER_WARNING);
			return;
		}

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
		$socket->removeListener('onStateChanged', array($this, 'socketStateChangedCallback'));

		$socket_id = $socket->getId();

self::$socketcount--;
if (!defined('NOOUTPUT')) printf('[EventLoop] DEL SOCKET: %s (Total: %d)%s', $socket_id, self::$socketcount, PHP_EOL);
		
		unset($this->_socketList[$socket_id]);

		unset($this->_read_sockets[$socket_id]);
		unset($this->_write_sockets[$socket_id]);
	}


	private $_timerList = array();
	private $_nextTimer = NULL;
	private $_lastLoop = NULL;

	public function addTimer(int $interval, callable $callback) : int {
		$id = rand();
		$this->_timerList[$id] = array(
			'call' => $callback,
			'interval' => $interval,
			'next' => time() + $interval
		);

		return $id;

	}

	public function delTimer(int $timer_id) {
		unset($this->_timerList[$timer_id]);
	}

	private function runTimers() {
		$this->_nextTimer = 60;

		foreach ($this->_timerList AS &$timer) {
			if ($timer['next'] <= time()) {
				call_user_func($timer['call']);
				$timer['next'] = time() + $timer['interval'];
			}

			$this->_nextTimer = min($this->_nextTimer, $timer['next'] - time());
		}
	}
}

