<?php

namespace Gpws\Message;

/* Message Class should be immutable so that we don't waste memory creating copies */

class PongMessage implements \Gpws\Interfaces\OutboundMessage {

	private $content;

	public function __construct(string $text = '') {
		$this->content = $text;
	}

	public function getContent() : string {
		return $this->frame($this->content);
	}


	protected function frame($message, $messageType = 'pong', $messageContinues = false) {
		switch ($messageType) {
			case 'continuous': $bytes[1] = 0;
				break;
			case 'text': $bytes[1] = ($messageContinues) ? 0 : 1;
				break;
			case 'binary': $bytes[1] = ($messageContinues) ? 0 : 2;
				break;
			case 'close': $bytes[1] = 8;
				break;
			case 'ping': $bytes[1] = 9;
				break;
			case 'pong': $bytes[1] = 10;
				break;
		}
		if (!$messageContinues) {
			$bytes[1] += 128;
		} 

		$length = strlen($message);
		if ($length < 126) {
			$bytes[2] = $length;
		} 
		elseif ($length <= 65536) {
			$bytes[2] = 126;
			$bytes[3] = ( $length >> 8 ) & 255;
			$bytes[4] = ( $length      ) & 255;
		} 
		else {
			$bytes[2] = 127;
			$bytes[3] = ( $length >> 56 ) & 255;
			$bytes[4] = ( $length >> 48 ) & 255;
			$bytes[5] = ( $length >> 40 ) & 255;
			$bytes[6] = ( $length >> 32 ) & 255;
			$bytes[7] = ( $length >> 24 ) & 255;
			$bytes[8] = ( $length >> 16 ) & 255;
			$bytes[9] = ( $length >>  8 ) & 255;
			$bytes[10]= ( $length       ) & 255;
		}
		$headers = "";
		foreach ($bytes as $chr) {
			$headers .= chr($chr);
		}
		return $headers . $message;
	}
}