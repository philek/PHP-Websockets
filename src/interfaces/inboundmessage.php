<?php

namespace Gpws\Interfaces;

interface InboundMessage extends Message {
	public function addFrame(\Gpws\Core\Frame $buffer) : bool;

	public function isReady() : bool;
	public function isBinary() : bool;
}