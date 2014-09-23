$(document).ready( function() {
	vdr.init();


	setInterval(function() {
		var now = new Date();
		var day = now.getDate();
		var month = now.getMonth();
		var year = now.getFullYear();
		var hours = now.getHours();
		var minutes = now.getMinutes();
		var seconds = now.getSeconds();
		if (hours < 10)
			hours = "0" + hours;
		if (minutes < 10)
			minutes  = "0" + minutes;
		if (seconds < 10)
			seconds = "0" + seconds;
		$("#date").html(day + "/" + month + "/" + year + " " + hours + ":" + minutes + ":" + seconds);
		
		// Move the red bar
		var epg_height = $("#epg_tab")[0].clientHeight - 30;
		var bar = $("#epg_now_bar")
		var width = $("#epg_periods div:first-child").width();
		var pos = ((now.getTime() / 1000) - vdr.display_start) / epg_time_divisor;
		bar.height(epg_height)
		bar.css("margin-left",  pos + "%");
		bar.css("margin-right", "-" + pos + "%");
		
		bar.css("margin-bottom", "-" + (epg_height + 5) + "px");

	}, 1000);
});

var vdr = {};

vdr.init = function () {

	var me = this;

	var date = new Date();
	var now = date.getTime() / 1000;
	this.display_start = now - (now % epg_time_period);
	$.getJSON( "vdr-json.php", "method=channels", function(data) {

		me.channels = data;
		me.loadChannels();

	});
}

vdr.channelPiconUrl = function(chan) {

	var hash;
	src = chan['source'];

	if (src == "T") {
		hash = 0xEEEE0000;
	} else if (src == "C") {
		hash = 0xFFFF0000;
	} else if (src == "A") {
		hash = 0xDDDD0000;
	} else {
		pos = parseFloat(src.substring(1, src.length - 1)) * 10;
		pos = parseInt(pos);
		if (src.charAt(src.length - 1) == 'E')
		pos = -pos;

		if (pos > 0x00007FFF)
			pos |= 0xFFFF0000;

		if (pos < 0)
			pos = -pos;
		else
			pos += 1800;

		hash = pos << 16;

	}
	var vtype = 1;
	if (chan['vpid'] == 0)
		vtype = 2;
	else if (chan['vtype'] == 27)
		vtype = 19;
	var picon_url = picons_url_base + "/1_0_" + vtype + "_" + parseInt(chan['sid']).toString(16).toUpperCase() + "_" + parseInt(chan['tid']).toString(16).toUpperCase() + "_" + parseInt(chan['nid']).toString(16).toUpperCase() + "_" + hash.toString(16).toUpperCase() + "_0_0_0.png";

	return picon_url;

}

vdr.loadChannels = function() {

	var chans = [];
	var epgs = [];
	var me = this;
	for (var idx in this.channels) {
		var chan = this.channels[idx];
		chans.push('<div class="chan_entry"><div id="chan_num" class="chan_list">' + chan['num'] + '</div><div class="chan_picon chan_list"><a href="/' + chan['url'] + '"><img id="chan_picon_' + idx + '" class="chan_picon_img"/></a></div><div id="chan_name" class="chan_list"><a href="/' + chan['url'] + '">' + chan['name'] + '</a></div></div>');
		epgs.push('<div id="chan_epg_' + idx + '" class="chan_epg">Loading epg ...</div>');
	};
	$("#chan_tab").html(chans.join(""));
	$("#epg_tab").append(epgs.join(""));

	$('.chan_picon').appear();
	$('.chan_picon').bind('appear', function() {
		var img = $(this).find('img');
		if (img.data('appeared'))
			return true;
		img.data('appeared', true);
		var chan_id = img.attr('id').replace('chan_picon_', '');
		var picon_url = me.channelPiconUrl(me.channels[chan_id]);
		img.attr('src', picon_url);
	});

	$(".chan_epg").appear();
	$(".chan_epg").bind('appear', function() {
		if ($(this).data('appeared'))
			return true;
		$(this).data('appeared', true);

		var chan_id = $(this).attr('id').replace('chan_epg_','');
		vdr.updateChanEpg(chan_id);
	});

	$.force_appear();

}

vdr.epochToHour = function(epoch) {

	var date = new Date(0);
	date.setUTCSeconds(epoch);
	var hour = date.getHours();
	var min = date.getMinutes();
	if (hour < 10)
		hour = "0" + hour;
	if (min < 10)
		min = "0" + min;

	return hour + ':' + min;
}

vdr.updateChanEpg = function(chan_id) {

	var me = this;

	var chan_num = me.channels[chan_id]['num'];

	$.getJSON("vdr-json.php", "method=epg&channel=" + chan_num, function(epg) {
		me.channels[chan_id].epg = epg;

		var epgs = [];
		var first = true;

		var end; // Remember end time of the last event

		for (var i = 0; i < epg.length; i++) {
			var evt = epg[i];

			var start = evt['start'];
			var duration = evt['duration'];
			end = start + duration;

			if (end <= me.display_start) // Skip events which are over
				continue;

			if (first) {
				// Perform some adjustment for the first event
				if (start > me.display_start) {
					// We need to add some space before the event
					var fill = (start - me.display_start) / epg_time_divisor;
					epgs.push('<div class="epg_event_container" style="width:' + fill + '%"></div>');
				} else if (start < me.display_start) {
					// Remove the begining of the event
					duration -= me.display_start - start;
				}
				first = false;
			}


			var size = duration / epg_time_divisor;
			var evt_text = evt['title'];
			if ('short_txt' in evt)
				evt_text += ' - ' + evt['short_txt'];
			var evt_tooltip = me.epochToHour(start) + ' - ' + me.epochToHour(end) + '\n' + evt_text;
			if ('description' in evt)
				evt_tooltip += '\n' + evt['description'];
			epgs.push('<div class="epg_event_container hidden" style="width:' + size + '%"><div class="epg_event" title="' + evt_tooltip + '">' + evt_text + '</div></div>');
		}

		// Check if we have the time displayed for at least the end of this event + period_min
		for (i = me.display_start; i < end + epg_time_period; i += epg_time_period) {
			if (!$("#epg_periods #epg_period_" + i).length) {

				var size = epg_time_period / epg_time_divisor;
				$("#epg_periods").append('<div id="epg_period_' + i + '" class="epg_period_container" style="width:' + size + '%"><div class="epg_period">' + me.epochToHour(i) + '</div></div>');
			}
		}


		$("#chan_epg_" + chan_id).html(epgs.join(""));

	});

}
