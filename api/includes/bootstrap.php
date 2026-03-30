<?php
    if(session_status()==PHP_SESSION_NONE){
        session_start();
    };
    header("Content-type:application/json;charset=UTF8");
    require_once dirname(__DIR__,2)."../config/db.php";
    require_once dirname(__DIR__,2)."../lib/db_tools.php";
    function api_json($data,int $status=200):void{
        http_response_code($status);
        echo json_encode($data,JSON_UNESCAPED_SLASHES);
        exit();
    }
    function api_ok(array $data=[]):void{
        api_json(["success"=>true,"data"=>$data],200);

    }
    function api_fail(string $message, int $status=400,array $meta=[]):void{
        api_json(
            ["success"=>false,"error"=>$message,"meta"=>$meta],
            $status
        );
    }
    function api_input_json():array{
        $raw=file_get_contents("php://input");
        if($raw===false){
            return [];
        }
        $decoded=json_decode($raw,true);
        return is_array($decoded)?$decoded:[];
    }


// function api_fail(string $message, int $status = 400, array $meta = []): void
// {
//     api_json(
//         ["success" => false, "error" => $message, "meta" => $meta],
//         $status,
//     );
// }
// function api_input_json(): array
// {
//     $raw = file_get_contents("php://input");
//     if (!$raw) {
//         return [];
//     }
//     $decoded = json_decode($raw, true);
//     return is_array($decoded) ? $decoded : [];
// }
