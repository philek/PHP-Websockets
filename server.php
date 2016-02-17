#!/usr/bin/env php
<?php

namespace Phpws;

if (in_array('perf', $GLOBALS['argv'])) define('NOOUTPUT', 1);


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


$ws = new \Gpws\Core\Server();

$ws->bind('0.0.0.0', 6100);

$ws->registerApp('/', $myApp);

$ws->run();
