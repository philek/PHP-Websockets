<?php

namespace Gpws\Core;

class MessageFramer {

	public function getFramedMessage(\Gpws\Interfaces\Message $message) : string {
		if (!isset($message->_framed)) {
			$message->_framed = self::frame($message->getContent(), $message->getType(), false);
		}

		return $message->_framed;
	}


	protected static function frame($messagePayload, $messageType = 'text', $messageContinues = false) {
		$bytes = array();

		switch ($messageType) {
			case \Gpws\Interfaces\Message::TYPE_TEXT:
				$bytes[1] = ($messageContinues) ? 0 : 1;
				break;
			case \Gpws\Interfaces\Message::TYPE_BINARY:
				$bytes[1] = ($messageContinues) ? 0 : 2;
				break;
			case \Gpws\Interfaces\Message::TYPE_CLOSE:
				$bytes[1] = 8;
				break;
			case \Gpws\Interfaces\Message::TYPE_PING:
				$bytes[1] = 9;
				break;
			case \Gpws\Interfaces\Message::TYPE_PONG:
				$bytes[1] = 10;
				break;
		}

		if (!$messageContinues) {
			$bytes[1] += 128;
		} 

		$length = strlen($messagePayload);

		if ($length < 126) {
			$bytes[2] = $length;
		} else if ($length < 65536) {
			$bytes[2] = 126;
			$bytes[3] = ( $length >> 8 ) & 255;
			$bytes[4] = ( $length      ) & 255;
		} else {
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

		return $headers . $messagePayload;
	}
}