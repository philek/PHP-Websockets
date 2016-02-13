<?php

namespace Gpws\Interfaces;

interface OutboundMessage {
	public function getContent() : string;
}