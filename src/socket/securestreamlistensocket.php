<?php

namespace Gpws\Socket;

class SecureStreamListenSocket extends ListenSocket {
	protected function _rawCreate(string $addr, int $port) {
		$errno = $errstr = null;
		$options = array(
			'ssl' => array(
//				'peer_name' => 'fake.com',
				'verify_peer' => false,
				'allow_self_signed' => true,
				'local_cert' => 'filename.pem',
//				'local_pk' => 'serverkey.pem',
//				'disable_compression' => true,
				'passphrase' => 'blabla',

//				'SNI_enabled' => true,
//				'ciphers' => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:ECDHE-RSA-RC4-SHA:ECDHE-ECDSA-RC4-SHA:AES128:AES256:RC4-SHA:HIGH:!aNULL:!eNULL:!EXPORT:!DES:!3DES:!MD5:!PSK',
			),
		);

		$context = stream_context_create($options);
		$socket = stream_socket_server(
			'tcp://' . $addr . ':' . $port,
			$errno,
			$errstr,
			STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
			$context
		);

stream_set_blocking($socket, false);

		return $socket;
	}

	public function _rawAccept() {
		return stream_socket_accept($this->_socket);
	}

}
