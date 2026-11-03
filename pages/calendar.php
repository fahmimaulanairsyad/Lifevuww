<?php
// pages/calendar.php
require_once __DIR__ . '/../config/database.php';
enforce_login();

$userId = $_SESSION['user_id'];
$todayDate = date('Y-m-d');
$nowTime = date('H:i');

// Handle ICS Export
if (isset($_GET['export_ics']) && !empty($_GET['date'])) {
    $expDate = $_GET['date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expDate)) {
        $expDate = $todayDate;
    }

    $stmtH = $pdo->prepare("SELECT * FROM habits WHERE user_id = ?");
    $stmtH->execute([$userId]);
    $allHabits = $stmtH->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtQ = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND (start_date <= ? AND (end_date IS NULL OR end_date >= ?))");
    $stmtQ->execute([$userId, $expDate, $expDate]);
    $allQuests = $stmtQ->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $events = [];
    foreach ($allHabits as $h) {
        if (isHabitDueToday($h, $expDate)) {
            $events[] = [
                'uid' => 'habit-' . $h['habit_id'] . '-' . $expDate . '@lifevuww',
                'summary' => $h['habit_name'],
                'description' => 'Kebiasaan Lifevuww (' . ($h['category'] ?? 'Health') . ' - ' . ($h['difficulty'] ?? 'Easy') . ')',
                'start_time' => $h['start_time'],
                'end_time' => $h['end_time'],
                'date' => $expDate
            ];
        }
    }
    foreach ($allQuests as $q) {
        $events[] = [
            'uid' => 'quest-' . $q['quest_id'] . '-' . $expDate . '@lifevuww',
            'summary' => '[Misi] ' . $q['quest_name'],
            'description' => 'Misi Lifevuww (' . ($q['category'] ?? 'General') . ' - ' . ($q['difficulty'] ?? 'Easy') . ')',
            'start_time' => $q['start_time'],
            'end_time' => $q['end_time'],
            'date' => $expDate
        ];
    }

    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Lifevuww//Tactical RPG Calendar//ID\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";

    $dtStamp = gmdate('Ymd\THis\Z');
    foreach ($events as $ev) {
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $ev['uid'] . "\r\n";
        $ics .= "DTSTAMP:" . $dtStamp . "\r\n";
        $cleanDate = str_replace('-', '', $ev['date']);
        if (!empty($ev['start_time']) && !empty($ev['end_time'])) {
            $stClean = str_replace(':', '', substr($ev['start_time'], 0, 5)) . '00';
            $etClean = str_replace(':', '', substr($ev['end_time'], 0, 5)) . '00';
            $ics .= "DTSTART:" . $cleanDate . "T" . $stClean . "\r\n";
            $ics .= "DTEND:" . $cleanDate . "T" . $etClean . "\r\n";
        } else {
            $ics .= "DTSTART;VALUE=DATE:" . $cleanDate . "\r\n";
            $nextDay = date('Ymd', strtotime($ev['date'] . ' +1 day'));
            $ics .= "DTEND;VALUE=DATE:" . $nextDay . "\r\n";
        }
        $ics .= "SUMMARY:" . addcslashes($ev['summary'], ",;\\") . "\r\n";
        $ics .= "DESCRIPTION:" . addcslashes($ev['description'], ",;\\") . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
    }
    $ics .= "END:VCALENDAR\r\n";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="lifevuww-schedule-' . $expDate . '.ics"');
    echo $ics;
    exit();
}

$pageTitle = 'Kalender Taktis';
require_once __DIR__ . '/../includes/header.php';

// View parameter: 'day', 'week', 'month'
$view = $_GET['view'] ?? 'day';
if (!in_array($view, ['day', 'week', 'month'], true)) {
    $view = 'day';
}

// Selected Date
$selectedDate = $_GET['date'] ?? $todayDate;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = $todayDate;
}

// Data Queries
$stmtHabits = $pdo->prepare("SELECT * FROM habits WHERE user_id = ? ORDER BY is_pinned DESC, sort_order ASC, habit_id ASC");
$stmtHabits->execute([$userId]);
$userHabits = $stmtHabits->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmtQuests = $pdo->prepare("SELECT * FROM quests WHERE user_id = ? AND status = 'In Progress' ORDER BY is_pinned DESC, sort_order ASC, quest_id DESC");
$stmtQuests->execute([$userId]);
$userQuests = $stmtQuests->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 1. Data for Day View
$todayItems = [];
foreach ($userHabits as $h) {
    if (isHabitDueToday($h, $selectedDate)) {
        $isDone = (!empty($h['completed_today']) || (int)($h['current_count'] ?? 0) >= (int)($h['target_count'] ?? 1));
        $todayItems[] = [
            'kind' => 'habit',
            'id' => $h['habit_id'],
            'title' => $h['habit_name'],
            'category' => $h['category'] ?? 'Health',
            'difficulty' => $h['difficulty'] ?? 'Easy',
            'exp' => $h['exp_reward'] ?? 5,
            'start_time' => $h['start_time'],
            'end_time' => $h['end_time'],
            'is_done' => $isDone,
            'is_pinned' => !empty($h['is_pinned']),
            'sort_order' => (int)($h['sort_order'] ?? 0),
        ];
    }
}
foreach ($userQuests as $q) {
    $qStart = $q['start_date'] ?? $todayDate;
    $qEnd = $q['end_date'] ?? date('Y-m-d', strtotime("$qStart +7 days"));
    if ($selectedDate >= $qStart && $selectedDate <= $qEnd) {
        $todayItems[] = [
            'kind' => 'quest',
            'id' => $q['quest_id'],
            'title' => $q['quest_name'],
            'category' => $q['category'] ?? 'General',
            'difficulty' => $q['difficulty'] ?? 'Easy',
            'exp' => $q['exp_reward'] ?? 10,
            'start_time' => $q['start_time'],
            'end_time' => $q['end_time'],
            'is_done' => false,
            'is_pinned' => !empty($q['is_pinned']),
            'sort_order' => (int)($q['sort_order'] ?? 0),
        ];
    }
}

