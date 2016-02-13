<?php

namespace Gpws\Core;

class Server implements \Gpws\Interfaces\Server {
	private $_config = array(
		'EventLoopClass' => '\Gpws\EventLoop\SelectLoop'
	);


	private $_eventLoop = NULL;

	public function __construct($config = NULL) {
		if ($config) $this->_config = $config + $this->_config;

		$this->_eventLoop = new $this->_config['EventLoopClass'];
	}

	private $_appList = array();

	public function registerApp(string $path, \Gpws\Interfaces\Application $app) {
		$this->_appList[$path] = $app;
	}

	public function run() {
		$this->_eventLoop->run();
	}

	public function bind(string $host, int $port) {
		$sObj = new ListenSocket($host, $port, array($this, 'onOpenCallback'));

		$this->_eventLoop->addSocket($sObj);
	}

	public function onOpenCallback($socket) {
		$sObj = new ClientSocket($socket);
		$sObj->onHandshake = array($this, 'onHandshakeCallback');
		$sObj->onOpen = array($this, 'onConnectCallback');

		$this->_eventLoop->addSocket($sObj);
	}

	public function onHandshakeCallback(array &$headers) {
		// Find App

		if (!isset($this->_appList[$headers['get']])) {
			$headers['__handshakeResponse'] = "HTTP/1.1 404 Not Found";
			return false;
		}

		$app = $this->_appList[$headers['get']];

		$app->onHandshake($headers);
	}

	public function onConnectCallback(\Gpws\Interfaces\Socket $socket, array &$headers) {
		// Find App
		$app = $this->_appList[$headers['get']];

		$cObj = $app->createClient($socket);
	}
}
