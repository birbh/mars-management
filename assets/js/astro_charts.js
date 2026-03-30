const astro_chart_store = {
    storm: null,
    radiation: null,
    power: null,
    health: null,
    power_history: null,
};

const telemetry_palette = {
    primary_blue: '#4da3ff',
    warning_orange: '#f59e0b',
    danger_red: '#ef4444',
    safe_green: '#22c55e',
    border: '#2a3442',
    grid: 'rgba(42, 52, 66, 0.45)',
    text: '#9eabb9',
};

const astro_alert_state = {
    storm: false,
    radiation: false,
    power: false,
    health: false,
};

const astro_emergency_state = {
    acknowledged: false,
    secondsRemaining: 15,
    timerId: null,
    wasCritical: false,
};

function astro_recent_labels(count) {
    const labels = [];
    const now = new Date();

    for (let idx = count - 1; idx >= 0; idx -= 1) {
        const point_time = new Date(now.getTime() - idx * 5 * 60000);
        labels.push(point_time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
    }

    return labels;
}

function astro_mock_storm_payload() {
    const labels = astro_recent_labels(12);
    const values = labels.map(function (_, idx) {
        return Math.max(1, Math.min(10, Math.round(5 + Math.sin((Date.now() / 850000) + idx) * 3)));
    });

    return {
        labels: labels,
        values: values,
        latest: {
            intensity: values[values.length - 1],
            created_at: labels[labels.length - 1],
        },
    };
}

function astro_mock_radiation_payload() {
    const labels = astro_recent_labels(12);
    const values = labels.map(function (_, idx) {
        return Number((2 + Math.abs(Math.cos((Date.now() / 900000) + idx)) * 3.3).toFixed(2));
    });
    const latest_level = values[values.length - 1];
    let status = 'safe';

    if (latest_level >= 5.1) {
        status = 'danger';
    } else if (latest_level >= 3.8) {
        status = 'warning';
    }

    return {
        labels: labels,
        values: values,
        latest: {
            radiation_level: latest_level,
            status: status,
            created_at: labels[labels.length - 1],
        },
    };
}

function astro_mock_power_payload() {
    const labels = astro_recent_labels(12);
    const solar_output = labels.map(function (_, idx) {
        return Math.max(18, Math.round(68 + Math.sin((Date.now() / 800000) + idx) * 24));
    });
    const battery_level = labels.map(function (_, idx) {
        return Math.max(12, Math.min(100, Math.round(58 + Math.cos((Date.now() / 1100000) + idx) * 21)));
    });
    const latest_battery = battery_level[battery_level.length - 1];
    const mode = latest_battery < 25 ? 'critical' : 'normal';

    return {
        labels: labels,
        solar_output: solar_output,
        battery_level: battery_level,
        latest: {
            solar_output: solar_output[solar_output.length - 1],
            battery_level: latest_battery,
            mode: mode,
            created_at: labels[labels.length - 1],
        },
    };
}

function astro_mock_health_value() {
    return Math.max(35, Math.min(96, Math.round(70 + Math.sin(Date.now() / 950000) * 24)));
}

function astro_sync_siren_state() {
    if (!window.MarsSound || typeof window.MarsSound.setSirenActive !== 'function') {
        astro_sync_emergency_banner(false);
        return;
    }

    const any_critical = astro_alert_state.storm || astro_alert_state.radiation || astro_alert_state.power || astro_alert_state.health;
    window.MarsSound.setSirenActive(any_critical);
    astro_sync_emergency_banner(any_critical);
}

function astro_sync_emergency_banner(active) {
    const alert_el = document.getElementById('astro_emergency_alert');
    if (!alert_el) {
        return;
    }

    if (active && !astro_emergency_state.wasCritical) {
        astro_emergency_state.acknowledged = false;
        astro_emergency_state.secondsRemaining = 15;
    }

    astro_emergency_state.wasCritical = active;

    if (active) {
        alert_el.classList.add('is_active');
        astro_start_emergency_countdown();
    } else {
        alert_el.classList.remove('is_active');
        alert_el.classList.remove('is_acknowledged');
        astro_stop_emergency_countdown();
        astro_emergency_state.acknowledged = false;
        astro_emergency_state.secondsRemaining = 15;
        astro_render_emergency_meta();
    }

    if (active) {
        if (astro_emergency_state.acknowledged) {
            alert_el.classList.add('is_acknowledged');
        } else {
            alert_el.classList.remove('is_acknowledged');
        }
        astro_render_emergency_meta();
    }
}

function astro_render_emergency_meta() {
    const countdown_el = document.getElementById('astro_emergency_countdown');
    const ack_btn = document.getElementById('astro_alert_ack');

    if (countdown_el) {
        countdown_el.textContent = String(Math.max(0, astro_emergency_state.secondsRemaining)) + 's';
    }

    if (ack_btn) {
        ack_btn.textContent = astro_emergency_state.acknowledged ? 'Acknowledged' : 'Acknowledge';
    }
}

function astro_start_emergency_countdown() {
    if (astro_emergency_state.timerId !== null) {
        return;
    }

    astro_emergency_state.timerId = window.setInterval(function () {
        if (astro_emergency_state.acknowledged) {
            return;
        }

        if (astro_emergency_state.secondsRemaining > 0) {
            astro_emergency_state.secondsRemaining -= 1;
            astro_render_emergency_meta();
        }
    }, 1000);
}

function astro_stop_emergency_countdown() {
    if (astro_emergency_state.timerId !== null) {
        window.clearInterval(astro_emergency_state.timerId);
        astro_emergency_state.timerId = null;
    }
}

function astro_init_emergency_ack() {
    const ack_btn = document.getElementById('astro_alert_ack');
    if (!ack_btn || ack_btn.dataset.bound === '1') {
        return;
    }

    ack_btn.dataset.bound = '1';
    ack_btn.addEventListener('click', function () {
        astro_emergency_state.acknowledged = true;
        astro_render_emergency_meta();

        const alert_el = document.getElementById('astro_emergency_alert');
        if (alert_el) {
            alert_el.classList.add('is_acknowledged');
        }
    });
}

function astro_set_text(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function astro_set_badge(id, status) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }

    el.classList.remove('status_safe', 'status_warn', 'status_critical');

    if (status === 'critical') {
        el.classList.add('status_critical');
        el.textContent = 'Critical';
        return;
    }

    if (status === 'warn') {
        el.classList.add('status_warn');
        el.textContent = 'Warn';
        return;
    }

    el.classList.add('status_safe');
    el.textContent = 'Safe';
}

