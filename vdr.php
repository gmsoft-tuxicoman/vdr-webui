<?php

function vdr_open($host, $port) {

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if (!$socket)
		die("ERROR : Unable to create socket");

	if (!socket_connect($socket, $host, $port)) {
		die("ERROR : Unable to connect to $host : ".  socket_strerror(socket_last_error($socket)));
		$socket_close($socket);
	}

	$vdr['socket'] = $socket;
	$banner = vdr_read($vdr);

	if ($banner['code'] != 220)
		die("Error while opening vdr : " . $banner['txt']);

	$vdr['banner'] = $banner['txt'];

	return $vdr;
}

function vdr_cmd($vdr, $cmd) {
	$cmd = $cmd . "\n";
	return socket_write($vdr['socket'], $cmd, strlen($cmd));
}

function vdr_read($vdr) {
	$len = 4096;
	$out = socket_read($vdr['socket'], $len, PHP_NORMAL_READ);

	while (strlen($out) == 1)
		$out = socket_read($vdr['socket'], $len, PHP_NORMAL_READ);
	//print "OUT (" . strlen($out) . "): " . $out . "\n";
	
	if (!strlen($out))
		return null;

	$reply['code'] = substr($out, 0, 3);

	$last = substr($out, 3, 1);
	if ($last == '-')
		$reply['last'] = false;
	elseif ($last == ' ')
		$reply['last'] = true;
	else
		return null;

	$reply['txt'] = trim(substr($out, 4));


	return $reply;
}

function vdr_close($vdr) {
	vdr_cmd($vdr, "QUIT");
	while (true) {
		$reply = vdr_read($vdr);
		if ($reply === null || $reply['code'] == 221)
			break;
	}

	return socket_close($vdr['socket']);
}



function vdr_get_channels($vdr) {

	$chans = [];

	$last_group = '';

	//vdr_cmd($vdr, "LSTC :groups");
	vdr_cmd($vdr, "LSTC");

	while (true) {
		$reply = vdr_read($vdr);
		if ($reply === null)
			return  null;
		$chan = [];

		$splitted = explode(" ", $reply['txt'], 2);
		$num = $splitted[0];

		if ($num == 0 && substr($splitted[1], 0, 1) == ":") {
			$last_group = substr($splitted[1], 1);
			continue;
		}

		$chan['num'] = $num;

		$splitted = explode(":", $splitted[1]);

		$chan_name_prov = explode(";", $splitted[0]);
		$chan['group'] = $last_group;
		$chan['name'] = $chan_name_prov[0];
		if (isset($chan_name_prov[1]))
			$chan['provider'] = $chan_name_prov[1];
		$chan['freq'] = $splitted[1];
		$chan['source'] = $splitted[3];
		$chan['symrate'] = $splitted[4];
		$vpid = explode("=", $splitted[5]);
		$chan['vpid'] = $vpid[0];
		if ($chan['vpid'] != "0") {
			if (isset($vpid[1]))
				$chan['vtype'] = $vpid[1];
			else
				$chan['vtype'] = "0";
		}
		$chan['apid'] = $splitted[6];
		$chan['tpid'] = $splitted[7];
		$chan['caid'] = $splitted[8];
		$chan['sid'] = $splitted[9];
		$chan['nid'] = $splitted[10];
		$chan['tid'] = $splitted[11];
		$chan['url'] = $chan['source'] . "-" . $chan['nid'] . "-" . $chan['tid'] . "-" . $chan['sid'] . ".ts";

		$chans[$num] = $chan;

		if ($reply['last'])
			break;
	}

	return $chans;
}

function vdr_get_epg($vdr, $channel) {

	$events = [];
	$chan_id = intval($channel);

	vdr_cmd($vdr, "LSTE " . $chan_id);
	$event = [];
	
	while (true) {
		$reply = vdr_read($vdr);
		if ($reply == null)
			return null;

		if ($reply['last'])
			break;

		$type = substr($reply['txt'], 0, 1);
		$txt = substr($reply['txt'], 2);


		switch ($type) {
			case 'C':
				// We know which chan we asked for
				break;
			case 'E':
				sscanf($txt, "%u %ld %d", $event["id"], $event["start"], $event["duration"]);
				break;
			case 'T':
				$event['title'] = $txt;
				break;
			case 'S':
				if (strlen($txt))
					$event['short_txt'] = $txt;
				break;
			case 'D':
				if (strlen($txt))
					$event['description'] = $txt;
				break;
			case 'e':
				$events[] = $event;
				$event = [];
				break;
			default:
				break;
		}
		
	}
	return $events;
}

?>
