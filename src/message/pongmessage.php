<?php

namespace Gpws\Message;

/* Message Class should be immutable so that we don't waste memory creating copies */

class PongMessage extends OutboundMessage {

	public function __construct(string $text = '') {
		parent::__construct('pong', $text);
	}
}