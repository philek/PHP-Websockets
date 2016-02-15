<?php

namespace Gpws\Message;

class CloseMessage extends OutboundMessage {

	public function __construct(string $text = '') {
		parent::__construct('close', $text);
	}

}