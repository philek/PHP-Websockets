<?php

namespace Gpws\Interfaces;

interface EventEmitter {
	public function addListener(string $event, callable $handler);
	public function removeListener(string $event, callable $handler);
}