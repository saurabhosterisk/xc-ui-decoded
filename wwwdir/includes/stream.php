<?php

class ipTV_stream
{
 public static $ipTV_db;

 static function manageCreatedChannelInternal($streamId)
 {
     return self::manageCreatedChannel($streamId);
 }
    static function stopStreamInternal($stream_id, $reset_stream_sys = false)
    {
        $stream_id = intval($stream_id);
        if (file_exists("/home/xtreamcodes/iptv_xtream_codes/streams/{$stream_id}.monitor")) {
            $monitorPid = intval(file_get_contents("/home/xtreamcodes/iptv_xtream_codes/streams/{$stream_id}.monitor"));
            if (self::isProcessRunningInternal($monitorPid, "XtreamCodes[{$stream_id}]")) {
                posix_kill($monitorPid, 9);
            }
        }
        if (file_exists(STREAMS_PATH . $stream_id . '_.pid')) {
            $pid = intval(file_get_contents(STREAMS_PATH . $stream_id . '_.pid'));
            if (self::isProcessRunningInternal($pid, "{$stream_id}_.m3u8")) {
                posix_kill($pid, 9);
            }
        }
        shell_exec('rm -f ' . STREAMS_PATH . $stream_id . '_*');
        if ($reset_stream_sys) {
            shell_exec('rm -f ' . DELAY_STREAM . $stream_id . '_*');
            self::$ipTV_db->query('UPDATE `streams_sys` SET `bitrate` = NULL,`current_source` = NULL,`to_analyze` = 0,`pid` = NULL,`stream_started` = NULL,`stream_info` = NULL,`stream_status` = 0,`monitor_pid` = NULL WHERE `stream_id` = \'%d\' AND `server_id` = \'%d\'', $stream_id, SERVER_ID);
        }
    }
    static function clearSourceProbeCacheInternal($sources)
    {
        if (empty($sources)) {
            return;
        }
        foreach ($sources as $source) {
            if (file_exists(STREAMS_PATH . md5($source))) {
                unlink(STREAMS_PATH . md5($source));
            }
        }
    }
    static function probeStreamInternal($streamUrl, $serverId, $ffprobeOptions = array(), $commandPrefix = '')
    {
        $stream_max_analyze = abs(intval(ipTV_lib::$settings['stream_max_analyze']));
        $probesize = abs(intval(ipTV_lib::$settings['probesize']));
        $timeout = intval($stream_max_analyze / 1000000) + 5;
        $command = "{$commandPrefix}/usr/bin/timeout {$timeout}s "
            . FFPROBE_PATH . " -probesize {$probesize} -analyzeduration {$stream_max_analyze} "
            . implode(' ', $ffprobeOptions)
            . ' -i ' . escapeshellarg($streamUrl)
            . ' -v quiet -print_format json -show_streams -show_format';
        $result = ipTV_servers::RunCommandServer($serverId, $command, 'raw', $timeout * 2, $timeout * 2);
        return self::normalizeProbeResultInternal(json_decode($result[$serverId], true));
    }
    public static function normalizeProbeResultInternal($probeData)
    {
        if (!empty($probeData)) {
            if (!empty($probeData['codecs'])) {
                return $probeData;
            }
            $output = array();
            $output['codecs']['video'] = '';
            $output['codecs']['audio'] = '';
            $output['container'] = $probeData['format']['format_name'];
            $output['filename'] = $probeData['format']['filename'];
            $output['bitrate'] = !empty($probeData['format']['bit_rate']) ? $probeData['format']['bit_rate'] : null;
            $output['of_duration'] = !empty($probeData['format']['duration']) ? $probeData['format']['duration'] : 'N/A';
            $output['duration'] = !empty($probeData['format']['duration']) ? gmdate('H:i:s', intval($probeData['format']['duration'])) : 'N/A';
            foreach ($probeData['streams'] as $streamInfo) {
                if (!isset($streamInfo['codec_type'])) {
                    continue;
                }
                if ($streamInfo['codec_type'] != 'audio' && $streamInfo['codec_type'] != 'video') {
                    continue;
                }
                $output['codecs'][$streamInfo['codec_type']] = $streamInfo;
            }
            return $output;
        }
        return false;
    }
    static function isProcessRunningInternal($pid, $search)
    {
        if (file_exists('/proc/' . $pid)) {
            $value = trim(file_get_contents("/proc/{$pid}/cmdline"));
            if (stristr($value, $search)) {
                return true;
            }
        }
        return false;
    }
    static function startStreamInternal($stream_id, $delay_minutes = 0)
    {
        $stream_id = intval($stream_id);
        $stream_lock_file = STREAMS_PATH . $stream_id . '.lock';
        $fp = fopen($stream_lock_file, 'a+');
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            $delay_minutes = intval($delay_minutes);
            shell_exec(PHP_BIN . ' ' . TOOLS_PATH . "stream_monitor.php {$stream_id} {$delay_minutes} >/dev/null 2>/dev/null &");
            usleep(300);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
    static function stopVodStreamInternal($stream_id)
    {
        $stream_id = intval($stream_id);
        if (file_exists(MOVIES_PATH . $stream_id . '_.pid')) {
            $pid = (int) file_get_contents(MOVIES_PATH . $stream_id . '_.pid');
            posix_kill($pid, 9);
        }
        shell_exec('rm -f ' . MOVIES_PATH . $stream_id . '.*');
        self::$ipTV_db->query('UPDATE `streams_sys` SET `bitrate` = NULL,`current_source` = NULL,`to_analyze` = 0,`pid` = NULL,`stream_started` = NULL,`stream_info` = NULL,`stream_status` = 0 WHERE `stream_id` = \'%d\' AND `server_id` = \'%d\'', $stream_id, SERVER_ID);
    }
 static function startVodStreamLegacy($streamId)
 {
     return self::startVodStream($streamId);
 }
    private static function runLiveStreamEngine($streamId, &$restartAttempt, $forcedSource = null)
    {
        $streamId = intval($streamId);
        $restartAttempt = intval($restartAttempt) + 1;
        $pidFile = STREAMS_PATH . $streamId . '_.pid';
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        self::$ipTV_db->query(
            'SELECT * FROM `streams` t1 INNER JOIN `streams_types` t2 '
            . 'ON t2.type_id = t1.type AND t2.live = 1 '
            . 'LEFT JOIN `transcoding_profiles` t4 ON t1.transcode_profile_id = t4.profile_id '
            . 'WHERE t1.direct_source = 0 AND t1.id = \'%d\'',
            $streamId
        );
        if (self::$ipTV_db->num_rows() <= 0) {
            return false;
        }
        $streamInfo = self::$ipTV_db->get_row();

        self::$ipTV_db->query(
            'SELECT * FROM `streams_sys` WHERE `stream_id` = \'%d\' AND `server_id` = \'%d\'',
            $streamId,
            SERVER_ID
        );
        if (self::$ipTV_db->num_rows() <= 0) {
            return false;
        }
        $serverInfo = self::$ipTV_db->get_row();

        self::$ipTV_db->query(
            'SELECT t1.*, t2.* FROM `streams_options` t1, `streams_arguments` t2 '
            . 'WHERE t1.stream_id = \'%d\' AND t1.argument_id = t2.id',
            $streamId
        );
        $streamArguments = self::$ipTV_db->get_rows();

        $onDemand = intval($serverInfo['on_demand'] ?? 0) === 1;
        $analyzeDuration = $onDemand
            ? 10000000
            : abs(intval(ipTV_lib::$settings['stream_max_analyze']));
        $probeSize = $onDemand
            ? abs(intval($streamInfo['probesize_ondemand'] ?? 0))
            : abs(intval(ipTV_lib::$settings['probesize']));
        $probeTimeout = intval($analyzeDuration / 1000000) + 7;

        if (intval($serverInfo['parent_id'] ?? 0) === 0) {
            $sources = ($streamInfo['type_key'] ?? '') === 'created_live'
                ? array(CREATED_CHANNELS . $streamId . '_.list')
                : json_decode((string) ($streamInfo['stream_source'] ?? ''), true);
            $sources = is_array($sources) ? $sources : array();
        } else {
            $parentId = intval($serverInfo['parent_id']);
            if (empty(ipTV_lib::$StreamingServers[$parentId]['site_url_ip'])) {
                return false;
            }
            $sources = array(
                ipTV_lib::$StreamingServers[$parentId]['site_url_ip']
                . 'streaming/admin_live.php?stream=' . $streamId
                . '&password=' . urlencode(ipTV_lib::$settings['live_streaming_pass'])
                . '&extension=ts'
            );
        }

        if ($forcedSource !== null && $forcedSource !== '') {
            $sources = array($forcedSource);
        } elseif (intval(ipTV_lib::$settings['priority_backup'] ?? 0) === 1
            && !empty($serverInfo['current_source'])) {
            $currentIndex = array_search($serverInfo['current_source'], $sources, true);
            if ($currentIndex !== false) {
                $sources = array_merge(
                    array_slice($sources, $currentIndex + 1),
                    array_slice($sources, 0, $currentIndex + 1)
                );
            }
        }
        if (empty($sources)) {
            return false;
        }

        $useProbeCache = $restartAttempt <= RESTART_TAKE_CACHE;
        if (!$useProbeCache) {
            self::clearSourceProbeCache($sources);
        }
        $probeResult = false;
        $streamSource = null;
        $source = null;
        $serverProtocol = '';
        $fetchOptions = '';
        foreach ($sources as $source) {
            $streamSource = self::ParseStreamURL($source);
            $serverProtocol = strtolower((string) parse_url($streamSource, PHP_URL_SCHEME));
            $fetchOptions = implode(
                ' ',
                self::buildStreamArguments($streamArguments, $serverProtocol, 'fetch')
            );
            $cacheFile = STREAMS_PATH . md5($streamSource);
            if ($useProbeCache && file_exists($cacheFile)) {
                $rawProbe = json_decode(file_get_contents($cacheFile), true);
            } else {
                $concat = ($streamInfo['type_key'] ?? '') === 'created_live'
                    && intval($serverInfo['parent_id'] ?? 0) === 0
                    ? '-safe 0 -f concat '
                    : '';
                $probeCommand = '/usr/bin/timeout ' . $probeTimeout . 's ' . FFPROBE_PATH
                    . ' ' . $fetchOptions . ' -probesize ' . $probeSize
                    . ' -analyzeduration ' . $analyzeDuration . ' ' . $concat
                    . '-i ' . escapeshellarg($streamSource)
                    . ' -v quiet -print_format json -show_streams -show_format';
                $rawProbe = json_decode((string) shell_exec($probeCommand), true);
            }
            if (!empty($rawProbe)) {
                $probeResult = self::normalizeProbeResult($rawProbe);
                if (!$useProbeCache) {
                    file_put_contents($cacheFile, json_encode($rawProbe));
                }
                break;
            }
        }

        if (empty($probeResult)) {
            self::$ipTV_db->query(
                'UPDATE `streams_sys` SET `progress_info` = \'\', `to_analyze` = 0, '
                . '`pid` = -1, `stream_status` = 1 WHERE `server_id` = \'%d\' '
                . 'AND `stream_id` = \'%d\'',
                SERVER_ID,
                $streamId
            );
            return 0;
        }

        $progressUrl = 'http://127.0.0.1:'
            . ipTV_lib::$StreamingServers[SERVER_ID]['http_broadcast_port']
            . '/progress.php?stream_id=' . $streamId;
        $customFfmpeg = trim((string) ($streamInfo['custom_ffmpeg'] ?? ''));
        $concat = ($streamInfo['type_key'] ?? '') === 'created_live'
            && intval($serverInfo['parent_id'] ?? 0) === 0 ? '-safe 0 -f concat' : '';
        if ($customFfmpeg !== '') {
            $ffmpegCommand = FFMPEG_PATH . ' -y -nostdin -hide_banner -loglevel quiet '
                . '-progress ' . escapeshellarg($progressUrl) . ' ' . $customFfmpeg;
        } else {
            $mapOptions = intval($streamInfo['stream_all'] ?? 0) === 1
                ? '-map 0 -copy_unknown'
                : (!empty($streamInfo['custom_map'])
                    ? $streamInfo['custom_map'] . ' -copy_unknown' : '');
            if (($streamInfo['type_key'] ?? '') === 'radio_streams') {
                $mapOptions = '-map 0:a?';
            }
            $generatePts = (intval($streamInfo['gen_timestamps'] ?? 0) === 1
                || $serverProtocol === '')
                && ($streamInfo['type_key'] ?? '') !== 'created_live';
            $timestampOptions = $generatePts
                ? '-fflags +genpts -async 1'
                : '-nofix_dts -start_at_zero -copyts -vsync 0 '
                    . '-correct_ts_overflow 0 -avoid_negative_ts disabled '
                    . '-max_interleave_delta 0';
            $readNative = intval($serverInfo['parent_id'] ?? 0) === 0
                && (intval($streamInfo['read_native'] ?? 0) === 1
                    || $serverProtocol === ''
                    || stristr($probeResult['container'], 'hls')
                    || stristr($probeResult['container'], 'mp4')
                    || stristr($probeResult['container'], 'matroska'))
                ? '-re' : '';

            $transcodeOptions = array();
            if (intval($serverInfo['parent_id'] ?? 0) === 0
                && intval($streamInfo['enable_transcode'] ?? 0) === 1
                && ($streamInfo['type_key'] ?? '') !== 'created_live') {
                if (intval($streamInfo['transcode_profile_id'] ?? 0) === -1) {
                    $customOptions = json_decode(
                        (string) ($streamInfo['transcode_attributes'] ?? ''),
                        true
                    );
                    $transcodeOptions = array_merge(
                        self::buildStreamArguments(
                            $streamArguments,
                            $serverProtocol,
                            'transcode'
                        ),
                        is_array($customOptions) ? $customOptions : array()
                    );
                } else {
                    $profileOptions = json_decode(
                        (string) ($streamInfo['profile_options'] ?? ''),
                        true
                    );
                    $transcodeOptions = is_array($profileOptions)
                        ? $profileOptions : array();
                }
            }
            $transcodeOptions += array(
                '-acodec' => 'copy',
                '-vcodec' => 'copy',
                '-scodec' => 'copy'
            );
            $aacFilter = !stristr($probeResult['container'], 'flv')
                && isset($probeResult['codecs']['audio']['codec_name'])
                && $probeResult['codecs']['audio']['codec_name'] === 'aac'
                && $transcodeOptions['-acodec'] === 'copy'
                ? '-bsf:a aac_adtstoasc' : '';

            $delayEnabled = intval($streamInfo['delay_minutes'] ?? 0) > 0
                && intval($serverInfo['parent_id'] ?? 0) === 0;
            $outputPath = $delayEnabled ? DELAY_STREAM : STREAMS_PATH;
            $segmentListSize = $delayEnabled
                ? intval($streamInfo['delay_minutes']) * 6
                : intval(ipTV_lib::$SegmentsSettings['seg_list_size']);

            $ffmpegCommand = FFMPEG_PATH
                . ' -y -nostdin -hide_banner -loglevel warning -err_detect ignore_err '
                . $fetchOptions . ' ' . $timestampOptions . ' ' . $readNative
                . ' -probesize ' . $probeSize . ' -analyzeduration ' . $analyzeDuration
                . ' -progress ' . escapeshellarg($progressUrl) . ' ' . $concat
                . ' -i ' . escapeshellarg($streamSource) . ' ';
            $ffmpegCommand .= implode(' ', self::formatFfmpegOptions($transcodeOptions))
                . ' ' . $mapOptions
                . ' -individual_header_trailer 0 -f segment -segment_format mpegts'
                . ' -segment_time ' . intval(ipTV_lib::$SegmentsSettings['seg_time'])
                . ' -segment_list_size ' . $segmentListSize
                . ' -segment_format_options '
                . escapeshellarg('mpegts_flags=+initial_discontinuity:mpegts_copyts=1')
                . ' -segment_list_type m3u8 -segment_list_flags +live+delete'
                . ' -segment_list ' . escapeshellarg($outputPath . $streamId . '_.m3u8')
                . ' ' . escapeshellarg($outputPath . $streamId . '_%d.ts') . ' ';

            if (intval($streamInfo['rtmp_output'] ?? 0) === 1) {
                $ffmpegCommand .= $mapOptions . ' ' . $aacFilter . ' -f flv '
                    . escapeshellarg(
                        'rtmp://127.0.0.1:'
                        . ipTV_lib::$StreamingServers[SERVER_ID]['rtmp_port']
                        . '/live/' . $streamId
                    ) . ' ';
            }
            $externalPushes = json_decode(
                (string) ($streamInfo['external_push'] ?? ''),
                true
            );
            if (!empty($externalPushes[SERVER_ID])) {
                foreach ($externalPushes[SERVER_ID] as $pushUrl) {
                    $ffmpegCommand .= $mapOptions . ' ' . $aacFilter
                        . ' -f flv ' . escapeshellarg($pushUrl) . ' ';
                }
            }
        }

        $ffmpegCommand .= '>/dev/null 2>>'
            . escapeshellarg(STREAMS_PATH . $streamId . '.errors')
            . ' & echo $! > ' . escapeshellarg($pidFile);
        shell_exec($ffmpegCommand);
        $pid = file_exists($pidFile) ? intval(file_get_contents($pidFile)) : 0;

        if (SERVER_ID == intval($streamInfo['tv_archive_server_id'] ?? 0)) {
            shell_exec(
                PHP_BIN . ' ' . TOOLS_PATH . 'archive.php ' . $streamId
                . ' >/dev/null 2>/dev/null &'
            );
        }

        $delayEnabled = isset($delayEnabled) ? $delayEnabled : false;
        $delayAvailableAt = $delayEnabled
            ? time() + (intval($streamInfo['delay_minutes']) * 60) : 0;
        self::$ipTV_db->query(
            'UPDATE `streams_sys` SET `delay_available_at` = \'%d\', `to_analyze` = 0, '
            . '`stream_started` = \'%d\', `stream_info` = \'%s\', '
            . '`stream_status` = 0, `pid` = \'%d\', `progress_info` = \'%s\', '
            . '`current_source` = \'%s\' WHERE `stream_id` = \'%d\' '
            . 'AND `server_id` = \'%d\'',
            $delayAvailableAt,
            time(),
            json_encode($probeResult),
            $pid,
            json_encode(array()),
            $source,
            $streamId,
            SERVER_ID
        );
        return array(
            'main_pid' => $pid,
            'stream_source' => $streamSource,
            'delay_enabled' => $delayEnabled,
            'parent_id' => intval($serverInfo['parent_id'] ?? 0),
            'delay_start_at' => $delayAvailableAt,
            'playlist' => ($delayEnabled ? DELAY_STREAM : STREAMS_PATH)
                . $streamId . '_.m3u8'
        );
    }
    public static function customOrder($a, $b)
    {
        if (substr($a, 0, 3) == '-i ') {
            return -1;
        }
        return 1;
    }
    public static function buildStreamArgumentsInternal($arguments, $server_protocol, $type)
    {
        $commands = array();
        if (!empty($arguments)) {
            foreach ($arguments as $argumentId => $option) {
                if ($option['argument_cat'] != $type) {
                    continue;
                }
                if (!is_null($option['argument_wprotocol']) && !stristr($server_protocol, $option['argument_wprotocol']) && !is_null($server_protocol)) {
                    continue;
                }
                if ($option['argument_type'] == 'text') {
                    $commands[] = sprintf($option['argument_cmd'], $option['value']);
                } else {
                    $commands[] = $option['argument_cmd'];
                }
            }
        }
        return $commands;
    }
    public static function formatFfmpegOptionsInternal($options)
    {
        $filters = array();
        foreach ($options as $k => $option) {
            if (isset($option['cmd'])) {
                $options[$k] = $option = $option['cmd'];
            }
            if (preg_match('/-filter_complex "(.*?)"/', $option, $matches)) {
                $options[$k] = trim(str_replace($matches[0], '', $options[$k]));
                $filters[] = $matches[1];
            }
        }
        if (!empty($filters)) {
            $options[] = '-filter_complex "' . implode(',', $filters) . '"';
        }
        $formattedOptions = array();
        foreach ($options as $k => $optionValue) {
            if (is_numeric($k)) {
                $formattedOptions[] = $optionValue;
            } else {
                $formattedOptions[] = $k . ' ' . $optionValue;
            }
        }
        $formattedOptions = array_filter($formattedOptions);
        uasort($formattedOptions, array(__CLASS__, 'customOrder'));
        return array_map('trim', array_values(array_filter($formattedOptions)));
    }
    public static function ParseStreamURL($streamUrl)
    {
        $protocol = strtolower(substr($streamUrl, 0, 4));
        if ($protocol === 'rtmp') {
            if (stristr($streamUrl, '$OPT')) {
                $rawPrefix = 'rtmp://$OPT:rtmp-raw=';
                $streamUrl = trim(substr(
                    $streamUrl,
                    stripos($streamUrl, $rawPrefix) + strlen($rawPrefix)
                ));
            }
            return $streamUrl . ' live=1 timeout=10';
        } elseif ($protocol === 'http') {
            $resolverHosts = array('youtube.com', 'youtu.be', 'livestream.com', 'ustream.tv', 'twitch.tv', 'vimeo.com', 'facebook.com', 'dailymotion.com', 'cnn.com', 'edition.cnn.com', 'youporn.com', 'pornhub.com', 'youjizz.com', 'xvideos.com', 'redtube.com', 'ruleporn.com', 'pornotube.com', 'skysports.com', 'screencast.com', 'xhamster.com', 'pornhd.com', 'pornktube.com', 'tube8.com', 'vporn.com', 'giniko.com', 'xtube.com');
            $host = str_ireplace('www.', '', (string) parse_url($streamUrl, PHP_URL_HOST));
            if (in_array($host, $resolverHosts, true)) {
                $urls = trim((string) shell_exec(
                    YOUTUBE_PATH . ' ' . escapeshellarg($streamUrl)
                    . ' -q --get-url --skip-download -f best'
                ));
                $resolvedUrls = preg_split('/\R+/', $urls);
                if (!empty($resolvedUrls[0])) {
                    $streamUrl = $resolvedUrls[0];
                }
            }
        }
        return $streamUrl;
    }

    public static function buildStreamArguments($arguments, $serverProtocol, $category)
    {
        return self::buildStreamArgumentsInternal(
            $arguments,
            $serverProtocol,
            $category
        );
    }

    /**
     * Start missing source workers for a locally-created channel and refresh its
     * FFmpeg concat playlist. The numeric return values are kept for callers:
     * 1 means at least one worker is active, while 2 means none are active.
     */
    public static function manageCreatedChannel($streamId)
    {
        $streamId = intval($streamId);
        self::$ipTV_db->query(
            'SELECT * FROM `streams` t1 '
            . 'LEFT JOIN `transcoding_profiles` t3 ON t1.transcode_profile_id = t3.profile_id '
            . 'WHERE t1.`id` = \'%d\'',
            $streamId
        );
        $stream = self::$ipTV_db->get_row();
        if (empty($stream)) {
            return 2;
        }

        $previousSources = json_decode((string) ($stream['cchannel_rsources'] ?? ''), true);
        $sources = json_decode((string) ($stream['stream_source'] ?? ''), true);
        $workerPids = json_decode((string) ($stream['pids_create_channel'] ?? ''), true);
        $transcodeOptions = json_decode((string) ($stream['profile_options'] ?? ''), true);
        $previousSources = is_array($previousSources) ? $previousSources : array();
        $sources = is_array($sources) ? $sources : array();
        $workerPids = is_array($workerPids) ? $workerPids : array();
        $transcodeOptions = is_array($transcodeOptions) ? $transcodeOptions : array();

        $transcodeOptions += array('-acodec' => 'copy', '-vcodec' => 'copy');
        $ffmpegCommand = FFMPEG_PATH
            . ' -fflags +genpts -async 1 -y -nostdin -hide_banner -loglevel quiet'
            . ' -i "{INPUT}" '
            . implode(' ', self::formatFfmpegOptions($transcodeOptions))
            . ' -strict -2 -mpegts_flags +initial_discontinuity -f mpegts "'
            . CREATED_CHANNELS . $streamId
            . '_{INPUT_MD5}.ts" >/dev/null 2>/dev/null & jobs -p';

        $newSources = array_values(array_diff($sources, $previousSources));
        $sourcesChanged = $sources !== $previousSources;
        if (!empty($newSources) || $sourcesChanged) {
            foreach ($newSources as $source) {
                $command = str_ireplace(
                    array('{INPUT}', '{INPUT_MD5}'),
                    array($source, md5($source)),
                    $ffmpegCommand
                );
                $result = ipTV_servers::RunCommandServer(
                    intval($stream['created_channel_location']),
                    $command,
                    'raw'
                );
                $workerPids[] = $result[intval($stream['created_channel_location'])] ?? null;
            }
            $workerPids = array_values(array_filter($workerPids, static function ($pid) {
                return $pid !== null && $pid !== '';
            }));

            self::$ipTV_db->query(
                'UPDATE `streams` SET `pids_create_channel` = \'%s\','
                . '`cchannel_rsources` = \'%s\' WHERE `id` = \'%d\'',
                json_encode($workerPids),
                json_encode($sources),
                $streamId
            );

            $concatList = '';
            foreach ($sources as $source) {
                $concatList .= "file '" . CREATED_CHANNELS . $streamId . '_'
                    . md5($source) . ".ts'\n";
            }
            $encodedList = base64_encode($concatList);
            ipTV_servers::RunCommandServer(
                intval($stream['created_channel_location']),
                'echo ' . escapeshellarg($encodedList) . ' | base64 --decode > '
                . escapeshellarg(CREATED_CHANNELS . $streamId . '_.list'),
                'raw'
            );
            return 1;
        }

        foreach ($workerPids as $key => $pid) {
            if (!ipTV_servers::PidsChannels(
                intval($stream['created_channel_location']),
                $pid,
                FFMPEG_PATH
            )) {
                unset($workerPids[$key]);
            }
        }
        $workerPids = array_values($workerPids);
        self::$ipTV_db->query(
            'UPDATE `streams` SET `pids_create_channel` = \'%s\' WHERE `id` = \'%d\'',
            json_encode($workerPids),
            $streamId
        );
        return empty($workerPids) ? 2 : 1;
    }

    public static function formatFfmpegOptions($options)
    {
        return self::formatFfmpegOptionsInternal($options);
    }

    public static function clearSourceProbeCache($sources)
    {
        return self::clearSourceProbeCacheInternal($sources);
    }

    public static function probeStream($streamUrl, $serverId, $arguments = array(), $commandPrefix = '')
    {
        return self::probeStreamInternal(
            $streamUrl,
            $serverId,
            $arguments,
            $commandPrefix
        );
    }

    public static function normalizeProbeResult($probeData)
    {
        return self::normalizeProbeResultInternal($probeData);
    }

    public static function isProcessRunning($pid, $commandFragment)
    {
        return self::isProcessRunningInternal(
            $pid,
            $commandFragment
        );
    }

    public static function startStream($streamId, $delayMinutes = 0)
    {
        return self::startStreamInternal(
            $streamId,
            $delayMinutes
        );
    }

    /**
     * Probe and launch the FFmpeg process for a live stream.
     *
     * This is distinct from startStream(), which launches stream_monitor.php.
     * The restart-attempt counter is passed by reference because the monitor
     * uses it to decide whether cached probe data may be reused.
     */
    public static function launchLiveStream($streamId, &$restartAttempt, $forcedSource = null)
    {
        $streamId = intval($streamId);
        $restartAttempt = intval($restartAttempt);
        return self::runLiveStreamEngine(
            $streamId,
            $restartAttempt,
            $forcedSource
        );
    }

    /** @deprecated Use launchLiveStream(). Retained for legacy encoded callers. */
    public static function launchLiveStreamLegacy(
        $streamId,
        &$restartAttempt,
        $forcedSource = null
    ) {
        return self::launchLiveStream($streamId, $restartAttempt, $forcedSource);
    }

    public static function stopStream($streamId, $resetDatabaseState = false)
    {
        return self::stopStreamInternal(
            $streamId,
            $resetDatabaseState
        );
    }

    public static function startVodStream($streamId)
    {
        $streamId = intval($streamId);
        self::$ipTV_db->query(
            'SELECT * FROM `streams` t1 '
            . 'INNER JOIN `streams_types` t2 ON t2.type_id = t1.type AND t2.live = 0 '
            . 'LEFT JOIN `transcoding_profiles` t4 ON t1.transcode_profile_id = t4.profile_id '
            . 'WHERE t1.direct_source = 0 AND t1.id = \'%d\'',
            $streamId
        );
        if (self::$ipTV_db->num_rows() <= 0) {
            return false;
        }
        $streamInfo = self::$ipTV_db->get_row();

        self::$ipTV_db->query(
            'SELECT * FROM `streams_sys` WHERE `stream_id` = \'%d\' AND `server_id` = \'%d\'',
            $streamId,
            SERVER_ID
        );
        if (self::$ipTV_db->num_rows() <= 0) {
            return false;
        }
        self::$ipTV_db->get_row();

        self::$ipTV_db->query(
            'SELECT t1.*, t2.* FROM `streams_options` t1, `streams_arguments` t2 '
            . 'WHERE t1.stream_id = \'%d\' AND t1.argument_id = t2.id',
            $streamId
        );
        $streamArguments = self::$ipTV_db->get_rows();

        $sources = json_decode((string) ($streamInfo['stream_source'] ?? ''), true);
        if (!is_array($sources) || empty($sources[0])) {
            return false;
        }
        $source = urldecode($sources[0]);
        $sourceServerId = null;
        $serverProtocol = null;
        $fetchOptions = '';

        if (substr($source, 0, 2) === 's:') {
            $sourceParts = explode(':', $source, 3);
            if (count($sourceParts) !== 3) {
                return false;
            }
            $sourceServerId = intval($sourceParts[1]);
            $sourcePath = $sourceParts[2];
            if ($sourceServerId === SERVER_ID) {
                $input = $sourcePath;
            } elseif (isset(ipTV_lib::$StreamingServers[$sourceServerId]['api_url'])) {
                $input = ipTV_lib::$StreamingServers[$sourceServerId]['api_url']
                    . '&action=getFile&filename=' . urlencode($sourcePath);
            } else {
                return false;
            }
        } else {
            $serverProtocol = (string) parse_url($source, PHP_URL_SCHEME);
            $input = str_replace(' ', '%20', $source);
            $fetchOptions = implode(
                ' ',
                self::buildStreamArguments($streamArguments, $serverProtocol, 'fetch')
            );
        }

        $targetContainers = json_decode((string) ($streamInfo['target_container'] ?? ''), true);
        if (!is_array($targetContainers)) {
            $targetContainers = array($streamInfo['target_container'] ?? 'mp4');
        }
        $targetContainers = array_values(array_filter($targetContainers, static function ($container) {
            return preg_match('/^[a-z0-9]+$/i', (string) $container) === 1;
        }));
        if (empty($targetContainers)) {
            return false;
        }

        $useSymlink = $sourceServerId === SERVER_ID
            && intval($streamInfo['movie_symlink'] ?? 0) === 1;
        if ($useSymlink) {
            $extension = pathinfo($input, PATHINFO_EXTENSION);
            if ($extension === '' || preg_match('/^[a-z0-9]+$/i', $extension) !== 1) {
                return false;
            }
            $command = 'ln -sf -- ' . escapeshellarg($input) . ' '
                . escapeshellarg(MOVIES_PATH . $streamId . '.' . $extension)
                . ' >/dev/null 2>/dev/null & echo $! > '
                . escapeshellarg(MOVIES_PATH . $streamId . '_.pid');
        } else {
            $transcodeOptions = array();
            if (intval($streamInfo['enable_transcode'] ?? 0) === 1) {
                if (intval($streamInfo['transcode_profile_id'] ?? 0) === -1) {
                    $customOptions = json_decode((string) ($streamInfo['transcode_attributes'] ?? ''), true);
                    $transcodeOptions = array_merge(
                        self::buildStreamArguments($streamArguments, $serverProtocol, 'transcode'),
                        is_array($customOptions) ? $customOptions : array()
                    );
                } else {
                    $profileOptions = json_decode((string) ($streamInfo['profile_options'] ?? ''), true);
                    $transcodeOptions = is_array($profileOptions) ? $profileOptions : array();
                }
            }
            $transcodeOptions += array('-acodec' => 'copy', '-vcodec' => 'copy');

            $map = '-map 0 -copy_unknown';
            if (!empty($streamInfo['custom_map'])) {
                $map = $streamInfo['custom_map'] . ' -copy_unknown';
            } elseif (intval($streamInfo['remove_subtitles'] ?? 0) === 1) {
                $map = '-map 0:a? -map 0:v?';
            }
            $readNative = intval($streamInfo['read_native'] ?? 0) === 1 ? '-re ' : '';
            $command = FFMPEG_PATH . ' -y -nostdin -hide_banner -loglevel warning '
                . '-err_detect ignore_err ' . $fetchOptions . ' -fflags +genpts -async 1 '
                . $readNative . '-i ' . escapeshellarg($input) . ' ';
            foreach ($targetContainers as $container) {
                $containerOptions = $transcodeOptions;
                $containerOptions['-scodec'] = $container === 'mp4'
                    ? 'mov_text'
                    : ($container === 'mkv' ? 'srt' : 'copy');
                $command .= implode(' ', self::formatFfmpegOptions($containerOptions))
                    . ' -movflags +faststart -dn ' . $map . ' -ignore_unknown '
                    . escapeshellarg(MOVIES_PATH . $streamId . '.' . $container) . ' ';
            }
            $command .= '>/dev/null 2>' . escapeshellarg(MOVIES_PATH . $streamId . '.errors')
                . ' & echo $! > ' . escapeshellarg(MOVIES_PATH . $streamId . '_.pid');
        }

        shell_exec($command);
        $pidFile = MOVIES_PATH . $streamId . '_.pid';
        $pid = file_exists($pidFile) ? intval(file_get_contents($pidFile)) : 0;
        self::$ipTV_db->query(
            'UPDATE `streams_sys` SET `to_analyze` = 1, `stream_started` = \'%d\','
            . '`stream_status` = 0, `pid` = \'%d\' '
            . 'WHERE `stream_id` = \'%d\' AND `server_id` = \'%d\'',
            time(),
            $pid,
            $streamId,
            SERVER_ID
        );
        return $pid;
    }

    public static function stopVodStream($streamId)
    {
        return self::stopVodStreamInternal($streamId);
    }
}

?>
