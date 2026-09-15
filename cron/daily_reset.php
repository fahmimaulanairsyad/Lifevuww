<?php
// cron/daily_reset.php
// Script ini idealnya dijalankan via server cron job setiap jam 00:00 (tengah malam).

require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // 1. CEK HABIT YANG TERLEWAT (DAMAGE HP & RESET STREAK)
    $stmt = $pdo->prepare("
        SELECT h.habit_id, h.user_id, h.difficulty, h.streak, u.streak_freeze 
        FROM habits h 
        JOIN users u ON h.user_id = u.user_id
        WHERE h.completed_today = 0 AND h.last_reset < CURDATE()
    ");
    $stmt->execute();
    $missedHabits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kumpulkan user yang butuh proteksi Streak Freeze
    $usersProtected = [];

    foreach ($missedHabits as $habit) {
        $userId = $habit['user_id'];
        
        // Cek apakah user punya Streak Freeze
        if (($habit['streak_freeze'] ?? 0) > 0 && !isset($usersProtected[$userId])) {
            // Gunakan 1 Streak Freeze untuk melindungi SEMUA habit user pada hari tersebut
            $pdo->prepare("UPDATE users SET streak_freeze = streak_freeze - 1 WHERE user_id = ?")
                ->execute([$userId]);
            $usersProtected[$userId] = true;
        }

        if (isset($usersProtected[$userId])) {
            // Dilindungi! Jangan kurangi HP dan jangan reset streak
            $updateHabit = $pdo->prepare("UPDATE habits SET last_reset = NOW() WHERE habit_id = ?");
            $updateHabit->execute([$habit['habit_id']]);
            continue;
        }

        // Jika tidak dilindungi: Hitung Damage HP berdasarkan difficulty
        $damage = match ($habit['difficulty']) {
            'Easy' => 5,
            'Medium' => 10,
            'Hard' => 15,
            default => 5,
        };

        // Kurangi HP user, reset streak ke 0
        $updateUser = $pdo->prepare("UPDATE users SET hp = GREATEST(0, hp - ?) WHERE user_id = ?");
        $updateUser->execute([$damage, $userId]);

        $updateHabit = $pdo->prepare("UPDATE habits SET streak = 0, last_reset = NOW() WHERE habit_id = ?");
        $updateHabit->execute([$habit['habit_id']]);

        // Catat log gagal
        $logFail = $pdo->prepare("
            INSERT INTO habit_logs (habit_id, user_id, completion_date, status, exp_reward)
            VALUES (?, ?, CURDATE(), 'Failed', 0)
        ");
        $logFail->execute([$habit['habit_id'], $userId]);
    }

    // 2. CEK QUEST YANG KADALUARSA
    // Jika start_date + duration_days < hari ini, maka Failed
    $stmt = $pdo->prepare("
        SELECT quest_id, user_id, difficulty 
        FROM quests 
        WHERE status = 'In Progress' 
        AND DATE_ADD(start_date, INTERVAL duration_days DAY) < CURDATE()
    ");
    $stmt->execute();
    $expiredQuests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiredQuests as $quest) {
        $damage = match ($quest['difficulty']) {
            'Easy' => 10,
            'Medium' => 20,
            'Hard' => 30,
            default => 10,
        };

        $updateUser = $pdo->prepare("UPDATE users SET hp = GREATEST(0, hp - ?) WHERE user_id = ?");
        $updateUser->execute([$damage, $quest['user_id']]);

        $updateQuest = $pdo->prepare("UPDATE quests SET status = 'Failed' WHERE quest_id = ?");
        $updateQuest->execute([$quest['quest_id']]);

        $logFail = $pdo->prepare("
            INSERT INTO quest_logs (quest_id, user_id, status, completed_at, exp_reward)
            VALUES (?, ?, 'Failed', NOW(), 0)
        ");
        $logFail->execute([$quest['quest_id'], $quest['user_id']]);
    }

    // 3. RESET STATUS HABIT UNTUK HARI BARU
    // Hanya untuk habit yang reset_period = 'Daily' (atau weekly/monthly sesuai logika yg lebih kompleks)
    $stmt = $pdo->prepare("
        UPDATE habits 
        SET completed_today = 0, last_reset = NOW() 
        WHERE completed_today = 1 
        AND (
            (reset_period = 'Daily' AND DATE(last_reset) < CURDATE()) OR
            (reset_period = 'Weekly' AND WEEK(last_reset, 1) < WEEK(CURDATE(), 1)) OR
            (reset_period = 'Monthly' AND MONTH(last_reset) < MONTH(CURDATE()))
        )
    ");
    $stmt->execute();

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Daily reset and damage calculation completed."]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Cron Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
