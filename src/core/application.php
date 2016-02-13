<?php
/**
 * Contains the core Websockets server class
 */

namespace Gpws\Core;

class Application implements \Gpws\Interfaces\Application {
	private $headerOriginRequired = false;
	private $headerProtocolRequired = false;

	public function onHandshake(array &$headers) {
		if (($this->headerOriginRequired && !isset($headers['origin']) ) || ($this->headerOriginRequired && !$this->checkOrigin($headers['origin']))) {
			$headers['__handshakeResponse'] = "HTTP/1.1 403 Forbidden";
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

	}

	public function createClient(\Gpws\Interfaces\Socket $socket) : \Gpws\Interfaces\Client {
		$cObj = new Client($socket);

		$cObj->onConnect = array($this, 'onConnect');
		$cObj->onMessage = array($this, 'onMessage');
		$cObj->onDisconnect = array($this, 'onDisconnect');




		return $cObj;
	}

	public function onConnect(\Gpws\Interfaces\Client $client) {


	}
	public function onMessage(\Gpws\Interfaces\Client $client) {


	}
	public function onDisconnect(\Gpws\Interfaces\Client $client) {


	}

}