<?php
require_once __DIR__ . '/bootstrap.php';



function api_session_guard() : void {
    $inactivlim=15*60;
    $abslim=60*60*8;
    $now=time();
    if (!isset($_SESSION['user_id'])) {
        api_fail('Unauthorized', 401);
    }
    if(!isset($_SESSION['login_started_at'])){
        $_SESSION['login_started_at']=$now; 
    }
    if(!isset($_SESSION['last_activity_at'])){
        $_SESSION['last_activity_at']=$now;
    }
    $inacfor=$now-$_SESSION['last_activity_at'];
    $alive=$now-$_SESSION['login_started_at'];
    if($inacfor>$inactivlim || $alive>$abslim){
        session_unset();
        session_destroy();
        api_fail('Session expired', 401);
    }
    $_SESSION['last_activity_at']=$now;
    
}

function api_require_login(): void
{   api_session_guard();
    if (!isset($_SESSION['user_id'])) {
        api_fail('Unauthorized', 401);
    }
}

function api_require_role(string $requiredRole): void
{
    api_require_login();
    $role = $_SESSION['role'] ?? '';
    if ($role !== $requiredRole) {
        api_fail('Forbidden', 403);
    }
}




