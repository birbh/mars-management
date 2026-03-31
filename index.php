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
<span id="index_refresh_note">Live update: static preview</span>
</div>
</section>

<section class="telemetry_grid">
<article class="panel storm_monitor">
<div class="panel_head">Storm Monitor</div>
<div class="panel_body">
<?php if ($storm_row): ?>
    <p>Intensity: <strong id="index_storm_intensity"><?php echo (int) $storm_row['intensity']; ?></strong></p>
    <p>Trend: <span id="index_storm_trend" class="<?php echo $storm_trend_cls; ?>"><?php echo htmlspecialchars($storm_trend); ?></span></p>
    <p>Last update: <span id="index_storm_time"><?php echo $storm_row['created_at']; ?></span></p>
<?php else: ?>
    <p>Intensity: <strong id="index_storm_intensity">N/A</strong></p>
    <p>Trend: <span id="index_storm_trend" class="status_warn">unknown</span></p>
    <p>Last update: <span id="index_storm_time">N/A</span></p>
<?php endif; ?>
</div>
</article>

<article class="panel rad_status">
<div class="panel_head">Radiation Status</div>
<div class="panel_body">
<?php if ($rad_row): ?>
    <p>Radiation level: <strong id="index_rad_level"><?php echo $rad_row['radiation_level']; ?></strong></p>
    <p>Status: <span id="index_rad_status" class="<?php echo status_cls($rad_row['status']); ?> <?php echo $rad_row['status'] === 'danger' ? 'pulse_danger' : ''; ?>"><?php echo htmlspecialchars($rad_row['status']); ?></span></p>
    <p>Last update: <span id="index_rad_time"><?php echo $rad_row['created_at']; ?></span></p>
<?php else: ?>
    <p>Radiation level: <strong id="index_rad_level">N/A</strong></p>
    <p>Status: <span id="index_rad_status" class="status_warn">unknown</span></p>
    <p>Last update: <span id="index_rad_time">N/A</span></p>
<?php endif; ?>
</div>
</article>

<article class="panel pwr_system">
<div class="panel_head">Power System</div>
<div class="panel_body">
<?php if ($pwr_row): ?>
    <p>Solar output: <strong id="index_power_solar"><?php echo $pwr_row['solar_output']; ?></strong></p>
    <p>Battery level: <strong id="index_power_battery"><?php echo $pwr_row['battery_level']; ?>%</strong></p>
    <p>Power mode: <span id="index_power_mode" class="<?php echo status_cls($pwr_row['mode']); ?> <?php echo $pwr_row['mode'] === 'critical' ? 'pulse_danger' : ''; ?>"><?php echo htmlspecialchars($pwr_row['mode']); ?></span></p>
<?php else: ?>
    <p>Solar output: <strong id="index_power_solar">N/A</strong></p>
    <p>Battery level: <strong id="index_power_battery">N/A</strong></p>
    <p>Power mode: <span id="index_power_mode" class="status_warn">unknown</span></p>
<?php endif; ?>
</div>
</article>

<article class="panel sys_health">
<div class="panel_head">System Health</div>
<div class="panel_body">
<p>System health: <strong id="index_health_value"><?php echo $sys_health; ?>%</strong></p>
<div class="health_bar">
    <div id="index_health_fill" class="health_fill" style="width: <?php echo $sys_health; ?>%; background: <?php echo $health_color; ?>;"></div>
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
        <tbody id="index_event_rows">
            <?php foreach ($event_log as $event): ?>
                <tr>
                    <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                    <td><?php echo htmlspecialchars($event['notes']); ?></td>
                    <td><?php echo $event['created_at']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <table>
        <tr>
            <th>Event type</th>
            <th>Notes</th>
            <th>Time</th>
        </tr>
        <tbody id="index_event_rows"></tbody>
    </table>
    <p id="index_event_empty">No events logged yet.</p>
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

