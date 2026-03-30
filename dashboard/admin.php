<?php
include '../includes/auth.php';
include '../config/db.php';
include '../lib/db_tools.php';

$role = $_SESSION['role'] ?? '';
if ($role !== 'admin') {
    if ($role === 'astronaut') {
        header('Location: astronaut.php');
    } elseif ($role === 'user') {
        header('Location: user.php');
    } else {
        header('Location: ../login.php');
    }
    exit();
}

$is_refresh = isset($_GET['refresh']);

function admin_url($params = [])
{
    $query = array_merge($_GET, $params);
    unset($query['refresh']);

    foreach ($query as $key => $value) {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            unset($query[$key]);
        }
    }

    return 'admin.php' . (count($query) ? ('?' . http_build_query($query)) : '');
}

$msg_ok = [];
$msg_err = [];
$edit_row = null;

$post_action = $_POST['action'] ?? '';

if ($post_action === 'create' || $post_action === 'update') {
    $storm_lvl = isset($_POST['storm_lvl']) ? (int) $_POST['storm_lvl'] : 0;
    $storm_desc = isset($_POST['storm_desc']) ? trim($_POST['storm_desc']) : '';

    if ($storm_lvl < 1 || $storm_lvl > 10) {
        $msg_err[] = 'Storm intensity must be between 1 and 10.';
    } else {
        if ($post_action === 'create') {
            $storm_stmt = db_run_stmt(
                $conn,
                'INSERT INTO solar_storms (intensity, description) VALUES (?, ?)',
                'is',
                [$storm_lvl, $storm_desc]
            );

            if (!$storm_stmt) {
                $msg_err[] = 'Failed to create storm record.';
            } else {
                $storm_stmt->close();
                $msg_ok[] = 'Storm record created.';

                $storm_id = (int) $conn->insert_id;

                // Keep linked telemetry generation deterministic from storm intensity.
                $rad_lvl = $storm_lvl * 12.5;

                if ($rad_lvl < 50) {
                    $rad_status = 'safe';
                } elseif ($rad_lvl <= 90) {
                    $rad_status = 'warning';
                } else {
                    $rad_status = 'danger';
                    db_insert_event_cooldown_storm(
                        $conn,
                        $storm_id,
                        'Emergency Shelter Activated',
                        'Radiation exceeded safe threshold.',
                        5
                    );
                }

                $rad_stmt = db_run_stmt(
                    $conn,
                    'INSERT INTO radiation_logs (storm_id, radiation_level, status) VALUES (?, ?, ?)',
                    'ids',
                    [$storm_id, $rad_lvl, $rad_status]
                );

                if ($rad_stmt) {
                    $rad_stmt->close();
                }

                $solar_out = 100 - $storm_lvl * 8;
                $battery_lvl = 100 - $storm_lvl * 10;
                $pwr_mode = $solar_out < 40 ? 'critical' : 'normal';

                $pwr_stmt = db_run_stmt(
                    $conn,
                    'INSERT INTO power_logs (storm_id, solar_output, battery_level, mode) VALUES (?, ?, ?, ?)',
                    'idds',
                    [$storm_id, $solar_out, $battery_lvl, $pwr_mode]
                );

                if ($pwr_stmt) {
                    $pwr_stmt->close();
                }
            }
        }

        if ($post_action === 'update') {
            $storm_id = isset($_POST['storm_id']) ? (int) $_POST['storm_id'] : 0;
            if ($storm_id <= 0) {
                $msg_err[] = 'Invalid storm id for update.';
            } else {
                $update_stmt = db_run_stmt(
                    $conn,
                    'UPDATE solar_storms SET intensity = ?, description = ? WHERE id = ? LIMIT 1',
                    'isi',
                    [$storm_lvl, $storm_desc, $storm_id]
                );

                if (!$update_stmt) {
                    $msg_err[] = 'Failed to update storm record.';
                } else {
                    $affected = $update_stmt->affected_rows;
                    $update_stmt->close();
                    if ($affected > 0) {
                        $msg_ok[] = 'Storm record updated.';
                    } else {
                        $msg_err[] = 'No storm record was updated.';
                    }
                }
            }
        }
    }
}

if ($post_action === 'delete') {
    $storm_id = isset($_POST['storm_id']) ? (int) $_POST['storm_id'] : 0;
    if ($storm_id <= 0) {
        $msg_err[] = 'Invalid storm id for delete.';
    } else {
        $delete_stmt = db_run_stmt(
            $conn,
            'DELETE FROM solar_storms WHERE id = ? LIMIT 1',
            'i',
            [$storm_id]
        );

        if (!$delete_stmt) {
            $msg_err[] = 'Failed to delete storm record.';
        } else {
            $affected = $delete_stmt->affected_rows;
            $delete_stmt->close();
            if ($affected > 0) {
                $msg_ok[] = 'Storm record deleted.';
            } else {
                $msg_err[] = 'No storm record was deleted.';
            }
        }
    }
}

