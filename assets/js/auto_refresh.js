function start_auto_ref(config) {
    const target_id = config.target_id;
    const refresh_url = config.refresh_url;
    const note_id = config.note_id;
    const interval_ms = config.interval_ms || 10000;
    const before_refresh = config.before_refresh || null;
    const on_refresh = config.on_refresh || null;

    function set_ref_note(msg, css_class) {
        const note_el = document.getElementById(note_id);
        if (!note_el) {
            return;
        }

        note_el.textContent = msg;
        note_el.classList.remove('status_safe', 'status_warn', 'status_critical');
        note_el.classList.add(css_class);
    }

    function run_refresh() {
        if (document.hidden) {
            return;
        }

        if (typeof before_refresh === 'function' && before_refresh() === false) {
            return;
        }

        fetch(refresh_url)
            .then((res) => res.text())
            .then((html) => {
                const target_el = document.getElementById(target_id);
                if (target_el) {
                    target_el.innerHTML = html;
                }

                if (typeof on_refresh === 'function') {
                    on_refresh();
                }

                const now = new Date();
                set_ref_note('Last refresh: ' + now.toLocaleTimeString(), 'status_safe');
            })
            .catch((err) => {
                console.log('Refresh failed:', err);
                set_ref_note('Refresh delayed: retrying', 'status_warn');
            });
    }

    setInterval(run_refresh, interval_ms);
}