// Split into All-Day and Timed
$dayAllDayItems = [];
$dayTimedItems = [];
foreach ($todayItems as $item) {
    if (!empty($item['start_time']) && !empty($item['end_time'])) {
        $dayTimedItems[] = $item;
    } else {
        $dayAllDayItems[] = $item;
    }
}

// Sort Timed by start_time
usort($dayTimedItems, function($a, $b) {
    return strcmp($a['start_time'], $b['start_time']);
});

$dayOverlaps = detect_time_block_overlaps($dayTimedItems);

// 2. Data for Week View
$weekDate = $_GET['week_date'] ?? $selectedDate;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)) {
    $weekDate = $selectedDate;
}
$isoDay = (int)date('N', strtotime($weekDate));
$mondayTs = strtotime('-' . ($isoDay - 1) . ' days', strtotime($weekDate));
$weekDays = [];
$dayNamesIndo = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

for ($i = 0; $i < 7; $i++) {
    $curDate = date('Y-m-d', strtotime("+$i days", $mondayTs));
    $curDayNum = (int)date('N', strtotime($curDate));
    $dayItems = [];

    foreach ($userHabits as $h) {
        if (isHabitDueToday($h, $curDate)) {
            $isDone = ($curDate === $todayDate) ? (!empty($h['completed_today']) || (int)($h['current_count'] ?? 0) >= (int)($h['target_count'] ?? 1)) : false;
            $dayItems[] = [
                'kind' => 'habit',
                'id' => $h['habit_id'],
                'title' => $h['habit_name'],
                'category' => $h['category'] ?? 'Health',
                'difficulty' => $h['difficulty'] ?? 'Easy',
                'exp' => $h['exp_reward'] ?? 5,
                'start_time' => $h['start_time'],
                'end_time' => $h['end_time'],
                'is_done' => $isDone,
                'is_pinned' => !empty($h['is_pinned']),
                'sort_order' => (int)($h['sort_order'] ?? 0),
            ];
        }
    }

    foreach ($userQuests as $q) {
        $qStart = $q['start_date'] ?? $todayDate;
        $qEnd = $q['end_date'] ?? date('Y-m-d', strtotime("$qStart +7 days"));
        if ($curDate >= $qStart && $curDate <= $qEnd) {
            $dayItems[] = [
                'kind' => 'quest',
                'id' => $q['quest_id'],
                'title' => $q['quest_name'],
                'category' => $q['category'] ?? 'General',
                'difficulty' => $q['difficulty'] ?? 'Easy',
                'exp' => $q['exp_reward'] ?? 10,
                'start_time' => $q['start_time'],
                'end_time' => $q['end_time'],
                'is_done' => false,
                'is_pinned' => !empty($q['is_pinned']),
                'sort_order' => (int)($q['sort_order'] ?? 0),
            ];
        }
    }

    usort($dayItems, function($a, $b) {
        $hasTimeA = (!empty($a['start_time']) && !empty($a['end_time'])) ? 1 : 0;
        $hasTimeB = (!empty($b['start_time']) && !empty($b['end_time'])) ? 1 : 0;
        if ($hasTimeA !== $hasTimeB) return $hasTimeB - $hasTimeA;
        if ($hasTimeA && $hasTimeB) {
            $cmp = strcmp($a['start_time'], $b['start_time']);
            if ($cmp !== 0) return $cmp;
        }
        $pinDiff = ($b['is_pinned'] ? 1 : 0) - ($a['is_pinned'] ? 1 : 0);
        if ($pinDiff !== 0) return $pinDiff;
        return $a['sort_order'] - $b['sort_order'];
    });

    $weekDays[] = [
        'date' => $curDate,
        'day_name' => $dayNamesIndo[$curDayNum],
        'day_num' => (int)date('j', strtotime($curDate)),
        'is_today' => ($curDate === $todayDate),
        'items' => $dayItems
    ];
}

$prevWeekDate = date('Y-m-d', strtotime('-7 days', $mondayTs));
$nextWeekDate = date('Y-m-d', strtotime('+7 days', $mondayTs));

// 3. Data for Month View
$monthParam = $_GET['month'] ?? date('Y-m', strtotime($selectedDate));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$monthTs = strtotime("$monthParam-01");
$daysInMonth = (int)date('t', $monthTs);
$firstDayIso = (int)date('N', $monthTs); // 1 = Mon, 7 = Sun
$prevMonthParam = date('Y-m', strtotime('-1 month', $monthTs));
$nextMonthParam = date('Y-m', strtotime('+1 month', $monthTs));

