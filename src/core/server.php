<?php

namespace Gpws\Core;

class Server implements \Gpws\Interfaces\Server {
	private $_config = array(
		'EventLoopClass' => '\Gpws\EventLoop\SelectLoop',
		'ServerSocketClass' => '\Gpws\Socket\BasicListenSocket',
		'ClientSocketClass' => '\Gpws\Socket\BasicClientSocket'
	);


	private $_eventLoop = NULL;

	public function __construct($config = NULL) {
		if ($config) $this->_config = $config + $this->_config;

		$this->_eventLoop = new $this->_config['EventLoopClass'];




		$this->_eventLoop->addTimer(20, function() {
static $_stored;

$formatBytes = function($bytes, $precision = 2) {if ($bytes < 0) {$sign = '-';$bytes *= -1;} else {$sign = '';}$units = array('B', 'KB', 'MB', 'GB', 'TB');$bytes = max($bytes, 0);$pow = floor(($bytes ? log($bytes) : 0) / log(1024));$pow = min($pow, count($units) - 1);$bytes /= (1 << (10 * $pow));return $sign . round($bytes, $precision) . ' ' . $units[$pow];};
$getMemoryStats = function() {$memoryData = array();$memoryData[] = memory_get_usage();$memoryData[] = memory_get_peak_usage();$memoryData[] = memory_get_usage(true);$memoryData[] = memory_get_peak_usage(true);return $memoryData;};

$before = $_stored;
$_stored = $after = call_user_func($getMemoryStats);

	if ($before) {
		vprintf('Memory usage change: %s / %s (%s / %s)', array_map($formatBytes, array_map(function($a, $b) { return $a - $b; }, $after, $before)));
		echo PHP_EOL;
	}
	vprintf('Current usage: %s / %s (%s / %s)', array_map($formatBytes, $after));
	echo PHP_EOL;


	vprintf('Current Object Counts: %d %d %d %d %d %d %s', array(
\Gpws\Core\GenericClient::$counter,
\Gpws\Core\Client::$counter,
\Gpws\Core\MessageBuilder::$counter,
\Gpws\Socket\Socket::$opencounter,
\Gpws\Socket\ClientSocket::$cscounter,
\Gpws\EventLoop\SelectLoop::$socketcount,
PHP_EOL));


		});
	}

	private $_appList = array();

	public function registerApp(string $path, \Gpws\Interfaces\Application $app) {
		$this->_appList[$path] = $app;
	}

	public function run() {
		$this->_eventLoop->run();
	}

	public function bind(string $host, int $port) {
		$sObj = new $this->_config['ServerSocketClass']($host, $port);

		$sObj->addListener('onConnectionReady', array($this, 'onConnectionReady'));

		$this->_eventLoop->addSocket($sObj);
	}

	public function onConnectionReady(\Gpws\Interfaces\Socket $listenObj) {
		$rawSocket = $listenObj->getWaitingConnection();

// Error Checking.

		$clientSocket = new $this->_config['ClientSocketClass']($rawSocket);

		$potentialClient = new GenericClient($clientSocket);

		$potentialClient->addListener('onHandshake', array($this, 'onHandshake'));

		$this->_eventLoop->addSocket($clientSocket);
		$clientSocket->addListener('onClose', function(\Gpws\Interfaces\Socket $clientSocket) {
			$this->_eventLoop->delSocket($clientSocket);
		});
	}

	public function onHandshake(\Gpws\Core\GenericClient $potentialClient, array $request, array &$response) {
		// Find App
		if (!isset($this->_appList[$request['get']])) {
			$response['handshakeError'] = "HTTP/1.1 404 Not Found";
			return false;
		}

		$app = $this->_appList[$request['get']];
		$clientAccepted = $app->acceptClient($potentialClient, $request, $response);

		assert($clientAccepted || !empty($response['handshakeError']));
	}
}
