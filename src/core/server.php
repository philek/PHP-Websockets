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
		$sObj = new ListenSocket($host, $port);

		$sObj->addListener('onConnectionReady', array($this, 'onConnectionReady'));

		$this->_eventLoop->addSocket($sObj);
	}

	public function onConnectionReady(\Gpws\Interfaces\Socket $listenObj) {
		$socket = $listenObj->getWaitingConnection();

// Error Checking.

		$sObj = new ClientSocket($socket);

		$sObj->addListener('onHandshake', array($this, 'onHandshake'));
		$sObj->addListener('onHandshakeComplete', array($this, 'onHandshakeComplete'));

		$this->_eventLoop->addSocket($sObj);
	}

	public function onHandshake(\Gpws\Interfaces\Socket $socket, array $request, array &$response) {
		// Find App

		if (!isset($this->_appList[$request['get']])) {
			$response['handshakeError'] = "HTTP/1.1 404 Not Found";
			return false;
		}

		$app = $this->_appList[$request['get']];

		$app->acceptClient($request, $response);
	}

	public function onHandshakeComplete(\Gpws\Interfaces\Socket $socket, array $request) {
		// Find App
		$app = $this->_appList[$request['get']];

		$cObj = $app->createClient($socket);
	}
}
