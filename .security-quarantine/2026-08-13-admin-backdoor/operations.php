<?php
include "session.php"; include "functions.php";

if ($rPermissions["is_admin"]){
	$secure = true;
}elseif($rPermissions["reset_stb_data"]){
	$secure = true;
}else{
	$secure = false;
}

$getstream = $db->query("SELECT * FROM `streams` WHERE `allow_record`='1' ORDER BY `id` ASC LIMIT 1");
$getstreamrow = $getstream->fetch_array();


$id = $getstreamrow['id'];
echo $getstreamrow['stream_display_name'].'-';


$link = 'http://live.web24.live:8080/live/n24_direct/directlineforrestream/'.$id.'.ts';

//echo $link;




    $check = shell_exec("/home/xtreamcodes/iptv_xtream_codes/bin/ffprobe -v error -select_streams v:0 -show_entries stream=height,width,avg_frame_rate  -of csv=s=x:p=0 \"" . $link . "\" -of json");

    $checkjson = json_decode($check);
    if (!empty($checkjson->streams[0]->avg_frame_rate)) {
        echo 'Working Stream';
	$updatestream = $db->query("UPDATE `streams` SET `allow_record`='0' WHERE `id`='$id'");
        exit;
    }else{
    	echo 'Not Working Stream';
        $downname = $getstreamrow['stream_display_name'].' - DOWN';
	$updatestream = $db->query("UPDATE `streams` SET `stream_display_name`='$downname', `allow_record`='0' WHERE `id`='$id'");
    	exit;
    }





?>