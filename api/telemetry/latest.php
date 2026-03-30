<?php
require_once dirname(__DIR__)."/includes/auth_api.php";
api_require_login();
$storm=db_fetch_one(
    $conn,
    "SELECT id,intensity,description,created_at FROM solar_storms ORDER BY created_at DESC LIMIT 1",
);
$radiation=db_fetch_one($conn,"SELECT id,strom_id,radiation_level,status,created_at FROM radiation_logs ORDER BY created_at DESC LIMIT 1",);
$power=db_fetch_one($conn,"SELECT id,storm_id,solar_output,battery_level,mode,crested_at FROM power_logs ORDER BY created_at DESC LIMIT 1",);
$events=db_fetch_all($conn,"SELECT event_type,notes,created_at FROM events ORDER BY created_at DESC LIMIT 10 ")
$health=100;
if($radiation){
    if($radiation["status"] === "danger")
        $health-=30;
    elseif($radiation["status"]==="warning")
        $health-=15;
}
if($power){
    if($power["mode"]==="critical")
        $health-=25;
    if((float)$power["battery_level"]<40)
        $health-=15;
    if((float)$power["battery_level"]<20)
        $health-=10;
}
$health=max(0,$health);
api_ok([
    "storm"=>$storm,
    "radiation"=>$radiation,
    "power"=>$power,
    "health"=>$health,
    "events"=>$events,
    "server_time"=>date("Y-m-d H:i:s"),
]);