$edit_id = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
if ($edit_id > 0) {
    // Preload one record for in-place update mode.
    $edit_row = db_fetch_one(
        $conn,
        'SELECT id, intensity, description FROM solar_storms WHERE id = ? LIMIT 1',
        'i',
        [$edit_id]
    );

    if (!$edit_row) {
        $msg_err[] = 'Storm record for edit not found.';
    }
}

$filter_lvl = isset($_GET['filter_lvl']) ? (int) $_GET['filter_lvl'] : 0;
if ($filter_lvl < 1 || $filter_lvl > 10) {
    $filter_lvl = 0;
}

$search_text = isset($_GET['search']) ? trim($_GET['search']) : '';
if (strlen($search_text) > 80) {
    $search_text = substr($search_text, 0, 80);
}

$where_parts = [];
$query_types = '';
$query_params = [];

if ($filter_lvl > 0) {
    $where_parts[] = 'intensity = ?';
    $query_types .= 'i';
    $query_params[] = $filter_lvl;
}

if ($search_text !== '') {
    $where_parts[] = 'description LIKE ?';
    $query_types .= 's';
    $query_params[] = '%' . $search_text . '%';
}

$where_sql = '';
if (count($where_parts) > 0) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
}

$per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// Resolve total rows for reliable pager bounds.
$total_rows_val = db_fetch_value(
    $conn,
    'SELECT COUNT(*) AS total_rows FROM solar_storms' . $where_sql,
    'total_rows',
    $query_types,
    $query_params
);
$total_rows = $total_rows_val !== null ? (int) $total_rows_val : 0;

$total_pages = (int) ceil($total_rows / $per_page);
if ($total_pages < 1) {
    $total_pages = 1;
}

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;

$row_sql = 'SELECT * FROM solar_storms' . $where_sql . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
$row_types = $query_types . 'ii';
$row_params = array_merge($query_params, [$per_page, $offset]);
$storm_rows = db_fetch_all($conn, $row_sql, $row_types, $row_params);

$storm_row = db_fetch_one($conn, 'SELECT * FROM solar_storms ORDER BY created_at DESC LIMIT 1');
$rad_row = db_fetch_one($conn, 'SELECT * FROM radiation_logs ORDER BY created_at DESC LIMIT 1');
$pwr_row = db_fetch_one($conn, 'SELECT * FROM power_logs ORDER BY created_at DESC LIMIT 1');
$event_log = db_fetch_all($conn, 'SELECT event_type, notes, created_at FROM events ORDER BY created_at DESC LIMIT 10');

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
?>

<?php if (!$is_refresh): ?>
<?php include '../includes/header.php';?>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <h1>Admin Dashboard</h1>
    <section class="status_bar">
        <div class="status_item">Mission <span class="status_badge status_safe">ACTIVE</span></div>
        <div class="status_item">Network <span class="status_badge status_safe">SYNCED</span></div>
        <div class="status_item">Role <span class="status_badge">ADMIN</span></div>
        <div id="refresh_note_admin" class="status_item">Last refresh: waiting</div>
    </section>
    <div id="dashboard_content">
