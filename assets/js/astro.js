function refresh_astro_charts() {
    if (typeof load_astro_charts === 'function') {
        load_astro_charts();
    }

    const note_el = document.getElementById('refresh_note_astro');
    if (note_el) {
        note_el.textContent = 'Last refresh: ' + new Date().toLocaleTimeString();
        note_el.classList.remove('status_warn', 'status_critical');
        note_el.classList.add('status_safe');
    }
}

refresh_astro_charts();
setInterval(refresh_astro_charts, 5000);