function astro_line_or_bar_options() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 240 },
        plugins: {
            legend: {
                labels: { color: telemetry_palette.text },
            },
        },
        scales: {
            x: {
                ticks: { color: telemetry_palette.text },
                grid: { color: telemetry_palette.grid, lineWidth: 1 },
            },
            y: {
                beginAtZero: true,
                ticks: { color: telemetry_palette.text },
                grid: { color: telemetry_palette.grid, lineWidth: 1 },
            },
        },
    };
}

function astro_doughnut_options() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 240 },
        plugins: {
            legend: {
                labels: { color: telemetry_palette.text },
            },
        },
    };
}

function astro_render_or_replace(chart_key, canvas_id, type, data, options) {
    const canvas = document.getElementById(canvas_id);
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    if (astro_chart_store[chart_key]) {
        const live_chart = astro_chart_store[chart_key];
        live_chart.data.labels = data.labels;
        live_chart.data.datasets = data.datasets;
        live_chart.options = options;
        live_chart.update('none');
        return;
    }

    astro_chart_store[chart_key] = new Chart(canvas.getContext('2d'), {
        type: type,
        data: data,
        options: options,
    });
}

function astro_load_storm_chart() {
    const payload = astro_mock_storm_payload();

    if (payload.latest) {
        const intensity = Number(payload.latest.intensity || 0);
        astro_set_text('astro_storm_intensity', String(intensity));
        astro_set_text('astro_storm_time', payload.latest.created_at || 'N/A');

        if (intensity >= 8) {
            astro_set_badge('astro_storm_status', 'critical');
            astro_alert_state.storm = true;
        } else if (intensity >= 5) {
            astro_set_badge('astro_storm_status', 'warn');
            astro_alert_state.storm = false;
        } else {
            astro_set_badge('astro_storm_status', 'safe');
            astro_alert_state.storm = false;
        }
    } else {
        astro_alert_state.storm = false;
    }

    astro_sync_siren_state();

    astro_render_or_replace(
        'storm',
        'astro_chart_storm',
        'line',
        {
            labels: payload.labels || [],
            datasets: [
                {
                    label: 'Storm intensity',
                    data: payload.values || [],
                    borderColor: telemetry_palette.warning_orange,
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.2,
                    fill: true,
                    borderWidth: 2,
                },
            ],
        },
        astro_line_or_bar_options()
    );

    return Promise.resolve();
}

