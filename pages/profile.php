<?php   
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/header.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user profile and statistics
try {
    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch quest completion statistics from quest_logs
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT quest_id) as quests_completed, COALESCE(SUM(exp_reward), 0) as total_quest_exp FROM quest_logs WHERE user_id = ? AND status = 'Completed'");
    $stmt->execute([$_SESSION['user_id']]);
    $questStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch habit completion statistics from habit_logs
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT habit_id) as habits_completed, COALESCE(SUM(exp_reward), 0) as total_habit_exp FROM habit_logs WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $habitStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Total EXP langsung dari kolom users.exp (sudah di-update saat complete habit/quest)
    $totalExp = $user['exp'] ?? 0;

    // EXP breakdown untuk display
    $habitExp = $habitStats['total_habit_exp'] ?? 0;
    $questExp = $questStats['total_quest_exp'] ?? 0;

    // Level & Rank calculation (using centralized functions)
    $level = calculateLevel($totalExp);
    $rank = calculateRank($level);

    // Calculate EXP for next level
    $expToNextLevel = ($level * 1000) - $totalExp;

    // Handle reminder time setting
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reminder'])) {
        validate_csrf();
        $reminderInput = $_POST['reminder_time'] ?? '20:00';
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $reminderInput)) {
            $pdo->prepare("UPDATE users SET reminder_time = ? WHERE user_id = ?")
                ->execute([$reminderInput . ':00', $_SESSION['user_id']]);
            $user['reminder_time'] = $reminderInput . ':00';
            $reminderSaved = true;
        } else {
            $reminderError = "Invalid time format.";
        }
    }

    // Handle profile picture upload and delete
    $uploadDir = "../assets/images/";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validate_csrf();
        if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['tmp_name'])) {
            $fileName = basename($_FILES['profile_picture']['name']);
            $targetFile = $uploadDir . uniqid() . "_" . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            // Check if file is an image
            $check = getimagesize($_FILES['profile_picture']['tmp_name']);
            if ($check !== false && in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                if ($_FILES['profile_picture']['size'] <= 2000000) {
                    // Delete old picture if exists
                    if (!empty($user['profile_picture']) && file_exists($uploadDir . $user['profile_picture'])) {
                        unlink($uploadDir . $user['profile_picture']);
                    }
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFile)) {
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                        $stmt->execute([basename($targetFile), $_SESSION['user_id']]);
                        header("Location: profile.php");
                        exit();
                    } else {
                        $error = "Failed to upload file.";
                    }
                } else {
                    $error = "File size exceeds 2MB.";
                }
            } else {
                $error = "Invalid file type.";
            }
        }

        if (isset($_POST['delete_picture'])) {
            // Delete profile picture and set to default
            if (!empty($user['profile_picture']) && file_exists($uploadDir . $user['profile_picture'])) {
                unlink($uploadDir . $user['profile_picture']); // Delete old picture
            }
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            header("Location: profile.php");
            exit();
        }
    }

    // Check for existing profile picture
    if (!empty($user['profile_picture']) && file_exists($uploadDir . $user['profile_picture'])) {
        $profilePicturePath = $uploadDir . htmlspecialchars($user['profile_picture']);
    } else {
        // Use Bootstrap's default profile icon if no profile picture
        $profilePicturePath = null; // To trigger the fallback for the default icon
    }

} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    die("Error loading profile. Please try again later.");
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Character Profile</h1>
        <p class="page-subtitle">User credentials, identity avatar, and lifetime record statistics.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="bento-panel text-center h-100 p-4">
            <!-- Profile Picture with Edit Icon -->
            <div class="position-relative d-inline-block mx-auto mb-3">
                <?php if ($profilePicturePath): ?>
                    <img src="<?php echo $profilePicturePath; ?>" alt="Profile Picture" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-subtle);">
                <?php else: ?>
                    <div style="width: 100px; height: 100px; border-radius: 50%; background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                        <i class="fas fa-user" style="font-size: 2.25rem;"></i>
                    </div>
                <?php endif; ?>
                <button class="btn-core position-absolute bottom-0 end-0" id="edit-picture-btn" title="Change Avatar" style="width: 28px; height: 28px; padding: 0; border-radius: 50%; background-color: var(--text-primary); color: var(--bg-base);">
                    <i class="fas fa-pencil-alt" style="font-size: 10px;"></i>
                </button>
            </div>

            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></h2>
            <span class="badge-status accent mb-3"><?php echo htmlspecialchars($rank); ?></span>

            <!-- Card for profile picture edit options -->
            <div id="upload-form" style="display: none; border-top: 1px solid var(--border-hairline); padding-top: 1.25rem; margin-top: 1rem;">
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*" class="form-control" style="font-size: 0.75rem;">
                    </div>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn-core btn-ghost" id="cancel-upload">Cancel</button>
                        <button type="submit" class="btn-core btn-primary">Upload</button>
                    </div>
                </form>
                <form action="profile.php" method="POST" class="mt-3">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="delete_picture" class="btn-core btn-ghost text-danger w-100" style="font-size: 0.75rem;">Remove Avatar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="bento-panel h-100 p-4">
            <div class="panel-header">
                <h3 class="panel-title"><i class="fas fa-chart-line text-accent"></i> Level & Advancement Metrics</h3>
            </div>
            
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-1" style="font-size: 0.78rem;">
                    <span style="font-weight: 500;">Level <?php echo $level; ?></span>
                    <span class="font-mono text-accent"><?php echo number_format($totalExp); ?> / <?php echo number_format($level * 1000); ?> EXP</span>
                </div>
                <div class="vitals-track">
                    <?php $expPercentage = min(100, max(0, (($totalExp - (($level-1)*1000)) / 1000) * 100)); ?>
                    <div class="vitals-fill" style="width: <?php echo $expPercentage; ?>%; background-color: var(--accent);"></div>
                </div>
                <div class="text-end text-muted mt-1 font-mono" style="font-size: 0.7rem;">
                    <?php echo number_format(max(0, $expToNextLevel)); ?> EXP TO NEXT LEVEL
                </div>
            </div>

            <div class="row g-3">
                <div class="col-6">
                    <div style="padding: 1rem; background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                        <div class="text-muted mb-1" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">Quests Completed</div>
                        <div style="font-size: 1.75rem; font-family: var(--font-mono); font-weight: 600; line-height: 1;"><?php echo number_format($questStats['quests_completed'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6">
                    <div style="padding: 1rem; background-color: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: var(--radius);">
                        <div class="text-muted mb-1" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;">Habits Completed</div>
                        <div style="font-size: 1.75rem; font-family: var(--font-mono); font-weight: 600; line-height: 1; color: var(--accent);"><?php echo number_format($habitStats['habits_completed'] ?? 0); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bento-panel mt-3 p-4">
    <div class="panel-header">
        <h3 class="panel-title"><i class="fas fa-bell text-accent"></i> Reminders & App</h3>
    </div>

    <?php if (!empty($reminderSaved)): ?>
        <div class="p-2 mb-3 text-center" style="background-color: var(--accent-dim); border: 1px solid var(--accent-border); color: var(--accent); border-radius: var(--radius); font-size: 0.8rem;">
            Reminder time saved.
        </div>
    <?php endif; ?>
    <?php if (!empty($reminderError)): ?>
        <div class="p-2 mb-3 text-center" style="background-color: var(--status-danger-dim); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--status-danger); border-radius: var(--radius); font-size: 0.8rem;">
            <?php echo htmlspecialchars($reminderError); ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <form method="POST" action="profile.php">
                <?php echo csrf_field(); ?>
                <label class="form-label" for="reminder_time">Daily streak warning at</label>
                <div class="d-flex gap-2">
                    <input type="time" class="form-control font-mono" id="reminder_time" name="reminder_time"
                           value="<?php echo htmlspecialchars(substr($user['reminder_time'] ?? '20:00:00', 0, 5)); ?>" required>
                    <button type="submit" name="save_reminder" value="1" class="btn-core btn-primary">Save</button>
                </div>
                <div class="text-muted mt-1" style="font-size: 0.72rem;">If routines are still open past this hour, the system nags you.</div>
            </form>
        </div>
        <div class="col-md-8">
            <label class="form-label">Browser notifications</label>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn-core btn-outline" id="btn-enable-notif">
                    <i class="fas fa-bell"></i> <span id="notif-label">Enable notifications</span>
                </button>
                <button type="button" class="btn-core btn-ghost" id="btn-test-notif">
                    <i class="fas fa-flask"></i> Send test
                </button>
                <button type="button" class="btn-core btn-ghost" id="btn-install-app" style="display: none;">
                    <i class="fas fa-download"></i> Install app
                </button>
            </div>
            <div class="text-muted mt-1" style="font-size: 0.72rem;">Works best after installing Lifevuww to your home screen.</div>
        </div>
    </div>
</div>

<script>
    const editBtn = document.getElementById('edit-picture-btn');
    const uploadForm = document.getElementById('upload-form');
    const cancelBtn = document.getElementById('cancel-upload');

    editBtn.addEventListener('click', () => {
        uploadForm.style.display = 'block';
        editBtn.style.display = 'none';
    });

    cancelBtn.addEventListener('click', () => {
        uploadForm.style.display = 'none';
        editBtn.style.display = 'flex';
    });
</script>

<?php require_once("../includes/footer.php"); ?>
