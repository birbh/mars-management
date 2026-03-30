const admin_params = new URLSearchParams(window.location.search);
admin_params.delete('edit_id');
admin_params.set('refresh', '1');

const admin_refresh_url = 'admin.php?' + admin_params.toString();

start_auto_ref({
    target_id: 'dashboard_content',
    refresh_url: admin_refresh_url,
    note_id: 'refresh_note_admin',
    interval_ms: 20000,
    before_refresh: function () {
        if (document.querySelector('input[name="action"][value="update"]')) {
            return false;
        }

        const active_el = document.activeElement;
        if (!active_el) {
            return true;
        }

        const tag = active_el.tagName;
        return !(tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT');
    },
    on_refresh: function () {
        if (typeof load_admin_charts === 'function') {
            load_admin_charts();
        }
    }
});

if (typeof load_admin_charts === 'function') {
    load_admin_charts();
}
