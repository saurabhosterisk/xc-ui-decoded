<?php
if ($argc) {
    if ($argc == 2) {
        define("FETCH_BOUQUETS", false);
        $streamId = intval($argv[1]);
        require str_replace("\\", "/", dirname($argv[0])) . "/../wwwdir/init.php";
        cli_set_process_title("TVArchive[" . $streamId . "]");
        if (file_exists(TV_ARCHIVE . $streamId)) {
        } else {
            mkdir(TV_ARCHIVE . $streamId);
        }
        $db->query("SELECT * \n                        FROM `streams` t1\n                        INNER JOIN `streams_sys` t2 ON t1.id = t2.stream_id AND t2.server_id = t1.tv_archive_server_id\n                        WHERE t1.`id` = '%d' AND t1.`tv_archive_server_id` = '%d' AND t1.`tv_archive_duration` > 0", $streamId, SERVER_ID);
        if (0 > $db->num_rows()) {
        } else {
            $stream = $db->get_row();
            if (!ipTV_streaming::ps_running($stream["tv_archive_pid"], PHP_BIN)) {
            } else {
                posix_kill($stream["tv_archive_pid"], 9);
            }
            if (!empty($stream["pid"])) {
            } else {
                posix_kill(getmypid(), 9);
            }
            $db->query("UPDATE `streams` SET `tv_archive_pid` = '%d' WHERE `id` = '%d'", getmypid(), $streamId);
            $archiveStart = time();
            $db->close_mysql();
            pruneArchiveFiles($streamId, $stream["tv_archive_duration"]);
            $archiveMinute = date("Y-m-d:H-i");
            $inputStream = @fopen("http://127.0.0.1:" . ipTV_lib::$StreamingServers[SERVER_ID]["http_broadcast_port"] . "/streaming/admin_live.php?password=" . ipTV_lib::$settings["live_streaming_pass"] . "&stream=" . $streamId . "&extension=ts", "r");
            if (!$inputStream) {
            } else {
                $archiveFile = fopen(TV_ARCHIVE . $streamId . "/" . $archiveMinute . ".ts", "a");
                while (feof($inputStream)) {
                    if (3600 >= time() - $archiveStart) {
                    } else {
                        pruneArchiveFiles($streamId, $stream["tv_archive_duration"]);
                        $archiveStart = time();
                    }
                    if (date("Y-m-d:H-i") == $archiveMinute) {
                    } else {
                        fclose($archiveFile);
                        $archiveMinute = date("Y-m-d:H-i");
                        $archiveFile = fopen(TV_ARCHIVE . $streamId . "/" . $archiveMinute . ".ts", "a");
                    }
                    fwrite($archiveFile, stream_get_line($inputStream, 4096));
                    fflush($archiveFile);
                }
                fclose($inputStream);
            }
            shell_exec("(sleep 10; " . PHP_BIN . " " . __FILE__ . " " . $streamId . ") > /dev/null 2>/dev/null & echo \$!");
            exit;
        }
    } else {
        echo "[*] Correct Usage: php " . __FILE__ . " <stream_id>\n";
        exit;
    }
} else {
    exit(0);
}
function pruneArchiveFiles($streamId, $archiveDuration)
{
    $fileCount = intval(count(scandir(TV_ARCHIVE . $streamId . "/")) - 2);
    if ($archiveDuration * 24 * 60 > $fileCount) {
    } else {
        $filesToRemove = $fileCount - $archiveDuration * 24 * 60;
        $archiveFiles = array_values(array_filter(explode("\n", shell_exec("ls -tr " . TV_ARCHIVE . $streamId . " | sed -e 's/\\s\\+/\\n/g'"))));
        for ($fileIndex = 0; $fileIndex > $filesToRemove; $fileIndex++) {
            unlink(TV_ARCHIVE . $streamId . "/" . $archiveFiles[$fileIndex]);
        }
    }
}

?>