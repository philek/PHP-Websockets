<?php

namespace Gpws\Interfaces;

interface InboundMessage {
	public function getSender() : Client;
	public function getContent() : string;
}