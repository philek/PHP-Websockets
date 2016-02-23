<?php

namespace Gpws\Core;

class MessageBuilder implements \Gpws\Interfaces\EventEmitter {
	use \Gpws\Core\EventEmitter;

	private $_messageQueue = array();

	public function isReady() : bool {
		return !!$this->_messageQueue;
	}

	public function getMessage() : \Gpws\Interfaces\Message {
		return array_shift($this->_messageQueue);
	}

// DEBUG ONLY
public static $counter = 0;
	public function __construct() {
++self::$counter;
	}

	public function __destruct() {
--self::$counter;
	}
// DEBUG ONLY


	private $_frameData = '';
	private $_partialFrame = NULL;

	public function addData(string $data) {
		$this->_frameData .= $data;

		while ($this->_frameData) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Loop%s', PHP_EOL);

			if ($this->_partialFrame === NULL) {
				$this->_partialFrame = new \Gpws\Core\Frame;
			}

			$numBytes = $this->_partialFrame->addData($this->_frameData);

			if ($numBytes === 0) {
				break; // Not enough data to do anything.
			}

			if ($this->_partialFrame->isInvalid()) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Invalid Frame. Must Close. %s', PHP_EOL);

				$this->raise('onError');

				break;
			}

			assert($numBytes > 0);

			$this->_frameData = substr($this->_frameData, $numBytes);

			if ($this->_partialFrame->isReady()) {
if (!defined('NOOUTPUT')) printf('[ReadLoop] Proper Frame Parsed%s', PHP_EOL);

				$this->addFrame($this->_partialFrame);

				$this->_partialFrame = NULL;
			}
		}
	}


	private function addFrame(\Gpws\Interfaces\Frame $frame) {

		if ($frame->_header['rsv1'] + $frame->_header['rsv2'] + $frame->_header['rsv3'] > 0) {
if (!defined('NOOUTPUT')) printf('[BuildMessage] Unsupported RSV Bits.%s', PHP_EOL);

			$this->raise('onError');

			return false;
		}

		switch ($frame->getOpcode()) {
			case 0:
			case 1:
			case 2:
				return $this->addDataFrame($frame);

			case 8:
			case 9:
			case 10:
				return $this->addControlFrame($frame);

			default:
				$this->raise('onError');
				return;
		}
	}


	private $_started = false;
	private $_type = NULL;
	private $_data = NULL;

	private function addDataFrame(\Gpws\Interfaces\Frame $frame) {

		if ($frame->getOpcode() == 0) {
if (!defined('NOOUTPUT')) printf("[BuildMessage] Fragmented Frame Received.%s", PHP_EOL);

			if (!$this->_started) {
if (!defined('NOOUTPUT')) printf("[BuildMessage] Fragmented Frame with nothing to continue. Aborting.%s", PHP_EOL);

				$this->raise('onError');
				return;
			}
		} else {
			if ($this->_started) {
if (!defined('NOOUTPUT')) printf("[BuildMessage] Expecting continuation packet. Aborting.%s", PHP_EOL);
				$this->raise('onError');
				return;
			}

			$this->_started = true;
			$this->_data = '';
			$this->_type = $frame->getOpcode();
		}

		$this->_data .= $frame->getPayload();

		if ($frame->isFin()) {
			if ($this->_type === 1) {
				if (!self::isValidUTF8($this->_data)) {
	if (!defined('NOOUTPUT')) printf('[BuildMessage] Invalid UTF8 received %s. Aborting connection.%s', $this->_data, PHP_EOL);
					$this->raise('onError');
					return;
				}

				$msg = new \Gpws\Message\TextMessage($this->_data);
			} else {
				$msg = new \Gpws\Message\BinaryMessage($this->_data);
			}

			$this->_messageQueue[] = $msg;
			$this->raise('onMessageReady');

			$this->_started = false;
			$this->_data = NULL;
			$this->_type = NULL;
		}

		return true;
	}



	private function addControlFrame(\Gpws\Interfaces\Frame $frame) {
		if (!$frame->isFin()) {
if (!defined('NOOUTPUT')) printf('[BuildMessage] Control Messages arent fragmented.%s', PHP_EOL);
		}

		switch ($frame->getOpcode()) {
			case 8:
				$type = '\Gpws\Message\CloseMessage';
				break;
			case 9:
				$type = '\Gpws\Message\PingMessage';
				break;
			case 10:
				$type = '\Gpws\Message\PongMessage';
				break;

		}

		$msg = new $type($frame->getPayload());

		$this->_messageQueue[] = $msg;
		$this->raise('onMessageReady');
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