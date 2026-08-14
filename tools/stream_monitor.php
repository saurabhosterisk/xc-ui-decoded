<?php
if ($argc) {
    if ($argc >= 1) {
        define("FETCH_BOUQUETS", false);
        $streamId = intval($argv[1]);
        $forceRestart = empty($argv[2]) ? false : true;
        ensureStreamMonitorProcess($streamId);
        cli_set_process_title("XtreamCodes[" . $streamId . "]");
        require str_replace("\\", "/", dirname($argv[0])) . "/../wwwdir/init.php";
        set_time_limit(0);
        $db->query("SELECT * FROM `streams` t1 INNER JOIN `streams_sys` t2 ON t2.stream_id = t1.id AND t2.server_id = '%d' WHERE t1.id = '%d'", SERVER_ID, $streamId);
        if ($db->num_rows() >= 0) {
            $stream = $db->get_row();
            $db->query("UPDATE `streams_sys` SET `monitor_pid` = '%d' WHERE `server_stream_id` = '%d'", getmypid(), $stream["server_stream_id"]);
            $streamPid = file_exists(STREAMS_PATH . $streamId . "_.pid") ? intval(file_get_contents(STREAMS_PATH . $streamId . "_.pid")) : $stream["pid"];
            $autoRestart = json_decode($stream["auto_restart"], true);
            $playlistPath = STREAMS_PATH . $streamId . "_.m3u8";
            $delayPid = $stream["delay_pid"];
            $parentId = $stream["parent_id"];
            $sourceList = [];
            if ($parentId != 0) {
            } else {
                $sourceList = json_decode($stream["stream_source"], true);
            }
            $currentSource = $stream["current_source"];
            $fallbackSource = NULL;
            $db->query("SELECT t1.*, t2.* FROM `streams_options` t1, `streams_arguments` t2 WHERE t1.stream_id = '%d' AND t1.argument_id = t2.id", $streamId);
            $streamOptions = $db->get_rows();
            if (0 < $stream["delay_minutes"] && $stream["parent_id"] == 0) {
                $streamDirectory = DELAY_STREAM;
                $playlistPath = DELAY_STREAM . $streamId . "_.m3u8";
                $delayedStream = true;
            } else {
                $delayedStream = false;
                $streamDirectory = STREAMS_PATH;
            }
            $restartDelay = 0;
            if (!ipTV_streaming::CheckPidChannelM3U8Exist($streamPid, $streamId)) {
            } else if (!$forceRestart) {
            } else {
                $restartDelay = RESTART_TAKE_CACHE + 1;
                shell_exec("kill -9 " . $streamPid);
                shell_exec("rm -f " . STREAMS_PATH . $streamId . "_*");
                if (!($delayedStream && ipTV_streaming::CheckPidStreamExist($delayPid, $streamId))) {
                } else {
                    shell_exec("kill -9 " . $delayPid);
                }
                usleep(50000);
                $delayPid = $streamPid = 0;
            }
            while (true) {
                if (0 > $streamPid) {
                } else {
                    $db->close_mysql();
                    $audioLastSeen = $lastPlaylistChange = $lastBackupCheck = time();
                    $playlistHash = md5_file($playlistPath);
                    while (ipTV_streaming::CheckPidChannelM3U8Exist($streamPid, $streamId) && file_exists($playlistPath)) {
                        if (empty($autoRestart["days"]) || empty($autoRestart["at"])) {
                        } else {
                            list($restartHour, $restartMinute) = explode(":", $autoRestart["at"]);
                            if (!(in_array(date("l"), $autoRestart["days"]) && date("H") == $restartHour)) {
                            } else if ($restartMinute != date("i")) {
                            }
                        }
                        if (!(ipTV_lib::$settings["audio_restart_loss"] == 1 && 300 < time() - $audioLastSeen)) {
                        } else {
                            list($audioSegment) = ipTV_streaming::GetStreamBitrate($playlistPath, 10);
                            if (!empty($audioSegment)) {
                                $audioProbe = ipTV_stream::probeStream($streamDirectory . $audioSegment, SERVER_ID);
                                if (isset($audioProbe["codecs"]["audio"]) && !empty($audioProbe["codecs"]["audio"])) {
                                    $audioLastSeen = time();
                                }
                            }
                        }
                        if (ipTV_lib::$SegmentsSettings["seg_time"] * 6 >= time() - $lastPlaylistChange) {
                        } else {
                            $currentPlaylistHash = md5_file($playlistPath);
                            if ($playlistHash != $currentPlaylistHash) {
                                $playlistHash = $currentPlaylistHash;
                                $lastPlaylistChange = time();
                            }
                        }
                        if (!(ipTV_lib::$settings["priority_backup"] == 1 && 1 < count($sourceList) && $parentId == 0 && 10 < time() - $lastBackupCheck)) {
                        } else {
                            $lastBackupCheck = time();
                            $currentSourceIndex = array_search($currentSource, $sourceList);
                            if (0 > $currentSourceIndex) {
                            } else {
                                foreach ($sourceList as $candidateSource) {
                                    $parsedSource = ipTV_stream::ParseStreamURL($candidateSource);
                                    if ($parsedSource != $currentSource) {
                                        $sourceProtocol = strtolower(substr($parsedSource, 0, strpos($parsedSource, "://")));
                                        $sourceOptions = implode(" ", ipTV_stream::buildStreamArguments($streamOptions, $sourceProtocol, "fetch"));
                                        if (!$sourceProbe = ipTV_stream::probeStream($parsedSource, SERVER_ID, $sourceOptions)) {
                                        } else {
                                            $fallbackSource = $parsedSource;
                                        }
                                    }
                                }
                            }
                        }
                        if (!($delayedStream && $stream["delay_available_at"] <= time()) || ipTV_streaming::CheckPidStreamExist($delayPid, $streamId)) {
                        } else {
                            $delayPid = intval(shell_exec(PHP_BIN . " " . TOOLS_PATH . "delay.php " . $streamId . " " . $stream["delay_minutes"] . " >/dev/null 2>/dev/null & echo \$!"));
                        }
                        sleep(1);
                    }
                    $db->close_mysql();
                }
                if (!ipTV_streaming::CheckPidChannelM3U8Exist($streamPid, $streamId)) {
                } else {
                    shell_exec("kill -9 " . $streamPid);
                    usleep(50000);
                }
                if (!ipTV_streaming::CheckPidStreamExist($delayPid, $streamId)) {
                } else {
                    shell_exec("kill -9 " . $delayPid);
                    usleep(50000);
                }
                while (!ipTV_streaming::CheckPidChannelM3U8Exist($streamPid, $streamId)) {
                    echo "Restarting...\n";
                    shell_exec("rm -f " . STREAMS_PATH . $streamId . "_*");
                    $launchedStream = ipTV_stream::launchLiveStream(
                        $streamId,
                        $restartDelay,
                        $fallbackSource
                    );
                    if ($launchedStream !== false) {
                        if (!(is_numeric($launchedStream) && $launchedStream == 0)) {
                            sleep(mt_rand(5, 10));
                            $streamPid = $launchedStream["main_pid"];
                            $playlistPath = $launchedStream["playlist"];
                            $delayedStream = $launchedStream["delay_enabled"];
                            $stream["delay_available_at"] = $launchedStream["delay_start_at"];
                            $currentSource = $launchedStream["stream_source"];
                            $parentId = $launchedStream["parent_id"];
                            $fallbackSource = NULL;
                            if ($delayedStream) {
                                $streamDirectory = DELAY_STREAM;
                            } else {
                                $streamDirectory = STREAMS_PATH;
                            }
                            for ($playlistWait = 0; !(ipTV_streaming::CheckPidChannelM3U8Exist($streamPid, $streamId) && !file_exists($playlistPath) && $playlistWait <= ipTV_lib::$SegmentsSettings["seg_time"] * 3); $playlistWait++) {
                                echo "Checking For PlayList...\n";
                                sleep(1);
                            }
                            if ($playlistWait != ipTV_lib::$SegmentsSettings["seg_time"] * 3) {
                            } else {
                                shell_exec("kill -9 " . $streamPid);
                                usleep(50000);
                            }
                            if (RESTART_TAKE_CACHE > $restartDelay) {
                            } else {
                                $restartDelay = 0;
                            }
                        } else {
                            sleep(mt_rand(10, 25));
                        }
                    } else {
                        exit;
                    }
                }
            }
        } else {
            ipTV_stream::stopStream($streamId);
            exit;
        }
    } else {
        echo "[*] Correct Usage: php " . __FILE__ . " <stream_id> [restart]\n";
        exit;
    }
} else {
    exit(0);
}
function ensureStreamMonitorProcess($streamId)
{
    clearstatcache(true);
    if (!file_exists("/home/xtreamcodes/iptv_xtream_codes/streams/" . $streamId . ".monitor")) {
    } else {
        $existingMonitorPid = intval(file_get_contents("/home/xtreamcodes/iptv_xtream_codes/streams/" . $streamId . ".monitor"));
    }
    if (empty($existingMonitorPid)) {
        shell_exec("kill -9 `ps -ef | grep 'XtreamCodes\\[" . $streamId . "\\]' | grep -v grep | awk '{print \$2}'`;");
    } else if (file_exists("/proc/" . $existingMonitorPid)) {
        $monitorCommand = trim(file_get_contents("/proc/" . $existingMonitorPid . "/cmdline"));
        if ($monitorCommand != "XtreamCodes[" . $streamId . "]") {
        } else {
            posix_kill($existingMonitorPid, 9);
        }
    }
    file_put_contents("/home/xtreamcodes/iptv_xtream_codes/streams/" . $streamId . ".monitor", getmypid());
}

?>
