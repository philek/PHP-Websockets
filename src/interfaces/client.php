<?php

namespace Gpws\Interfaces;

interface Client {
//	public function getConnection() : resource;

	public function queueMessage(\Gpws\Interfaces\Message $message) : int;
}