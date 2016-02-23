<?php

namespace Gpws\Interfaces;

interface Socket {
	const SOCKET_READ = 1;
	const SOCKET_WRITE = 2;

	public function getId() : int;
	public function getHandle();

	public function doRead();
	public function doWrite();

	public function getState() : int;
}