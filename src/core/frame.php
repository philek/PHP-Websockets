<?php

namespace Gpws\Core;

class Frame implements \Gpws\Interfaces\Frame {
	private $_ready = false;
	private $_invalid = false;

	public function isReady() : bool {
		return $this->_ready;
	}

	public function isInvalid() : bool {
		return $this->_invalid;
	}

	public function getOpcode() : int {
		assert($this->_ready);
		return $this->_header['opcode'];
	}

	public function isFin() : bool {
		assert($this->_ready);
		return $this->_header['fin'] == 1;
	}

	public function getPayload() : string {
		assert($this->_ready);
		return $this->_payload;
	}


// TODO TEMPORARY, CREATE GETTErS
	public $_header = NULL;
	private $_payload = '';

	public function addData(string $buffer) : int {
		if (!$this->_header) {
			if (!$this->extractHeader($buffer)) {
				return 0;
			}

			$offset = $this->_header['offset'];

			if (!$this->verifyHeader()) {
				return -1;
			}

		} else {
			$offset = 0;
		}

		if ($this->_header['length']) {
			$need = $this->_header['length'] - strlen($this->_payload);
			$take = min($need, strlen($buffer) - $offset);

			$this->_payload .= substr($buffer, $offset, $take);
		} else {
			$take = 0;
		}

		if (strlen($this->_payload) == $this->_header['length']) {
			$this->finalizeFrame();
		}

		return $offset + $take;
	}


	protected function extractHeader($message) {
		if (strlen($message) < 2) return false;

		$header = array(
			'fin'       => ord($message[0])>>7 & 1,
			'rsv1'      => ord($message[0])>>6 & 1,
			'rsv2'      => ord($message[0])>>5 & 1,
			'rsv3'      => ord($message[0])>>4 & 1,
			'opcode'    => ord($message[0] & chr(15)),
			'hasmask'   => ord($message[1])>>7 & 1,
			'length'    => 0,
			'indexMask' => 2,
			'offset'    => 0,
			'mask'      => ""
		);

		$header['length'] = ord($message[1] & chr(127)) ;
	
		if ($header['length'] == 126) {
			if (strlen($message) < 4) return false;

			$header['indexMask'] = 4;
			$header['length'] =  (ord($message[2])<<8) | (ord($message[3])) ;
		} else if ($header['length'] == 127) {
			if (strlen($message) < 10) return false;

			$header['indexMask'] = 10;
			$header['length'] = (ord($message[2]) << 56 ) | ( ord($message[3]) << 48 ) | ( ord($message[4]) << 40 ) | (ord($message[5]) << 32 ) | ( ord($message[6]) << 24 ) | ( ord($message[7]) << 16 ) | (ord($message[8]) << 8  ) | ( ord($message[9]));
		} 

		$header['offset'] = $header['indexMask'];
		if ($header['hasmask']) {
			if (strlen($message) < $header['indexMask'] + 4) return false;

			$header['mask'] = $message[$header['indexMask']] . $message[$header['indexMask']+1] . $message[$header['indexMask']+2] . $message[$header['indexMask']+3];
			$header['offset'] += 4;
		}

		$this->_header = $header;

		return true;
	}


	protected function verifyHeader() : bool {
		if (!in_array($this->_header['opcode'], array(0, 1, 2, 8, 9, 10))) {

			$this->_invalid = true;

			return false;
		}

		if ($this->_header['opcode'] == 9 || $this->_header['opcode'] == 10) {
			if ($this->_header['length'] > 125) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Invalid ping/pong - too long.' . PHP_EOL);
				$this->_invalid = true;
				return false;
			}

			if (!$this->_header['fin']) {
if (!defined('NOOUTPUT')) printf('[DeFrame] Ping/Pong cannot be fragmented.'.PHP_EOL);
				$this->_invalid = true;

				return false;
			}
		}

		return true;
	}

	protected function finalizeFrame() {
		if ($this->_header['mask']) {
			$this->applyMask();
		}

		$this->_ready = true;
	}


	protected function applyMask() {
		$effectiveMask = str_repeat($this->_header['mask'], ($this->_header['length'] / 4) + 1);

//		$over = $headers['length'] - strlen($effectiveMask);
//		$effectiveMask = substr($effectiveMask, 0, $over);

		$effectiveMask = substr($effectiveMask, 0, $this->_header['length']);
		
		$this->_payload = $effectiveMask ^ $this->_payload;
	}

}