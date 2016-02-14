#!/usr/bin/env php
<?php

namespace Phpws;

require_once(__DIR__ . '/src/bootstrap.php');

class MyApplication extends \Gpws\Core\Application {
	public function onMessage(\Gpws\Interfaces\Client $client, string $message) {
		$x = new \Gpws\Message\TextMessage('ECHOOO ' . $message);

		$client->queueMessage($x);

	}
}


$myApp = new MyApplication();


$ws = new \Gpws\Core\Server();

$ws->bind('0.0.0.0', 6100);

$ws->registerApp('/', $myApp);

$ws->run();
