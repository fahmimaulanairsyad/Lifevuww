            </div> <!-- end .main-container -->
        </main> <!-- end .app-main -->
    </div> <!-- end .app-layout -->

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
            // Jalankan Confetti
            confetti({
                particleCount: 70,
                spread: 55,
                origin: { y: 0.85 }
            });

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
    <?php endif; ?>

    <!-- Bootstrap JS for Modals -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
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