<script src="assets/js/api_client.js"></script>
<script>
(function () {
    function indexSetText(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    function indexStatusClass(status) {
        if (status === 'danger' || status === 'critical') {
            return 'status_danger';
        }
        if (status === 'warning' || status === 'warn') {
            return 'status_warn';
        }
        return 'status_safe';
    }

    function indexHealthColor(health) {
        if (health < 50) {
            return '#ff5a66';
        }
        if (health < 80) {
            return '#f5a93b';
        }
        return '#57d783';
    }

    function indexEscapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function isAuthenticated() {
        try {
            var res = await fetch('api/auth/session.php', { credentials: 'same-origin' });
            var json = await res.json();
            return Boolean(json && json.authenticated);
        } catch (err) {
            return false;
        }
    }

    function renderIndexEvents(events) {
        var rowsEl = document.getElementById('index_event_rows');
        var emptyEl = document.getElementById('index_event_empty');
        if (!rowsEl || !Array.isArray(events)) {
            return;
        }

        if (events.length === 0) {
            rowsEl.innerHTML = '';
            if (emptyEl) {
                emptyEl.style.display = '';
            }
            return;
        }

        if (emptyEl) {
            emptyEl.style.display = 'none';
        }

        rowsEl.innerHTML = events.map(function (event) {
            return '<tr>'
                + '<td>' + indexEscapeHtml(event && event.event_type ? event.event_type : '') + '</td>'
                + '<td>' + indexEscapeHtml(event && event.notes ? event.notes : '') + '</td>'
                + '<td>' + indexEscapeHtml(event && event.created_at ? event.created_at : 'N/A') + '</td>'
                + '</tr>';
        }).join('');
    }

    function updateIndexFromLatest(latest) {
        if (!latest) {
            return;
        }

        if (latest.storm) {
            var intensity = Number(latest.storm.intensity || 0);
            var trend = 'stable';
            if (intensity >= 8) {
                trend = 'rising';
            } else if (intensity < 4) {
                trend = 'falling';
            }

            indexSetText('index_storm_intensity', String(intensity));
            indexSetText('index_storm_time', latest.storm.created_at || 'N/A');

            var trendEl = document.getElementById('index_storm_trend');
            if (trendEl) {
                trendEl.textContent = trend;
                trendEl.classList.remove('status_safe', 'status_warn', 'status_danger');
                trendEl.classList.add(intensity >= 8 ? 'status_danger' : (intensity >= 5 ? 'status_warn' : 'status_safe'));
            }
        }

        if (latest.radiation) {
            var radLevel = Number(latest.radiation.radiation_level || 0);
            var radStatus = String(latest.radiation.status || 'safe').toLowerCase();

            indexSetText('index_rad_level', radLevel.toFixed(2));
            indexSetText('index_rad_time', latest.radiation.created_at || 'N/A');

            var radEl = document.getElementById('index_rad_status');
            if (radEl) {
                radEl.textContent = radStatus;
                radEl.classList.remove('status_safe', 'status_warn', 'status_danger', 'pulse_danger');
                radEl.classList.add(indexStatusClass(radStatus));
                if (radStatus === 'danger' || radStatus === 'critical') {
                    radEl.classList.add('pulse_danger');
                }
            }
        }

        if (latest.power) {
            var powerMode = String(latest.power.mode || 'normal').toLowerCase();
            indexSetText('index_power_solar', String(latest.power.solar_output ?? 'N/A'));
            indexSetText('index_power_battery', String(latest.power.battery_level ?? 'N/A') + '%');

            var powerEl = document.getElementById('index_power_mode');
            if (powerEl) {
                powerEl.textContent = powerMode;
                powerEl.classList.remove('status_safe', 'status_warn', 'status_danger', 'pulse_danger');
                powerEl.classList.add(indexStatusClass(powerMode === 'critical' ? 'danger' : 'safe'));
                if (powerMode === 'critical') {
                    powerEl.classList.add('pulse_danger');
                }
            }
        }

        var health = Number(latest.health || 0);
        indexSetText('index_health_value', String(health) + '%');
        var fillEl = document.getElementById('index_health_fill');
        if (fillEl) {
            fillEl.style.width = health + '%';
            fillEl.style.background = indexHealthColor(health);
        }
    }

    async function refreshIndexLive() {
        try {
            var latest = await api_get('api/telemetry/latest.php');
            var events = await api_get('api/events/recent.php?limit=8');

            updateIndexFromLatest(latest);
            renderIndexEvents(events && events.events ? events.events : []);

            indexSetText('index_refresh_note', 'Live update: ' + new Date().toLocaleTimeString());
        } catch (err) {
            console.log('Index live refresh failed:', err.message);
        }
    }

    isAuthenticated().then(function (ok) {
        if (!ok) {
            return;
        }

        refreshIndexLive();
        setInterval(function () {
            if (!document.hidden) {
                refreshIndexLive();
            }
        }, 5000);

        window.addEventListener('mars_api_bridge_updated', refreshIndexLive);
    });
})();
</script>

<?php
$close_content_wrapper = false;
include 'includes/footer.php';
?>



