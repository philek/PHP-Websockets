<?php

namespace Gpws\Interfaces;

interface Application {
	public function acceptClient($genericclient, array $request, array &$response) : bool;

	public function onConnect(\Gpws\Interfaces\Client $client);
	public function onMessage(\Gpws\Interfaces\Client $client, \Gpws\Interfaces\Message $message);
	public function onDisconnect(\Gpws\Interfaces\Client $client);
}