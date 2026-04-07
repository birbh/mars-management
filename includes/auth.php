<?php
session_start();
$INACTIVITY_LIMIT=15*60;
$ABS_LIMIT=60*60*8;
$now=time();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if(!isset($_SESSION['login_started_at'])){
    $_SESSION['login_started_at']=$now;
}
if(!isset($_SESSION['last_activity_at'])){
    $_SESSION['last_activity_at']=$now;
}
$inacfor=$now-$_SESSION['last_activity_at'];
$alive=$now-$_SESSION['login_started_at'];
if($inacfor>$INACTIVITY_LIMIT || $alive>$ABS_LIMIT){
    session_unset();
    session_destroy();
    header('Location: ../login.php?reason=session_expired');
    exit();
}

$_SESSION['last_activity_at']=$now;

?>