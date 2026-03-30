<?php
include '../includes/auth.php';
include '../config/db.php';
include '../lib/db_tools.php';

$role = $_SESSION['role'] ?? '';
if ($role !== 'user') {
    if ($role === 'admin') {
        header('Location: admin.php');
    } elseif ($role === 'astronaut') {
        header('Location: astronaut.php');
    } else {
        header('Location: ../login.php');
    }
    exit();
}

$is_refresh = isset($_GET['refresh']);

$storm_row = db_fetch_one($conn, 'SELECT * FROM solar_storms ORDER BY created_at DESC LIMIT 1');
$rad_row = db_fetch_one($conn, 'SELECT * FROM radiation_logs ORDER BY created_at DESC LIMIT 1');
$latest_power = db_fetch_one($conn, 'SELECT mode, battery_level FROM power_logs ORDER BY created_at DESC LIMIT 1');

$event_log = db_fetch_all(
    $conn,
    'SELECT event_type, notes, created_at FROM events ORDER BY created_at DESC LIMIT 5'
);

function event_severity_cls($event_type, $notes)
{
    $combined = strtolower($event_type . ' ' . $notes);

    if (strpos($combined, 'critical') !== false || strpos($combined, 'emergency') !== false || strpos($combined, 'danger') !== false) {
        return 'status_critical';
    }

    if (strpos($combined, 'warn') !== false || strpos($combined, 'elevated') !== false || strpos($combined, 'monitor') !== false) {
        return 'status_warn';
    }

    return 'status_safe';
}

function event_severity_label($event_type, $notes)
{
    $cls = event_severity_cls($event_type, $notes);
    if ($cls === 'status_critical') {
        return 'Critical';
    }
    if ($cls === 'status_warn') {
        return 'Warn';
    }

    return 'Safe';
}

$health = 100;
if ($rad_row) {
    if ($rad_row['status'] === 'danger') {
        $health -= 30;
    } elseif ($rad_row['status'] === 'warning') {
        $health -= 15;
    }
}
if ($latest_power) {
    if ($latest_power['mode'] === 'critical') {
        $health -= 25;
    }
    if ((float) $latest_power['battery_level'] < 40) {
        $health -= 15;
    }
    if ((float) $latest_power['battery_level'] < 20) {
        $health -= 10;
    }
}
$health = max(0, $health);
$health_status = $health >= 80 ? 'status_safe' : ($health >= 50 ? 'status_warn' : 'status_critical');
$health_label = $health >= 80 ? 'Safe' : ($health >= 50 ? 'Warn' : 'Critical');

$storm_summary = 'Low';
$storm_summary_class = 'status_safe';
if ($storm_row) {
    $intensity = (int) $storm_row['intensity'];
    if ($intensity >= 8) {
        $storm_summary = 'High';
        $storm_summary_class = 'status_critical';
    } elseif ($intensity >= 5) {
        $storm_summary = 'Moderate';
        $storm_summary_class = 'status_warn';
    }
}

$rad_summary = 'Safe';
$rad_summary_class = 'status_safe';
if ($rad_row) {
    if ($rad_row['status'] === 'danger') {
        $rad_summary = 'Critical';
        $rad_summary_class = 'status_critical';
    } elseif ($rad_row['status'] === 'warning') {
        $rad_summary = 'Warn';
        $rad_summary_class = 'status_warn';
    }
}
?>
<?php if (!$is_refresh): ?>
<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/user.css">
<h1>User Dashboard</h1>
<section class="status_bar">
    <div class="status_item">Mission <span class="status_badge status_safe">ACTIVE</span></div>
    <div class="status_item">Network <span class="status_badge status_safe">SYNCED</span></div>
    <div class="status_item">Role <span class="status_badge">USER</span></div>
    <div id="refresh_note" class="status_item">Last refresh: waiting</div>
</section>
<div id="dashboard_content">
<?php endif; ?>

<section class="user_summary_grid">
    <article class="panel">
        <h2 class="panel_head">System Health</h2>
        <div class="panel_body">
            <div class="stat_row"><span>Current health</span><span id="user_health_value" class="stat_val"><?php echo $health; ?>%</span></div>
            <div class="stat_row"><span>Status</span><span id="user_health_status" class="status_badge <?php echo $health_status; ?>"><?php echo $health_label; ?></span></div>
            <div class="stat_row"><span>Last update</span><span id="user_health_time"><?php echo date('Y-m-d H:i:s'); ?></span></div>
        </div>
    </article>

    <article class="panel">
        <h2 class="panel_head">Storm Activity</h2>
        <div class="panel_body">
            <?php if ($storm_row): ?>
                <div class="stat_row"><span>Activity level</span><span id="user_storm_level" class="stat_val"><?php echo $storm_summary; ?></span></div>
                <div class="stat_row"><span>Status</span><span id="user_storm_status" class="status_badge <?php echo $storm_summary_class; ?>"><?php echo $storm_summary; ?></span></div>
                <div class="stat_row"><span>Last update</span><span id="user_storm_time"><?php echo htmlspecialchars($storm_row['created_at']); ?></span></div>
            <?php else: ?>
                <div class="stat_row"><span>Activity level</span><span id="user_storm_level" class="stat_val">N/A</span></div>
                <div class="stat_row"><span>Status</span><span id="user_storm_status" class="status_badge status_warn">Warn</span></div>
                <div class="stat_row"><span>Last update</span><span id="user_storm_time">N/A</span></div>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="user_summary_grid">
    <article class="panel">
        <h2 class="panel_head">Radiation Status</h2>
        <div class="panel_body">
            <?php if ($rad_row): ?>
                <div class="stat_row"><span>Current level</span><span id="user_rad_level" class="stat_val"><?php echo number_format((float) $rad_row['radiation_level'], 1); ?></span></div>
                <div class="stat_row"><span>Summary</span><span id="user_rad_status" class="status_badge <?php echo $rad_summary_class; ?>"><?php echo $rad_summary; ?></span></div>
                <div class="stat_row"><span>Last update</span><span id="user_rad_time"><?php echo htmlspecialchars($rad_row['created_at']); ?></span></div>
            <?php else: ?>
                <div class="stat_row"><span>Current level</span><span id="user_rad_level" class="stat_val">N/A</span></div>
                <div class="stat_row"><span>Summary</span><span id="user_rad_status" class="status_badge status_warn">Warn</span></div>
                <div class="stat_row"><span>Last update</span><span id="user_rad_time">N/A</span></div>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel">
        <h2 class="panel_head">Recent Events</h2>
        <div class="panel_body">
            <?php if (count($event_log) > 0): ?>
                <ul class="events_list">
                    <?php foreach ($event_log as $event): ?>
                        <li>
                            <span class="events_time"><?php echo htmlspecialchars($event['created_at']); ?></span>
                            <span class="events_text"><?php echo htmlspecialchars($event['event_type']); ?></span>
                            <span class="status_badge <?php echo event_severity_cls($event['event_type'], $event['notes']); ?>"><?php echo event_severity_label($event['event_type'], $event['notes']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No events logged.</p>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="user_activity_section">
    <article class="panel">
        <h2 class="panel_head">System Activity Trend</h2>
        <div class="panel_body">
            <div class="chart_box user_small_chart">
                <canvas id="user_chart_activity"></canvas>
            </div>
        </div>
    </article>
</section>

<?php if (!$is_refresh): ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/user_charts.js"></script>
<script src="../assets/js/auto_refresh.js"></script>
<script src="../assets/js/user.js"></script>
<?php include '../includes/footer.php'; ?>
<?php endif; ?>
