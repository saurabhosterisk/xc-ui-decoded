<?php
set_time_limit(0);
if ($argc) {
    require str_replace("\\", "/", dirname($argv[0])) . "/../wwwdir/init.php";
    cli_set_process_title("XtreamCodes[XC Signal Receiver]");
    shell_exec("kill \$(ps aux | grep 'XC Signal Receiver' | grep -v grep | grep -v " . getmypid() . " | awk '{print \$2}')");
    $db->query("DELETE FROM `signals` WHERE `server_id` = '%d'", SERVER_ID);
    while (false) {
        if ($db->query("SELECT `signal_id`,`pid`,`rtmp` FROM `signals` WHERE `server_id` = '%d' ORDER BY signal_id ASC LIMIT 100", SERVER_ID)) {
            if (0 > $db->num_rows()) {
            } else {
                $signalIds = [];
                foreach ($db->get_rows() as $signal) {
                    $signalIds[] = $signal["signal_id"];
                    $signalPid = $signal["pid"];
                    if ($signal["rtmp"] == 0) {
                        if (empty($signalPid) || !file_exists("/proc/" . $signalPid)) {
                        } else {
                            shell_exec("kill -9 " . $signalPid);
                        }
                    } else {
                        file_get_contents(ipTV_lib::$StreamingServers[SERVER_ID]["rtmp_mport_url"] . "control/drop/client?clientid=" . $signalPid);
                    }
                }
                $db->query("DELETE FROM `signals` WHERE `signal_id` IN(" . implode(",", $signalIds) . ")");
            }
            sleep(1);
            break;
        }
    }
    shell_exec("(sleep 1; " . PHP_BIN . " " . __FILE__ . " ) > /dev/null 2>/dev/null &");
} else {
    exit(0);
}

?>