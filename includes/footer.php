            </div> <!-- end .main-container -->
        </main> <!-- end .app-main -->
    </div> <!-- end .app-layout -->

    <!-- Streak Nag Banner (hidden by default, shown by JS) -->
    <div id="nagBanner" class="nag-banner" style="display: none;" role="alert">
        <i class="fas fa-fire nag-icon"></i>
        <span id="nagText" class="nag-text"></span>
        <a href="habits.php" class="nag-cta" aria-label="Buka daftar kebiasaan">
            <i class="fas fa-arrow-right"></i>
        </a>
        <button id="nagClose" class="nag-close" aria-label="Tutup peringatan">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <script>
        /* ============ Lifevuww Sound Engine (Web Audio, zero assets) ============ */
        /* Defined early: toast/death/shop blocks below depend on it. */
        const LVSound = (() => {
            let ctx = null;
            let pending = [];
            const isOn = () => localStorage.getItem('lv_sound') !== 'off';

            function ac() {
                if (!ctx) {
                    const AC = window.AudioContext || window.webkitAudioContext;
                    if (!AC) return null;
                    ctx = new AC();
                }
                if (ctx.state === 'suspended') ctx.resume().catch(() => {});
                return ctx;
            }

            function tone(freq, delay, dur, type, vol) {
                if (!isOn()) return;
                const c = ac();
                if (!c) return;
                // Browser blocks audio before first gesture: queue and replay on interaction
                if (c.state === 'suspended') {
                    pending.push(() => tone(freq, 0, dur, type, vol));
                    return;
                }
                const t = c.currentTime + (delay || 0);
                const o = c.createOscillator();
                const g = c.createGain();
                o.type = type || 'sine';
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, t);
                g.gain.exponentialRampToValueAtTime(vol || 0.1, t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
                o.connect(g);
                g.connect(c.destination);
                o.start(t);
                o.stop(t + dur + 0.05);
            }

            function flush() {
                if (ctx && ctx.state === 'running' && pending.length) {
                    const q = pending;
                    pending = [];
                    q.forEach((fn) => fn());
                }
            }
            window.addEventListener('pointerdown', flush);
            window.addEventListener('keydown', flush);

            return {
                tick() { tone(2600, 0, 0.012, 'sine', 0.025); },
                fanfare() { [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => tone(f, i * 0.09, 0.22, 'triangle', 0.11)); },
                levelUp() { [523.25, 659.25, 783.99, 1046.5, 1318.5, 1567.98].forEach((f, i) => tone(f, i * 0.09, 0.3, 'triangle', 0.13)); },
                quest() { [392, 523.25, 659.25, 783.99, 1046.5].forEach((f, i) => tone(f, i * 0.08, 0.2, 'triangle', 0.1)); },
                coin() { tone(988, 0, 0.08, 'square', 0.05); tone(1319, 0.08, 0.18, 'square', 0.05); },
                sad() { tone(196, 0, 0.3, 'sawtooth', 0.06); tone(146.8, 0.25, 0.5, 'sawtooth', 0.06); },
                toggle() {
                    const off = isOn();
                    localStorage.setItem('lv_sound', off ? 'off' : 'on');
                    return !off;
                },
                isOn
            };
        })();

        function fireConfetti(opts) {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const run = () => { if (typeof confetti === 'function') confetti(opts); };
            if (typeof confetti === 'function') {
                run();
            } else {
                window.addEventListener('DOMContentLoaded', run, { once: true });
            }
        }
    </script>

    <!-- Dopamine Toast Notification -->
    <?php if (isset($_SESSION['reward_toast'])):
        $toast = $_SESSION['reward_toast'];
        unset($_SESSION['reward_toast']);
    ?>
        <div class="dopamine-toast" id="dopamineToast" role="status">
            <div class="toast-icon">
                <i class="fas <?php echo ($toast['type'] === 'quest') ? 'fa-scroll' : 'fa-check'; ?>"></i>
            </div>
            <div class="toast-body">
                <div class="toast-title"><?php echo htmlspecialchars($toast['name']); ?> selesai!</div>
                <div class="toast-rewards">
                    <span class="toast-reward exp">+<?php echo $toast['exp']; ?> EXP</span>
                    <span class="toast-reward gold"><i class="fas fa-coins"></i> +<?php echo $toast['gold']; ?></span>
                    <?php if (isset($toast['streak']) && $toast['streak'] > 1): ?>
                        <span class="toast-reward streak"><i class="fas fa-fire"></i> <?php echo $toast['streak']; ?>x streak</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            // Confetti blast + celebration chime
            fireConfetti({ particleCount: 70, spread: 55, origin: { y: 0.85 } });
            LVSound.<?php echo ($toast['type'] === 'quest') ? 'quest' : 'fanfare'; ?>();

            // Hilangkan toast setelah 4.5 detik
            setTimeout(() => {
                const toast = document.getElementById('dopamineToast');
                if (toast) {
                    toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(20px)';
                    setTimeout(() => toast.remove(), 400);
                }
            }, 4500);
        </script>
    <?php endif; ?>

    <!-- Level Up / Rank Up Modal -->
    <?php if (isset($_SESSION['level_up'])):
        $lvlUp = $_SESSION['level_up'];
        unset($_SESSION['level_up']);
    ?>
        <div class="modal fade show" id="levelUpModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.85);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-center p-4" style="border: 1px solid var(--accent); background-color: var(--bg-surface); position: relative; overflow: hidden;">
                    <div style="width: 72px; height: 72px; border-radius: 50%; background-color: var(--accent-dim); border: 2px solid var(--accent); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin: 0 auto 1.25rem auto;">
                        <i class="fas <?php echo $lvlUp['is_rank_up'] ? 'fa-crown' : 'fa-arrow-circle-up'; ?>"></i>
                    </div>
                    <span class="badge-status accent font-mono mb-2" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">
                        <?php echo $lvlUp['is_rank_up'] ? 'RANK UP ACHIEVED!' : 'LEVEL UP!'; ?>
                    </span>
                    <h2 class="fw-bold mb-1" style="font-size: 1.75rem; letter-spacing: -0.02em;">
                        Tingkat <?php echo $lvlUp['new_level']; ?> Tercapai!
                    </h2>
                    <p class="text-secondary" style="font-size: 0.85rem; margin-bottom: 1.25rem;">
                        Disiplin dan konsistensimu membuahkan hasil. Karaktermu bertambah kuat!
                    </p>
                    
                    <?php if ($lvlUp['is_rank_up']): ?>
                        <div class="p-3 mb-4 text-center" style="background-color: var(--bg-surface-hover); border-radius: var(--radius); border: 1px solid var(--border-subtle);">
                            <div class="text-muted" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px;">Gelar Baru Terbuka</div>
                            <div class="font-mono text-accent" style="font-size: 1.15rem; font-weight: 600;">
                                <i class="fas fa-medal me-1"></i> <?php echo htmlspecialchars($lvlUp['new_rank']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="button" class="btn-core btn-primary w-100 py-2" onclick="document.getElementById('levelUpModal').remove();">
                        Lanjutkan Perjuangan
                    </button>
                </div>
            </div>
        </div>
        <script>
            fireConfetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
            setTimeout(() => fireConfetti({ particleCount: 60, angle: 60, spread: 55, origin: { x: 0 } }), 250);
            setTimeout(() => fireConfetti({ particleCount: 60, angle: 120, spread: 55, origin: { x: 1 } }), 400);
            LVSound.levelUp();
        </script>
    <?php endif; ?>

    <!-- Game Over Modal / Alert -->
    <?php if (isset($_SESSION['game_over'])):
        $death = $_SESSION['game_over'];
        unset($_SESSION['game_over']);
    ?>
        <div class="modal fade show" id="deathModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.85);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-center p-4" style="border: 1px solid var(--accent-danger); background-color: var(--bg-surface);">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background-color: rgba(239, 68, 68, 0.15); color: var(--accent-danger); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem auto;">
                        <i class="fas fa-skull"></i>
                    </div>
                    <h3 class="text-danger fw-bold mb-2">KAMU GUGUR DALAM PERTEMPURAN!</h3>
                    <p class="text-secondary" style="font-size: 0.9rem;">
                        HP-mu habis karena kebiasaan harian terlewat atau misi yang gagal.
                    </p>
                    <div class="p-3 my-3" style="background-color: var(--bg-base); border-radius: 8px; border: 1px solid var(--border-dim); font-family: var(--font-mono); font-size: 0.85rem;">
                        <div class="text-danger mb-1">-<?php echo $death['lost_exp']; ?> EXP Hilang (Penalti 50%)</div>
                        <div class="text-muted">Semua streak aktif di-reset ke 0</div>
                    </div>
                    <p class="text-light small mb-4">
                        Sistem telah menghidupkanmu kembali dengan HP penuh. Jangan biarkan disiplinmu kendor lagi!
                    </p>
                    <button type="button" class="btn-core btn-primary w-100 py-2" onclick="document.getElementById('deathModal').remove();">
                        Bangkit Lagi
                    </button>
                </div>
            </div>
        </div>
        <script>LVSound.sad();</script>
    <?php endif; ?>

    <!-- Shop purchase coin sound -->
    <?php if (isset($_GET['success']) && in_array($_GET['success'], ['bought', 'healed', 'frozen'], true)): ?>
        <script>window.addEventListener('DOMContentLoaded', () => LVSound.coin());</script>
    <?php endif; ?>

    <!-- Reminder scheduler data (from header nag engine) -->
    <?php $nagIncompleteJs = $nagIncomplete ?? 0; ?>
    <?php if (isset($nagIncomplete)): ?>
    <script>
        window.LV_REMINDER = {
            time: <?php echo json_encode($reminderShort ?? '20:00'); ?>,
            incomplete: <?php echo (int) $nagIncompleteJs; ?>,
            streak: <?php echo (int) ($nagMaxStreak ?? 0); ?>
        };
        // Always-on snapshot for the native shell: re-syncs (or cancels)
        // the daily scheduled nag on every page load.
        window.LV_REMINDER_ALWAYS = {
            time: <?php echo json_encode($reminderShort ?? '20:00'); ?>,
            incomplete: <?php echo (int) $nagIncompleteJs; ?>,
            streak: <?php echo (int) ($nagMaxStreak ?? 0); ?>
        };
    </script>
    <?php endif; ?>

    <!-- Streak nag flag (past reminder time) -->
    <?php if (isset($_SESSION['streak_nag'])):
        $nag = $_SESSION['streak_nag'];
        unset($_SESSION['streak_nag']);
    ?>
    <script>
        window.LV_NAG_NOW = {
            incomplete: <?php echo (int) $nag['incomplete']; ?>,
            streak: <?php echo (int) $nag['max_streak']; ?>
        };
    </script>
    <?php endif; ?>

    <!-- Linear Command Palette Modal (Ctrl+K / Cmd+K) -->
    <div class="modal fade" id="lvCommandPalette" tabindex="-1" aria-hidden="true" style="background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
            <div class="modal-content" style="background-color: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: calc(var(--radius) * 1.5); overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.85);">
                <div class="p-3 d-flex align-items-center gap-2" style="border-bottom: 1px solid var(--border-hairline);">
                    <i class="fas fa-search text-muted"></i>
                    <input type="text" id="cpSearchInput" class="form-control border-0 p-0 shadow-none font-sans" placeholder="Ketik perintah atau nama modul..." autocomplete="off" style="background: transparent !important; color: var(--text-primary); font-size: 0.92rem; outline: none !important;">
                    <kbd class="font-mono text-muted" style="font-size: 0.65rem; background: var(--bg-surface-hover); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--border-hairline);">ESC</kbd>
                </div>
                <div id="cpResultsList" class="p-2" style="max-height: 320px; overflow-y: auto;">
                    <!-- Commands rendered dynamically via JS -->
                </div>
                <div class="p-2 px-3 d-flex justify-content-between align-items-center" style="border-top: 1px solid var(--border-hairline); background-color: var(--bg-surface-hover); font-size: 0.72rem; color: var(--text-muted);">
                    <span>Gunakan <kbd style="font-size: 0.65rem;">↑</kbd> <kbd style="font-size: 0.65rem;">↓</kbd> untuk memilih, <kbd style="font-size: 0.65rem;">Enter</kbd> untuk eksekusi</span>
                    <span class="font-mono text-accent">Lifevuww v2.0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Reusable Dark Confirmation Modal -->
    <div class="modal fade" id="lvConfirmModal" tabindex="-1" aria-hidden="true" style="background: rgba(0,0,0,0.8);">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
            <div class="modal-content p-3" style="background-color: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius);">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div id="lvConfirmIcon" style="width: 32px; height: 32px; border-radius: 50%; background-color: var(--status-warning-dim); color: var(--status-warning); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h5 class="modal-title mb-0" id="lvConfirmTitle" style="font-size: 0.95rem; font-weight: 600;">Konfirmasi</h5>
                </div>
                <p id="lvConfirmMsg" class="text-secondary mb-3" style="font-size: 0.84rem; line-height: 1.5;"></p>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn-core btn-ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn-core btn-primary" id="lvConfirmBtn">Lanjutkan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS for Modals -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <script>
        /* ============ Teleport all .modal elements directly to document.body ============ */
        (function teleportModalsToBody() {
            function moveModals() {
                document.querySelectorAll('.modal').forEach(function(modalEl) {
                    if (modalEl.parentElement !== document.body) {
                        document.body.appendChild(modalEl);
                    }
                });
            }
            moveModals();
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', moveModals);
            }
            document.addEventListener('show.bs.modal', function(e) {
                if (e.target && e.target.parentElement !== document.body) {
                    document.body.appendChild(e.target);
                }
            }, true);
        })();

        /* ============ Sound toggles (sidebar + profile) ============ */
        (function () {
            const render = (btn) => {
                const on = LVSound.isOn();
                btn.querySelector('i').className = on ? 'fas fa-volume-up' : 'fas fa-volume-mute';
                btn.querySelector('span').textContent = on ? 'Suara nyala' : 'Senyap';
            };
            document.querySelectorAll('.btn-sound-toggle').forEach((btn) => {
                render(btn);
                btn.addEventListener('click', () => {
                    LVSound.toggle();
                    document.querySelectorAll('.btn-sound-toggle').forEach(render);
                });
            });
        })();

        /* ============ Native shell (Capacitor APK): scheduled daily nag ============
           Fires even with the app fully closed. No-op on plain browsers. */
        const LVNative = (() => {
            const plugin = (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.LocalNotifications) || null;

            async function ensurePerm() {
                if (!plugin) return false;
                try {
                    const st = await plugin.checkPermissions();
                    if (st.display === 'granted') return true;
                    const req = await plugin.requestPermissions();
                    return req.display === 'granted';
                } catch (e) { return false; }
            }

            async function scheduleDaily(hour, minute, incomplete, streak) {
                if (!plugin) return;
                try {
                    await plugin.cancel({ notifications: [{ id: 1001 }] });
                    if (!incomplete) return; // nothing open: no nag needed
                    const threat = streak > 0 ? ` Streak ${streak} harimu terancam.` : '';
                    await plugin.schedule({
                        notifications: [{
                            id: 1001,
                            title: 'Streak terancam!',
                            body: `${incomplete} rutinitas masih terbuka.${threat}`,
                            schedule: { on: { hour, minute }, repeats: true, allowWhileIdle: true },
                            sound: null,
                            smallIcon: 'ic_stat_notify',
                            autoCancel: true
                        }]
                    });
                } catch (e) {
                    console.warn('Daily nag schedule failed:', (e && e.message) || e);
                }
            }

            return { plugin, ensurePerm, scheduleDaily };
        })();

        // (Re)sync the daily native schedule on every page load
        (function () {
            const R = window.LV_REMINDER_ALWAYS;
            if (!R || !LVNative.plugin) return;
            const parts = String(R.time).split(':');
            LVNative.scheduleDaily(parseInt(parts[0], 10), parseInt(parts[1], 10), R.incomplete, R.streak);
        })();

        /* ============ PWA: service worker + install prompt ============ */
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('../sw.js').catch((err) => {
                    console.warn('SW registration failed:', err);
                });
            });
        }

        let lvDeferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            lvDeferredPrompt = e;
            const btn = document.getElementById('btn-install-app');
            if (btn) btn.style.display = '';
        });
        (function () {
            const btn = document.getElementById('btn-install-app');
            if (!btn) return;
            btn.addEventListener('click', async () => {
                if (!lvDeferredPrompt) return;
                lvDeferredPrompt.prompt();
                await lvDeferredPrompt.userChoice;
                lvDeferredPrompt = null;
                btn.style.display = 'none';
            });
        })();

        /* ============ Browser notifications + nag banner ============ */
        function nagMessage(incomplete, streak) {
            const threat = streak > 0
                ? ` Streak ${streak} harimu terancam.`
                : '';
            return `${incomplete} rutinitas masih terbuka.${threat}`;
        }

        function showNagBanner(incomplete, streak) {
            if (sessionStorage.getItem('lv_nag_dismissed')) return;
            const bar = document.getElementById('nagBanner');
            const txt = document.getElementById('nagText');
            if (!bar || !txt) return;
            txt.textContent = nagMessage(incomplete, streak);
            bar.style.display = '';
            document.body.classList.add('nag-active');
            // Geser konten agar tidak tertimpa banner
            const measure = () => {
                document.documentElement.style.setProperty('--nag-h', bar.offsetHeight + 'px');
            };
            measure();
            window.addEventListener('resize', measure);
            document.getElementById('nagClose').addEventListener('click', () => {
                bar.style.display = 'none';
                document.body.classList.remove('nag-active');
                document.documentElement.style.setProperty('--nag-h', '0px');
                sessionStorage.setItem('lv_nag_dismissed', '1');
            }, { once: true });
        }

        async function fireNagNotification(incomplete, streak, tag) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            const title = 'Streak terancam!';
            const options = {
                body: nagMessage(incomplete, streak),
                icon: '../assets/icons/icon-192.png',
                tag: tag || 'lv-nag'
            };
            // Android (Chrome/Brave) does NOT support `new Notification()` from a
            // page at all — it must go through the service worker. Desktop
            // supports both, so try SW first, fall back to constructor.
            try {
                if ('serviceWorker' in navigator) {
                    const reg = await navigator.serviceWorker.ready;
                    if (reg && reg.showNotification) {
                        await reg.showNotification(title, options);
                        return;
                    }
                }
            } catch (e) {
                console.warn('SW notification failed, trying constructor:', (e && e.message) || e);
            }
            try {
                new Notification(title, options);
            } catch (e) {
                console.warn('Web notification failed:', (e && e.message) || e);
            }
        }

        // Immediate nag (past reminder hour, computed server-side)
        if (window.LV_NAG_NOW) {
            showNagBanner(window.LV_NAG_NOW.incomplete, window.LV_NAG_NOW.streak);
            fireNagNotification(window.LV_NAG_NOW.incomplete, window.LV_NAG_NOW.streak);
        }

        // Scheduled nag (reminder hour still ahead today, tab stays open)
        (function () {
            const R = window.LV_REMINDER;
            if (!R || !R.incomplete || window.LV_NAG_NOW) return;
            const parts = String(R.time).split(':');
            const target = new Date();
            target.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0, 0);
            const ms = target - Date.now();
            if (ms > 0 && ms < 12 * 60 * 60 * 1000) {
                setTimeout(() => {
                    showNagBanner(R.incomplete, R.streak);
                    fireNagNotification(R.incomplete, R.streak);
                }, ms);
            }
        })();

        /* ============ Reusable Dark Confirmation Dialog ============ */
        window.showConfirmDialog = function(title, message, onConfirm, opts = {}) {
            const modalEl = document.getElementById('lvConfirmModal');
            if (!modalEl || typeof bootstrap === 'undefined') {
                if (confirm(message)) onConfirm();
                return;
            }
            document.getElementById('lvConfirmTitle').textContent = title || 'Konfirmasi';
            document.getElementById('lvConfirmMsg').textContent = message || 'Apakah kamu yakin?';
            const btn = document.getElementById('lvConfirmBtn');
            const iconEl = document.getElementById('lvConfirmIcon');

            const variant = opts.variant || (opts.danger ? 'danger' : 'primary');
            if (variant === 'danger') {
                btn.className = 'btn-core';
                btn.style.backgroundColor = 'var(--status-danger)';
                btn.style.borderColor = 'var(--status-danger)';
                btn.style.color = '#ffffff';
                iconEl.style.backgroundColor = 'var(--status-danger-dim)';
                iconEl.style.color = 'var(--status-danger)';
                iconEl.innerHTML = '<i class="fas fa-trash-alt"></i>';
            } else if (variant === 'gold' || variant === 'warning') {
                btn.className = 'btn-core font-mono';
                btn.style.backgroundColor = 'var(--status-warning)';
                btn.style.borderColor = 'var(--status-warning)';
                btn.style.color = '#000000';
                iconEl.style.backgroundColor = 'var(--status-warning-dim)';
                iconEl.style.color = 'var(--status-warning)';
                iconEl.innerHTML = '<i class="fas fa-coins"></i>';
            } else {
                btn.className = 'btn-core btn-primary';
                btn.style.backgroundColor = '';
                btn.style.borderColor = '';
                btn.style.color = '';
                iconEl.style.backgroundColor = 'var(--accent-dim)';
                iconEl.style.color = 'var(--accent)';
                iconEl.innerHTML = '<i class="fas fa-question-circle"></i>';
            }
            btn.textContent = opts.confirmText || (variant === 'danger' ? 'Hapus' : (variant === 'gold' ? 'Beli Sekarang' : 'Lanjutkan'));

            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            newBtn.addEventListener('click', function() {
                bsModal.hide();
                onConfirm();
            });
            bsModal.show();
        };

        // Declarative data-confirm interceptor
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-confirm]');
            if (!target) return;
            const form = target.closest('form');
            if (!form) return;
            // Never intercept forms inside modals (such as add/edit habit, quest, reward)
            if (form.closest('.modal')) return;
            if (target.dataset.confirmed) return;

            e.preventDefault();
            e.stopPropagation();

            const msg = target.getAttribute('data-confirm');
            const title = target.getAttribute('data-confirm-title') || 'Konfirmasi Tindakan';
            const variant = target.getAttribute('data-confirm-variant') || 
                (target.classList.contains('text-danger') || (target.name && target.name.indexOf('delete') !== -1) ? 'danger' : 
                ((target.name && (target.name.indexOf('buy') !== -1 || target.name.indexOf('potion') !== -1)) ? 'gold' : 'primary'));
            const confirmText = target.getAttribute('data-confirm-btn');

            window.showConfirmDialog(title, msg, function() {
                target.dataset.confirmed = '1';
                if (target.name) {
                    let hidden = form.querySelector(`input[type="hidden"][name="${target.name}"]`);
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = target.name;
                        form.appendChild(hidden);
                    }
                    hidden.value = target.value || '1';
                }
                if (form.classList.contains('ajax-shop-form') || form.dataset.ajax) {
                    form.dispatchEvent(new CustomEvent('shop:ajax-submit', { detail: { triggerButton: target } }));
                    return;
                }
                form.submit();
            }, { variant, confirmText });
        }, true);

        // Keyboard Shortcut: Escape to close confirmation modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.key === 'Esc') {
                const modalEl = document.getElementById('lvConfirmModal');
                if (modalEl && modalEl.classList.contains('show') && typeof bootstrap !== 'undefined') {
                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    if (bsModal) bsModal.hide();
                }
            }
        });

        /* ============ Floating RPG Combat/Reward Numbers ============ */
        window.spawnFloatingReward = function(x, y, textHtml) {
            const el = document.createElement('div');
            el.className = 'floating-combat-text';
            el.innerHTML = textHtml;
            el.style.left = (x || (window.innerWidth / 2)) + 'px';
            el.style.top = (y || (window.innerHeight / 2)) + 'px';
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 900);
        };

        /* ============ Dynamic Floating Toast Notification ============ */
        window.showToast = function(title, message, icon = 'fa-check', type = 'accent') {
            const existing = document.getElementById('dopamineToast');
            if (existing) existing.remove();

            const toast = document.createElement('div');
            toast.className = 'dopamine-toast';
            toast.id = 'dopamineToast';
            toast.setAttribute('role', 'status');
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="toast-body">
                    <div class="toast-title">${title}</div>
                    <div class="toast-rewards">
                        <span class="toast-reward ${type}">${message}</span>
                    </div>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 400);
            }, 4000);
        };

        // Capture clicks on completion buttons
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('button[name="complete_habit"], button[name="complete_quest"]');
            if (btn) {
                const rect = btn.getBoundingClientRect();
                const x = rect.left + rect.width / 2;
                const y = rect.top;
                const row = btn.closest('.data-row');
                const expVal = row && row.dataset.exp ? `+${row.dataset.exp} EXP` : '+EXP!';
                window.spawnFloatingReward(x, y, `<i class="fas fa-bolt text-accent"></i> ${expVal}`);
                LVSound.coin();
            }
        });

        // Shake Boss HP Bar on subtask toggle (Attacking only)
        document.addEventListener('change', function(e) {
            if (e.target.closest('.subtask-row')) {
                const box = e.target.closest('.boss-card-container');
                // Only shake when checked (attacking boss), never on uncheck (healing/reverting)
                if (box && e.target.checked && !e.target.classList.contains('subtask-checkbox')) {
                    box.classList.add('boss-hit-anim', 'boss-hit-shake');
                    setTimeout(() => box.classList.remove('boss-hit-anim', 'boss-hit-shake'), 300);
                }
            }
        });

        /* ============ Fast Client-Side Sorting (Habits & Quests) with LocalStorage ============ */
        document.addEventListener('change', function(e) {
            if (e.target.id === 'sortQuests') {
                const val = e.target.value;
                localStorage.setItem('lv_quests_sort', val);
                const panel = e.target.closest('.bento-panel') || document;
                const list = panel.querySelector('.data-list');
                if (!list) return;
                const rows = Array.from(list.querySelectorAll('.data-row'));
                rows.sort((a, b) => {
                    if (val === 'deadline') {
                        return (parseInt(a.dataset.deadline) || 9999) - (parseInt(b.dataset.deadline) || 9999);
                    } else if (val === 'exp-desc') {
                        return (parseInt(b.dataset.exp) || 0) - (parseInt(a.dataset.exp) || 0);
                    } else if (val === 'newest') {
                        return (parseInt(b.dataset.id) || 0) - (parseInt(a.dataset.id) || 0);
                    }
                    return 0;
                });
                rows.forEach(r => list.appendChild(r));
            }

            if (e.target.id === 'sortHabits') {
                const val = e.target.value;
                localStorage.setItem('lv_habits_sort', val);
                const panel = e.target.closest('.bento-panel') || document;
                const list = panel.querySelector('.data-list');
                if (!list) return;
                const rows = Array.from(list.querySelectorAll('.data-row'));
                rows.sort((a, b) => {
                    if (val === 'streak-desc') {
                        return (parseInt(b.dataset.streak) || 0) - (parseInt(a.dataset.streak) || 0);
                    } else if (val === 'exp-desc') {
                        return (parseInt(b.dataset.exp) || 0) - (parseInt(a.dataset.exp) || 0);
                    } else if (val === 'newest') {
                        return (parseInt(b.dataset.id) || 0) - (parseInt(a.dataset.id) || 0);
                    }
                    return (parseInt(a.dataset.id) || 0) - (parseInt(b.dataset.id) || 0);
                });
                rows.forEach(r => list.appendChild(r));
            }
        });

        /* ============ Quick Attribute Filter Pills with LocalStorage ============ */
        document.addEventListener('click', function(e) {
            const pill = e.target.closest('.btn-filter-pill');
            if (!pill) return;
            const container = pill.closest('.filter-pills-category');
            if (!container) return;
            const filterCat = pill.getAttribute('data-filter');

            // Save to LocalStorage
            if (window.location.pathname.indexOf('habits.php') !== -1) {
                localStorage.setItem('lv_habits_filter', filterCat);
            } else if (window.location.pathname.indexOf('quests.php') !== -1) {
                localStorage.setItem('lv_quests_filter', filterCat);
            }

            container.querySelectorAll('.btn-filter-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');

            const panel = container.closest('.bento-panel') || document;
            let visibleCount = 0;
            panel.querySelectorAll('.data-row[data-category]').forEach(row => {
                const cat = row.getAttribute('data-category');
                if (filterCat === 'all' || cat === filterCat) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const emptyEl = panel.querySelector('.filter-empty-state');
            if (emptyEl) {
                if (visibleCount === 0) {
                    emptyEl.style.display = 'block';
                    const catSpan = emptyEl.querySelector('.filter-empty-cat-name');
                    if (catSpan) catSpan.textContent = filterCat;
                } else {
                    emptyEl.style.display = 'none';
                }
            }
        });

        // Auto-restore filter & sort state on load
        (function restoreFiltersAndSort() {
            const isHabits = window.location.pathname.indexOf('habits.php') !== -1;
            const isQuests = window.location.pathname.indexOf('quests.php') !== -1;

            if (isHabits) {
                const savedFilter = localStorage.getItem('lv_habits_filter');
                if (savedFilter && savedFilter !== 'all') {
                    const pill = document.querySelector(`.btn-filter-pill[data-filter="${savedFilter}"]`);
                    if (pill) pill.click();
                }
                const savedSort = localStorage.getItem('lv_habits_sort');
                const sortSel = document.getElementById('sortHabits');
                if (savedSort && sortSel) {
                    sortSel.value = savedSort;
                    sortSel.dispatchEvent(new Event('change'));
                }
            }

            if (isQuests) {
                const savedFilter = localStorage.getItem('lv_quests_filter');
                if (savedFilter && savedFilter !== 'all') {
                    const pill = document.querySelector(`.btn-filter-pill[data-filter="${savedFilter}"]`);
                    if (pill) pill.click();
                }
                const savedSort = localStorage.getItem('lv_quests_sort');
                const sortSel = document.getElementById('sortQuests');
                if (savedSort && sortSel) {
                    sortSel.value = savedSort;
                    sortSel.dispatchEvent(new Event('change'));
                }
            }
        })();

        /* ============ Linear Command Palette Logic (Ctrl+K) ============ */
        (function () {
            const commands = [
                { title: 'Dasbor', category: 'Navigasi', icon: 'fa-home', url: 'dashboard.php', keywords: 'home dasbor utama' },
                { title: 'Kebiasaan & Rutinitas', category: 'Navigasi', icon: 'fa-check-square', url: 'habits.php', keywords: 'habit kebiasaan rutinitas checklist' },
                { title: 'Misi & Petualangan', category: 'Navigasi', icon: 'fa-map-signs', url: 'quests.php', keywords: 'quest misi petualangan boss target' },
                { title: 'Keahlian & Radar Karakter', category: 'Navigasi', icon: 'fa-book-open', url: 'skills.php', keywords: 'skill keahlian radar atribut stats' },
                { title: 'Toko Hadiah & Ramuan', category: 'Navigasi', icon: 'fa-store', url: 'shop.php', keywords: 'shop toko belanja potion ramuan reward' },
                { title: 'Riwayat & Heatmap Konsistensi', category: 'Navigasi', icon: 'fa-history', url: 'history.php', keywords: 'history riwayat log heatmap catatan' },
                { title: 'Profil & Kredensial', category: 'Navigasi', icon: 'fa-user-circle', url: 'profile.php', keywords: 'profil profile akun settings kata sandi backup' },
                { title: 'Buat Kebiasaan Baru', category: 'Aksi Cepat', icon: 'fa-plus', action: 'add_habit', keywords: 'tambah buat habit kebiasaan baru' },
                { title: 'Buat Misi Baru', category: 'Aksi Cepat', icon: 'fa-plus-circle', action: 'add_quest', keywords: 'tambah buat misi quest baru boss' },
                { title: 'Toggle Efek Suara (On/Off)', category: 'Preferensi', icon: 'fa-volume-up', action: 'toggle_sound', keywords: 'suara sound audio mute senyap toggle' },
                { title: 'Keluar dari Akun', category: 'Akun', icon: 'fa-sign-out-alt', url: '../logout.php', keywords: 'keluar logout signout exit' }
            ];

            let activeIdx = 0;
            let filtered = [...commands];

            function renderCommands() {
                const listEl = document.getElementById('cpResultsList');
                if (!listEl) return;
                if (filtered.length === 0) {
                    listEl.innerHTML = '<div class="text-center py-4 text-muted" style="font-size: 0.82rem;"><i class="fas fa-search me-1"></i> Tidak ada perintah yang cocok.</div>';
                    return;
                }

                let currentCat = '';
                let html = '';
                filtered.forEach((cmd, idx) => {
                    if (cmd.category !== currentCat) {
                        currentCat = cmd.category;
                        html += `<div class="cp-category-header">${currentCat}</div>`;
                    }
                    const isAct = idx === activeIdx;
                    html += `
                        <div class="cp-item ${isAct ? 'active' : ''}" data-idx="${idx}">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 24px; text-align: center; color: var(--text-muted); font-size: 0.85rem;"><i class="fas ${cmd.icon}"></i></div>
                                <span>${cmd.title}</span>
                            </div>
                            <span class="font-mono text-muted" style="font-size: 0.7rem;"><i class="fas fa-arrow-turn-down me-1"></i>Pilih</span>
                        </div>
                    `;
                });
                listEl.innerHTML = html;
            }

            function executeCommand(cmd) {
                if (!cmd) return;
                const modalEl = document.getElementById('lvCommandPalette');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    if (bsModal) bsModal.hide();
                }

                if (cmd.url) {
                    window.location.href = cmd.url;
                } else if (cmd.action === 'add_habit') {
                    if (window.location.pathname.indexOf('habits.php') !== -1) {
                        const m = document.getElementById('addHabitModal');
                        if (m && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(m).show();
                    } else {
                        window.location.href = 'habits.php';
                    }
                } else if (cmd.action === 'add_quest') {
                    if (window.location.pathname.indexOf('quests.php') !== -1) {
                        const m = document.getElementById('addQuestModal');
                        if (m && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(m).show();
                    } else {
                        window.location.href = 'quests.php';
                    }
                } else if (cmd.action === 'toggle_sound') {
                    LVSound.toggle();
                    document.querySelectorAll('.btn-sound-toggle').forEach(btn => {
                        const on = LVSound.isOn();
                        btn.querySelector('i').className = on ? 'fas fa-volume-up' : 'fas fa-volume-mute';
                        btn.querySelector('span').textContent = on ? 'Suara nyala' : 'Senyap';
                    });
                }
            }

            window.openCommandPalette = function () {
                const modalEl = document.getElementById('lvCommandPalette');
                if (!modalEl || typeof bootstrap === 'undefined') return;
                const input = document.getElementById('cpSearchInput');
                if (input) input.value = '';
                filtered = [...commands];
                activeIdx = 0;
                renderCommands();

                const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                bsModal.show();
                setTimeout(() => { if (input) input.focus(); }, 150);
            };

            // Global Keyboard Shortcut: Ctrl+K or Cmd+K
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    window.openCommandPalette();
                }
            });

            // Input filter & Arrow key listeners inside Command Palette
            const searchInput = document.getElementById('cpSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.toLowerCase().trim();
                    filtered = commands.filter(c => 
                        c.title.toLowerCase().includes(q) || 
                        c.category.toLowerCase().includes(q) || 
                        c.keywords.toLowerCase().includes(q)
                    );
                    activeIdx = 0;
                    renderCommands();
                });

                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIdx = (activeIdx + 1) % Math.max(1, filtered.length);
                        renderCommands();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIdx = (activeIdx - 1 + filtered.length) % Math.max(1, filtered.length);
                        renderCommands();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        executeCommand(filtered[activeIdx]);
                    }
                });
            }

            // Click listener for command items
            document.addEventListener('click', function(e) {
                const item = e.target.closest('.cp-item');
                if (!item) return;
                const idx = parseInt(item.getAttribute('data-idx'), 10);
                if (filtered[idx]) {
                    executeCommand(filtered[idx]);
                }
            });
        })();

        /* ============ Bridge diagnostic readout (profile) ============ */
        (function () {
            const el = document.getElementById('bridge-status');
            if (!el) return;
            const hasCap = typeof window.Capacitor !== 'undefined';
            let plat = '-';
            try {
                plat = hasCap && window.Capacitor.getPlatform ? window.Capacitor.getPlatform() : (hasCap ? 'yes' : '-');
            } catch (e) { plat = 'err'; }
            const hasPlugin = !!(hasCap && window.Capacitor.Plugins && window.Capacitor.Plugins.LocalNotifications);
            const hasSW = ('serviceWorker' in navigator);
            const hasNotif = ('Notification' in window);

            const badge = (label, active, activeText = 'Aktif', inactiveText = 'Tidak') => `
                <span class="badge-status ${active ? 'accent' : ''} font-mono" style="font-size: 0.68rem; margin: 2px;">
                    <i class="fas fa-circle me-1" style="font-size: 0.45rem; color: ${active ? 'var(--accent)' : 'var(--text-muted)'};"></i>
                    ${label}: ${active ? activeText : inactiveText}
                </span>
            `;

            el.innerHTML = `
                <div class="d-flex flex-wrap gap-1 align-items-center">
                    ${badge('Service Worker', hasSW)}
                    ${badge('Notifikasi Browser', hasNotif)}
                    ${badge('Capacitor Bridge', hasCap, plat, 'Web')}
                    ${badge('Plugin Notifikasi', hasPlugin, 'Tersedia', 'Tidak')}
                </div>
            `;
        })();

        /* ============ Notification permission buttons (profile) ============ */
        (function () {
            const enableBtn = document.getElementById('btn-enable-notif');
            const testBtn = document.getElementById('btn-test-notif');
            const label = document.getElementById('notif-label');
            // NOTE: no early return when the web Notification API is missing —
            // the APK WebView has no window.Notification by design, yet its
            // native path below is fully functional.
            const hasWebNotif = ('Notification' in window);
            const render = async () => {
                if (!label) return;
                // Native shell first: its permission is the one that matters in the APK
                if (LVNative.plugin) {
                    try {
                        const st = await LVNative.plugin.checkPermissions();
                        label.textContent = st.display === 'granted' ? 'Notifikasi aktif' : 'Aktifkan notifikasi';
                        return;
                    } catch (e) { /* fall through to web */ }
                }
                if (!hasWebNotif) {
                    label.textContent = 'Not supported here';
                    return;
                }
                label.textContent =
                    Notification.permission === 'granted' ? 'Notifikasi aktif' :
                    Notification.permission === 'denied' ? 'Notifikasi diblokir' :
                    'Aktifkan notifikasi';
            };
            render();
            if (enableBtn) {
                enableBtn.addEventListener('click', async () => {
                    if (LVNative.plugin) {
                        await LVNative.ensurePerm();
                        render();
                        return;
                    }
                    if (!hasWebNotif) return;
                    try { await Notification.requestPermission(); } catch (e) { /* ignore */ }
                    render();
                });
            }
            if (testBtn) {
                testBtn.addEventListener('click', async () => {
                    // Cegah double-tap: kunci tombol 3 detik
                    testBtn.disabled = true;
                    setTimeout(() => { testBtn.disabled = false; }, 3000);
                    // Native shell: fire a real notification 3 seconds out
                    if (LVNative.plugin) {
                        const ok = await LVNative.ensurePerm();
                        if (!ok) {
                            alert('Aktifkan notifikasi dulu.');
                            return;
                        }
                        try {
                            await LVNative.plugin.schedule({
                                notifications: [{
                                    id: 9999,
                                    title: 'Tes notifikasi',
                                    body: 'Kalau ini muncul, nag harian juga akan muncul.',
                                    schedule: { at: new Date(Date.now() + 3000), allowWhileIdle: true },
                                    sound: null,
                                    smallIcon: 'ic_stat_notify'
                                }]
                            });
                            LVSound.coin();
                        } catch (e) {
                            alert('Gagal menjadwalkan notif: ' + ((e && e.message) || e));
                        }
                        return;
                    }
                    if (!hasWebNotif || Notification.permission !== 'granted') {
                        alert('Aktifkan notifikasi dulu.');
                        return;
                    }
                    fireNagNotification(2, 5, 'lv-test');
                    LVSound.coin();
                });
            }
        })();
    </script>

    <script>
        // Clicking anywhere on a date field opens the native calendar popup
        document.querySelectorAll('input[type="date"]').forEach((el) => {
            el.addEventListener('click', () => {
                try {
                    if (typeof el.showPicker === 'function') el.showPicker();
                } catch (e) { /* picker unsupported, fall back to typing */ }
            });
        });
    </script>

    <script>
        // Custom confirmation for all destructive actions
        document.querySelectorAll('button[name*="delete"], button[title*="Delete"], button[title*="Remove"], .btn-danger').forEach(button => {
            button.addEventListener('click', function (event) {
                if (!confirm('Yakin ingin menghapus ini? Tindakan ini tidak bisa dibatalkan.')) {
                    event.preventDefault();
                }
            });
        });

        /* ============ UI Maxing: Antigravity Spotlight, Sunderland Ticks, & A24 Holographic Tilt ============ */
        (function() {
            const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // 1. Google Antigravity: Cursor Spotlight coordinates (Desktop only)
            if (canHover) {
                let spotlightScheduled = false;
                document.addEventListener('mousemove', (e) => {
                    if (spotlightScheduled) return;
                    spotlightScheduled = true;
                    requestAnimationFrame(() => {
                        const cards = document.querySelectorAll('.spotlight-card');
                        cards.forEach(card => {
                            const rect = card.getBoundingClientRect();
                            card.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
                            card.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
                        });
                        spotlightScheduled = false;
                    });
                }, { passive: true });
            }

            // 2. A24: 3D Holographic Card Tilt with Specular Glare (Desktop only & respects motion preference)
            if (canHover && !reduceMotion) {
                document.querySelectorAll('.holo-card').forEach(card => {
                    if (!card.querySelector('.holo-glare')) {
                        const glare = document.createElement('div');
                        glare.className = 'holo-glare';
                        card.appendChild(glare);
                    }

                    card.addEventListener('mousemove', (e) => {
                        const rect = card.getBoundingClientRect();
                        const x = e.clientX - rect.left;
                        const y = e.clientY - rect.top;
                        const centerX = rect.width / 2;
                        const centerY = rect.height / 2;
                        const rotateX = ((y - centerY) / centerY) * -8;
                        const rotateY = ((x - centerX) / centerX) * 8;

                        card.style.transform = `perspective(850px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-3px)`;
                        card.style.setProperty('--glare-x', `${(x / rect.width) * 100}%`);
                        card.style.setProperty('--glare-y', `${(y / rect.height) * 100}%`);
                    });

                    card.addEventListener('mouseleave', () => {
                        card.style.transform = 'perspective(850px) rotateX(0deg) rotateY(0deg) translateY(0px)';
                    });
                });
            }

            // 3. Gray Sunderland: Tactile Mechanical Audio Ticks on Interaction (Throttled 50ms)
            let lastTickTime = 0;
            document.addEventListener('pointerenter', (e) => {
                const el = e.target.closest('.btn-filter-pill, .nav-item, .dock-item, .cp-item, .btn-command-palette-trigger, .hud-cmd-trigger');
                if (el) {
                    const now = performance.now();
                    if (now - lastTickTime < 50) return;
                    lastTickTime = now;
                    LVSound.tick();
                }
            }, true);

            // 4. Custom Magnetic Cursor Follower (Desktop only)
            (function initMagneticCursor() {
                if (!canHover || reduceMotion) return;
                const dot = document.getElementById('cursorDot');
                const ring = document.getElementById('cursorRing');
                if (!dot || !ring) return;

                let mouseX = window.innerWidth / 2;
                let mouseY = window.innerHeight / 2;
                let ringX = mouseX;
                let ringY = mouseY;

                window.addEventListener('mousemove', (e) => {
                    mouseX = e.clientX;
                    mouseY = e.clientY;
                    dot.style.transform = `translate3d(${mouseX}px, ${mouseY}px, 0) translate(-50%, -50%)`;
                }, { passive: true });

                function renderRing() {
                    ringX += (mouseX - ringX) * 0.18;
                    ringY += (mouseY - ringY) * 0.18;
                    ring.style.transform = `translate3d(${ringX}px, ${ringY}px, 0) translate(-50%, -50%)`;
                    requestAnimationFrame(renderRing);
                }
                requestAnimationFrame(renderRing);

                document.addEventListener('pointerenter', (e) => {
                    const target = e.target.closest('.dock-item, .btn-core, .btn-filter-pill, .hud-cmd-trigger, .hud-btn-icon, .hud-profile-btn');
                    if (target) {
                        ring.classList.add('magnetic');
                    }
                }, true);

                document.addEventListener('pointerleave', (e) => {
                    const target = e.target.closest('.dock-item, .btn-core, .btn-filter-pill, .hud-cmd-trigger, .hud-btn-icon, .hud-profile-btn');
                    if (target) {
                        ring.classList.remove('magnetic');
                    }
                }, true);
            })();

            // Native Browser Scroll & Modal Body Overflow Cleanup (Safe for APK & Mobile)
            document.addEventListener('hidden.bs.modal', function() {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                document.documentElement.style.overflow = '';
            });
        })();
    </script>
</body>
</html>