// Pre-fetch completed habit logs for this month
$stmtHLogs = $pdo->prepare("SELECT completion_date, COUNT(*) as cnt FROM habit_logs WHERE user_id = ? AND status = 'Completed' AND completion_date BETWEEN ? AND ? GROUP BY completion_date");
$stmtHLogs->execute([$userId, "$monthParam-01", "$monthParam-$daysInMonth"]);
$monthHabitLogs = $stmtHLogs->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Pre-fetch completed quest logs for this month
$stmtQLogs = $pdo->prepare("SELECT DATE(completed_at) as cdate, COUNT(*) as cnt FROM quest_logs WHERE user_id = ? AND status = 'Completed' AND DATE(completed_at) BETWEEN ? AND ? GROUP BY DATE(completed_at)");
$stmtQLogs->execute([$userId, "$monthParam-01", "$monthParam-$daysInMonth"]);
$monthQuestLogs = $stmtQLogs->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// Month cells data cache
$monthGridDays = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dStr = sprintf('%s-%02d', $monthParam, $day);
    $dueHabitsCount = 0;
    foreach ($userHabits as $h) {
        if (isHabitDueToday($h, $dStr)) {
            $dueHabitsCount++;
        }
    }

    $questsEndingCount = 0;
    foreach ($userQuests as $q) {
        if (!empty($q['end_date']) && $q['end_date'] === $dStr) {
            $questsEndingCount++;
        }
    }

    $doneLogsCount = (int)($monthHabitLogs[$dStr] ?? 0) + (int)($monthQuestLogs[$dStr] ?? 0);

    $monthGridDays[$day] = [
        'date' => $dStr,
        'day' => $day,
        'is_today' => ($dStr === $todayDate),
        'due_habits' => $dueHabitsCount,
        'quests_deadline' => $questsEndingCount,
        'done_count' => $doneLogsCount
    ];
}

$nowMinutes = time_to_minutes(date('H:i'));
?>

<div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-alt text-accent me-2"></i>Kalender Taktis</h1>
        <p class="page-subtitle">Pusat kendali jadwal harian, time-blocking presisi, dan sinkronisasi agenda.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="calendar-tab-pills">
            <a href="calendar.php?view=day&date=<?php echo urlencode($selectedDate); ?>" class="btn-filter-pill <?php echo $view === 'day' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Hari Ini
            </a>
            <a href="calendar.php?view=week&week_date=<?php echo urlencode($selectedDate); ?>" class="btn-filter-pill <?php echo $view === 'week' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i> Mingguan
            </a>
            <a href="calendar.php?view=month&month=<?php echo urlencode(substr($selectedDate, 0, 7)); ?>" class="btn-filter-pill <?php echo $view === 'month' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> Bulanan
            </a>
        </div>
        <button type="button" class="btn-core btn-outline font-mono" data-bs-toggle="modal" data-bs-target="#quickAddScheduleModal">
            <i class="fas fa-plus text-accent me-1"></i> Tambah Jadwal
        </button>
    </div>
</div>

