<?php
include "session.php";
include "functions.php";

/*

$getstream = $db->query("SELECT * FROM `streams_sys` WHERE `pid` = -1 ORDER BY `monitor_pid` DESC");
	while($getstreamrow = $getstream->fetch_array()){
		$id = $getstreamrow['stream_id'];
		$url = '["http://live.web24.live:8080/live/recycle_n24/network24_selfrestream/1046.ts"]';
	
		$markdown = $db->query("UPDATE `streams` SET `stream_source`='$url' WHERE `id`='$id'");
		
		echo $id.' : '.$getstreamrow['stream_display_name'].'<br>';
	}
	
*/


$getstream = $db->query("SELECT (SELECT COUNT(`id`) FROM `epg_data` WHERE `epg_data`.`epg_id` = `streams`.`epg_id` AND `epg_data`.`channel_id` = `streams`.`channel_id`) AS `count_epg`, `streams`.`id`, `streams`.`type`, `streams`.`stream_icon`, `streams`.`cchannel_rsources`, `streams`.`stream_source`, `streams`.`stream_display_name`, `streams`.`tv_archive_duration`, `streams_sys`.`server_id`, `streams`.`notes`, `streams`.`direct_source`, `streams_sys`.`pid`, `streams_sys`.`monitor_pid`, `streams_sys`.`stream_status`, `streams_sys`.`stream_started`, `streams_sys`.`stream_info`, `streams_sys`.`current_source`, `streams_sys`.`bitrate`, `streams_sys`.`progress_info`, `streams_sys`.`on_demand`, `stream_categories`.`category_name`, `streaming_servers`.`server_name`, (SELECT COUNT(*) FROM `user_activity_now` WHERE `user_activity_now`.`server_id` = `streams_sys`.`server_id` AND `user_activity_now`.`stream_id` = `streams`.`id`) AS `clients` FROM `streams` LEFT JOIN `streams_sys` ON `streams_sys`.`stream_id` = `streams`.`id` LEFT JOIN `stream_categories` ON `stream_categories`.`id` = `streams`.`category_id` LEFT JOIN `streaming_servers` ON `streaming_servers`.`id` = `streams_sys`.`server_id` WHERE `streams`.`type` in (1,3) AND ((`streams_sys`.`monitor_pid` IS NOT NULL AND `streams_sys`.`monitor_pid` > 0) AND (`streams_sys`.`pid` IS NULL OR `streams_sys`.`pid` <= 0) AND `streams_sys`.`stream_status` <> 0) ORDER BY `streams`.`id` desc");
	while($getstreamrow = $getstream->fetch_array()){
		$id = $getstreamrow['id'];
		$url = '["http://live.web24.live:8080/live/recycle_n24/network24_selfrestream/1046.ts"]';
		//$markdown = $db->query("UPDATE `streams` SET `stream_source`='$url' WHERE `id`='62269'");
		$markdown = $db->query("UPDATE `streams` SET `stream_source`='$url' WHERE `id`='$id'");
		
		echo $id.' : '.$getstreamrow['stream_display_name'].'<br>';
	}	

?>