<?php

namespace Gpws\Interfaces;

interface Message {
	const TYPE_TEXT = 1;
	const TYPE_BINARY = 2;
	const TYPE_PING = 3;
	const TYPE_PONG = 4;
	const TYPE_CLOSE = 5;

	public function getType() : int;

	public function getContent() : string;
}