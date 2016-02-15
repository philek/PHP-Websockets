<?php

namespace Gpws\Interfaces;

interface InboundMessage extends Message {
	public function getSender() : Client;

}