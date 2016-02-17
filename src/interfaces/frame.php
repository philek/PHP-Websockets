<?php

namespace Gpws\Interfaces;

interface Frame {
	public function addData(string $buffer) : int;

	public function isReady() : bool;
	public function isInvalid() : bool;

	public function getOpcode() : int;

	public function isFin() : bool;

	public function getPayload() : string;
}