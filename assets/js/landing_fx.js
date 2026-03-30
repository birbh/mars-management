function run_counter(el_id, end_value, duration_ms) {
    const el = document.getElementById(el_id);
    if (!el) {
        return;
    }

    const start = performance.now();

    function draw(now) {
        const ratio = Math.min((now - start) / duration_ms, 1);
        const value = Math.floor(end_value * ratio);
        el.textContent = String(value);

        if (ratio < 1) {
            requestAnimationFrame(draw);
        }
    }

    requestAnimationFrame(draw);
}

document.addEventListener('DOMContentLoaded', () => {
    run_counter('storm_cnt', 12, 900);
    run_counter('rad_cnt', 96, 1100);
    run_counter('pwr_cnt', 87, 1300);
});
 