<?php if ($view === 'day'): ?>
    <!-- ========================================== -->
    <!-- 1. TAB HARI INI: TIME-BLOCK TIMELINE VIEW -->
    <!-- ========================================== -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="calendar.php?view=day&date=<?php echo date('Y-m-d', strtotime("$selectedDate -1 day")); ?>" class="btn-core btn-ghost p-1" title="Hari Sebelumnya">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0;">
                <?php echo indo_short_date($selectedDate); ?>
                <?php if ($selectedDate === $todayDate): ?>
                    <span class="badge-status accent font-mono ms-1" style="font-size: 0.68rem;">HARI INI</span>
                <?php endif; ?>
            </h2>
            <a href="calendar.php?view=day&date=<?php echo date('Y-m-d', strtotime("$selectedDate +1 day")); ?>" class="btn-core btn-ghost p-1" title="Hari Berikutnya">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <div class="d-flex gap-2">
            <?php if ($selectedDate !== $todayDate): ?>
                <a href="calendar.php?view=day&date=<?php echo $todayDate; ?>" class="btn-core btn-ghost font-mono" style="font-size: 0.76rem;">
                    Ke Hari Ini
                </a>
            <?php endif; ?>
            <a href="calendar.php?export_ics=1&date=<?php echo urlencode($selectedDate); ?>" class="btn-core btn-outline font-mono" style="font-size: 0.76rem;">
                <i class="fas fa-download me-1"></i> Export .ics
            </a>
        </div>
    </div>

    <!-- Overlap Warning Banner -->
    <?php if (!empty($dayOverlaps)): ?>
        <div class="alert alert-warning py-2 px-3 d-flex align-items-center gap-2 mb-3" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b; border-radius: var(--radius); font-size: 0.82rem;">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                Ada <strong><?php echo count($dayOverlaps); ?> bentrok jadwal (overlap)</strong> pada jam yang sama hari ini. Pertimbangkan untuk menyesuaikan jam mulai/selesai.
            </div>
        </div>
    <?php endif; ?>

    <!-- All-Day Items Panel -->
    <div class="bento-panel mb-3 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted" style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">
                <i class="fas fa-sun text-warning me-1"></i> Sepanjang Hari (All-Day Tasks)
            </span>
            <span class="badge-status font-mono" style="font-size: 0.68rem;"><?php echo count($dayAllDayItems); ?> Agenda</span>
        </div>
        <?php if (!empty($dayAllDayItems)): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($dayAllDayItems as $ad): ?>
                    <div class="allday-chip d-inline-flex align-items-center gap-2 px-2 py-1" style="background: var(--bg-surface-hover); border: 1px solid var(--border-hairline); border-radius: 6px; font-size: 0.78rem;">
                        <span class="badge-status font-mono" style="font-size: 0.62rem;"><?php echo $ad['kind'] === 'quest' ? 'Misi' : 'Habit'; ?></span>
                        <span class="allday-title" style="<?php echo $ad['is_done'] ? 'text-decoration: line-through; opacity: 0.6;' : 'color: var(--text-primary);'; ?>"><?php echo htmlspecialchars($ad['title']); ?></span>
                        <span class="text-accent font-mono" style="font-size: 0.7rem;">+<?php echo $ad['exp']; ?> EXP</span>
                        <?php if ($selectedDate === $todayDate && !$ad['is_done']): ?>
                            <?php if ($ad['kind'] === 'habit'): ?>
                                <button type="button" class="btn-core btn-accent btn-sm py-0 px-2 btn-cal-checkin-habit" data-habit-id="<?php echo $ad['id']; ?>" style="font-size: 0.65rem;" title="Selesaikan Habit">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-core btn-ghost btn-sm py-0 px-1" onclick="window.openFocusRiftModal && window.openFocusRiftModal();" style="font-size: 0.65rem;" title="Fokus">
                                    <i class="fas fa-hourglass-half text-warning"></i>
                                </button>
                                <button type="button" class="btn-core btn-accent btn-sm py-0 px-2 btn-cal-checkin-quest" data-quest-id="<?php echo $ad['id']; ?>" style="font-size: 0.65rem;" title="Selesaikan Misi">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-muted" style="font-size: 0.78rem;">Tidak ada agenda sepanjang hari tanpa jam. Semua terjadwal rapi pada timeline!</div>
        <?php endif; ?>
    </div>

    <!-- 24-Hour Vertical Time-Block Timeline -->
    <div class="bento-panel p-0 overflow-hidden position-relative">
        <div class="panel-header p-3 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--bg-surface-hover); border-bottom: 1px solid var(--border-hairline);">
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted font-mono" style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.06em;">
                    <i class="fas fa-stream me-1 text-accent"></i> Time-Block Timeline
                </span>
                <span class="text-muted font-mono" style="font-size: 0.72rem;">WIB (UTC+7)</span>
            </div>
            <div class="btn-group btn-group-sm font-mono" role="group" id="dayHoursFilterGroup">
                <button type="button" class="btn-core btn-filter-pill active" id="btnFilterActiveHours" style="font-size: 0.72rem; padding: 0.25rem 0.65rem;" title="Sembunyikan jam malam kosong (00:00-05:00 & 23:00)">
                    <i class="fas fa-business-time me-1"></i> Jam Aktif (06:00-22:00)
                </button>
                <button type="button" class="btn-core btn-filter-pill" id="btnFilterAllHours" style="font-size: 0.72rem; padding: 0.25rem 0.65rem;" title="Tampilkan 24 jam penuh">
                    <i class="fas fa-clock me-1"></i> 24 Jam
                </button>
            </div>
        </div>

        <div class="timeline-container" id="timelineScrollContainer" style="height: 620px; overflow-y: auto; position: relative;">
            <div class="timeline-grid position-relative" style="height: 1440px;">
                <!-- Live Red Line Indicator (Only shown on today) -->
                <?php if ($selectedDate === $todayDate): ?>
                    <div class="timeline-live-line" id="timelineLiveLine" data-now-min="<?php echo $nowMinutes; ?>" style="top: <?php echo $nowMinutes; ?>px;">
                        <span class="timeline-live-badge font-mono"><?php echo $nowTime; ?></span>
                    </div>
                <?php endif; ?>

                <!-- 24 Hour Slots -->
                <?php for ($hour = 0; $hour < 24; $hour++): 
                    $hLabel = sprintf('%02d:00', $hour);
                ?>
                    <div class="timeline-hour-row" data-hour="<?php echo $hLabel; ?>" data-hour-num="<?php echo $hour; ?>" style="top: <?php echo $hour * 60; ?>px;" title="Klik jam <?php echo $hLabel; ?> untuk menjadwalkan">
                        <div class="timeline-hour-label font-mono"><?php echo $hLabel; ?></div>
                        <div class="timeline-hour-line"></div>
                        <span class="timeline-schedule-hint font-mono"><i class="fas fa-plus me-1"></i>Jadwalkan</span>
                    </div>
                <?php endfor; ?>

                <!-- Positioned Timed Blocks -->
                <?php foreach ($dayTimedItems as $tItem): 
                    $sMin = time_to_minutes(substr($tItem['start_time'], 0, 5));
                    $eMin = time_to_minutes(substr($tItem['end_time'], 0, 5));
                    if ($eMin <= $sMin) $eMin += 1440;
                    $durMin = max(30, $eMin - $sMin);
                    $topPx = $sMin;
                    $heightPx = $durMin;
                    $isItemLive = ($selectedDate === $todayDate) && is_time_block_active($tItem['start_time'], $tItem['end_time']);
                ?>
                    <div class="timeblock-card <?php echo $isItemLive ? 'is-live' : ''; ?> <?php echo $tItem['kind'] === 'quest' ? 'type-quest' : 'type-habit'; ?>" data-start-min="<?php echo $sMin; ?>" data-end-min="<?php echo $eMin; ?>" data-dur-min="<?php echo $durMin; ?>" style="top: <?php echo $topPx; ?>px; height: <?php echo $heightPx; ?>px;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="timeblock-time font-mono">
                                <i class="fas fa-clock me-1"></i><?php echo htmlspecialchars(format_time_block($tItem['start_time'], $tItem['end_time'])); ?>
                            </span>
                            <div class="d-flex align-items-center gap-1">
                                <?php if ($isItemLive): ?>
                                    <span class="badge-status accent font-mono" style="font-size: 0.6rem; padding: 0.1rem 0.35rem;"><i class="fas fa-circle me-1" style="font-size: 0.4rem;"></i>LIVE</span>
                                <?php endif; ?>
                                <span class="badge-status font-mono" style="font-size: 0.62rem;"><?php echo htmlspecialchars($tItem['category']); ?></span>
                            </div>
                        </div>
                        <div class="timeblock-title"><?php echo htmlspecialchars($tItem['title']); ?></div>
                        <div class="timeblock-meta font-mono mt-1 d-flex align-items-center justify-content-between">
                            <div>
                                <span>+<?php echo $tItem['exp']; ?> EXP</span>
                                <?php if ($tItem['is_done']): ?>
                                    <span class="text-accent ms-2"><i class="fas fa-check-circle"></i> Selesai</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($selectedDate === $todayDate && !$tItem['is_done']): ?>
                                <div class="timeblock-actions d-flex align-items-center gap-1">
                                    <?php if ($tItem['kind'] === 'habit'): ?>
                                        <button type="button" class="btn-core btn-accent btn-sm p-1 px-2 btn-cal-checkin-habit" data-habit-id="<?php echo $tItem['id']; ?>" style="font-size: 0.68rem;" title="Selesaikan Habit">
                                            <i class="fas fa-check me-1"></i> Selesai
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-core btn-outline btn-sm p-1 px-2 me-1" onclick="window.openFocusRiftModal && window.openFocusRiftModal();" style="font-size: 0.68rem;" title="Mulai Sesi Fokus">
                                            <i class="fas fa-hourglass-half"></i>
                                        </button>
                                        <button type="button" class="btn-core btn-accent btn-sm p-1 px-2 btn-cal-checkin-quest" data-quest-id="<?php echo $tItem['id']; ?>" style="font-size: 0.68rem;" title="Tandai Selesai">
                                            <i class="fas fa-check me-1"></i> Selesai
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php elseif ($view === 'week'): ?>
    <!-- ========================================== -->
    <!-- 2. TAB MINGGUAN: 7-DAY SCHEDULE VIEW       -->
    <!-- ========================================== -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="calendar.php?view=week&week_date=<?php echo urlencode($prevWeekDate); ?>" class="btn-core btn-ghost p-1" title="Minggu Sebelumnya">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0;">
                Minggu: <?php echo indo_short_date($weekDays[0]['date']); ?> - <?php echo indo_short_date($weekDays[6]['date']); ?>
            </h2>
            <a href="calendar.php?view=week&week_date=<?php echo urlencode($nextWeekDate); ?>" class="btn-core btn-ghost p-1" title="Minggu Berikutnya">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <div>
            <a href="calendar.php?view=week&week_date=<?php echo $todayDate; ?>" class="btn-core btn-ghost font-mono" style="font-size: 0.76rem;">
                Ke Minggu Ini
            </a>
        </div>
    </div>

    <div class="week-schedule-wrapper">
        <div class="week-schedule-grid">
            <?php foreach ($weekDays as $wDay): ?>
                <div class="week-day-col <?php echo $wDay['is_today'] ? 'is-today-col' : ''; ?>">
                    <div class="week-day-header">
                        <div class="week-day-title"><?php echo $wDay['day_name']; ?></div>
                        <div class="week-day-number font-mono <?php echo $wDay['is_today'] ? 'text-accent' : ''; ?>"><?php echo $wDay['day_num']; ?></div>
                    </div>
                    <div class="week-day-items">
                        <?php if (!empty($wDay['items'])): ?>
                            <?php foreach ($wDay['items'] as $item): 
                                $hasTime = (!empty($item['start_time']) && !empty($item['end_time']));
                                $isLive = $wDay['is_today'] && $hasTime && is_time_block_active($item['start_time'], $item['end_time']);
                            ?>
                                <div class="week-item-card <?php echo $item['kind'] === 'quest' ? 'type-quest' : 'type-habit'; ?> <?php echo $isLive ? 'is-live' : ''; ?>">
                                    <?php if ($hasTime): ?>
                                        <div class="font-mono text-muted mb-1" style="font-size: 0.65rem;">
                                            <i class="fas fa-clock me-1"></i><?php echo htmlspecialchars(format_time_block($item['start_time'], $item['end_time'])); ?>
                                            <?php if ($isLive): ?><span class="text-accent ms-1">• LIVE</span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="week-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div class="d-flex align-items-center justify-content-between mt-1 font-mono" style="font-size: 0.65rem;">
                                        <span class="text-muted"><?php echo htmlspecialchars($item['category']); ?></span>
                                        <span class="text-accent">+<?php echo $item['exp']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted" style="font-size: 0.72rem; opacity: 0.6;">
                                Kosong
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php elseif ($view === 'month'): ?>
    <!-- ========================================== -->
    <!-- 3. TAB BULANAN: MONTH GRID VIEW            -->
    <!-- ========================================== -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="calendar.php?view=month&month=<?php echo urlencode($prevMonthParam); ?>" class="btn-core btn-ghost p-1" title="Bulan Sebelumnya">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h2 style="font-size: 1.15rem; font-weight: 600; margin: 0;">
                <?php 
                    $monthsIndo = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
                    $mNum = (int)date('n', $monthTs);
                    echo $monthsIndo[$mNum] . ' ' . date('Y', $monthTs);
                ?>
            </h2>
            <a href="calendar.php?view=month&month=<?php echo urlencode($nextMonthParam); ?>" class="btn-core btn-ghost p-1" title="Bulan Berikutnya">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <div>
            <a href="calendar.php?view=month&month=<?php echo date('Y-m'); ?>" class="btn-core btn-ghost font-mono" style="font-size: 0.76rem;">
                Ke Bulan Ini
            </a>
        </div>
    </div>

    <div class="bento-panel p-3">
        <!-- Month Days Header -->
        <div class="month-grid-header">
            <div>Sen</div>
            <div>Sel</div>
            <div>Rab</div>
            <div>Kam</div>
            <div>Jum</div>
            <div>Sab</div>
            <div>Min</div>
        </div>

        <div class="month-grid-body">
            <!-- Padding empty cells before day 1 -->
            <?php for ($pad = 1; $pad < $firstDayIso; $pad++): ?>
                <div class="month-cell is-empty"></div>
            <?php endfor; ?>

            <!-- Day Cells -->
            <?php foreach ($monthGridDays as $dayNum => $mCell): ?>
                <div class="month-cell <?php echo $mCell['is_today'] ? 'is-today-cell' : ''; ?>" onclick="openDateDetails('<?php echo $mCell['date']; ?>')">
                    <div class="month-cell-top">
                        <span class="month-cell-day font-mono <?php echo $mCell['is_today'] ? 'text-accent' : ''; ?>"><?php echo $dayNum; ?></span>
                        <?php if ($mCell['done_count'] > 0): ?>
                            <span class="month-cell-done font-mono" title="<?php echo $mCell['done_count']; ?> aktivitas tuntas">
                                <i class="fas fa-check text-accent"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="month-cell-indicators">
                        <?php if ($mCell['due_habits'] > 0): ?>
                            <div class="month-indicator habit-indicator" title="<?php echo $mCell['due_habits']; ?> kebiasaan jatuh tempo">
                                <span class="indicator-dot"></span>
                                <span class="indicator-text"><?php echo $mCell['due_habits']; ?> Habit</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($mCell['quests_deadline'] > 0): ?>
                            <div class="month-indicator quest-indicator" title="<?php echo $mCell['quests_deadline']; ?> tenggat misi">
                                <span class="indicator-dot danger"></span>
                                <span class="indicator-text"><?php echo $mCell['quests_deadline']; ?> Tenggat</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- CSRF Token for Quick Check-ins -->
