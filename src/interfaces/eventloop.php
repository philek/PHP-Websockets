<?php

namespace Gpws\Interfaces;

interface EventLoop {
	public function run();


	public function addSocket(\Gpws\Interfaces\Socket $socket);
	public function delSocket(\Gpws\Interfaces\Socket $socket);


	public function addTimer(int $interval, callable $callback) : int;
	public function delTimer(int $timer_id);
}