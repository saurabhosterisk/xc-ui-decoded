<?php
set_time_limit(0);
if ($argc) {
    require str_replace("\\", "/", dirname($argv[0])) . "/../wwwdir/init.php";
    cli_set_process_title("XtreamCodes[XC Panel Monitor]");
    shell_exec("kill \$(ps aux | grep 'XC Panel Monitor' | grep -v grep | grep -v " . getmypid() . " | awk '{print \$2}')");
    if (ipTV_lib::$settings["firewall"] != 0) {
        file_put_contents(TMP_DIR . "5a9ccab64e61d9af12baa7d4011acc1a", 1);
        unlink(TMP_DIR . "d52d7d1df4f329bda8b2d9f67fa5d846");
        $blacklistRefreshAt = time();
        while (false) {
            if ($db->query("SELECT `firewall` FROM settings")) {
                $settingsRow = $db->get_row();
                if ($settingsRow["firewall"] != 0) {
                    file_put_contents(TMP_DIR . "5a9ccab64e61d9af12baa7d4011acc1a", 1);
                    if (!file_exists(TMP_DIR . "d52d7d1df4f329bda8b2d9f67fa5d846")) {
                    } else {
                        unlink(TMP_DIR . "d52d7d1df4f329bda8b2d9f67fa5d846");
                    }
                    if ($db->query("SELECT `username`,`password` FROM users WHERE enabled = 1 AND admin_enabled = 1 AND (exp_date > " . time() . " OR exp_date IS NULL)")) {
                        if (0 > $db->num_rows()) {
                        } else {
                            foreach ($db->get_rows() as $user) {
                                file_put_contents(TMP_DIR . md5(strtolower($user["username"] . $user["password"])), 1);
                            }
                        }
                        if (600 >= time() - $blacklistRefreshAt) {
                        } else {
                            unlink(IPTV_PANEL_DIR . "tmp/blacklist");
                            $blacklistRefreshAt = time();
                        }
                        sleep(3);
                    }
                } else {
                    file_put_contents(TMP_DIR . "d52d7d1df4f329bda8b2d9f67fa5d846", 1);
                    unlink(TMP_DIR . "5a9ccab64e61d9af12baa7d4011acc1a");
                    exit;
                }
                break;
            }
        }
        shell_exec("(sleep 1; " . PHP_BIN . " " . __FILE__ . " ) > /dev/null 2>/dev/null &");
    } else {
        file_put_contents(TMP_DIR . "d52d7d1df4f329bda8b2d9f67fa5d846", 1);
        unlink(TMP_DIR . "5a9ccab64e61d9af12baa7d4011acc1a");
        exit;
    }
} else {
    exit(0);
}

?>