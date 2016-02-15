<?php

namespace Gpws\Message;

class InboundMessage implements \Gpws\Interfaces\InboundMessage {
	private $_ready = false;
	private $_started = false;

	public function isReady() : bool {
		return $this->_ready;
	}

	public function getContent() : string {
		assert($this->_ready);
		return $this->_data;
	}

	public function isBinary() : bool {
		assert($this->_ready);
		return $this->_binary;
	}


	private $_binary = NULL;
	private $_data = NULL;

	public function addFrame(\Gpws\Core\Frame $frame) : bool {
		assert(in_array($frame->getType(), array(0,1,2)));

		if ($frame->getType() == 0) {
if (!defined('NOOUTPUT')) printf("[DeFrame] Fragmented Frame Received.%s", PHP_EOL);

			if (!$this->_started) {
if (!defined('NOOUTPUT')) printf("[DeFrame] Fragmented Frame with nothing to continue. Aborting.%s", PHP_EOL);

				return false;
			}
		} else {
			if ($this->_started) {
if (!defined('NOOUTPUT')) printf("[DeFrame] Expecting continuation packet. Aborting.%s", PHP_EOL);
				return false;
			}

			$this->_started = true;
			$this->_binary = $frame->getType() == 2;
		}

		$this->_data .= $frame->getPayload();

		if ($frame->isFin()) {
			if (!$this->_binary && !self::isValidUTF8($this->_data)) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Invalid UTF8 received %s. Aborting connection.%s', $this->_data, PHP_EOL);
				return false;
			}

			$this->_ready = true;
		}

		return true;
	}

	private static function isValidUTF8($string) {
		return preg_match('//u', $string);

		return preg_match('%^(?:
      [\x09\x0A\x0D\x20-\x7E]            # ASCII
    | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
    | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
    | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
    | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
    | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
    | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
    | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
)*$%xs', $string);
	}
}