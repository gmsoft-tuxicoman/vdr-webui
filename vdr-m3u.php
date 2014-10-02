<?php
header("Content-type: application/x-mpegurl");
header("Content-Disposition: attachment; filename=" . $m3u_filename);

include 'vdr.php';
include 'config.php';

$vdr = vdr_open($vdr_host, $vdr_port);
$channels = vdr_get_channels($vdr, $vdr_stream_url_base);
vdr_close($vdr);

$proto = "http://";
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
	$proto = "https://";

$creds = '';
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
	$creds = urlencode($_SERVER['PHP_AUTH_USER']) . ':' . urlencode($_SERVER['PHP_AUTH_PW']) . '@';

$host = $_SERVER['HTTP_HOST'];

print "#EXTM3U\n";

foreach ($channels as $chan) {
	print "#EXTINF:-1," . $chan['num'] . " " . $chan['name'] . "\n";
	print $proto . $creds . $host . $chan['url'] . "\n";
}


?>
