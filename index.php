<?php
session_start();
include 'config/db.php';
include 'lib/db_tools.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && !isset($_GET['console'])) {
header('Location: dashboard/' . $_SESSION['role'] . '.php');
exit();
}

function status_cls($value)
{
if ($value === 'danger' || $value === 'critical') {
return 'status_danger';
}

if ($value === 'warning') {
return 'status_warn';
}

return 'status_safe';
}

$storm_row = db_fetch_one($conn, 'SELECT * FROM solar_storms ORDER BY created_at DESC LIMIT 1');
$rad_row = db_fetch_one($conn, 'SELECT * FROM radiation_logs ORDER BY created_at DESC LIMIT 1');
$pwr_row = db_fetch_one($conn, 'SELECT * FROM power_logs ORDER BY created_at DESC LIMIT 1');
$event_log = db_fetch_all($conn, 'SELECT event_type, notes, created_at FROM events ORDER BY created_at DESC LIMIT 8');

$storm_trend = 'stable';
$storm_trend_cls = 'status_safe';
$storm_trend_rows = db_fetch_all($conn, 'SELECT intensity FROM solar_storms ORDER BY created_at DESC LIMIT 3');
if (count($storm_trend_rows) === 3) {
$i1 = (int) $storm_trend_rows[0]['intensity'];
$i2 = (int) $storm_trend_rows[1]['intensity'];
$i3 = (int) $storm_trend_rows[2]['intensity'];

if ($i1 > $i2 && $i2 > $i3) {
$storm_trend = 'rising';
$storm_trend_cls = $i1 >= 8 ? 'status_danger' : 'status_warn';
} elseif ($i1 < $i2 && $i2 < $i3) {
$storm_trend = 'falling';
$storm_trend_cls = 'status_safe';
}
}

$sys_health = 100;
if ($rad_row) {
if ($rad_row['status'] === 'danger') {
$sys_health -= 30;
} elseif ($rad_row['status'] === 'warning') {
$sys_health -= 15;
}
}
if ($pwr_row) {
if ($pwr_row['mode'] === 'critical') {
$sys_health -= 25;
}
if ((float) $pwr_row['battery_level'] < 40) {
$sys_health -= 15;
}
if ((float) $pwr_row['battery_level'] < 20) {
$sys_health -= 10;
}
}
if ($sys_health < 0) {
$sys_health = 0;
}
 
$health_color = '#57d783';
if ($sys_health < 50) {
$health_color = '#ff5a66';
} elseif ($sys_health < 80) {
$health_color = '#f5a93b';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mars Haven Control System</title>
<link rel="stylesheet" href="assets/css/index.css">
<script src="assets/js/sound_system.js" defer></script>
</head>
<body>
<main class="telemetry_shell">
<header class="panel telemetry_header">
<div class="panel_head">Mars Haven Control System</div>
<div class="panel_body header_body">
<span class="status_safe">console preview</span>
<button type="button" id="sound_toggle" class="sound_toggle" aria-pressed="false">Sound: On</button>
</div>
</header>

<section class="panel status_strip">
<div class="panel_body strip_row">
<span>Mission: <strong class="status_safe">Active</strong></span>
<span>Network: <strong class="status_safe">Synced</strong></span>
<span>Orbit cycle: <strong>Sol 483</strong></span>
</div>
</section>

<section class="telemetry_grid">
<article class="panel storm_monitor">
<div class="panel_head">Storm Monitor</div>
<div class="panel_body">
<?php if ($storm_row): ?>
    <p>Intensity: <strong><?php echo (int) $storm_row['intensity']; ?></strong></p>
    <p>Trend: <span class="<?php echo $storm_trend_cls; ?>"><?php echo htmlspecialchars($storm_trend); ?></span></p>
    <p>Last update: <?php echo $storm_row['created_at']; ?></p>
<?php else: ?>
    <p>No storm telemetry available.</p>
<?php endif; ?>
</div>
</article>

<article class="panel rad_status">
<div class="panel_head">Radiation Status</div>
<div class="panel_body">
<?php if ($rad_row): ?>
    <p>Radiation level: <strong><?php echo $rad_row['radiation_level']; ?></strong></p>
    <p>Status: <span class="<?php echo status_cls($rad_row['status']); ?> <?php echo $rad_row['status'] === 'danger' ? 'pulse_danger' : ''; ?>"><?php echo htmlspecialchars($rad_row['status']); ?></span></p>
    <p>Last update: <?php echo $rad_row['created_at']; ?></p>
<?php else: ?>
    <p>No radiation telemetry available.</p>
<?php endif; ?>
</div>
</article>

<article class="panel pwr_system">
<div class="panel_head">Power System</div>
<div class="panel_body">
<?php if ($pwr_row): ?>
    <p>Solar output: <strong><?php echo $pwr_row['solar_output']; ?></strong></p>
    <p>Battery level: <strong><?php echo $pwr_row['battery_level']; ?>%</strong></p>
    <p>Power mode: <span class="<?php echo status_cls($pwr_row['mode']); ?> <?php echo $pwr_row['mode'] === 'critical' ? 'pulse_danger' : ''; ?>"><?php echo htmlspecialchars($pwr_row['mode']); ?></span></p>
<?php else: ?>
    <p>No power telemetry available.</p>
<?php endif; ?>
</div>
</article>

<article class="panel sys_health">
<div class="panel_head">System Health</div>
<div class="panel_body">
<p>System health: <strong><?php echo $sys_health; ?>%</strong></p>
<div class="health_bar">
    <div class="health_fill" style="width: <?php echo $sys_health; ?>%; background: <?php echo $health_color; ?>;"></div>
</div>
</div>
</article>

<article class="panel event_log">
<div class="panel_head">Recent Events Log</div>
<div class="panel_body">
<?php if (count($event_log) > 0): ?>
    <table>
        <tr>
            <th>Event type</th>
            <th>Notes</th>
            <th>Time</th>
        </tr>
        <?php foreach ($event_log as $event): ?>
            <tr>
                <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                <td><?php echo htmlspecialchars($event['notes']); ?></td>
                <td><?php echo $event['created_at']; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p>No events logged yet.</p>
<?php endif; ?>
</div>
</article>

<article class="panel login_access">
<div class="panel_head">Login Access</div>
<div class="panel_body login_body">
<p>Authenticated access is required for mission dashboards.</p>
<a href="login.php" class="access_btn">Login</a>
</div>
</article>
</section>
</main>

<?php
$close_content_wrapper = false;
include 'includes/footer.php';
?>



