<?php

namespace Gpws\Interfaces;

interface Socket {
	const SOCKET_READ = 1;
	const SOCKET_WRITE = 2;

	public function getId() : int;
	public function getHandle();

	public function read();
	public function write();

	public function registerOnStateChanged(callable $func);
	public function getState() : int;
}