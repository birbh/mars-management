const user_chart_store = {
    activity: null,
};

const user_palette = {
    primary_blue: '#4da3ff',
    warning_orange: '#f59e0b',
    danger_red: '#ef4444',
    safe_green: '#22c55e',
    grid: 'rgba(42, 52, 66, 0.45)',
    text: '#9eabb9',
};

function user_recent_labels(count) {
    const labels = [];
    const now = new Date();

    for (let idx = count - 1; idx >= 0; idx -= 1) {
        const point_time = new Date(now.getTime() - idx * 5 * 60000);
        labels.push(point_time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
    }

    return labels;
}

function user_mock_storm_payload() {
    const labels = user_recent_labels(12);
    const values = labels.map(function (_, idx) {
        return Math.max(1, Math.min(10, Math.round(5 + Math.sin((Date.now() / 900000) + idx) * 2)));
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

function user_mock_radiation_payload() {
    const labels = user_recent_labels(12);
    const values = labels.map(function (_, idx) {
        return Number((1.8 + Math.abs(Math.cos((Date.now() / 700000) + idx)) * 3.5).toFixed(2));
    });
    const latest_level = values[values.length - 1];
    let status = 'safe';

    if (latest_level >= 4.8) {
        status = 'danger';
    } else if (latest_level >= 3.6) {
        status = 'warning';
    }

    return {
        latest: {
            radiation_level: latest_level,
            status: status,
            created_at: labels[labels.length - 1],
        },
    };
}

function user_mock_health_value() {
    return Math.max(45, Math.min(98, Math.round(74 + Math.sin(Date.now() / 900000) * 18)));
}

function user_set_text(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function user_set_badge(id, status) {
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
 
function user_line_options() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 220 },
        plugins: {
            legend: {
                labels: { color: user_palette.text },
            },
        },
        scales: {
            x: {
                ticks: { color: user_palette.text },
                grid: { color: user_palette.grid, lineWidth: 1 },
            },
            y: {
                beginAtZero: true,
                ticks: { color: user_palette.text },
                grid: { color: user_palette.grid, lineWidth: 1 },
            },
        },
    };
}

function user_render_or_replace(chart_key, canvas_id, data, options) {
    const canvas = document.getElementById(canvas_id);
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    if (user_chart_store[chart_key]) {
        const live_chart = user_chart_store[chart_key];
        if (live_chart.canvas !== canvas) {
            live_chart.destroy();
            user_chart_store[chart_key] = null;
        } else {
            live_chart.data.labels = data.labels;
            live_chart.data.datasets = data.datasets;
            live_chart.options = options;
            live_chart.update('none');
            return;
        }
    }

    user_chart_store[chart_key] = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: data,
        options: options,
    });
}

function user_load_health_summary() {
    const health = user_mock_health_value();
    user_set_text('user_health_value', String(health) + '%');
    user_set_text('user_health_time', new Date().toLocaleTimeString());

    if (health >= 80) {
        user_set_badge('user_health_status', 'safe');
    } else if (health >= 50) {
        user_set_badge('user_health_status', 'warn');
    } else {
        user_set_badge('user_health_status', 'critical');
    }

    return Promise.resolve();
}

function user_load_storm_summary_and_chart() {
    const payload = user_mock_storm_payload();

    if (payload.latest) {
        const intensity = Number(payload.latest.intensity || 0);
        user_set_text('user_storm_time', payload.latest.created_at || 'N/A');

        if (intensity >= 8) {
            user_set_text('user_storm_level', 'High');
            user_set_badge('user_storm_status', 'critical');
        } else if (intensity >= 5) {
            user_set_text('user_storm_level', 'Moderate');
            user_set_badge('user_storm_status', 'warn');
        } else {
            user_set_text('user_storm_level', 'Low');
            user_set_badge('user_storm_status', 'safe');
        }
    }

    user_render_or_replace(
        'activity',
        'user_chart_activity',
        {
            labels: payload.labels || [],
            datasets: [
                {
                    label: 'Activity index',
                    data: payload.values || [],
                    borderColor: user_palette.primary_blue,
                    backgroundColor: 'rgba(77, 163, 255, 0.08)',
                    fill: true,
                    tension: 0.2,
                    borderWidth: 2,
                },
            ],
        },
        user_line_options()
    );

    return Promise.resolve();
}

function user_load_radiation_summary() {
    const payload = user_mock_radiation_payload();
    if (!payload.latest) {
        return Promise.resolve();
    }

    const level = Number(payload.latest.radiation_level || 0);
    const status = String(payload.latest.status || 'safe');
    user_set_text('user_rad_level', level.toFixed(1));
    user_set_text('user_rad_time', payload.latest.created_at || 'N/A');

    if (status === 'danger' || status === 'critical') {
        user_set_badge('user_rad_status', 'critical');
    } else if (status === 'warning') {
        user_set_badge('user_rad_status', 'warn');
    } else {
        user_set_badge('user_rad_status', 'safe');
    }

    return Promise.resolve();
}

function load_user_charts() {
    Promise.all([
        user_load_health_summary(),
        user_load_storm_summary_and_chart(),
        user_load_radiation_summary(),
    ]).catch((err) => {
        console.log('User summary load failed:', err);
    });
}
