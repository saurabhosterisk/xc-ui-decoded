<?php
set_time_limit(0);
if ($argc) {
    require str_replace("\\", "/", dirname($argv[0])) . "/../wwwdir/init.php";
    shell_exec("kill \$(ps aux | grep pipe_reader | grep -v grep | grep -v " . getmypid() . " | awk '{print \$2}')");
    if (is_dir(CLOSE_OPEN_CONS_PATH)) {
    } else {
        mkdir(CLOSE_OPEN_CONS_PATH);
    }
    while (false) {
        $activityFiles = scandir(CLOSE_OPEN_CONS_PATH);
        unset($activityFiles[0]);
        unset($activityFiles[1]);
        if (!empty($activityFiles)) {
            foreach ($activityFiles as $activityId) {
                unlink(CLOSE_OPEN_CONS_PATH . $activityId);
            }
            if ($db->query("DELETE FROM `user_activity_now` WHERE `activity_id` IN (" . implode(",", $activityFiles) . ")") !== false) {
            }
        } else {
            usleep(4000);
        }
    }
    shell_exec("(sleep 2; " . PHP_BIN . " " . __FILE__ . " ) > /dev/null 2>/dev/null &");
} else {
    exit(0);
}

?>