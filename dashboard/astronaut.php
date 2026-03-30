<?php
include '../includes/auth.php';
include '../config/db.php';
include '../lib/db_tools.php';

$role = $_SESSION['role'] ?? '';
if ($role !== 'astronaut') {
    if ($role === 'admin') {
        header('Location: admin.php');
    } elseif ($role === 'user') {
        header('Location: user.php');
    } else {
        header('Location: ../login.php');
    }
    exit();
}

$is_refresh = isset($_GET['refresh']);

function status_cls($value)
{
    if ($value === 'danger' || $value === 'critical') {
        return 'status_critical';
    }

    if ($value === 'warning') {
        return 'status_warn';
    }

    return 'status_safe';
}

$latest_storm = db_fetch_one($conn, "SELECT * FROM solar_storms ORDER BY created_at DESC LIMIT 1");
$latest_radiation = db_fetch_one($conn, "SELECT * FROM radiation_logs ORDER BY created_at DESC LIMIT 1");

$latest_power = db_fetch_one(
    $conn,
    "SELECT p.*, s.intensity FROM power_logs p LEFT JOIN solar_storms s ON p.storm_id = s.id ORDER BY p.created_at DESC LIMIT 1"
);

$event_log = db_fetch_all(
    $conn,
    "SELECT event_type, notes, created_at FROM events ORDER BY created_at DESC LIMIT 10"
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
if ($latest_radiation) {
    if ($latest_radiation['status'] === 'danger') {
        $health -= 30;
    } elseif ($latest_radiation['status'] === 'warning') {
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

    if (isset($latest_power['intensity']) && (int) $latest_power['intensity'] > 7) {
        $health -= 15;
    }
}

if ($health < 0) {
    $health = 0;
}

if ($health < 40) {
    db_insert_event_cooldown(
        $conn,
        'System-wide Critical Condition',
        'Combined system health dropped below 40%. Immediate intervention required.',
        5
    );
}
?>

<?php if (!$is_refresh): ?>
<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/astro.css">
<h1>Astronaut Dashboard</h1>
<section class="status_bar">
    <div class="status_item">Mission <span class="status_badge status_safe">ACTIVE</span></div>
    <div class="status_item">Network <span class="status_badge status_safe">SYNCED</span></div>
    <div class="status_item">Role <span class="status_badge">ASTRONAUT</span></div>
    <div id="refresh_note_astro" class="status_item">Last refresh: waiting</div>
</section>
<section id="astro_emergency_alert" class="emergency_alert" aria-live="assertive" role="alert">
    <div class="emergency_text">Emergency protocol active: move to shielded zone immediately.</div>
    <div class="emergency_meta">
        <span>Escalation countdown: <strong id="astro_emergency_countdown">15s</strong></span>
        <button type="button" id="astro_alert_ack" class="emergency_ack">Acknowledge</button>
    </div>
</section>
<div id="dashboard_content">
<?php endif; ?>

<section class="telemetry_grid">
    <article class="panel">
        <h2 class="panel_head">Storm Monitor</h2>
        <div class="panel_body">
            <?php if ($latest_storm): ?>
                <div class="stat_row"><span>Current intensity</span><span id="astro_storm_intensity" class="stat_val"><?php echo (int) $latest_storm['intensity']; ?></span></div>
                <div class="stat_row"><span>Status</span>
                    <?php
                    $storm_status = 'status_safe';
                    if ((int) $latest_storm['intensity'] >= 8) {
                        $storm_status = 'status_critical';
                    } elseif ((int) $latest_storm['intensity'] >= 5) {
                        $storm_status = 'status_warn';
                    }
                    ?>
                    <span id="astro_storm_status" class="status_badge <?php echo $storm_status; ?>"><?php echo $storm_status === 'status_critical' ? 'Critical' : ($storm_status === 'status_warn' ? 'Warn' : 'Safe'); ?></span>
                </div>
                <div class="stat_row"><span>Last update</span><span id="astro_storm_time"><?php echo htmlspecialchars($latest_storm['created_at']); ?></span></div>
            <?php else: ?>
                <div class="stat_row"><span>Current intensity</span><span id="astro_storm_intensity" class="stat_val">N/A</span></div>
                <div class="stat_row"><span>Status</span><span id="astro_storm_status" class="status_badge status_warn">Warn</span></div>
                <div class="stat_row"><span>Last update</span><span id="astro_storm_time">N/A</span></div>
            <?php endif; ?>
            <div class="chart_box">
                <canvas id="astro_chart_storm"></canvas>
            </div>
        </div>
    </article>

    <article class="panel">
        <h2 class="panel_head">Power System</h2>
        <div class="panel_body">
            <?php if ($latest_power): ?>
                <div class="stat_row"><span>Solar output</span><span id="astro_power_solar" class="stat_val"><?php echo (int) $latest_power['solar_output']; ?></span></div>
                <div class="stat_row"><span>Battery level</span><span id="astro_power_battery" class="stat_val"><?php echo (int) $latest_power['battery_level']; ?>%</span></div>
                <div class="stat_row"><span>Status</span><span id="astro_power_status" class="status_badge <?php echo status_cls($latest_power['mode']); ?>"><?php echo $latest_power['mode'] === 'critical' ? 'Critical' : 'Safe'; ?></span></div>
                <div class="stat_row"><span>Last update</span><span id="astro_power_time"><?php echo htmlspecialchars($latest_power['created_at']); ?></span></div>
            <?php else: ?>
                <div class="stat_row"><span>Solar output</span><span id="astro_power_solar" class="stat_val">N/A</span></div>
                <div class="stat_row"><span>Battery level</span><span id="astro_power_battery" class="stat_val">N/A</span></div>
                <div class="stat_row"><span>Status</span><span id="astro_power_status" class="status_badge status_warn">Warn</span></div>
                <div class="stat_row"><span>Last update</span><span id="astro_power_time">N/A</span></div>
            <?php endif; ?>
            <div class="chart_box">
                <canvas id="astro_chart_power"></canvas>
            </div>
        </div>
    </article>

    <article class="panel">
        <h2 class="panel_head">Radiation Monitor</h2>
        <div class="panel_body">
            <?php if ($latest_radiation): ?>
                <div class="stat_row"><span>Current level</span><span id="astro_rad_level" class="stat_val"><?php echo number_format((float) $latest_radiation['radiation_level'], 2); ?></span></div>
                <div class="stat_row"><span>Status</span><span id="astro_rad_status" class="status_badge <?php echo status_cls($latest_radiation['status']); ?>"><?php echo $latest_radiation['status'] === 'danger' ? 'Critical' : ucfirst($latest_radiation['status']); ?></span></div>
                <div class="stat_row"><span>Last update</span><span id="astro_rad_time"><?php echo htmlspecialchars($latest_radiation['created_at']); ?></span></div>
            <?php else: ?>
                <div class="stat_row"><span>Current level</span><span id="astro_rad_level" class="stat_val">N/A</span></div>
                <div class="stat_row"><span>Status</span><span id="astro_rad_status" class="status_badge status_warn">Warn</span></div>
                <div class="stat_row"><span>Last update</span><span id="astro_rad_time">N/A</span></div>
            <?php endif; ?>
            <div class="chart_box">
                <canvas id="astro_chart_radiation"></canvas>
            </div>
        </div>
    </article>

    <article class="panel">
        <h2 class="panel_head">System Health</h2>
        <div class="panel_body">
            <div class="stat_row"><span>Healthy ratio</span><span id="astro_health_value" class="stat_val"><?php echo $health; ?>%</span></div>
            <div class="stat_row"><span>Status</span>
                <?php
                $health_status = $health >= 80 ? 'status_safe' : ($health >= 50 ? 'status_warn' : 'status_critical');
                $health_label = $health >= 80 ? 'SAFE' : ($health >= 50 ? 'WARN' : 'CRITICAL');
                ?>
                <span id="astro_health_status" class="status_badge <?php echo $health_status; ?>"><?php echo ucfirst(strtolower($health_label)); ?></span>
            </div>
            <div class="stat_row"><span>Last update</span><span id="astro_health_time"><?php echo date('Y-m-d H:i:s'); ?></span></div>
            <div class="chart_box doughnut_box">
                <canvas id="astro_chart_health"></canvas>
            </div>
        </div>
    </article>
</section>

<section class="telemetry_secondary">
    <article class="panel">
        <h2 class="panel_head">Power History</h2>
        <div class="panel_body">
            <div class="chart_box wide_chart">
                <canvas id="astro_chart_power_history"></canvas>
            </div>
        </div>
    </article>

    <article class="panel">
        <h2 class="panel_head">Recent Events</h2>
        <div class="panel_body">
            <?php if (count($event_log) > 0): ?>
                <table class="events_table telemetry_table">
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>Severity</th>
                    </tr>
                    <?php foreach ($event_log as $event): ?>
                        <?php $severity_class = event_severity_cls($event['event_type'], $event['notes']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                            <td><span class="status_badge <?php echo $severity_class; ?>"><?php echo event_severity_label($event['event_type'], $event['notes']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No events logged.</p>
            <?php endif; ?>
        </div>
    </article>
</section>

<?php if (!$is_refresh): ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/astro_charts.js"></script>
<script src="../assets/js/auto_refresh.js"></script>
<script src="../assets/js/astro.js"></script>
<?php include '../includes/footer.php'; ?>
<?php endif; ?>