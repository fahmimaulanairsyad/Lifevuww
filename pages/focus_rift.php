<?php
// pages/focus_rift.php
// Backend endpoint for Focus Rift (Pomodoro RPG Deep Work Raid)
require_once __DIR__ . '/../config/database.php';
enforce_login();

const RIFT_TOLERANCE_SECONDS = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $userId = $_SESSION['user_id'];
    $action = trim($_POST['action'] ?? '');
    $riftKey = trim($_POST['rift_key'] ?? 'beta');
    $attribute = trim($_POST['attribute'] ?? 'Intelligence');

    $rifts = game_focus_rifts();
    if (!isset($rifts[$riftKey])) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Tingkat Focus Rift tidak valid!']);
        exit();
    }

    // Aksi baru: start_rift
    if ($action === 'start_rift') {
        $_SESSION['active_rift'] = [
            'key' => $riftKey,
            'started_at' => time()
        ];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit();
    }

    // Klaim hadiah Focus Rift: verifikasi session active_rift dan durasi waktu
    $activeRift = $_SESSION['active_rift'] ?? null;
    $durationSeconds = (int) ($rifts[$riftKey]['duration_seconds'] ?? 1500);

    if (
        !is_array($activeRift) ||
        ($activeRift['key'] ?? '') !== $riftKey ||
        !isset($activeRift['started_at']) ||
        (time() - (int)$activeRift['started_at']) < ($durationSeconds - RIFT_TOLERANCE_SECONDS)
    ) {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Focus Rift belum dimulai atau waktunya belum selesai.'
        ]);
        exit();
    }

    // Sekali pakai: bersihkan active_rift SEBELUM memberi hadiah
    unset($_SESSION['active_rift']);

    $cats = game_categories();
    if (!isset($cats[$attribute])) {
        $attribute = 'Intelligence';
    }

    $rift = $rifts[$riftKey];
    $baseExp = (int) $rift['exp_reward'];
    $baseGold = (int) $rift['gold_reward'];

    // Passive EXP Mastery boost dari Boss yang sudah ditaklukkan & Passive Buffs (Spesialisasi/Relik)
    $mastery = getBossMasteryStats($pdo, $userId);
    $masteryMul = $mastery['multiplier'] ?? 1.0;

    $riftSource = ($riftKey === 'crimson') ? 'focus_rift_crimson' : 'focus_rift_' . $riftKey;
    $buffs = get_user_passive_buffs($pdo, $userId, $attribute, $riftSource, 'Medium');

    $expGain = (int) floor($baseExp * $masteryMul * $buffs['exp_multiplier']);
    $goldGain = (int) floor($baseGold * $buffs['gold_multiplier']);

    $pdo->beginTransaction();
    try {
        $stmtUser = $pdo->prepare("SELECT exp, level, gold, hp, max_hp, `rank` FROM users WHERE user_id = ? FOR UPDATE");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $pdo->rollBack();
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan.']);
            exit();
        }

        $oldExp = (int)($user['exp'] ?? 0);
        $oldLvl = (int)($user['level'] ?? 1);
        $oldRank = $user['rank'] ?? calculateRank($oldLvl);

        $newExp = $oldExp + $expGain;
        $newLvl = calculateLevel($newExp);
        $newRank = calculateRank($newLvl);

        $stmtUp = $pdo->prepare("UPDATE users SET exp = ?, level = ?, `rank` = ?, gold = gold + ? WHERE user_id = ?");
        $stmtUp->execute([$newExp, $newLvl, $newRank, $goldGain, $userId]);

        // Bump skill attribute progress
        bump_skill_progress($pdo, $userId, $attribute);

        $pdo->commit();

        $isLevelUp = ($newLvl > $oldLvl);
        if ($isLevelUp) {
            $_SESSION['level_up'] = [
                'old_level' => $oldLvl,
                'new_level' => $newLvl,
                'old_rank' => $oldRank,
                'new_rank' => $newRank,
                'is_rank_up' => ($oldRank !== $newRank)
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => "⚡ FOCUS RIFT CLEARED! +{$expGain} EXP & +{$goldGain} Gold ({$attribute} dilatih)",
            'rift_title' => $rift['title'],
            'exp_gain' => $expGain,
            'gold_gain' => $goldGain,
            'attribute' => $attribute,
            'is_level_up' => $isLevelUp,
            'user' => [
                'exp' => $newExp,
                'level' => $newLvl,
                'rank' => $newRank,
                'gold' => (int)($user['gold'] + $goldGain),
                'hp' => (int)$user['hp'],
                'max_hp' => (int)$user['max_hp']
            ]
        ]);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Focus Rift claim error: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Gagal mengklaim Focus Rift.']);
        exit();
    }
}

// Redirect ke dasbor bila diakses via GET
header("Location: dashboard.php?rift=1");
exit();
