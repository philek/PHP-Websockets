<?php

namespace Gpws\Interfaces;

interface Extension {

	public function processInboundMessage(Message &$message);

	public function processOutboundMessage(Message &$message);

}