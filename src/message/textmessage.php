<?php

namespace Gpws\Message;

/* Message Class should be immutable so that we don't waste memory creating copies */

class TextMessage implements \Gpws\Interfaces\OutboundMessage {

    private $content;

    public function __construct(string $text) {
        $this->content = $message;
    }

    public function getContent() {
        return $this->content;
    }
}