<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_api.php';

api_session_guard();
if(isset($_SESSION['user_id'])){
    api_json([
        'success'=>true,
        'authenticated'=>true,
        'user'=>[
            'id'=>$_SESSION['user_id'] ?? null,
            'username'=>$_SESSION['username'] ?? null,
            'role'=>$_SESSION['role'] ?? null
        ]
    ],200);
}
api_json([
    'success'=>true,
    'authenticated'=>false,
    'user'=>null
],200);


