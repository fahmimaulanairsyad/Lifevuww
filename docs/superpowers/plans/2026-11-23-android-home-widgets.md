# Android Home Screen Widgets (Calendar & Streak/Tasks) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build two Duolingo-motivated Android home screen widgets (Monthly Calendar + Today's Agenda, and Companion Streak + Habits/Quests) in `C:\Lifevuww-App` fed by a free WebView-to-SharedPreferences sync bridge from `C:\Lifevuww\lifevuww-deploy`.

**Architecture:** Authenticated PHP endpoint `pages/widget_snapshot.php` outputs a JSON snapshot (`today_summary` counts due habits only; `quests[]` includes `In Progress` + `completed_today` from `quest_logs`). `includes/footer.php` pushes that snapshot into Android `SharedPreferences` via Capacitor plugin `LvWidgetPlugin`, while `WidgetScheduleHelper` evaluates midnight rollover and multi-month calendar markers offline in Java, and `MainActivity` routes widget taps / native pending check-in actions back into the CSRF-protected WebView.

**Tech Stack:** PHP 8.1+ (PDO), Vanilla JS, Capacitor 8 (`@capacitor/android ^8.5.3`), Android Java (`minSdkVersion 24`, `compileSdkVersion 36`), Android `AppWidgetProvider`, `RemoteViewsService`, JUnit 4.

