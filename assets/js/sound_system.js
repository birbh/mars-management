(function () {
    const is_astronaut = window.location.pathname.indexOf('/dashboard/astronaut.php') !== -1;
    const this_script = document.currentScript;
    let base_path = '';
    if (this_script && this_script.src) {
        const script_path = new URL(this_script.src, window.location.href).pathname;
        const marker = '/assets/js/sound_system.js';
        const marker_idx = script_path.indexOf(marker);
        if (marker_idx >= 0) {
            base_path = script_path.slice(0, marker_idx);
        }
    }

    const ambient_tracks = [
        base_path + '/assets/sounds/rand1.mp3',
        base_path + '/assets/sounds/rand2.mp3',
    ];
    const ambient_choice_key = 'mh_ambient_choice';
    const ambient_start_key = 'mh_ambient_started_at';
    const ambient_position_key = 'mh_ambient_position';
    const ambient_position_saved_key = 'mh_ambient_position_saved_at';
    const ambient_last_track_key = 'mh_ambient_last_track';
    const muted_key = 'mh_sound_muted';
    const activated_key = 'mh_sound_activated';

    let ambient_audio = null;
    let siren_audio = null;
    let siren_active = false;
    let siren_has_triggered = false;
    let siren_ready = false;
    let ambient_ready = false;
    let unlock_bound = false;
    let user_enabled = localStorage.getItem(muted_key) !== '1';
    let sound_activated = true;

    // Keep activation sticky so future loads start automatically.
    if (localStorage.getItem(activated_key) !== '1') {
        localStorage.setItem(activated_key, '1');
    }

    function build_audio(src, loop, volume) {
        const audio = new Audio(src);
        audio.loop = loop;
        audio.volume = volume;
        audio.preload = 'auto';
        return audio;
    }

    function get_navigation_type() {
        try {
            const nav_entries = window.performance && window.performance.getEntriesByType
                ? window.performance.getEntriesByType('navigation')
                : null;
            if (nav_entries && nav_entries.length > 0 && nav_entries[0] && nav_entries[0].type) {
                return String(nav_entries[0].type);
            }
        } catch (err) {
            // Ignore and fall back to default behavior.
        }

        return 'navigate';
    }

    function persist_ambient_state() {
        if (!ambient_audio || !ambient_ready) {
            return;
        }

        sessionStorage.setItem(ambient_position_key, String(ambient_audio.currentTime || 0));
        sessionStorage.setItem(ambient_position_saved_key, String(Date.now()));
    }

    function remove_unlock_listeners() {
        if (!unlock_bound) {
            return;
        }

        ['click', 'pointerdown', 'touchstart', 'keydown', 'wheel', 'scroll'].forEach(function (event_name) {
            window.removeEventListener(event_name, handle_unlock_interaction, true);
        });
        unlock_bound = false;
    }

    function handle_unlock_interaction() {
        if (!user_enabled) {
            return;
        }

        sound_activated = true;
        localStorage.setItem(activated_key, '1');
        sync_audio_state();
        remove_unlock_listeners();
    }

    function add_unlock_listeners() {
        if (unlock_bound) {
            return;
        }

        ['click', 'pointerdown', 'touchstart', 'keydown', 'wheel', 'scroll'].forEach(function (event_name) {
            window.addEventListener(event_name, handle_unlock_interaction, { capture: true, passive: true });
        });
        unlock_bound = true;
    }

    function pick_ambient_track() {
        const existing_track = sessionStorage.getItem(ambient_choice_key);
        const nav_type = get_navigation_type();
        const should_rotate_track = nav_type === 'reload';
        if (!should_rotate_track && existing_track && ambient_tracks.indexOf(existing_track) >= 0) {
            return existing_track;
        }

        let next = ambient_tracks[Math.floor(Math.random() * ambient_tracks.length)];
        const last_track = localStorage.getItem(ambient_last_track_key);

        // With only 2 tracks, forcing "not last" makes playback deterministic.
        // Keep true randomness for 2 tracks; only avoid repeats when 3+ are available.
        if (ambient_tracks.length > 2 && last_track && next === last_track) {
            const candidates = ambient_tracks.filter(function (track) {
                return track !== last_track;
            });
            next = candidates[Math.floor(Math.random() * candidates.length)];
        }

        sessionStorage.setItem(ambient_choice_key, next);
        sessionStorage.setItem(ambient_start_key, String(Date.now()));
        sessionStorage.removeItem(ambient_position_key);
        sessionStorage.removeItem(ambient_position_saved_key);
        localStorage.setItem(ambient_last_track_key, next);
        return next;
    }

    function ensure_toggle() {
        const existing_btn = document.getElementById('sound_toggle');
        if (existing_btn) {
            return existing_btn;
        }

        return null;
    }

    function update_toggle_ui() {
        const btn = ensure_toggle();
        if (!btn) {
            return;
        }

        btn.textContent = user_enabled ? 'Sound: On' : 'Sound: Off';
        btn.setAttribute('aria-pressed', user_enabled ? 'true' : 'false');
    }

    function play_if_allowed(audio) {
        if (!audio || !user_enabled) {
            return;
        }

        const play_promise = audio.play();
        if (play_promise && typeof play_promise.catch === 'function') {
            play_promise.catch(function () {
                add_unlock_listeners();
            });
        }
    }

    function try_start_siren() {
        if (!siren_audio || !user_enabled || !sound_activated || !siren_active || !siren_ready) {
            return;
        }

        if (!siren_audio.paused) {
            return;
        }

        siren_audio.muted = true;
        const play_promise = siren_audio.play();

        if (play_promise && typeof play_promise.then === 'function') {
            play_promise
                .then(function () {
                    siren_audio.muted = false;
                    siren_audio.volume = 0.22;
                    remove_unlock_listeners();
                })
                .catch(function () {
                    siren_audio.muted = false;
                    add_unlock_listeners();
                });
            return;
        }

        siren_audio.muted = false;
    }

    function try_start_ambient() {
        if (!ambient_audio || !user_enabled || !sound_activated) {
            return;
        }

        if (!ambient_ready) {
            return;
        }

        if (!ambient_audio.paused) {
            return;
        }

        ambient_audio.muted = true;
        const play_promise = ambient_audio.play();

        if (play_promise && typeof play_promise.then === 'function') {
            play_promise
                .then(function () {
                    ambient_audio.muted = false;
                    ambient_audio.volume = 0.1;
                    remove_unlock_listeners();
                })
                .catch(function () {
                    ambient_audio.muted = false;
                    add_unlock_listeners();
                });
            return;
        }

        ambient_audio.muted = false;
    }

    function sync_audio_state() {
        if (!ambient_audio) {
            return;
        }

        if (!user_enabled) {
            ambient_audio.pause();
            if (siren_audio) {
                siren_audio.pause();
            }
            return;
        }

        if (!sound_activated) {
            return;
        }

        try_start_ambient();

        if (is_astronaut && siren_audio && siren_active) {
            try_start_siren();
        }
    }

    function init_toggle() {
        const btn = ensure_toggle();
        if (!btn) {
            return;
        }

        update_toggle_ui();
        btn.addEventListener('click', function () {
            user_enabled = !user_enabled;
            localStorage.setItem(muted_key, user_enabled ? '0' : '1');

            if (user_enabled && !sound_activated) {
                sound_activated = true;
                localStorage.setItem(activated_key, '1');
            }

            update_toggle_ui();
            sync_audio_state();
        });
    }

    function init_ambient() {
        const chosen_track = pick_ambient_track();

        if (!sessionStorage.getItem(ambient_start_key)) {
            sessionStorage.setItem(ambient_start_key, String(Date.now()));
        }

        ambient_audio = build_audio(chosen_track, true, 0.1);
        ambient_audio.addEventListener('loadedmetadata', function () {
            if (ambient_audio.duration && isFinite(ambient_audio.duration) && ambient_audio.duration > 0) {
                const saved_position = Number(sessionStorage.getItem(ambient_position_key) || 0);
                const saved_at = Number(sessionStorage.getItem(ambient_position_saved_key) || Date.now());
                if (saved_position > 0) {
                    const elapsed_since_save = (Date.now() - saved_at) / 1000;
                    ambient_audio.currentTime = (saved_position + elapsed_since_save) % ambient_audio.duration;
                } else {
                    const started_at = Number(sessionStorage.getItem(ambient_start_key) || Date.now());
                    const elapsed = (Date.now() - started_at) / 1000;
                    ambient_audio.currentTime = elapsed % ambient_audio.duration;
                }
            }

            ambient_ready = true;
            sync_audio_state();
        });

        ambient_audio.load();

        window.addEventListener('load', sync_audio_state);
        window.addEventListener('pageshow', sync_audio_state);
        window.addEventListener('pagehide', persist_ambient_state);
        window.addEventListener('beforeunload', persist_ambient_state);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                persist_ambient_state();
            } else {
                sync_audio_state();
            }
        });
    }

    function init_siren() {
        if (!is_astronaut) {
            return;
        }

        siren_audio = build_audio(base_path + '/assets/sounds/siren.mp3', true, 0.22);
        siren_audio.addEventListener('canplaythrough', function () {
            siren_ready = true;
            sync_audio_state();
        });
        siren_audio.addEventListener('loadedmetadata', function () {
            siren_ready = true;
            sync_audio_state();
        });
        siren_audio.load();
    }

    function set_siren_active(next_state) {
        if (!is_astronaut || !siren_audio) {
            return;
        }

        if (next_state) {
            if (!siren_active && !siren_has_triggered) {
                siren_active = true;
                siren_has_triggered = true;
                try_start_siren();
                return;
            }

            if (!siren_active && siren_has_triggered) {
                siren_active = true;
                try_start_siren();
            }
            return;
        }

        siren_active = false;
        siren_has_triggered = false;
        siren_ready = false;
        siren_audio.pause();
        siren_audio.currentTime = 0;
    }

    window.MarsSound = {
        setSirenActive: set_siren_active,
    };

    init_toggle();
    init_ambient();
    init_siren();

    document.addEventListener('DOMContentLoaded', sync_audio_state);
})();