<form id="calCsrfForm" style="display: none;"><?php echo csrf_field(); ?></form>

<!-- Quick Add Schedule Modal -->
<div class="modal fade" id="quickAddScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size: 1rem; font-weight: 600;"><i class="fas fa-plus text-accent me-2"></i> Tambah Agenda Cepat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-secondary mb-4" style="font-size: 0.85rem;">Pilih jenis aktivitas yang ingin kamu jadwalkan ke dalam kalender:</p>
                <div class="row g-3">
                    <div class="col-6">
                        <a href="habits.php?open_modal=1" id="quickAddHabitLink" class="bento-panel spotlight-card d-block p-3 text-center text-decoration-none h-100">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--accent-dim); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin: 0 auto 0.5rem auto;">
                                <i class="fas fa-check-square"></i>
                            </div>
                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">Kebiasaan Baru</div>
                            <div class="text-muted mt-1" style="font-size: 0.72rem;">Rutinitas harian &amp; mingguan</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="quests.php?open_modal=1&start_date=<?php echo urlencode($selectedDate); ?>" id="quickAddQuestLink" class="bento-panel spotlight-card d-block p-3 text-center text-decoration-none h-100">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--status-warning-dim); color: var(--status-warning); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin: 0 auto 0.5rem auto;">
                                <i class="fas fa-map-signs"></i>
                            </div>
                            <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">Misi Baru</div>
                            <div class="text-muted mt-1" style="font-size: 0.72rem;">Target &amp; proyek terikat tenggat</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Details Modal (Triggered by clicking Day Cell) -->
