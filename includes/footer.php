            </div> <!-- end .main-container -->
        </main> <!-- end .app-main -->
    </div> <!-- end .app-layout -->

    <!-- Streak Nag Banner (hidden by default, shown by JS) -->
    <div id="nagBanner" class="nag-banner" style="display: none;" role="alert">
        <i class="fas fa-fire text-warning"></i>
        <span id="nagText"></span>
        <a href="habits.php" class="btn-core btn-accent" style="padding: 0.3rem 0.7rem; font-size: 0.75rem;">Do it now</a>
        <button id="nagClose" class="btn-core btn-ghost" style="padding: 0.3rem 0.5rem;" aria-label="Dismiss">
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
                fanfare() { [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => tone(f, i * 0.09, 0.22, 'triangle', 0.11)); },
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
    </script>

    <!-- Dopamine Toast Notification -->
    <?php if (isset($_SESSION['reward_toast'])):
        $toast = $_SESSION['reward_toast'];
        unset($_SESSION['reward_toast']);
    ?>
        <div class="dopamine-toast" id="dopamineToast">
            <div class="icon-box">
                <i class="fas <?php echo ($toast['type'] === 'quest') ? 'fa-scroll' : 'fa-check'; ?>"></i>
            </div>
            <div>
                <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 2px;">
                    <?php echo htmlspecialchars($toast['name']); ?> Completed!
                </div>
                <div class="d-flex align-items-center gap-2" style="font-size: 0.85rem; font-family: var(--font-mono);">
                    <span style="color: var(--accent-emerald);">+<?php echo $toast['exp']; ?> EXP</span>
                    <span style="color: var(--accent-warning);"><i class="fas fa-coins me-1"></i>+<?php echo $toast['gold']; ?></span>
                    <?php if (isset($toast['streak']) && $toast['streak'] > 1): ?>
                        <span style="color: #f97316;"><i class="fas fa-fire me-1"></i><?php echo $toast['streak']; ?>x Streak!</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            // Confetti blast + celebration chime
            confetti({ particleCount: 70, spread: 55, origin: { y: 0.85 } });
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
                    <h3 class="text-danger fw-bold mb-2">YOU FELL IN BATTLE!</h3>
                    <p class="text-secondary" style="font-size: 0.9rem;">
                        Your HP dropped to 0 due to missed daily habits or failed quests.
                    </p>
                    <div class="p-3 my-3" style="background-color: var(--bg-base); border-radius: 8px; border: 1px solid var(--border-dim); font-family: var(--font-mono); font-size: 0.85rem;">
                        <div class="text-danger mb-1">-<?php echo $death['lost_exp']; ?> EXP Lost (50% Penalty)</div>
                        <div class="text-muted">All active streaks reset to 0</div>
                    </div>
                    <p class="text-light small mb-4">
                        The system has revived you with full HP. Don't let your discipline slip again!
                    </p>
                    <button type="button" class="btn-core btn-primary w-100 py-2" onclick="document.getElementById('deathModal').remove();">
                        Rise Again
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
    <?php if ($nagIncompleteJs > 0): ?>
    <script>
        window.LV_REMINDER = {
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

    <!-- Bootstrap JS for Modals -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /* ============ Sound toggle (sidebar) ============ */
        (function () {
            const btn = document.getElementById('btn-sound');
            if (!btn) return;
            const render = () => {
                const on = LVSound.isOn();
                btn.querySelector('i').className = on ? 'fas fa-volume-up' : 'fas fa-volume-mute';
                btn.querySelector('span').textContent = on ? 'Sound on' : 'Muted';
            };
            render();
            btn.addEventListener('click', () => { LVSound.toggle(); render(); });
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
            const s = incomplete > 1 ? 's' : '';
            const threat = streak > 0
                ? ` Your ${streak}-day streak is on the line.`
                : '';
            return `${incomplete} routine${s} still open.${threat}`;
        }

        function showNagBanner(incomplete, streak) {
            if (sessionStorage.getItem('lv_nag_dismissed')) return;
            const bar = document.getElementById('nagBanner');
            const txt = document.getElementById('nagText');
            if (!bar || !txt) return;
            txt.textContent = nagMessage(incomplete, streak);
            bar.style.display = '';
            document.getElementById('nagClose').addEventListener('click', () => {
                bar.style.display = 'none';
                sessionStorage.setItem('lv_nag_dismissed', '1');
            }, { once: true });
        }

        function fireNagNotification(incomplete, streak) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            try {
                new Notification('Streak at risk!', {
                    body: nagMessage(incomplete, streak),
                    icon: '../assets/icons/icon-192.png',
                    tag: 'lv-nag'
                });
            } catch (e) { /* ignore */ }
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

        /* ============ Notification permission buttons (profile) ============ */
        (function () {
            const enableBtn = document.getElementById('btn-enable-notif');
            const testBtn = document.getElementById('btn-test-notif');
            const label = document.getElementById('notif-label');
            if (!('Notification' in window)) {
                if (label) label.textContent = 'Not supported here';
                if (enableBtn) enableBtn.disabled = true;
                return;
            }
            const render = () => {
                if (!label) return;
                label.textContent =
                    Notification.permission === 'granted' ? 'Notifications on' :
                    Notification.permission === 'denied' ? 'Notifications blocked' :
                    'Enable notifications';
            };
            render();
            if (enableBtn) {
                enableBtn.addEventListener('click', async () => {
                    try { await Notification.requestPermission(); } catch (e) { /* ignore */ }
                    render();
                });
            }
            if (testBtn) {
                testBtn.addEventListener('click', () => {
                    if (Notification.permission !== 'granted') {
                        alert('Enable notifications first.');
                        return;
                    }
                    fireNagNotification(2, 5);
                    LVSound.coin();
                });
            }
        })();
    </script>

    <script>
        // Custom confirmation for all destructive actions
        document.querySelectorAll('button[name*="delete"], button[title*="Delete"], button[title*="Remove"], .btn-danger').forEach(button => {
            button.addEventListener('click', function (event) {
                if (!confirm('Are you sure you want to delete this? This action cannot be undone.')) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
