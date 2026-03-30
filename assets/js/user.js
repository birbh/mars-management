function refresh_user_charts() {
    if (typeof load_user_charts === 'function') {
        load_user_charts();
    }

    const note_el = document.getElementById('refresh_note');
    if (note_el) {
        note_el.textContent = 'Last refresh: ' + new Date().toLocaleTimeString();
        note_el.classList.remove('status_warn', 'status_critical');
        note_el.classList.add('status_safe');
    }
}

refresh_user_charts();
setInterval(refresh_user_charts, 15000);