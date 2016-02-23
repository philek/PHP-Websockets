#!/usr/bin/env php
<?php

namespace Phpws;

if (in_array('perf', $GLOBALS['argv'])) define('NOOUTPUT', 1);

define('SERVER_NAME', 'ghedipunk/PHP-Websockets-0.0~fr0.2tls');


require_once(__DIR__ . '/src/bootstrap.php');

class MyApplication extends \Gpws\Core\Application {
	public function onMessage(\Gpws\Interfaces\Client $client, \Gpws\Interfaces\Message $message) {
		$content = $message->getContent();
		$binary = $message->getType() == $message::TYPE_BINARY;

if (!defined('NOOUTPUT')) 		printf('[App] New message (Length: %d Binary == %d)%s', strlen($content), $binary, PHP_EOL);

		$client->queueMessage($message);

	}
}


$myApp = new MyApplication();


$ws = new \Gpws\Core\Server(
	array(
		'EventLoopClass' => '\Gpws\EventLoop\StreamSelectLoop',
		'ServerSocketClass' => '\Gpws\Socket\SecureStreamListenSocket',
		'ClientSocketClass' => '\Gpws\Socket\SecureStreamClientSocket',
	)
);

$ws->bind('0.0.0.0', 6110);

$ws->registerApp('/', $myApp);

$ws->run();
