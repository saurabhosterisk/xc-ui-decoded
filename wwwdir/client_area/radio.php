<?php
require '../init.php';
session_start();
if (empty($_SESSION['client_loggedin']) && $_SESSION['client_loggedin'] !== true && empty($_SESSION['cl_data'])) { header('Location: index.php'); exit; }
$client = $_SESSION['cl_data'] ?? [];
$username = $client['username'] ?? '';
$password = $client['password'] ?? '';
$userInfo = ipTV_streaming::GetUserInfo(null, $username, $password, true, true, true, ['radio_streams']);
$channels = $userInfo['channels'] ?? [];
$serverUrl = ipTV_lib::$StreamingServers[SERVER_ID]['site_url'] ?? '';
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Live_TV</title><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/greedynav.css"><link rel="stylesheet" href="css/reset.min.css"><script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script><script src="js/greedynav.js"></script></head><body>
<div class="header"><div class="logo"></div><div class="button_Live"><img src="images/live_btn.png" onclick="parent.location='live.php'"></div><div class="button_Movies"><img src="images/videos_btn.png" onclick="parent.location='vod.php'"></div><div class="button_Radio"><img src="images/radio_btn_hover.png"></div><div class="User"><img src="images/user_icon.png"><a style="margin-left:10px;color:#C60"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></a><ul><li><a style="color:#c60;font-size:12px">Expire Date:</a><a style="margin-left:10px;color:#fff;font-size:12px"><?php echo !empty($client['exp_date']) ? date('d/m/Y H:i', $client['exp_date']) : 'Unlimited'; ?></a></li><li><a href="index.php?action=logout"><img src="images/logout_btn.png"></a></li></ul></div></div>
<div class="wrapper"><div data-role="listview" data-inset="true" data-filter="true" data-filter-placeholder="search"><center><nav class="greedy-nav"><button><div class="hamburger"></div></button><ul class="visible-links"></ul><ul class="hidden-links hidden"></ul></nav><radio>
<?php foreach ($channels as $channel) { $id=(int)($channel['id']??0); $name=(string)($channel['stream_display_name']??''); $icon=(string)($channel['stream_icon']??''); $url=rtrim($serverUrl,'/').'/live/'.rawurlencode($username).'/'.rawurlencode($password).'/'.$id.'.ts'; ?>
<div class="Radio_Frame"><div class="Radio_Icon"><?php if ($icon !== '' && @getimagesize($icon)) { ?><img src="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"><?php } else { ?><img width="100" height="100" src="images/no_radio.png"><?php } ?></div><div class="Radio_Line"></div><div class="Radio_Live_Now"><br><p><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p></div><div class="Radio_Line"></div><a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><div class="Play_Radio_Button"></div></a></div>
<?php } ?></radio></center></div></div><div class="footer"><img style="float:right" src="images/footer.png"></div></body></html>
