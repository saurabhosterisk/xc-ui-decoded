<?php
require '../init.php';
session_start();
if (empty($_SESSION['client_loggedin']) && $_SESSION['client_loggedin'] !== true && empty($_SESSION['cl_data'])) {
    header('Location: index.php');
    exit;
}
$streamUrl = !empty($_POST['link']) ? $_POST['link'] : '';
$displayName = !empty($_POST['display_name']) ? $_POST['display_name'] : '';
?>
<!DOCTYPE html>
<html><head>
<meta charset="utf-8"><title><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/functional.css">
<script src="js/flowplayer.min.js"></script><script src="js/flowplayer.hlsjs.min.js"></script>
<style>.fullscreen-bg{position:fixed;top:0;right:0;bottom:0;left:0;overflow:hidden;z-index:-100}.fullscreen-bg__video{position:absolute;top:0;left:0;width:100%;height:100%}</style>
</head><body>
<?php if ((ipTV_lib::$settings['client_area_plugin'] ?? '') === 'vlc') { ?>
<object classid="clsid:9BE31822-FDAD-461B-AD51-BE1D1C159921" codebase="http://download.videolan.org/pub/videolan/vlc/last/win32/axvlc.cab" id="vlc">
<embed type="application/x-vlc-plugin" pluginspage="http://www.videolan.org" name="vlc" class="fullscreen-bg fullscreen-bg__video" target="<?= htmlspecialchars($streamUrl, ENT_QUOTES, 'UTF-8') ?>" autoplay="yes" /></object>
<?php } else { ?>
<div id="fp-hlsjs"></div><script>
flowplayer('#fp-hlsjs',{ratio:9/16,clip:{autoplay:true,title:<?= json_encode($displayName, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,sources:[{type:'application/x-mpegurl',src:<?= json_encode($streamUrl, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>}],live:true},embed:false});
</script>
<?php } ?></body></html>
