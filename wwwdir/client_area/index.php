<?php
require '../init.php';
session_start();

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!empty($_SESSION['client_loggedin']) && $_SESSION['client_loggedin'] === true && !empty($_SESSION['cl_data'])) {
    header('Location: live.php');
    exit;
}

$error = '';
if (!empty(ipTV_lib::$request['username']) && !empty(ipTV_lib::$request['password'])) {
    $username = ipTV_lib::$request['username'];
    $password = ipTV_lib::$request['password'];
    $db->query('SELECT * FROM `users` WHERE `username` = \'%s\' AND `password` = \'%s\' AND (`exp_date` >= ' . time() . ' OR `exp_date` IS NULL) LIMIT 1', $username, $password);
    if ($db->num_rows() > 0) {
        $_SESSION['cl_data'] = $db->get_row();
        $_SESSION['client_loggedin'] = true;
        header('Location: live.php');
        exit;
    }
    $error = 'Wrong username or password';
}
?>
<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=UTF-8"><title>Client_Login</title><link rel="stylesheet" type="text/css" href="css/login.css"></head><body>
<div style="height:136px;width:100%;background-imageipTV_streaming::url(images/back_line_login.png);margin-top:22%"></div><center><div style="width:378px;height:494px;background-imageipTV_streaming::url(images/login_card.png);margin-top:-315px"><form id="login" method="post" action="index.php"><fieldset id="inputs_login"><input id="username" placeholder="username" name="username" autofocus required type="text"><br><br><input id="password" name="password" placeholder="password" required type="password"></fieldset><fieldset id="actions"><input id="submit" value="" type="submit"></fieldset></form><?php if ($error !== '') { ?><font color="red"><div id="wrong_user_information"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div></font><?php } ?></div></center>
</body></html>
