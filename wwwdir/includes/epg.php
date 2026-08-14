<?php

/**
 * Loads XMLTV EPG sources and converts their programmes into the value tuples
 * consumed by the existing database import cron.
 */
class EPGParser
{
    public $validEpg = false;
    public $epgSource;
    public $from_cache = false;

    public function __construct($source, $fromCache = false)
    {
        // Kept for API compatibility. The legacy implementation accepted this
        // flag but did not alter loading behavior with it.
        $this->from_cache = false;
        $this->load($source, $fromCache);
    }

    /**
     * Return XMLTV channels keyed by channel ID, including the title languages
     * found in their programme records.
     */
    public function getChannels()
    {
        $channels = array();

        if (!$this->epgSource) {
            return $channels;
        }

        foreach ($this->epgSource->channel as $channel) {
            $channelId = trim((string) $channel->attributes()->id);
            $displayName = !empty($channel->{'display-name'})
                ? trim((string) $channel->{'display-name'})
                : '';

            if (!array_key_exists($channelId, $channels)) {
                $channels[$channelId] = array();
                $channels[$channelId]['display_name'] = $displayName;
                $channels[$channelId]['langs'] = array();
            }
        }

        foreach ($this->epgSource->programme as $programme) {
            $channelId = trim((string) $programme->attributes()->channel);
            if (!array_key_exists($channelId, $channels)) {
                continue;
            }

            foreach ($programme->title as $title) {
                $language = (string) $title->attributes()->lang;
                if (!in_array($language, $channels[$channelId]['langs'])) {
                    $channels[$channelId]['langs'][] = $language;
                }
            }
        }

        return $channels;
    }

    /**
     * Build escaped SQL value tuples for programmes belonging to mapped XMLTV
     * channels. The return value is intentionally SQL fragments for legacy
     * importer compatibility.
     */
    public function getProgrammeSqlValues($epgId, $channelMap)
    {
        global $db;

        $values = array();

        if (!$this->epgSource) {
            return false;
        }

        foreach ($this->epgSource->programme as $programme) {
            $channelId = (string) $programme->attributes()->channel;
            if (!array_key_exists($channelId, $channelMap)) {
                continue;
            }

            $titleEncoded = '';
            $descriptionEncoded = '';
            $startTimestamp = strtotime((string) $programme->attributes()->start);
            $stopTimestamp = strtotime((string) $programme->attributes()->stop);

            if (empty($programme->title)) {
                continue;
            }

            $titleEncoded = $this->selectLocalizedText(
                $programme->title,
                $channelMap[$channelId]['epg_lang']
            );

            if (!empty($programme->desc)) {
                $descriptionEncoded = $this->selectLocalizedText(
                    $programme->desc,
                    $channelMap[$channelId]['epg_lang']
                );
            }

            $escapedChannelId = addslashes($channelId);
            $language = addslashes($channelMap[$channelId]['epg_lang']);
            $start = date('Y-m-d H:i:s', $startTimestamp);
            $stop = date('Y-m-d H:i:s', $stopTimestamp);

            $values[] = "('"
                . $db->escape($epgId)
                . "', '"
                . $db->escape($escapedChannelId)
                . "', '"
                . $db->escape($start)
                . "', '"
                . $db->escape($stop)
                . "', '"
                . $db->escape($language)
                . "', '"
                . $db->escape($titleEncoded)
                . "', '"
                . $db->escape($descriptionEncoded)
                . "')";
        }

        return !empty($values) ? $values : false;
    }

    /** Load an XMLTV, gzip-compressed XMLTV, or xz-compressed XMLTV source. */
    public function load($source, $fromCache = false)
    {
        $this->validEpg = false;
        $this->epgSource = null;

        $extension = strtolower(pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION));
        $contents = $this->readSource($source);

        if ($contents === false) {
            $this->log('No XML Found At: ' . $source);
            return;
        }

        if ($extension === 'gz') {
            $contents = @gzdecode($contents);
        } elseif ($extension === 'xz') {
            $contents = $this->decompressXz($contents);
        }

        if ($contents === false || $contents === '') {
            $this->log('No XML Found At: ' . $source);
            return;
        }

        $xml = @simplexml_load_string(
            $contents,
            'SimpleXMLElement',
            LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        if ($xml === false) {
            $this->log('No XML Found At: ' . $source);
            return;
        }

        $this->epgSource = $xml;
        if (empty($this->epgSource->programme)) {
            $this->log('Not A Valid EPG Source Specified or EPG Crashed: ' . $source);
            $this->epgSource = null;
            return;
        }

        $this->validEpg = true;
    }

    private function selectLocalizedText($nodes, $preferredLanguage)
    {
        $fallback = '';
        $selected = null;

        foreach ($nodes as $node) {
            if ($fallback === '') {
                $fallback = (string) $node;
            }

            if ((string) $node->attributes()->lang === (string) $preferredLanguage) {
                // Preserve the legacy behavior of selecting the last matching
                // node when a language appears more than once.
                $selected = (string) $node;
            }
        }

        return base64_encode($selected !== null ? $selected : $fallback);
    }

    private function readSource($source)
    {
        return @file_get_contents($source);
    }

    /** Safely decompress xz bytes without interpolating the source into a shell. */
    private function decompressXz($contents)
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'epg_xz_');
        if ($temporaryFile === false
            || file_put_contents($temporaryFile, $contents) === false
        ) {
            if ($temporaryFile !== false) {
                @unlink($temporaryFile);
            }
            return false;
        }

        $pipes = array();
        $process = @proc_open(
            array('unxz', '-c', $temporaryFile),
            array(
                0 => array('file', '/dev/null', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w'),
            ),
            $pipes
        );

        if (!is_resource($process)) {
            @unlink($temporaryFile);
            return false;
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);
        @unlink($temporaryFile);

        return $status === 0 ? $output : false;
    }

    private function log($message)
    {
        $logger = 'ipTV_lib';
        $method = 'SaveLog';

        if (class_exists($logger, false) && is_callable(array($logger, $method))) {
            call_user_func(array($logger, $method), $message);
            return;
        }

        error_log($message);
    }

    // Legacy compatibility methods.
    public function getChannelsLegacy()
    {
        return $this->getChannels();
    }

    public function getProgrammeValuesLegacy($epgId, $channelMap)
    {
        return $this->getProgrammeSqlValues($epgId, $channelMap);
    }

    public function loadLegacy($source, $fromCache = false)
    {
        return $this->load($source, $fromCache);
    }
}

class_alias(EPGParser::class, 'EPGParser');