<div class="modal fade" id="dateDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="modalDetailDateTitle" style="font-size: 1rem; font-weight: 600;">Detail Tanggal</h5>
                    <span class="text-muted font-mono" id="modalDetailDateSub" style="font-size: 0.72rem;">Agenda &amp; Jadwal</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex flex-column gap-2 mb-3" id="modalDetailItemList">
                    <!-- Loaded dynamically via JS -->
                </div>
                <div class="p-2 d-flex flex-column gap-2 pt-3" style="border-top: 1px solid var(--border-hairline);">
                    <a href="#" id="btnCreateQuestOnDate" class="btn-core btn-primary w-100 font-mono" style="font-size: 0.8rem;">
                        <i class="fas fa-plus me-1"></i> Buat Quest di Tanggal Ini
                    </a>
                    <div class="d-flex gap-2">
                        <a href="#" id="btnExportGoogleCal" target="_blank" rel="noopener noreferrer" class="btn-core btn-outline w-100 font-mono" style="font-size: 0.75rem;">
                            <i class="fab fa-google text-warning me-1"></i> Google Calendar
                        </a>
                        <a href="#" id="btnExportIcsFile" class="btn-core btn-outline w-100 font-mono" style="font-size: 0.75rem;">
                            <i class="fas fa-download text-accent me-1"></i> Unduh .ics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll timeline to current live red line
    const liveLine = document.getElementById('timelineLiveLine');
    const container = document.getElementById('timelineScrollContainer');
    if (liveLine && container) {
        setTimeout(() => {
            const topPos = parseInt(liveLine.style.top, 10) || 0;
            container.scrollTop = Math.max(0, topPos - 180);
        }, 150);
    }

    // Toggle Jam Aktif (06:00 - 22:00) vs 24 Jam Penuh
    const defaultMode = (window.innerWidth <= 768) ? 'active' : '24h';
    const savedHoursMode = localStorage.getItem('lv_calendar_hours_mode') || defaultMode;
    applyDayTimelineFilter(savedHoursMode);

    document.getElementById('btnFilterActiveHours')?.addEventListener('click', () => applyDayTimelineFilter('active'));
    document.getElementById('btnFilterAllHours')?.addEventListener('click', () => applyDayTimelineFilter('24h'));

    // Klik Baris Jam Kosong -> Buka Quick Add Modal dengan Pre-fill Jam
    document.addEventListener('click', function(e) {
        const hourRow = e.target.closest('.timeline-hour-row');
        if (!hourRow) return;
        if (e.target.closest('.timeblock-card') || e.target.closest('button') || e.target.closest('a')) return;

        const hourStr = hourRow.getAttribute('data-hour');
        const hourNum = parseInt(hourRow.getAttribute('data-hour-num'), 10);
        if (!hourStr || isNaN(hourNum)) return;

        const nextH = (hourNum + 1) % 24;
        const endStr = String(nextH).padStart(2, '0') + ':00';
        const curDate = <?php echo json_encode($selectedDate); ?>;

        const habitLink = document.getElementById('quickAddHabitLink');
        const questLink = document.getElementById('quickAddQuestLink');
        if (habitLink) {
            habitLink.href = `habits.php?open_modal=1&start_time=${encodeURIComponent(hourStr)}&end_time=${encodeURIComponent(endStr)}`;
        }
        if (questLink) {
            questLink.href = `quests.php?open_modal=1&start_date=${encodeURIComponent(curDate)}&end_date=${encodeURIComponent(curDate)}&start_time=${encodeURIComponent(hourStr)}&end_time=${encodeURIComponent(endStr)}`;
        }

        const modalEl = document.getElementById('quickAddScheduleModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });

    // Quick Check-in Habit langsung dari Kalender
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-cal-checkin-habit');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        e.stopPropagation();

        const habitId = btn.getAttribute('data-habit-id');
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        btn.disabled = true;
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('complete_habit', '1');
        fd.append('habit_id', habitId);

        try {
            const res = await fetch('habits.php', {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data && data.success) {
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.className = 'badge-status accent font-mono';
                btn.title = 'Tuntas';

                const card = btn.closest('.timeblock-card') || btn.closest('.allday-chip');
                if (card) {
                    const titleEl = card.querySelector('.timeblock-title, .allday-title');
                    if (titleEl) {
                        titleEl.style.textDecoration = 'line-through';
                        titleEl.style.opacity = '0.6';
                    }
                }
                if (typeof LVSound !== 'undefined') LVSound.coin();
                if (window.lvHaptic) window.lvHaptic(18);
                if (window.spawnFloatingReward) {
                    const rect = btn.getBoundingClientRect();
                    window.spawnFloatingReward(rect.left + rect.width / 2, rect.top, '<i class="fas fa-check text-accent"></i> Habit Selesai!');
                }
            } else {
                window.location.reload();
            }
        } catch (err) {
            window.location.reload();
        }
    });

    // Quick Check-in Quest langsung dari Kalender
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-cal-checkin-quest');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        e.stopPropagation();

        const questId = btn.getAttribute('data-quest-id');
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';

        btn.disabled = true;
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('complete_quest', '1');
        fd.append('quest_id', questId);

        try {
            const res = await fetch('quests.php', {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data && data.success) {
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.className = 'badge-status accent font-mono';
                btn.title = 'Tuntas';

                const card = btn.closest('.timeblock-card') || btn.closest('.allday-chip');
                if (card) {
                    const titleEl = card.querySelector('.timeblock-title, .allday-title');
                    if (titleEl) {
                        titleEl.style.textDecoration = 'line-through';
                        titleEl.style.opacity = '0.6';
                    }
                }
                if (typeof LVSound !== 'undefined') LVSound.fanfare();
                if (window.lvHaptic) window.lvHaptic(28);
                if (window.spawnFloatingReward) {
                    const rect = btn.getBoundingClientRect();
                    window.spawnFloatingReward(rect.left + rect.width / 2, rect.top, '<i class="fas fa-crown text-warning"></i> Misi Tuntas!');
                }
            } else {
                window.location.reload();
            }
        } catch (err) {
            window.location.reload();
        }
    });
});

