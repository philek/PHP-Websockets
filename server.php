#!/usr/bin/env php
<?php

namespace Phpws;

if (in_array('perf', $GLOBALS['argv'])) define('NOOUTPUT', 1);


require_once(__DIR__ . '/src/bootstrap.php');

class MyApplication extends \Gpws\Core\Application {
	public function onMessage(\Gpws\Interfaces\Client $client, \Gpws\Interfaces\InboundMessage $message) {
		$content = $message->getContent();
		$binary = $message->isBinary();

		printf('[App] New message (Length: %d Binary == %d)%s', strlen($content), $binary, PHP_EOL);

		if ($binary) {
			$x = new \Gpws\Message\BinaryMessage($content);
		} else {
			$x = new \Gpws\Message\TextMessage($content);
		}

		$client->queueMessage($x);

	}
}


$myApp = new MyApplication();


$ws = new \Gpws\Core\Server();

$ws->bind('0.0.0.0', 6100);

$ws->registerApp('/', $myApp);

$ws->run();
