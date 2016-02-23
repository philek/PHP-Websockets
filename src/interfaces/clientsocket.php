<?php

namespace Gpws\Interfaces;

interface ClientSocket extends Socket {
	public function read($maxBytes = NULL);

	public function writeBufferEmpty();

	public function send(string $buffer);

	public function close();
}