<?php
//泉州大白网络科技
	$server = new swoole_websocket_server("116.62.8.229", 9502);

	$server->on('open', function (swoole_websocket_server $server, $request) {
	    echo "server: handshake success with fd{$request->fd}\n";
	});

	$server->on('message', function (swoole_websocket_server $server, $frame) {
	    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
	    $server->push($frame->fd, "this is server");
	});

	$server->on('close', function ($ser, $fd) {
	    echo "client {$fd} closed\n";
	});

	$server->start();