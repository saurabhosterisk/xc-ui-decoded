<?php
require '../init.php';
session_start();

if (empty($_SESSION['client_loggedin']) && $_SESSION['client_loggedin'] !== true && empty($_SESSION['cl_data'])) {
    header('Location: index.php');
    exit;
}

function client_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function client_play_action(string $url, string $name, bool $vlc): string {
    if ($vlc) return "post('player.php',{link:'" . addslashes($url) . "',display_name:'" . addslashes($name) . "'});";
    return "window.location.href='" . addslashes($url) . "';";
}

$client = $_SESSION['cl_data'] ?? [];
$username = (string)($client['username'] ?? '');
$password = (string)($client['password'] ?? '');
$userInfo = ipTV_streaming::GetUserInfo(null, $username, $password, true, true, true, ['live_streams']);
$channels = $userInfo['channels'] ?? [];
$categories = [];
foreach ($channels as $channel) {
    $category = $channel['category_name'] ?? 'Uncategorized';
    $categories[$category][] = $channel;
}
$baseUrl = rtrim((string)(ipTV_lib::$StreamingServers[SERVER_ID]['site_url'] ?? ''), '/');
$vlc = (ipTV_lib::$settings['client_area_plugin'] ?? '') === 'vlc';
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Live_TV</title><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/greedynav.css"><link rel="stylesheet" href="css/reset.min.css"><script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script><script src="js/greedynav.js"></script><script>function post(path,params){var f=document.createElement('form');f.method='post';f.action=path;for(var k in params){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=params[k];f.appendChild(i)}document.body.appendChild(f);f.submit()}</script></head><body>
<div class="header"><div class="logo"></div><div class="button_Live"><img src="images/live_btn_hover.png"></div><div class="button_Movies"><img src="images/videos_btn.png" onclick="parent.location='vod.php'"></div><div class="button_Radio"><img src="images/radio_btn.png" onclick="parent.location='radio.php'"></div><div class="User"><img src="images/user_icon.png"><a style="margin-left:10px;color:#C60"><?= client_escape($username) ?></a><ul><li><a style="color:#c60;font-size:12px">Expire Date:</a><a style="margin-left:10px;color:#fff;font-size:12px"><?php echo !empty($client['exp_date']) ? date('d/m/Y H:i', $client['exp_date']) : 'Unlimited'; ?></a></li><li><a href="index.php?action=logout"><img src="images/logout_btn.png"></a></li></ul></div></div>
<div class="wrapper"><div data-role="listview" data-inset="true" data-filter="true" data-filter-placeholder="search"><center><nav class="greedy-nav"><button><div class="hamburger"></div></button><ul class="visible-links"><li><a href="#" onclick="window.location='live.php'">All</a></li><?php foreach (array_keys($categories) as $category) { ?><li><a href="live.php?cat_id=<?= rawurlencode($category) ?>"><?= client_escape($category) ?></a></li><?php } ?></ul><ul class="hidden-links hidden"></ul></nav></center>
<div class="live_now"><a style="color:#FFF;font-size:15px;margin-left:120px;position:relative">Live Now...</a></div><div class="coming_next"><a style="color:#252525;font-size:15px;margin-left:45%;position:relative">Coming Next...</a></div>
<?php $selected = $_GET['cat_id'] ?? null; foreach ($categories as $category => $items) { if ($selected !== null && (string)$selected !== (string)$category) continue; foreach ($items as $channel) { $id=(int)($channel['id']??0); $name=(string)($channel['stream_display_name']??''); $icon=(string)($channel['stream_icon']??''); $url=$baseUrl.'/live/'.rawurlencode($username).'/'.rawurlencode($password).'/'.$id.'.ts'; $action=client_play_action($url,$name,$vlc); ?>
<div class="channel_Frame"><div class="channel_thumb"><?php if ($icon !== '') { ?><img src="<?= client_escape($icon) ?>" width="100" height="40"><?php } ?></div><div class="channel_Line"></div><div class="channel_Live_Now"><p><?= client_escape($name) ?></p><div class="Play_Live_Button" onclick="<?= client_escape($action) ?>"></div></div><div class="channel_Line"></div></div>
<?php } } ?></div></div><div class="footer"><img style="float:right" src="images/footer.png"></div></body></html>