function applyDayTimelineFilter(mode) {
    const container = document.getElementById('timelineScrollContainer');
    const grid = container ? container.querySelector('.timeline-grid') : null;
    const liveLine = document.getElementById('timelineLiveLine');
    if (!grid) return;

    localStorage.setItem('lv_calendar_hours_mode', mode);

    const btnActive = document.getElementById('btnFilterActiveHours');
    const btnAll = document.getElementById('btnFilterAllHours');
    if (btnActive && btnAll) {
        btnActive.classList.toggle('active', mode === 'active');
        btnAll.classList.toggle('active', mode === '24h');
    }

    const cards = Array.from(grid.querySelectorAll('.timeblock-card'));
    let hoursSet = [];

    if (mode === 'active') {
        const baseActive = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22];
        const extraHours = new Set(baseActive);

        cards.forEach(card => {
            const sMin = parseInt(card.dataset.startMin, 10) || 0;
            const eMin = parseInt(card.dataset.endMin, 10) || sMin + 30;
            const hStart = Math.floor(sMin / 60);
            const hEnd = Math.floor(Math.max(sMin, eMin - 1) / 60);
            for (let h = hStart; h <= hEnd; h++) {
                if (h >= 0 && h < 24) extraHours.add(h);
            }
        });
        hoursSet = Array.from(extraHours).sort((a, b) => a - b);
    } else {
        hoursSet = Array.from({ length: 24 }, (_, i) => i);
    }

    // Update hour rows
    const hourRows = grid.querySelectorAll('.timeline-hour-row');
    hourRows.forEach(row => {
        const h = parseInt(row.dataset.hourNum, 10);
        const idx = hoursSet.indexOf(h);
        if (idx !== -1) {
            row.style.display = '';
            row.style.top = (idx * 60) + 'px';
        } else {
            row.style.display = 'none';
        }
    });

    // Update grid height
    grid.style.height = (hoursSet.length * 60) + 'px';

    // Reposition cards
    cards.forEach(card => {
        const sMin = parseInt(card.dataset.startMin, 10) || 0;
        const durMin = parseInt(card.dataset.durMin, 10) || 30;
        const h = Math.floor(sMin / 60);
        const idx = hoursSet.indexOf(h);
        if (idx !== -1) {
            card.style.display = '';
            card.style.top = (idx * 60 + (sMin % 60)) + 'px';
            card.style.height = Math.max(30, durMin) + 'px';
        } else {
            card.style.display = 'none';
        }
    });

    // Reposition live line
    if (liveLine) {
        const nowMin = parseInt(liveLine.dataset.nowMin, 10) || 0;
        const nowH = Math.floor(nowMin / 60);
        const nowIdx = hoursSet.indexOf(nowH);
        if (nowIdx !== -1) {
            liveLine.style.display = '';
            liveLine.style.top = (nowIdx * 60 + (nowMin % 60)) + 'px';
        } else {
            liveLine.style.display = 'none';
        }
    }
}

