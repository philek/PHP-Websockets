<?php

namespace Gpws\Message;

/* Message Class should be immutable so that we don't waste memory creating copies */

abstract class Message implements \Gpws\Interfaces\Message {

	private $content;
	private $type;

	public function __construct(int $type, string $text) {
		$this->content = $text;
		$this->type = $type;
	}

	public function getContent() : string {
		return $this->content;
	}

	public function getType() : int {
		return $this->type;
	}

}