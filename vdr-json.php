<?php

include 'vdr.php';
include 'config.php';


$method = $_GET["method"];


$vdr = vdr_open($vdr_host, $vdr_port);


$output = "Unknown method";

if ($method == "channels") {
	$output = vdr_get_channels($vdr);
	if (isset($max_channels))
		$output = array_slice($output, 0, $max_channels);
} elseif ($method == "epg") {

	$chan = $_GET["channel"];
	$output = vdr_get_epg($vdr, $chan);
}

vdr_close($vdr);

print json_encode($output);

?>
