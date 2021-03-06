<?php require_once "config.php" ?>
<!DOCTYPE html>
<html>
<head>
	<title><?php print $site_title; ?></title>
	<meta charset="utf-8"/>
	<link rel="stylesheet" href="vdr.css"/>
	<script src="<?php print $jquery_url; ?>"></script>
	<script src="vdr.js"></script>
	<script src="config.js"></script>
</head>
<body>

<div id="header">
	<div id="title"><?php print $site_title ?></div>
	<div id="date"></div>
</div>

<div id="main">
	<div id="chan_tab"><div id="toolbox"><a href="vdr-m3u.php">Playlist</a></div><div class="loading">Loading channels ...</div></div>
	<div id="epg_tab"><div id="epg_periods"></div><div id="epg_now_bar"></div></div>
</div>
</body>
</html>