// Cache of all habits & quests for client-side modal popup
const allHabitsData = <?php echo json_encode($userHabits); ?>;
const allQuestsData = <?php echo json_encode($userQuests); ?>;

function openDateDetails(dateStr) {
    const listEl = document.getElementById('modalDetailItemList');
    const titleEl = document.getElementById('modalDetailDateTitle');
    const subEl = document.getElementById('modalDetailDateSub');
    const btnQuest = document.getElementById('btnCreateQuestOnDate');
    const btnGoogle = document.getElementById('btnExportGoogleCal');
    const btnIcs = document.getElementById('btnExportIcsFile');

    if (!listEl) return;

    titleEl.textContent = dateStr;
    subEl.textContent = 'Agenda & Komitmen Lifevuww';

    btnQuest.href = `quests.php?open_modal=1&start_date=${encodeURIComponent(dateStr)}&end_date=${encodeURIComponent(dateStr)}`;
    btnIcs.href = `calendar.php?export_ics=1&date=${encodeURIComponent(dateStr)}`;

    // Google Calendar URL Template
    const cleanDate = dateStr.replace(/-/g, '');
    const gCalText = encodeURIComponent(`Lifevuww Tactical Agenda (${dateStr})`);
    const gCalUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${gCalText}&dates=${cleanDate}/${cleanDate}&details=${encodeURIComponent('Agenda disiplin & petualangan RPG dari Lifevuww.')}`;
    btnGoogle.href = gCalUrl;

    // XSS-Safe DOM construction
    while (listEl.firstChild) {
        listEl.removeChild(listEl.firstChild);
    }

    let found = 0;

    // Filter quests for this date
    allQuestsData.forEach(q => {
        const s = q.start_date || dateStr;
        const e = q.end_date || s;
        if (dateStr >= s && dateStr <= e) {
            found++;
            const hasTime = q.start_time && q.end_time;
            const timeTxt = hasTime ? `${q.start_time.substring(0, 5)} - ${q.end_time.substring(0, 5)}` : 'Sepanjang Hari';

            const row = document.createElement('div');
            row.className = 'data-row p-2';
            row.style.background = 'var(--bg-surface-hover)';
            row.style.borderRadius = '6px';

            const main = document.createElement('div');
            main.className = 'data-main';

            const iconBox = document.createElement('div');
            iconBox.style.cssText = 'width: 26px; height: 26px; border-radius: 4px; background: var(--status-warning-dim); color: var(--status-warning); display: flex; align-items: center; justify-content: center; font-size: 0.75rem;';
            const iconI = document.createElement('i');
            iconI.className = 'fas fa-map-signs';
            iconBox.appendChild(iconI);

            const textWrap = document.createElement('div');
            const titleDiv = document.createElement('div');
            titleDiv.className = 'data-title';
            titleDiv.style.fontSize = '0.82rem';
            titleDiv.textContent = q.quest_name || '';

            const metaDiv = document.createElement('div');
            metaDiv.className = 'data-meta font-mono';
            metaDiv.style.fontSize = '0.68rem';

            const badgeTime = document.createElement('span');
            badgeTime.className = 'badge-status font-mono';
            badgeTime.textContent = timeTxt;

            const badgeCat = document.createElement('span');
            badgeCat.className = 'badge-status';
            badgeCat.textContent = q.category || 'General';

            metaDiv.appendChild(badgeTime);
            metaDiv.appendChild(badgeCat);
            textWrap.appendChild(titleDiv);
            textWrap.appendChild(metaDiv);

            main.appendChild(iconBox);
            main.appendChild(textWrap);

            const actions = document.createElement('div');
            actions.className = 'data-actions';
            const expBadge = document.createElement('span');
            expBadge.className = 'badge-status accent font-mono';
            expBadge.textContent = '+' + (q.exp_reward || 10) + ' EXP';
            actions.appendChild(expBadge);

            row.appendChild(main);
            row.appendChild(actions);
            listEl.appendChild(row);
        }
    });

    if (found === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'text-center py-4 text-muted';
        emptyDiv.style.fontSize = '0.82rem';
        const emptyI = document.createElement('i');
        emptyI.className = 'fas fa-calendar-check me-1';
        emptyDiv.appendChild(emptyI);
        emptyDiv.appendChild(document.createTextNode(' Tidak ada misi khusus pada tanggal ini.'));
        listEl.appendChild(emptyDiv);
    }

    const modalEl = document.getElementById('dateDetailsModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}
</script>

<?php require_once("../includes/footer.php"); ?>
