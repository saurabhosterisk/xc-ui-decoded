<?php

if ($argc) {
    define("USE_CACHE", false);
    require str_replace("\\", "/", dirname($argv[0])) . "/../wwwdir/init.php";
    cli_set_process_title("XtreamCodes[Cache Builder]");
    $cacheLockFile = TMP_DIR . md5(getCacheIdentity() . __FILE__);
    acquireCacheLock($cacheLockFile);
    ini_set("memory_limit", -1);
    ipTV_lib::phpFileCache("settings_cache", ipTV_lib::$settings);
    ipTV_lib::phpFileCache("customisp_cache", ipTV_lib::$customISP);
    ipTV_lib::phpFileCache("uagents_cache", ipTV_lib::$blockedUA);
    ipTV_lib::phpFileCache("bouquets_cache", ipTV_lib::$Bouquets);
    ipTV_lib::phpFileCache("servers_cache", ipTV_lib::$StreamingServers);
    $db->query("SELECT t1.id, \n       t1.added, \n       t1.allow_record, \n       t1.channel_id, \n       if(t1.direct_source = 1 AND t1.redirect_stream = 0,t1.stream_source,NULL) as stream_source,\n       t1.tv_archive_server_id, \n       t1.tv_archive_duration, \n       t1.stream_icon, \n       t1.custom_sid, \n       t1.category_id, \n       t1.stream_display_name, \n       t2.type_output, \n       t1.target_container, \n       t2.live, \n       t3.category_name, \n       t1.rtmp_output, \n       t1.number, \n       t2.type_key,\n       t2.type_name\n       FROM   `streams` t1 \n       LEFT JOIN `stream_categories` t3 ON t3.id = t1.category_id \n       INNER JOIN `streams_types` t2 ON t2.type_id = t1.type");
    $streamsByType = $db->get_rows(true, "type_key", false, "id");
    $streamCache = [];
    foreach ($streamsByType as $typeKey => $streamRow) {
        $streamCache = array_replace($streamCache, $streamRow);
        $cacheContents = "<?php return " . var_export($streamRow, true) . "; ?>";
        $cacheFile = TMP_DIR . $typeKey . "_main.php";
        if (file_exists($cacheFile) && md5_file($cacheFile) == md5($cacheContents)) {
        } else {
            file_put_contents($cacheFile . "_tmp", $cacheContents, LOCK_EX);
            rename($cacheFile . "_tmp", $cacheFile);
        }
    }
    buildMoviePropertiesCache();
    buildBouquetCategoriesCache($streamCache);
    buildSeriesCache();
    $nginxRouteCount = (int) shell_exec("cat " . IPTV_PANEL_DIR . "nginx/conf/nginx.conf | grep -c '\\/(\\\\d+)'");
    if ($nginxRouteCount != 1) {
    } else {
        file_put_contents(TMP_DIR . "new_rewrite", 1);
    }
    @unlink($cacheLockFile);
} else {
    exit(0);
}
function buildMoviePropertiesCache()
{
    global $db;
    $db->query("SELECT id,movie_propeties FROM `streams`");
    foreach ($db->get_rows(true, "id") as $streamId => $streamRow) {
        if (3 > strlen($streamRow["movie_propeties"])) {
        } else {
            $movieProperties = json_decode($streamRow["movie_propeties"], true);
            if (!is_array($movieProperties)) {
            } else {
                file_put_contents(TMP_DIR . $streamId . "_cache_properties", serialize($movieProperties), LOCK_EX);
            }
        }
    }
}
function buildBouquetCategoriesCache($streamCache)
{
    $bouquetCategories = [];
    foreach (ipTV_lib::$Bouquets as $streamId => $bouquet) {
        $bouquetCategories[$streamId] = [];
        if (is_array($bouquet["streams"])) {
            foreach ($bouquet["streams"] as $bouquetStreamId) {
                if (!isset($streamCache[$bouquetStreamId])) {
                } else if (in_array($streamCache[$bouquetStreamId]["category_id"], $bouquetCategories[$streamId])) {
                } else {
                    $bouquetCategories[$streamId][] = $streamCache[$bouquetStreamId]["category_id"];
                }
            }
        }
    }
    file_put_contents(TMP_DIR . "categories_bouq", serialize($bouquetCategories), LOCK_EX);
}
function buildSeriesCache()
{
    global $db;
    $db->query("SELECT t1.*,t2.category_name FROM `series` t1 LEFT JOIN `stream_categories` t2 ON t1.category_id = t2.id");
    $seriesCache = $db->get_rows(true, "id");
    foreach ($seriesCache as $seriesId => $seriesRow) {
        $db->query("SELECT t1.season_num,t2.added,if(t2.direct_source = 1 AND t2.redirect_stream = 0,t2.stream_source,NULL) as stream_source,t2.custom_sid,t1.stream_id,t2.stream_display_name,t2.target_container FROM `series_episodes` t1 INNER JOIN `streams` t2 ON t2.id=t1.stream_id WHERE t1.series_id = '%d' ORDER BY t1.season_num ASC, t1.sort ASC", $seriesId);
        $episodesBySeason = $db->get_rows(true, "season_num", false, "stream_id");
        $seriesCache[$seriesId]["series_data"] = $episodesBySeason;
    }
    $bouquet = "<?php \$output = " . var_export($seriesCache, true) . "; ?>";
    $seriesCacheFile = TMP_DIR . "series_data.php";
    if (file_exists($seriesCacheFile) && md5_file($seriesCacheFile) == md5($bouquet)) {
    } else {
        file_put_contents($seriesCacheFile . "_tmp", $bouquet, LOCK_EX);
        rename($seriesCacheFile . "_tmp", $seriesCacheFile);
    }
}

?>