<?php

namespace Gpws\Message;

class CloseMessage extends Message {

	public function __construct(string $text = '') {
		parent::__construct(self::TYPE_CLOSE, $text);
	}

}