**Spec:** [2026-11-23-android-home-widgets-design.md](file:///C:/Lifevuww/lifevuww-deploy/docs/superpowers/specs/2026-11-23-android-home-widgets-design.md)

## Global Constraints
- Hosting `https://lifevuww.42web.io` enforces `__test` / `aes.js` anti-bot JS check: no background `HttpURLConnection` polling; all server reads/mutations happen inside WebView.
- `today_summary` (`done_count`, `total_count`), 5-segment progress bar, and `"Semua tuntas! Streak aman"` count **due habits today only**.
- Completed-today quests remain in `quests[]` with `"completed_today": true` and render crossed out/dimmed at the bottom of Widget 2 until midnight.
- Visuals follow Material 3 + Taste Skill: obsidian `#13151a` / `#1a1d24`, single Emerald accent `#10b981` + lip `#047857`, corners `24dp / 12dp / 8dp`, zero emojis, zero rainbow colors, bundled nearest-neighbor companion PNGs.
- Android code is pure Java in `com.mivuww.lifevuww` (`C:\Lifevuww-App\android`).

## Review Focus
- `interval_days` / `interval_weeks` / `interval_months` habit due-date calculation in Java must match `isHabitDueToday()` in `config/game.php` when `last_completed_at` is null or past.
- Midnight rollover in Java when `LocalDate.now() > synced_date`: must reset `completed_today` / `done_count` to `0` locally without waiting for app launch.
- Cold-start deep link from widget tap when `MainActivity` was killed: `lv_route` and `lv_widget_action` must survive Capacitor bridge initialization and `CookieManager.flush()` must preserve session.
- `lv_route` allowlist in `MainActivity`: reject any non-`pages/` route so external intents cannot redirect WebView to arbitrary URLs.
- `0` due habits + `1+` active quests in Widget 2: header caption shows `"0 habit hari ini · X misi aktif"` instead of `"Belum ada tugas aktif"`.

---

### Task 1: Web Snapshot Endpoint & Payload Builder (`C:\Lifevuww\lifevuww-deploy`)

**Files:**
- Create: `C:\Lifevuww\lifevuww-deploy\pages\widget_snapshot.php`
- Test: `C:\Lifevuww\lifevuww-deploy\tests\widget_snapshot_test.php`

**Interfaces:**
- Produces: `build_widget_snapshot_payload(array $user, array $habits, array $activeQuests, array $completedTodayQuests, array $companionMeta, string $todayDate): array`
- Produces: `GET /pages/widget_snapshot.php` -> HTTP 200 `application/json` (authenticated via `enforce_login()`).

- [ ] **Step 1: Write the failing PHP unit test `tests/widget_snapshot_test.php`**

```php
<?php
require_once __DIR__ . '/../config/game.php';
require_once __DIR__ . '/../pages/widget_snapshot.php';

$user = ['username' => 'Fahmi', 'exp' => 450, 'active_companion' => 'chrono_owl', 'companion_exp' => 240];
$habits = [
    ['habit_id' => 1, 'habit_name' => 'Minum', 'category' => 'Health', 'exp_reward' => 5, 'target_count' => 3, 'current_count' => 1, 'completed_today' => 0, 'streak' => 12, 'schedule_type' => 'daily', 'schedule_value' => '1', 'last_completed_at' => null, 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_pinned' => 1, 'sort_order' => 0],
    ['habit_id' => 2, 'habit_name' => 'Baca', 'category' => 'Intelligence', 'exp_reward' => 5, 'target_count' => 1, 'current_count' => 1, 'completed_today' => 1, 'streak' => 4, 'schedule_type' => 'daily', 'schedule_value' => '1', 'last_completed_at' => '2026-11-23 07:00:00', 'start_time' => null, 'end_time' => null, 'is_pinned' => 0, 'sort_order' => 1],
];
$activeQuests = [
    ['quest_id' => 10, 'quest_name' => 'Lari 5K', 'category' => 'Physical', 'exp_reward' => 15, 'difficulty' => 'Hard', 'start_date' => '2026-11-18', 'end_date' => '2026-11-26', 'start_time' => '19:00:00', 'end_time' => '20:00:00', 'is_pinned' => 1, 'subtasks' => '[{"title":"A","done":true},{"title":"B","done":false}]', 'sort_order' => 0],
];
$doneQuests = [
    ['quest_id' => 11, 'quest_name' => 'Kuis JS', 'category' => 'Intelligence', 'exp_reward' => 10, 'difficulty' => 'Easy', 'start_date' => '2026-11-20', 'end_date' => '2026-11-23', 'start_time' => null, 'end_time' => null, 'is_pinned' => 0, 'subtasks' => null, 'sort_order' => 1],
];

$payload = build_widget_snapshot_payload($user, $habits, $activeQuests, $doneQuests, ['stage' => 'Awakened', 'bond_level' => 4], '2026-11-23');
assert($payload['schema_version'] === 1);
assert($payload['today_summary']['done_count'] === 1 && $payload['today_summary']['total_count'] === 2);
assert($payload['user']['streak_max'] === 12);
assert($payload['user']['companion_sprite'] === 'chrono_owl_awakened');
assert(count($payload['quests']) === 2 && $payload['quests'][0]['completed_today'] === false && $payload['quests'][1]['completed_today'] === true);
echo "PASS: widget_snapshot_test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php C:\Lifevuww\lifevuww-deploy\tests\widget_snapshot_test.php`
Expected: FAIL (`Failed to open stream: No such file or directory`).

- [ ] **Step 3: Implement `build_widget_snapshot_payload` and HTTP handler in `C:\Lifevuww\lifevuww-deploy\pages\widget_snapshot.php`**
  - Guard HTTP execution with `if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? ''))` so unit tests can `require_once` without triggering `enforce_login()`.
  - Query `users`, `habits`, `quests WHERE status = 'In Progress'`, and `quests` joined with `quest_logs WHERE status = 'Completed' AND DATE(COALESCE(completed_at, completion_date, created_at)) = CURDATE()`.
  - Map `companion_sprite` to `<active_companion>_<lowercase_stage>` (`hatchling`, `awakened`, `sovereign`, or base key).

- [ ] **Step 4: Run test and syntax check**

Run: `php -l C:\Lifevuww\lifevuww-deploy\pages\widget_snapshot.php; php C:\Lifevuww\lifevuww-deploy\tests\widget_snapshot_test.php`
Expected: `No syntax errors detected` and `PASS: widget_snapshot_test`.

---

### Task 2: WebView Sync & Safe One-Tap Check-In Bridge (`C:\Lifevuww\lifevuww-deploy`)

**Files:**
- Modify: `C:\Lifevuww\lifevuww-deploy\includes\footer.php`
- Modify: `C:\Lifevuww\lifevuww-deploy\pages\habits.php`

**Interfaces:**
- Consumes: `window.Capacitor.Plugins.LvWidget.saveSnapshot({ json })`, `clearSnapshot()`, `consumePendingAction()`
- Produces: `window.lvSyncWidgetSnapshot()` global helper.

- [ ] **Step 1: Add `lvSyncWidgetSnapshot()` and auto-sync hooks in `includes/footer.php`**
  - No-op when `!(window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.LvWidget)`.
  - On `DOMContentLoaded` (if on `/pages/`), fetch `widget_snapshot.php` and call `LvWidget.saveSnapshot({ json: text })`.
  - On `login.php` or `register.php`, call `LvWidget.clearSnapshot()`.
  - Expose `window.lvSyncWidgetSnapshot` and call it after `persistListOrder()` and habit stepper AJAX success.

- [ ] **Step 2: Add `consumePendingAction()` handler in `pages/habits.php`**
  - On `DOMContentLoaded`, if `LvWidget.consumePendingAction` returns `{ type: 'complete_or_increment_habit', id }`, locate `.data-row[data-id="<id>"]`: click `.btn-stepper-inc:not(:disabled)` if present, else click `button[name="complete_habit"]` (uses the page's genuine `csrf_token`).

- [ ] **Step 3: Verify PHP syntax**

Run: `php -l C:\Lifevuww\lifevuww-deploy\includes\footer.php; php -l C:\Lifevuww\lifevuww-deploy\pages\habits.php`
Expected: `No syntax errors detected` on both files.

---

### Task 3: Android Native Foundation, Assets, Cookie Flush & Schedule Helper (`C:\Lifevuww-App`)

**Files:**
- Create: `C:\Lifevuww-App\android\app\src\main\res\drawable-nodpi\*.png` (20 companion sprites copied from `C:\Lifevuww\lifevuww-deploy\assets\images\companions\*.png`)
- Create: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\WidgetStorage.java`
- Create: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\WidgetScheduleHelper.java`
- Create: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\LvWidgetPlugin.java`
- Modify: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\MainActivity.java`
- Test: `C:\Lifevuww-App\android\app\src\test\java\com\mivuww\lifevuww\WidgetScheduleHelperTest.java`

**Interfaces:**
- Produces: `WidgetScheduleHelper.isHabitDueOnDate(JSONObject habit, LocalDate targetDate): boolean`
- Produces: `WidgetScheduleHelper.computeSnapshotForDate(JSONObject raw, LocalDate today): WidgetState` (handles midnight rollover reset)
- Produces: `LvWidgetPlugin` (`@CapacitorPlugin(name = "LvWidget")`) with `saveSnapshot`, `clearSnapshot`, `consumePendingAction`.

- [ ] **Step 1: Write failing JUnit test `WidgetScheduleHelperTest.java`**
  - Test `daily`, `weekly_days` (`1,3,5` on Mon vs Tue), `interval_days` (`2` days since `last_completed_at`), midnight rollover (`synced_date = 2026-11-22`, `today = 2026-11-23` resets `doneCount = 0`), and `0` due habits + `2` active quests caption.

- [ ] **Step 2: Run Gradle unit test to verify failure**

Run: `C:\Lifevuww-App\android\gradlew.bat -p C:\Lifevuww-App\android testDebugUnitTest`
Expected: FAIL (`cannot find symbol class WidgetScheduleHelper`).

- [ ] **Step 3: Copy 20 companion PNGs & implement `WidgetStorage`, `WidgetScheduleHelper`, `LvWidgetPlugin`, and `MainActivity`**
  - `MainActivity.java`: register `LvWidgetPlugin.class` before `super.onCreate()`, wrap `BridgeWebViewClient` with `CookieManager.getInstance().flush()`, validate `lv_route` starts with `pages/`, and store `lv_widget_action` in `LvWidgetPlugin` static/instance pending slot.

- [ ] **Step 4: Run Gradle unit test to verify pass**

Run: `C:\Lifevuww-App\android\gradlew.bat -p C:\Lifevuww-App\android testDebugUnitTest`
Expected: `BUILD SUCCESSFUL`.

---

### Task 4: Widget 1 — Monthly Calendar + Today's Agenda (`C:\Lifevuww-App`)

**Files:**
- Create: `C:\Lifevuww-App\android\app\src\main\res\layout\widget_calendar.xml`
- Create: `C:\Lifevuww-App\android\app\src\main\res\xml\widget_calendar_info.xml`
- Create: `C:\Lifevuww-App\android\app\src\main\res\drawable\bg_widget_obsidian.xml`, `bg_widget_slot.xml`, `bg_cal_today.xml`, `ic_lv_chevron_left.xml`, `ic_lv_chevron_right.xml`, `ic_lv_calendar.xml`
- Create: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\CalendarWidgetProvider.java`
- Modify: `C:\Lifevuww-App\android\app\src\main\AndroidManifest.xml`

- [ ] **Step 1: Create Material 3 obsidian drawables & `widget_calendar.xml` (7x6 static cell grid `cal_cell_0..41` + bottom agenda strip + logged-out state)**
- [ ] **Step 2: Implement `CalendarWidgetProvider.java`**
  - Handle `ACTION_PREV_MONTH`, `ACTION_NEXT_MONTH`, `ACTION_RESET_MONTH`, and `onUpdate` using `WidgetScheduleHelper.isHabitDueOnDate` + quest date range for markers.
  - Set `PendingIntent` on each day cell -> `MainActivity` with `lv_route = "pages/calendar.php?view=day&date=YYYY-MM-DD"`.
- [ ] **Step 3: Register `CalendarWidgetProvider` in `AndroidManifest.xml` and verify build**

Run: `C:\Lifevuww-App\android\gradlew.bat -p C:\Lifevuww-App\android assembleDebug`
Expected: `BUILD SUCCESSFUL`.

---

### Task 5: Widget 2 — Companion Streak + Habits & Quests (`C:\Lifevuww-App`)

**Files:**
- Create: `C:\Lifevuww-App\android\app\src\main\res\layout\widget_streak_tasks.xml`, `widget_task_row.xml`
- Create: `C:\Lifevuww-App\android\app\src\main\res\xml\widget_streak_tasks_info.xml`
- Create: `C:\Lifevuww-App\android\app\src\main\res\drawable\bg_tactile_emerald.xml`, `bg_check_unchecked.xml`, `bg_check_checked.xml`, `bg_seg_filled.xml`, `bg_seg_empty.xml`, `ic_lv_flame.xml`, `ic_lv_check.xml`
- Create: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\StreakTasksWidgetProvider.java`
- Create: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\StreakTasksWidgetService.java`
- Create: `C:\Lifevuww-App\android\app\src\main\java\com\mivuww\lifevuww\MidnightWidgetRefreshReceiver.java`
- Modify: `C:\Lifevuww-App\android\app\src\main\AndroidManifest.xml`

- [ ] **Step 1: Create layouts & tactile Duolingo-style drawables (`widget_streak_tasks.xml`, `widget_task_row.xml`)**
- [ ] **Step 2: Implement `StreakTasksWidgetService.java` (`RemoteViewsService` + `RemoteViewsFactory`) & `StreakTasksWidgetProvider.java`**
  - Load bundled companion PNG with `BitmapFactory` + `Bitmap.createScaledBitmap(..., false)` (nearest-neighbor).
  - Render 5-segment progress bar + dynamic Duolingo streak copy from `today_summary` (due habits only).
  - Order unfinished items first (`is_pinned DESC, sort_order ASC`), then `completed_today` habits & quests crossed out at bottom; attach `fillInIntent` for 1-tap habit check-in vs open page.
- [ ] **Step 3: Register `StreakTasksWidgetProvider`, `StreakTasksWidgetService`, and `MidnightWidgetRefreshReceiver` in `AndroidManifest.xml`**
- [ ] **Step 4: Run full verification (PHP tests + Android unit tests + APK debug build)**

Run:
`php C:\Lifevuww\lifevuww-deploy\tests\run_tests.php; php C:\Lifevuww\lifevuww-deploy\tests\widget_snapshot_test.php`
`C:\Lifevuww-App\android\gradlew.bat -p C:\Lifevuww-App\android testDebugUnitTest assembleDebug`
Expected: All PHP tests pass; Gradle `BUILD SUCCESSFUL` producing `C:\Lifevuww-App\android\app\build\outputs\apk\debug\app-debug.apk`.
