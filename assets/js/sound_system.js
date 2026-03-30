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
    const ambient_last_track_key = 'mh_ambient_last_track';
    const muted_key = 'mh_sound_muted';
    const activated_key = 'mh_sound_activated';

    let ambient_audio = null;
    let siren_audio = null;
    let siren_active = false;
    let siren_has_triggered = false;
    let ambient_ready = false;
    let user_enabled = localStorage.getItem(muted_key) !== '1';
    let sound_activated = localStorage.getItem(activated_key) === '1';
    let user_gesture_bound = false;

    function build_audio(src, loop, volume) {
        const audio = new Audio(src);
        audio.loop = loop;
        audio.volume = volume;
        audio.preload = 'auto';
        return audio;
    }

    function pick_ambient_track() {
        const existing = sessionStorage.getItem(ambient_choice_key);
        if (existing && ambient_tracks.indexOf(existing) >= 0) {
            return existing;
        }

        let next = ambient_tracks[Math.floor(Math.random() * ambient_tracks.length)];
        const last_track = localStorage.getItem(ambient_last_track_key);

        if (ambient_tracks.length > 1 && last_track && next === last_track) {
            next = ambient_tracks.find(function (track) {
                return track !== last_track;
            });
        }

        sessionStorage.setItem(ambient_choice_key, next);
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
                // Browsers may block autoplay; retry on user gesture.
            });
        }
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
                })
                .catch(function () {
                    ambient_audio.muted = false;
                    prime_on_user_action();
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
            play_if_allowed(siren_audio);
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

    function prime_on_user_action() {
        if (user_gesture_bound) {
            return;
        }
        user_gesture_bound = true;

        function run_once() {
            sound_activated = true;
            localStorage.setItem(activated_key, '1');
            sync_audio_state();
            window.removeEventListener('click', run_once);
            window.removeEventListener('keydown', run_once);
            window.removeEventListener('mousedown', run_once);
            window.removeEventListener('touchstart', run_once);
            user_gesture_bound = false;
        }

        window.addEventListener('click', run_once);
        window.addEventListener('keydown', run_once);
        window.addEventListener('mousedown', run_once);
        window.addEventListener('touchstart', run_once, { passive: true });
    }

    function init_ambient() {
        const chosen_track = pick_ambient_track();

        if (!sessionStorage.getItem(ambient_start_key)) {
            sessionStorage.setItem(ambient_start_key, String(Date.now()));
        }

        ambient_audio = build_audio(chosen_track, true, 0.1);
        ambient_audio.addEventListener('loadedmetadata', function () {
            const started_at = Number(sessionStorage.getItem(ambient_start_key) || Date.now());
            const elapsed = (Date.now() - started_at) / 1000;
            if (ambient_audio.duration && isFinite(ambient_audio.duration) && ambient_audio.duration > 0) {
                ambient_audio.currentTime = elapsed % ambient_audio.duration;
            }

            ambient_ready = true;
            sync_audio_state();
        });

        ambient_audio.load();

        window.addEventListener('load', sync_audio_state);
        window.addEventListener('pageshow', sync_audio_state);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                sync_audio_state();
            }
        });
    }

    function init_siren() {
        if (!is_astronaut) {
            return;
        }

        siren_audio = build_audio(base_path + '/assets/sounds/siren.mp3', true, 0.22);
    }

    function set_siren_active(next_state) {
        if (!is_astronaut || !siren_audio) {
            return;
        }

        if (next_state) {
            if (!siren_active && !siren_has_triggered) {
                siren_active = true;
                siren_has_triggered = true;
                if (user_enabled) {
                    play_if_allowed(siren_audio);
                }
                return;
            }

            if (!siren_active && siren_has_triggered) {
                siren_active = true;
                if (user_enabled) {
                    play_if_allowed(siren_audio);
                }
            }
            return;
        }

        siren_active = false;
        siren_has_triggered = false;
        siren_audio.pause();
        siren_audio.currentTime = 0;
    }

    window.MarsSound = {
        setSirenActive: set_siren_active,
    };

    init_toggle();
    init_ambient();
    init_siren();
    prime_on_user_action();
})();
