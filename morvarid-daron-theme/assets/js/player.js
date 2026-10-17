/**
 * Morvarid Daron media players.
 *
 * Replaces the native audio/video controls with the theme's own interface:
 * pearl play buttons, an animated progress ring for audio, and a full custom
 * control bar for video.
 */
(function () {
    'use strict';

    var RING_RADIUS = 45;
    var RING_LENGTH = 2 * Math.PI * RING_RADIUS;
    var RATES = [1, 1.25, 1.5, 2];
    var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

    function fa(value) {
        return String(value).replace(/\d/g, function (digit) {
            return FA_DIGITS.charAt(parseInt(digit, 10));
        });
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            seconds = 0;
        }
        seconds = Math.floor(seconds);
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var rest = seconds % 60;

        if (hours > 0) {
            return fa(hours) + ':' + fa(pad(minutes)) + ':' + fa(pad(rest));
        }
        return fa(minutes) + ':' + fa(pad(rest));
    }

    function secondsLabel(audio, duration) {
        return formatTime(audio.currentTime) + ' / ' + formatTime(duration);
    }

    function setIconState(root, playing) {
        root.classList.toggle('is-playing', !!playing);
        var toggles = root.querySelectorAll('[data-md-action="toggle"]');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].setAttribute('aria-label', playing ? 'توقف' : 'پخش');
        }
    }

    function setMuteState(root, media) {
        root.classList.toggle('is-muted', media.muted || media.volume === 0);
        var mute = root.querySelector('[data-md-action="mute"]');
        if (mute) {
            mute.setAttribute('aria-label', media.muted ? 'باصدا' : 'بی‌صدا');
        }
    }

    function setProgress(root, media, duration) {
        var ratio = duration > 0 ? Math.min(1, media.currentTime / duration) : 0;
        var fill = root.querySelector('.md-seek-fill');
        var knob = root.querySelector('.md-seek-knob');
        var seek = root.querySelector('.md-seek');
        var ring = root.querySelector('.md-audio-ring-fill');

        if (fill) {
            fill.style.width = (ratio * 100) + '%';
        }
        if (knob) {
            knob.style.left = (ratio * 100) + '%';
        }
        if (seek) {
            seek.setAttribute('aria-valuenow', Math.round(ratio * 100));
        }
        if (ring) {
            ring.style.strokeDasharray = RING_LENGTH;
            ring.style.strokeDashoffset = RING_LENGTH * (1 - ratio);
        }
    }

    function bindSeek(root, media) {
        var seek = root.querySelector('.md-seek');
        if (!seek) {
            return;
        }

        function seekTo(clientX) {
            var rect = seek.getBoundingClientRect();
            if (!rect.width) {
                return;
            }
            var ratio = (clientX - rect.left) / rect.width;
            ratio = Math.max(0, Math.min(1, ratio));
            var duration = media.duration;
            if (isFinite(duration) && duration > 0) {
                media.currentTime = ratio * duration;
                setProgress(root, media, duration);
            }
        }

        seek.addEventListener('pointerdown', function (event) {
            event.preventDefault();
            seekTo(event.clientX);
            seek.setPointerCapture && seek.setPointerCapture(event.pointerId);

            function onMove(moveEvent) {
                seekTo(moveEvent.clientX);
            }
            function onUp() {
                document.removeEventListener('pointermove', onMove);
                document.removeEventListener('pointerup', onUp);
                document.removeEventListener('pointercancel', onUp);
            }
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
            document.addEventListener('pointercancel', onUp);
        });

        seek.addEventListener('keydown', function (event) {
            var step = 5;
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                var direction = event.key === 'ArrowRight' ? 1 : -1;
                media.currentTime = Math.max(0, Math.min(media.duration || 0, media.currentTime + direction * step));
                setProgress(root, media, media.duration || 0);
            }
        });
    }

    function initAudio(root) {
        var media = root.querySelector('audio');
        if (!media) {
            return;
        }

        var time = root.querySelector('[data-md-time]');
        var rate = root.querySelector('[data-md-action="rate"]');
        var ring = root.querySelector('.md-audio-ring-fill');

        if (ring) {
            ring.style.strokeDasharray = RING_LENGTH;
            ring.style.strokeDashoffset = RING_LENGTH;
        }

        function refresh() {
            var duration = isFinite(media.duration) ? media.duration : 0;
            if (time) {
                time.textContent = secondsLabel(media, duration);
            }
            setProgress(root, media, duration);
        }

        function toggle() {
            if (media.paused) {
                media.play();
            } else {
                media.pause();
            }
        }

        root.querySelectorAll('[data-md-action="toggle"]').forEach(function (button) {
            button.addEventListener('click', toggle);
        });

        var mute = root.querySelector('[data-md-action="mute"]');
        if (mute) {
            mute.addEventListener('click', function () {
                media.muted = !media.muted;
                setMuteState(root, media);
            });
        }

        if (rate) {
            rate.addEventListener('click', function () {
                var current = media.playbackRate || 1;
                var index = RATES.indexOf(current);
                var next = RATES[(index + 1) % RATES.length];
                media.playbackRate = next;
                rate.textContent = fa(String(next).replace('.', '٫')) + '×';
            });
        }

        media.addEventListener('play', function () { setIconState(root, true); });
        media.addEventListener('pause', function () { setIconState(root, false); });
        media.addEventListener('ended', function () { setIconState(root, false); });
        media.addEventListener('timeupdate', refresh);
        media.addEventListener('loadedmetadata', refresh);
        media.addEventListener('durationchange', refresh);
        media.addEventListener('volumechange', function () { setMuteState(root, media); });
        media.addEventListener('seeked', refresh);

        bindSeek(root, media);
        refresh();
    }

    function initVideo(root) {
        var media = root.querySelector('video');
        if (!media) {
            return;
        }

        var time = root.querySelector('[data-md-time]');
        var stage = root.querySelector('.md-video-stage');
        var idleTimer = null;

        function refresh() {
            var duration = isFinite(media.duration) ? media.duration : 0;
            if (time) {
                time.textContent = secondsLabel(media, duration);
            }
            setProgress(root, media, duration);
        }

        function toggle() {
            if (media.paused) {
                media.play();
            } else {
                media.pause();
            }
        }

        function wake() {
            root.classList.remove('is-idle');
            window.clearTimeout(idleTimer);
            idleTimer = window.setTimeout(function () {
                if (!media.paused) {
                    root.classList.add('is-idle');
                }
            }, 2600);
        }

        root.querySelectorAll('[data-md-action="toggle"]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                toggle();
                wake();
            });
        });

        if (stage) {
            stage.addEventListener('click', function (event) {
                if (event.target.closest('[data-md-controls]') || event.target.closest('[data-md-action]')) {
                    return;
                }
                toggle();
                wake();
            });
            stage.addEventListener('pointermove', wake);
            stage.addEventListener('pointerleave', function () {
                if (!media.paused) {
                    root.classList.add('is-idle');
                }
            });
        }

        var mute = root.querySelector('[data-md-action="mute"]');
        if (mute) {
            mute.addEventListener('click', function () {
                media.muted = !media.muted;
                setMuteState(root, media);
            });
        }

        var fullscreen = root.querySelector('[data-md-action="fullscreen"]');
        if (fullscreen) {
            fullscreen.addEventListener('click', function () {
                var element = root;
                if (document.fullscreenElement) {
                    document.exitFullscreen && document.exitFullscreen();
                    return;
                }
                if (element.requestFullscreen) {
                    element.requestFullscreen();
                } else if (element.webkitRequestFullscreen) {
                    element.webkitRequestFullscreen();
                }
            });
        }

        media.addEventListener('play', function () {
            setIconState(root, true);
            wake();
        });
        media.addEventListener('pause', function () {
            setIconState(root, false);
            root.classList.remove('is-idle');
        });
        media.addEventListener('ended', function () { setIconState(root, false); });
        media.addEventListener('timeupdate', refresh);
        media.addEventListener('loadedmetadata', refresh);
        media.addEventListener('durationchange', refresh);
        media.addEventListener('volumechange', function () { setMuteState(root, media); });
        media.addEventListener('seeked', refresh);

        bindSeek(root, media);
        refresh();
    }

    function init(scope) {
        var root = scope || document;
        var players = root.querySelectorAll('[data-md-player]');

        for (var i = 0; i < players.length; i++) {
            var player = players[i];
            if (player.dataset.mdReady === '1') {
                continue;
            }
            player.dataset.mdReady = '1';

            if (player.getAttribute('data-md-player') === 'audio') {
                initAudio(player);
            } else {
                initVideo(player);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }

    window.MDPlayer = { init: init, formatTime: formatTime };
})();
