<?php
    require_once __DIR__."/bootstrap.php";
    function api_require_login():void{
        if(!isset($_SESSION["user_id"])){
            api_fail("UNAUTHORIZED",401);

        }
    }
    function api_require_role(string $requiredRole):void{
        api_require_login();
        $role=$_SESSION["role"]??"";
        if($role!==$requiredRole){
            api_fail("FORBIDDEN",403);
        }
    }

