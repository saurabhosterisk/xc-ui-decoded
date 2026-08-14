<?php
/*Rev:26.09.18r0*/

set_time_limit(0);
require './init.php';
$streaming_block = true;
if (!empty(ipTV_lib::$request['username']) && !empty(ipTV_lib::$request['password'])) {
    $username = ipTV_lib::$request['username'];
    $password = ipTV_lib::$request['password'];
    $previousDays = empty(ipTV_lib::$request['prev_days']) ? 1 : abs(intval(ipTV_lib::$request['prev_days']));
    $nextDays = empty(ipTV_lib::$request['next_days']) ? 1 : abs(intval(ipTV_lib::$request['next_days']));
    ini_set('memory_limit', -1);
    if ($result = ipTV_streaming::GetUserInfo(null, $username, $password, true, true, true)) {
        if ((is_null($result['exp_date']) or $result['exp_date'] > time()) and $result['admin_enabled'] == 1 and $result['enabled'] == 1) {
            $streaming_block = false;
            header('Content-Type: application/xml; charset=utf-8');
            $serverName = htmlspecialchars(ipTV_lib::$settings['server_name'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
            echo '<?xml version="1.0" encoding="utf-8" ?><!DOCTYPE tv SYSTEM "xmltv.dtd">';
            echo "<tv generator-info-name=\"{$serverName}\" generator-info-url=\"" . ipTV_lib::$StreamingServers[SERVER_ID]['site_url'] . '">';
            $ipTV_db->query('SELECT `stream_display_name`,`stream_icon`,`channel_id`,`epg_id` FROM `streams` WHERE `epg_id` IS NOT NULL');
            $rows = $ipTV_db->get_rows();
            $epgIds = array();
            foreach ($rows as $row) {
                $displayName = htmlspecialchars($row['stream_display_name'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
                $stream_icon = htmlspecialchars($row['stream_icon'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
                $channelId = htmlspecialchars($row['channel_id'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
                echo "<channel id=\"{$channelId}\">";
                echo "<display-name>{$displayName}</display-name>";
                if (!empty($row['stream_icon'])) {
                    echo "<icon src=\"{$stream_icon}\" />";
                }
                echo '</channel>';
                $epgIds[] = $row['epg_id'];
            }
            $epgIds = array_unique($epgIds);
            $query = mysqli_query($ipTV_db->dbh, 'SELECT * FROM `epg_data` WHERE `epg_id` IN(' . implode(',', $epgIds) . ') AND `start` BETWEEN \'' . date('Y-m-d H:i:00', strtotime("-{$previousDays} day")) . '\' AND \'' . date('Y-m-d H:i:00', strtotime("+{$nextDays} day")) . '\'', MYSQLI_USE_RESULT);
            //f1bcbc646b7caf73aa5b0b71be389f78:
            while ($row = mysqli_fetch_assoc($query)) {
                $title = htmlspecialchars(base64_decode($row['title']), ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
                $desc = htmlspecialchars(base64_decode($row['description']), ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
                $channelId = htmlspecialchars($row['channel_id'], ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
                $epgStart = new DateTime($row['start']);
                $epgEnd = new DateTime($row['end']);
                $start = $epgStart->format('YmdHis O');
                $end = $epgEnd->format('YmdHis O');
                echo "<programme start=\"{$start}\" stop=\"{$end}\" channel=\"{$channelId}\" >";
                echo '<title>' . $title . '</title>';
                echo '<desc>' . $desc . '</desc>';
                echo '</programme>';
            }
            //cbb7c0585d6bcc07f8df162c7bd39253:
            echo '</tv>';
        }
    }
}
if ($streaming_block) {
    http_response_code(401);
    CheckFlood();
}
?>
