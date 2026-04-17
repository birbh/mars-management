function astro_escape_html(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function astro_set_text(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function astro_set_badge(id, status, safeLabel, warnLabel, criticalLabel) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }

    el.classList.remove('status_safe', 'status_warn', 'status_critical');

    if (status === 'critical' || status === 'danger') {
        el.classList.add('status_critical');
        el.textContent = criticalLabel || 'Critical';
        return;
    }

    if (status === 'warn' || status === 'warning') {
        el.classList.add('status_warn');
        el.textContent = warnLabel || 'Warn';
        return;
    }

    el.classList.add('status_safe');
    el.textContent = safeLabel || 'Safe';
}

function astro_event_severity_class(eventType, notes) {
    const combined = String((eventType || '') + ' ' + (notes || '')).toLowerCase();
    if (combined.indexOf('critical') !== -1 || combined.indexOf('emergency') !== -1 || combined.indexOf('danger') !== -1) {
        return 'status_critical';
    }
    if (combined.indexOf('warn') !== -1 || combined.indexOf('elevated') !== -1 || combined.indexOf('monitor') !== -1) {
        return 'status_warn';
    }
    return 'status_safe';
}

function astro_event_severity_label(cssClass) {
    if (cssClass === 'status_critical') {
        return 'Critical';
    }
    if (cssClass === 'status_warn') {
        return 'Warn';
    }
    return 'Safe';
}

function astro_set_siren_from_latest(latest) {
    if (!window.MarsSound || typeof window.MarsSound.setSirenActive !== 'function') {
        return;
    }

    const stormIntensity = Number(latest && latest.storm ? latest.storm.intensity || 0 : 0);
    const radiationStatus = String(latest && latest.radiation ? latest.radiation.status || '' : '').toLowerCase();
    const powerMode = String(latest && latest.power ? latest.power.mode || '' : '').toLowerCase();
    const health = Number(latest && latest.health ? latest.health : 0);

    const criticalActive = stormIntensity >= 8
        || radiationStatus === 'danger'
        || radiationStatus === 'critical'
        || powerMode === 'critical'
        || health < 50;

    window.MarsSound.setSirenActive(criticalActive);
}

function astro_sync_siren_when_ready() {
    if (window.MarsSound && typeof window.MarsSound.setSirenActive === 'function') {
        refresh_astro_panels();
    }
}

function astro_render_events(events) {
    const rowsEl = document.getElementById('astro_event_rows');
    const emptyEl = document.getElementById('astro_event_empty');
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
        const eventType = event && event.event_type ? event.event_type : '';
        const notes = event && event.notes ? event.notes : '';
        const time = event && event.created_at ? event.created_at : 'N/A';
        const severityClass = astro_event_severity_class(eventType, notes);
        const severityLabel = astro_event_severity_label(severityClass);

        return '<tr>'
            + '<td>' + astro_escape_html(time) + '</td>'
            + '<td>' + astro_escape_html(eventType) + '</td>'
            + '<td><span class="status_badge ' + severityClass + '">' + severityLabel + '</span></td>'
            + '</tr>';
    }).join('');
}

const ASTRO_REFRESH_MIN_GAP_MS = 1200;
let astro_refresh_inflight = null;
let astro_last_refresh_start = 0;

async function refresh_astro_panels() {
    try {
        let latest = null;
        let events = [];

        if (typeof window.mars_api_bridge_get_latest === 'function') {
            latest = window.mars_api_bridge_get_latest();
        }
        if (typeof window.mars_api_bridge_get_events === 'function') {
            events = window.mars_api_bridge_get_events(10);
        }

        if (!latest) {
            latest = await api_get('../api/telemetry/latest.php');
            const recent = await api_get('../api/events/recent.php?limit=10');
            events = recent && recent.events ? recent.events : [];
        }

        if (latest && latest.storm) {
            const intensity = Number(latest.storm.intensity || 0);
            astro_set_text('astro_storm_intensity', String(intensity));
            astro_set_text('astro_storm_time', latest.storm.created_at || 'N/A');

            if (intensity >= 8) {
                astro_set_badge('astro_storm_status', 'critical', 'Safe', 'Warn', 'Critical');
            } else if (intensity >= 5) {
                astro_set_badge('astro_storm_status', 'warn', 'Safe', 'Warn', 'Critical');
            } else {
                astro_set_badge('astro_storm_status', 'safe', 'Safe', 'Warn', 'Critical');
            }
        }

        if (latest && latest.power) {
            astro_set_text('astro_power_solar', String(latest.power.solar_output ?? 'N/A'));
            astro_set_text('astro_power_battery', String(latest.power.battery_level ?? 'N/A') + '%');
            astro_set_text('astro_power_time', latest.power.created_at || 'N/A');
            astro_set_badge(
                'astro_power_status',
                latest.power.mode === 'critical' ? 'critical' : 'safe',
                'Safe',
                'Warn',
                'Critical'
            );
        }

        if (latest && latest.radiation) {
            const radLevel = Number(latest.radiation.radiation_level || 0);
            astro_set_text('astro_rad_level', radLevel.toFixed(2));
            astro_set_text('astro_rad_time', latest.radiation.created_at || 'N/A');
            astro_set_badge('astro_rad_status', String(latest.radiation.status || 'safe'), 'Safe', 'Warn', 'Critical');
        }

        const health = Number(latest && latest.health ? latest.health : 0);
        astro_set_text('astro_health_value', String(health) + '%');
        astro_set_text('astro_health_time', new Date().toLocaleTimeString());

        if (health >= 80) {
            astro_set_badge('astro_health_status', 'safe', 'Safe', 'Warn', 'Critical');
        } else if (health >= 50) {
            astro_set_badge('astro_health_status', 'warn', 'Safe', 'Warn', 'Critical');
        } else {
            astro_set_badge('astro_health_status', 'critical', 'Safe', 'Warn', 'Critical');
        }

        astro_set_siren_from_latest(latest);

        astro_render_events(Array.isArray(events) ? events : []);

        const noteEl = document.getElementById('refresh_note_astro');
        if (noteEl) {
            noteEl.textContent = 'Last refresh: ' + new Date().toLocaleTimeString();
            noteEl.classList.remove('status_warn', 'status_critical');
            noteEl.classList.add('status_safe');
        }
    } catch (err) {
        console.log('Astronaut panel refresh failed:', err.message);
    }
}

function refresh_astro_all() {
    const now = Date.now();

    if (astro_refresh_inflight) {
        return astro_refresh_inflight;
    }
    if (now - astro_last_refresh_start < ASTRO_REFRESH_MIN_GAP_MS) {
        return Promise.resolve();
    }

    astro_last_refresh_start = now;
    astro_refresh_inflight = Promise.resolve()
        .then(function () {
            return refresh_astro_panels();
        })
        .then(function () {
            if (typeof load_astro_charts === 'function') {
                load_astro_charts();
            }
        })
        .finally(function () {
            astro_refresh_inflight = null;
        });

    return astro_refresh_inflight;
}

if (typeof window.mars_api_bridge_ready !== 'undefined' && typeof window.mars_api_bridge_ready.then === 'function') {
    window.mars_api_bridge_ready.then(function () {
        refresh_astro_all();
    });
} else {
    refresh_astro_all();

    // Keep fallback polling only when the bridge script is not loaded.
    setInterval(function () {
        if (!document.hidden) {
            refresh_astro_all();
        }
    }, 5000);
}

window.addEventListener('mars_api_bridge_updated', function () {
    refresh_astro_all();
});

window.addEventListener('load', astro_sync_siren_when_ready);
