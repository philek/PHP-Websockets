<?php

namespace Gpws\Message;

/* Message Class should be immutable so that we don't waste memory creating copies */

class TextMessage extends OutboundMessage {

	public function __construct(string $text = '') {
		parent::__construct('text', $text);
	}
}