function astro_load_radiation_chart() {
    const payload = astro_mock_radiation_payload();

    if (payload.latest) {
        const level = Number(payload.latest.radiation_level || 0);
        const status = String(payload.latest.status || 'safe');

        astro_set_text('astro_rad_level', level.toFixed(2));
        astro_set_text('astro_rad_time', payload.latest.created_at || 'N/A');

        if (status === 'danger' || status === 'critical') {
            astro_set_badge('astro_rad_status', 'critical');
            astro_alert_state.radiation = true;
        } else if (status === 'warning') {
            astro_set_badge('astro_rad_status', 'warn');
            astro_alert_state.radiation = false;
        } else {
            astro_set_badge('astro_rad_status', 'safe');
            astro_alert_state.radiation = false;
        }
    } else {
        astro_alert_state.radiation = false;
    }

    astro_sync_siren_state();

    astro_render_or_replace(
        'radiation',
        'astro_chart_radiation',
        'line',
        {
            labels: payload.labels || [],
            datasets: [
                {
                    label: 'Radiation level',
                    data: payload.values || [],
                    borderColor: telemetry_palette.danger_red,
                    backgroundColor: 'rgba(239, 68, 68, 0.08)',
                    tension: 0.2,
                    fill: true,
                    borderWidth: 2,
                },
            ],
        },
        astro_line_or_bar_options()
    );

    return Promise.resolve();
}

function astro_load_power_charts() {
    const payload = astro_mock_power_payload();
    const latest = payload.latest || null;
    const backup_value = latest && latest.mode === 'critical' ? 100 : 0;

    if (latest) {
        astro_set_text('astro_power_solar', String(latest.solar_output ?? 'N/A'));
        astro_set_text('astro_power_battery', String(latest.battery_level ?? 'N/A') + '%');
        astro_set_text('astro_power_time', latest.created_at || 'N/A');
        astro_set_badge('astro_power_status', latest.mode === 'critical' ? 'critical' : 'safe');
        astro_alert_state.power = latest.mode === 'critical' || Number(latest.battery_level || 0) < 20;
    } else {
        astro_alert_state.power = false;
    }

    astro_sync_siren_state();

    astro_render_or_replace(
        'power',
        'astro_chart_power',
        'bar',
        {
            labels: ['Solar Output', 'Battery Level', 'Backup Status'],
            datasets: [
                {
                    label: 'Current values',
                    data: latest
                        ? [
                              Number(latest.solar_output || 0),
                              Number(latest.battery_level || 0),
                              backup_value,
                          ]
                        : [0, 0, 0],
                    backgroundColor: [
                        telemetry_palette.primary_blue,
                        telemetry_palette.safe_green,
                        telemetry_palette.warning_orange,
                    ],
                    borderColor: telemetry_palette.border,
                    borderWidth: 1,
                },
            ],
        },
        astro_line_or_bar_options()
    );

    astro_render_or_replace(
        'power_history',
        'astro_chart_power_history',
        'line',
        {
            labels: payload.labels || [],
            datasets: [
                {
                    label: 'Solar output',
                    data: payload.solar_output || [],
                    borderColor: telemetry_palette.primary_blue,
                    backgroundColor: 'rgba(77, 163, 255, 0.08)',
                    fill: false,
                    tension: 0.2,
                    borderWidth: 2,
                },
                {
                    label: 'Battery level',
                    data: payload.battery_level || [],
                    borderColor: telemetry_palette.safe_green,
                    backgroundColor: 'rgba(34, 197, 94, 0.08)',
                    fill: false,
                    tension: 0.2,
                    borderWidth: 2,
                },
            ],
        },
        astro_line_or_bar_options()
    );

    return Promise.resolve();
}

function astro_load_health_chart() {
    const health = astro_mock_health_value();
    astro_set_text('astro_health_value', String(health) + '%');
    astro_set_text('astro_health_time', new Date().toLocaleTimeString());

    if (health >= 80) {
        astro_set_badge('astro_health_status', 'safe');
        astro_alert_state.health = false;
    } else if (health >= 50) {
        astro_set_badge('astro_health_status', 'warn');
        astro_alert_state.health = false;
    } else {
        astro_set_badge('astro_health_status', 'critical');
        astro_alert_state.health = true;
    }

    astro_sync_siren_state();

    astro_render_or_replace(
        'health',
        'astro_chart_health',
        'doughnut',
        {
            labels: ['Healthy', 'Risk'],
            datasets: [
                {
                    data: [health, 100 - health],
                    backgroundColor: [telemetry_palette.safe_green, telemetry_palette.danger_red],
                    borderColor: telemetry_palette.border,
                    borderWidth: 1,
                },
            ],
        },
        astro_doughnut_options()
    );

    return Promise.resolve();
}

function load_astro_charts() {
    astro_init_emergency_ack();
    Promise.all([
        astro_load_storm_chart(),
        astro_load_radiation_chart(),
        astro_load_power_charts(),
        astro_load_health_chart(),
    ]).catch((err) => {
        console.log('Astronaut chart load failed:', err);
    });
}
