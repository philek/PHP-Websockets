<?php

namespace Gpws\Interfaces;

interface Server {
	public function bind(string $ip, int $port);

	public function registerApp(string $path, \Gpws\Interfaces\Application $app);

	public function run();
}