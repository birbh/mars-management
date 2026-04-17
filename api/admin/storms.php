<?php
require_once dirname(__DIR__) . '/includes/auth_api.php';
api_require_role('admin');

$method = $_SERVER['REQUEST_METHOD'];
$body = api_input_json();

if ($method === 'POST') {
    $storm_lvl = isset($body['storm_lvl']) ? (int) $body['storm_lvl'] : (isset($_POST['storm_lvl']) ? (int) $_POST['storm_lvl'] : 0);
    $storm_desc = isset($body['storm_desc']) ? trim((string) $body['storm_desc']) : (isset($_POST['storm_desc']) ? trim($_POST['storm_desc']) : '');

    if ($storm_lvl < 1 || $storm_lvl > 10) {
        api_fail('Storm intensity must be between 1 and 10.', 422);

    }
    $storm_id = 0;
    try {
        if (!$conn->begin_transaction()) {
            api_fail('Failed to create storm record.',500);
        }

        $storm_stmt = db_run_stmt($conn,'INSERT INTO solar_storms(intensity,description) VALUES (?,?)','is',[$storm_lvl,$storm_desc]);
        if (!$storm_stmt) {
            throw new Exception('storm_insert_failed');
        }
        $storm_stmt->close();

        $storm_id = (int) $conn->insert_id;
        $rad_lvl = $storm_lvl * 12.5;
        $rad_status = $rad_lvl < 50 ? 'safe' : ($rad_lvl <= 90 ? 'warning' : 'danger');

        $rad_stmt = db_run_stmt($conn,'INSERT INTO radiation_logs(storm_id,radiation_level,status) VALUES (?,?,?)','ids',[$storm_id,$rad_lvl,$rad_status]);
        if (!$rad_stmt) {
            throw new Exception('radiation_insert_failed');
        }
        $rad_stmt->close();

        $solar_out = 100 - $storm_lvl * 8;
        $battery_lvl = 100 - $storm_lvl * 10;
        $pwr_mode = $solar_out < 40 ? 'critical' : 'normal';

        $pwr_stmt = db_run_stmt($conn,'INSERT INTO power_logs(storm_id,solar_output,battery_level,mode) VALUES (?,?,?,?)','idds',[$storm_id,$solar_out,$battery_lvl,$pwr_mode]);
        if (!$pwr_stmt) {
            throw new Exception('power_insert_failed');
        }
        $pwr_stmt->close();

        if ($rad_status === 'danger') {
            db_insert_event_cooldown_storm($conn,$storm_id,'Emergency Shelter Activated','Radiation exceeded safe threshold.',5);
        }

        if (!$conn->commit()) {
            throw new Exception('commit_failed');
        }
    } catch (Throwable $e) {
        $conn->rollback();
        api_fail('Failed to create storm record.',500);
    }

    api_ok(['id'=>$storm_id,'intensity'=>$storm_lvl,'description'=>$storm_desc]);
}
if($method==='PUT'){
    $storm_id=isset($_GET['id'])?(int)$_GET['id']:0;
    $storm_lvl=isset($body['storm_lvl'])?(int)$body['storm_lvl']:0;
    $storm_desc=isset($body['storm_desc'])?trim((string)$body['storm_desc']):'';
    if($storm_id<=0){
        api_fail('Invalid storm id.',422);

    }
    if($storm_lvl<1||$storm_lvl>10){
        api_fail('Storm intensity must be between 1 and 10.',422);
    }
    $stmt=db_run_stmt($conn,'UPDATE solar_storms SET intensity=?,description=? WHERE id=? LIMIT 1','isi',[$storm_lvl,$storm_desc,$storm_id]);
    if(!$stmt){
        api_fail('Failed to update storm record.',500);
    }
    $affected=$stmt->affected_rows;
    $stmt->close();
    $stmt=null;
    api_ok(['updated'=>$affected>0]);

}

if($method==='DELETE'){
    $storm_id=isset($_GET['id'])?(int)$_GET['id']:0;
    if($storm_id<=0){
        api_fail('Invalid storm id.',422);

    }
    // Remove child records first so delete works even if FK is RESTRICT in existing DB.
    $cleanup_queries = [
        'DELETE FROM events WHERE storm_id = ?',
        'DELETE FROM radiation_logs WHERE storm_id = ?',
        'DELETE FROM power_logs WHERE storm_id = ?'
    ];

    foreach ($cleanup_queries as $sql) {
        $cleanup_stmt = db_run_stmt($conn, $sql, 'i', [$storm_id]);
        if (!$cleanup_stmt) {
            api_fail('Failed to delete linked storm records.',500);
        }
        $cleanup_stmt->close();
    }

    $stmt=db_run_stmt($conn,'DELETE FROM solar_storms WHERE id=? LIMIT 1','i',[$storm_id]);
    if(!$stmt){
        api_fail('Failed to delete storm record.',500);
    }
    $affected=$stmt->affected_rows;
    $stmt->close();
    api_ok(['deleted'=>$affected>0]);

}
api_fail('Method not allowed',405);




