function user_escape_html(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function user_set_text(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function user_set_badge(id, status, safeLabel, warnLabel, criticalLabel) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }

    el.classList.remove('status_safe', 'status_warn', 'status_critical');

    if (status === 'critical' || status === 'danger' || status === 'high') {
        el.classList.add('status_critical');
        el.textContent = criticalLabel || 'Critical';
        return;
    }

    if (status === 'warn' || status === 'warning' || status === 'moderate') {
        el.classList.add('status_warn');
        el.textContent = warnLabel || 'Warn';
        return;
    }

    el.classList.add('status_safe');
    el.textContent = safeLabel || 'Safe';
}

function user_render_events(events) {
    const listEl = document.getElementById('user_event_list');
    const emptyEl = document.getElementById('user_event_empty');
    if (!listEl || !Array.isArray(events)) {
        return;
    }

    if (events.length === 0) {
        listEl.innerHTML = '';
        if (emptyEl) {
            emptyEl.style.display = '';
        }
        return;
    }

    if (emptyEl) {
        emptyEl.style.display = 'none';
    }

    listEl.innerHTML = events.map(function (event) {
        const eventType = event && event.event_type ? event.event_type : '';
        const notes = event && event.notes ? event.notes : '';
        const time = event && event.created_at ? event.created_at : 'N/A';
        const combined = String(eventType + ' ' + notes).toLowerCase();
        let cls = 'status_safe';
        let label = 'Safe';

        if (combined.indexOf('critical') !== -1 || combined.indexOf('emergency') !== -1 || combined.indexOf('danger') !== -1) {
            cls = 'status_critical';
            label = 'Critical';
        } else if (combined.indexOf('warn') !== -1 || combined.indexOf('elevated') !== -1 || combined.indexOf('monitor') !== -1) {
            cls = 'status_warn';
            label = 'Warn';
        }

        return '<li>'
            + '<span class="events_time">' + user_escape_html(time) + '</span>'
            + '<span class="events_text">' + user_escape_html(eventType) + '</span>'
            + '<span class="status_badge ' + cls + '">' + label + '</span>'
            + '</li>';
    }).join('');
}

async function refresh_user_panels() {
    try {
        const latest = await api_get('../api/telemetry/latest.php');
        const recent = await api_get('../api/events/recent.php?limit=5');

        const health = Number(latest && latest.health ? latest.health : 0);
        user_set_text('user_health_value', String(health) + '%');
        user_set_text('user_health_time', new Date().toLocaleTimeString());
        if (health >= 80) {
            user_set_badge('user_health_status', 'safe', 'Safe', 'Warn', 'Critical');
        } else if (health >= 50) {
            user_set_badge('user_health_status', 'warn', 'Safe', 'Warn', 'Critical');
        } else {
            user_set_badge('user_health_status', 'critical', 'Safe', 'Warn', 'Critical');
        }

        if (latest && latest.storm) {
            const intensity = Number(latest.storm.intensity || 0);
            let level = 'Low';
            let levelStatus = 'safe';
            if (intensity >= 8) {
                level = 'High';
                levelStatus = 'high';
            } else if (intensity >= 5) {
                level = 'Moderate';
                levelStatus = 'moderate';
            }

            user_set_text('user_storm_level', level);
            user_set_text('user_storm_time', latest.storm.created_at || 'N/A');
            user_set_badge('user_storm_status', levelStatus, 'Low', 'Moderate', 'High');
        }

        if (latest && latest.radiation) {
            const radLevel = Number(latest.radiation.radiation_level || 0);
            user_set_text('user_rad_level', radLevel.toFixed(1));
            user_set_text('user_rad_time', latest.radiation.created_at || 'N/A');

            const status = String(latest.radiation.status || 'safe');
            if (status === 'danger') {
                user_set_badge('user_rad_status', 'critical', 'Safe', 'Warn', 'Critical');
            } else if (status === 'warning') {
                user_set_badge('user_rad_status', 'warn', 'Safe', 'Warn', 'Critical');
            } else {
                user_set_badge('user_rad_status', 'safe', 'Safe', 'Warn', 'Critical');
            }
        }

        user_render_events(recent && recent.events ? recent.events : []);

        const noteEl = document.getElementById('refresh_note');
        if (noteEl) {
            noteEl.textContent = 'Last refresh: ' + new Date().toLocaleTimeString();
            noteEl.classList.remove('status_warn', 'status_critical');
            noteEl.classList.add('status_safe');
        }
    } catch (err) {
        console.log('User panel refresh failed:', err.message);
    }
}

function refresh_user_all() {
    refresh_user_panels();
    if (typeof load_user_charts === 'function') {
        load_user_charts();
    }
}

if (typeof window.mars_api_bridge_ready !== 'undefined' && typeof window.mars_api_bridge_ready.then === 'function') {
    window.mars_api_bridge_ready.then(function () {
        refresh_user_all();
    });
} else {
    refresh_user_all();
}

setInterval(function () {
    if (!document.hidden) {
        refresh_user_all();
    }
}, 5000);

window.addEventListener('mars_api_bridge_updated', function () {
    refresh_user_all();
});