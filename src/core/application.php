<?php
/**
 * Contains the core Websockets server class
 */

namespace Gpws\Core;

class Application implements \Gpws\Interfaces\Application {
	private $headerOriginRequired = false;
	private $headerProtocolRequired = false;

	public function acceptClient($genericclient, array $request, array &$response) : bool {
		if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
			$response['StatusLine'] = "HTTP/1.1 403 Forbidden";
			$response['error'] = true;
			return false;
		}

// check $headers['host'])) {

/*
		// Protocol work on message level. So you can enforce it
		$protocol = $this->checkProtocol(explode(', ',$headers['sec-websocket-protocol']));
		if (($this->headerProtocolRequired && !isset($headers['sec-websocket-protocol'])) || ($this->headerProtocolRequired && !$protocol)) {
			$handshakeResponse = "HTTP/1.1 400 Bad Request";
		} else if ($protocol){
			$user->headers["protocol"] = $protocol;
			$subProtocol = "Sec-WebSocket-Protocol: ".$protocol."\r\n";
		}

*/

		$cObj = new Client($genericclient->getSocket());

		$cObj->addListener('onConnect', array($this, 'onConnect'));
		$cObj->addListener('onMessage', array($this, 'onMessage'));
		$cObj->addListener('onDisconnect', array($this, 'onDisconnect'));

		return true;
	}

	public function onConnect(\Gpws\Interfaces\Client $client) {


	}

	public function onMessage(\Gpws\Interfaces\Client $client, \Gpws\Interfaces\Message $message) {


	}

	public function onDisconnect(\Gpws\Interfaces\Client $client) {
		$client->removeListener('onConnect', array($this, 'onConnect'));
		$client->removeListener('onMessage', array($this, 'onMessage'));
		$client->removeListener('onDisconnect', array($this, 'onDisconnect'));
	}
}