<?php endif; ?>

    <section class="telemetry_grid">
        <article class="panel">
            <h2 class="panel_head">Storm Monitor</h2>
            <div class="panel_body">
                <?php if ($storm_row): ?>
                    <div class="stat_row"><span>Current intensity</span><span id="admin_storm_intensity" class="stat_val"><?php echo (int) $storm_row['intensity']; ?></span></div>
                    <?php
                    $storm_status = 'status_safe';
                    if ((int) $storm_row['intensity'] >= 8) {
                        $storm_status = 'status_critical';
                    } elseif ((int) $storm_row['intensity'] >= 5) {
                        $storm_status = 'status_warn';
                    }
                    ?>
                    <div class="stat_row"><span>Status</span><span id="admin_storm_status" class="status_badge <?php echo $storm_status; ?>"><?php echo $storm_status === 'status_critical' ? 'Critical' : ($storm_status === 'status_warn' ? 'Warn' : 'Safe'); ?></span></div>
                    <div class="stat_row"><span>Last update</span><span id="admin_storm_time"><?php echo htmlspecialchars($storm_row['created_at']); ?></span></div>
                <?php else: ?>
                    <div class="stat_row"><span>Current intensity</span><span id="admin_storm_intensity" class="stat_val">N/A</span></div>
                    <div class="stat_row"><span>Status</span><span id="admin_storm_status" class="status_badge status_warn">Warn</span></div>
                    <div class="stat_row"><span>Last update</span><span id="admin_storm_time">N/A</span></div>
                <?php endif; ?>
                <div class="chart_box">
                    <canvas id="admin_chart_storm"></canvas>
                </div>
            </div>
        </article>

        <article class="panel">
            <h2 class="panel_head">Power System</h2>
            <div class="panel_body">
                <?php if ($pwr_row): ?>
                    <div class="stat_row"><span>Solar output</span><span id="admin_power_solar" class="stat_val"><?php echo (int) $pwr_row['solar_output']; ?></span></div>
                    <div class="stat_row"><span>Battery level</span><span id="admin_power_battery" class="stat_val"><?php echo (int) $pwr_row['battery_level']; ?>%</span></div>
                    <div class="stat_row"><span>Status</span><span id="admin_power_mode" class="status_badge <?php echo status_cls($pwr_row['mode']); ?>"><?php echo $pwr_row['mode'] === 'critical' ? 'Critical' : 'Safe'; ?></span></div>
                    <div class="stat_row"><span>Last update</span><span id="admin_power_time"><?php echo htmlspecialchars($pwr_row['created_at']); ?></span></div>
                <?php else: ?>
                    <div class="stat_row"><span>Solar output</span><span id="admin_power_solar" class="stat_val">N/A</span></div>
                    <div class="stat_row"><span>Battery level</span><span id="admin_power_battery" class="stat_val">N/A</span></div>
                    <div class="stat_row"><span>Status</span><span id="admin_power_mode" class="status_badge status_warn">Warn</span></div>
                    <div class="stat_row"><span>Last update</span><span id="admin_power_time">N/A</span></div>
                <?php endif; ?>
                <div class="chart_box">
                    <canvas id="admin_chart_power"></canvas>
                </div>
            </div>
        </article>

        <article class="panel">
            <h2 class="panel_head">Radiation Monitor</h2>
            <div class="panel_body">
                <?php if ($rad_row): ?>
                    <div class="stat_row"><span>Current level</span><span id="admin_rad_level" class="stat_val"><?php echo number_format((float) $rad_row['radiation_level'], 2); ?></span></div>
                    <div class="stat_row"><span>Status</span><span id="admin_rad_status" class="status_badge <?php echo status_cls($rad_row['status']); ?>"><?php echo $rad_row['status'] === 'danger' ? 'Critical' : ucfirst($rad_row['status']); ?></span></div>
                    <div class="stat_row"><span>Last update</span><span id="admin_rad_time"><?php echo htmlspecialchars($rad_row['created_at']); ?></span></div>
                <?php else: ?>
                    <div class="stat_row"><span>Current level</span><span id="admin_rad_level" class="stat_val">N/A</span></div>
                    <div class="stat_row"><span>Status</span><span id="admin_rad_status" class="status_badge status_warn">Warn</span></div>
                    <div class="stat_row"><span>Last update</span><span id="admin_rad_time">N/A</span></div>
                <?php endif; ?>
                <div class="chart_box">
                    <canvas id="admin_chart_radiation"></canvas>
                </div>
            </div>
        </article>

        <article class="panel">
            <h2 class="panel_head">System Health</h2>
            <div class="panel_body">
                <?php
                $health = 100;
                if ($rad_row) {
                    if ($rad_row['status'] === 'danger') {
                        $health -= 30;
                    } elseif ($rad_row['status'] === 'warning') {
                        $health -= 15;
                    }
                }
                if ($pwr_row) {
                    if ($pwr_row['mode'] === 'critical') {
                        $health -= 25;
                    }
                    if ((float) $pwr_row['battery_level'] < 40) {
                        $health -= 15;
                    }
                    if ((float) $pwr_row['battery_level'] < 20) {
                        $health -= 10;
                    }
                }
                $health = max(0, $health);
                $health_status = $health >= 80 ? 'status_safe' : ($health >= 50 ? 'status_warn' : 'status_critical');
                $health_label = $health >= 80 ? 'SAFE' : ($health >= 50 ? 'WARN' : 'CRITICAL');
                ?>
                <div class="stat_row"><span>Healthy ratio</span><span id="admin_health_value" class="stat_val"><?php echo $health; ?>%</span></div>
                <div class="stat_row"><span>Status</span><span id="admin_health_status" class="status_badge <?php echo $health_status; ?>"><?php echo ucfirst(strtolower($health_label)); ?></span></div>
                <div class="stat_row"><span>Last update</span><span id="admin_health_time"><?php echo date('Y-m-d H:i:s'); ?></span></div>
                <div class="chart_box doughnut_box">
                    <canvas id="admin_chart_health"></canvas>
                </div>
            </div>
        </article>
    </section>

    <section class="telemetry_secondary">
        <article class="panel">
            <h2 class="panel_head">Power History</h2>
            <div class="panel_body">
                <div class="chart_box wide_chart">
                    <canvas id="admin_chart_power_history"></canvas>
                </div>
            </div>
        </article>

        <article class="panel">
            <h2 class="panel_head">Recent Events</h2>
            <div class="panel_body">
                <?php if (count($event_log) > 0): ?>
                    <table class="telemetry_table">
                        <tr>
                            <th>Time</th>
                            <th>Event</th>
                            <th>Severity</th>
                        </tr>
                        <?php foreach ($event_log as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                <td><span class="status_badge <?php echo event_severity_cls($event['event_type'], $event['notes']); ?>"><?php echo event_severity_label($event['event_type'], $event['notes']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No events logged.</p>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="admin_tools">
    <article class="panel mod_admin_form">
    <h2 class="panel_head">Storm Management</h2>
    <div class="panel_body">
    <form method="POST" class="admin_form">
        <input type="hidden" name="action" value="<?php echo $edit_row ? 'update' : 'create'; ?>">
        <?php if ($edit_row): ?>
            <input type="hidden" name="storm_id" value="<?php echo (int) $edit_row['id']; ?>">
        <?php endif; ?>

        <label for="storm_lvl">Storm intensity (1-10)</label>
        <input id="storm_lvl" type="number" name="storm_lvl" min="1" max="10" required value="<?php echo $edit_row ? (int) $edit_row['intensity'] : ''; ?>">

        <label for="storm_desc">Storm description</label>
        <textarea id="storm_desc" name="storm_desc" placeholder="Describe storm condition"><?php echo $edit_row ? htmlspecialchars($edit_row['description']) : ''; ?></textarea>

        <div class="btn_row">
            <input type="submit" value="<?php echo $edit_row ? 'Update Storm' : 'Create Storm'; ?>">
            <?php if ($edit_row): ?>
                <a class="btn_link" href="admin.php">Cancel Edit</a>
            <?php endif; ?>
        </div>
    </form>
    </div>
    </article>

    <article class="panel mod_admin_logs">
    <h2 class="panel_head">Storm Log</h2>
    <div class="panel_body">

    <form method="GET" class="filter_form">
        <label for="filter_lvl">Storm intensity</label>
        <select id="filter_lvl" name="filter_lvl">
            <option value="">All</option>
            <?php for ($i = 1; $i <= 10; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $filter_lvl === $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
            <?php endfor; ?>
        </select>

        <label for="search">Description search</label>
        <input id="search" type="text" name="search" value="<?php echo htmlspecialchars($search_text); ?>" placeholder="Description contains">

        <button type="submit">Apply</button>
        <a class="btn_link" href="admin.php">Clear</a>
    </form>

<?php
    foreach ($msg_ok as $msg) {
        echo "<p class='success'>" . htmlspecialchars($msg) . "</p>";
    }
    foreach ($msg_err as $msg) {
        echo "<p class='error'>" . htmlspecialchars($msg) . "</p>";
    }

    if (count($storm_rows) > 0) {
        echo"<table>";
        echo "<tr><th>ID</th>
        <th>Intensity</th>
        <th>Description</th>
        <th>Timestamp</th>
        <th>Actions</th></tr>";
        foreach ($storm_rows as $row) {
            $row_id = (int) $row['id'];
            $edit_link = admin_url(['edit_id' => $row_id, 'page' => $page]);

            echo "<tr>
            <td>".$row_id."</td>
            <td>".(int) $row['intensity']."</td>
            <td>".htmlspecialchars($row['description'])."</td>
            <td>".htmlspecialchars($row['created_at'])."</td>
            <td>
                <div class='table_action'>
                    <a class='btn_link small' href='".htmlspecialchars($edit_link)."'>Edit</a>
                    <form method='POST' class='inline_form'>
                        <input type='hidden' name='action' value='delete'>
                        <input type='hidden' name='storm_id' value='".$row_id."'>
                        <button type='submit' class='btn_link small danger'>Delete</button>
                    </form>
                </div>
            </td>
            </tr>";
        }
        echo"</table>";

        echo "<div class='pager'>";
        echo "<span>Page " . $page . " of " . $total_pages . "</span>";

        if ($page > 1) {
            $prev_url = admin_url(['page' => $page - 1]);
            echo "<a class='btn_link small' href='" . htmlspecialchars($prev_url) . "'>Previous</a>";
        }

        if ($page < $total_pages) {
            $next_url = admin_url(['page' => $page + 1]);
            echo "<a class='btn_link small' href='" . htmlspecialchars($next_url) . "'>Next</a>";
        }

        echo "</div>";
    }
    else
        echo"<p>No storm data found.<br>Log some data to see it.</p>";
?>
</div>
</article>
</section>

<?php if (!$is_refresh): ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin_charts.js"></script>
    <script src="../assets/js/auto_refresh.js"></script>
    <script src="../assets/js/admin.js"></script>
<?php include '../includes/footer.php';?>
<?php endif; ?>
