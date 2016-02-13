<?php

namespace Gpws\Interfaces;

interface Application {
	public function onHandshake(array &$headers);

	public function createClient(\Gpws\Interfaces\Socket $socket) : \Gpws\Interfaces\Client;

	public function onConnect(\Gpws\Interfaces\Client $client);
	public function onMessage(\Gpws\Interfaces\Client $client);
	public function onDisconnect(\Gpws\Interfaces\Client $client);
}