# Desain Widget Home Screen Android Lifevuww (Opsi C: Kalender Bulanan + Kartu Streak Harian)

Tanggal: 2026-11-23
Cakupan proyek: `C:\Lifevuww\lifevuww-deploy` (Web/PHP) dan `C:\Lifevuww-App` (Capacitor Android APK `com.mivuww.lifevuww`)

## 1. Tujuan & Alasan
Pengguna ingin melihat kalender beserta daftar habit, quest, dan time-block langsung dari home screen HP Android dengan tampilan yang terasa menarik dan bikin nagih seperti Duolingo, tanpa biaya hosting tambahan (tetap memakai hosting gratis `https://lifevuww.42web.io`).

Karena server `42web.io` menerapkan pengecekan browser JS anti-bot (`__test` / `aes.js`) pada request HTTP di luar browser, sinkronisasi widget dilakukan dari WebView APK yang memang sudah terautentikasi ke penyimpanan lokal Android (`SharedPreferences`), bukan dengan polling HTTP background.

## 2. Goals, Non-Goals, dan Anti-Goals

### Goals
- **Dua widget Android native (Opsi C):**
  1. **Widget Kalender Bulanan + Agenda Hari Ini** (`4x3` minimum, resizable): grid bulan 7x6 (Sen-Min) lengkap dengan penanda tanggal yang memiliki habit jatuh tempo atau quest aktif, sorotan Emerald pada hari ini, tombol ganti bulan (`<` / `>` / kembali ke hari ini), dan strip ringkasan time-block hari ini di bagian bawah.
  2. **Widget Streak Companion + Habit & Quest** (`4x3` minimum, resizable): sprite pixel-art companion aktif milik user, angka streak besar bergaya Duolingo, kalimat dorongan dinamis sesuai progres harian, bar progres 5 segmen, dan daftar scrollable habit jatuh tempo + quest aktif hari ini lengkap dengan kotak cek bergaya tombol fisik dan info reward EXP.
- **Tampilan Anti-Slop (Material 3 + Taste Skill):** latar obsidian matte (`#13151a` / `#1a1d24`), satu warna aksen Emerald terkunci (`#10b981`, border lip `#047857`), sudut terkunci (`24dp` widget, `12dp` kartu dalam, `8dp` tombol/kontrol), tanpa emoji, tanpa gradien neon/pelangi, ikon vektor seragam.
- **Sinkronisasi 100% gratis & tetap akurat saat ganti hari:** setiap kali aplikasi dibuka atau ada aksi di web (`habits.php`, `quests.php`, `dashboard.php`, `calendar.php`, `skills.php`, `focus_rift.php`, dll.), WebView mengirim snapshot data terbaru ke Android. Widget juga mengevaluasi ulang tanggal hari ini di sisi perangkat jika melewati tengah malam sebelum aplikasi dibuka lagi.
- **Interaksi sentuh cepat & aman:** tap tanggal di Widget Kalender membuka `calendar.php?view=day&date=YYYY-MM-DD`; tap baris atau kotak cek di Widget Streak membuka APK dan menjalankan check-in / aksi lewat sesi WebView yang sah (CSRF-safe).

### Non-Goals
- Polling HTTP langsung dari `WorkManager` / `HttpURLConnection` ke `lifevuww.42web.io` (tidak kompatibel dengan gerbang JS `__test` pada hosting gratis saat ini).
- Membuat atau mengedit form habit/quest baru langsung di dalam Android `RemoteViews`.
- Dukungan iOS WidgetKit (target hanya proyek Android `C:\Lifevuww-App`).

### Anti-Goals
- Mengubah state database lewat URL `GET` tanpa CSRF atau melakukan check-in lokal di HP tanpa sinkron ke server (berisiko EXP/streak tidak sinkron atau ganda).
- Menimpa atau mengacaukan halaman web desktop/mobile yang sudah ada.
- Menampilkan widget kosong permanen setelah force-stop atau pergantian hari tanpa fallback status yang jelas.

## 3. Batasan Teknis & Fondasi Kode yang Ada
- **Web (`C:\Lifevuww\lifevuww-deploy`):** PHP 8.1+, MySQL/MariaDB, sesi persisten 30 hari (`PHPSESSID` + `lv_remember` di `config/database.php`), semua halaman terautentikasi memuat `includes/header.php` dan `includes/footer.php`.
- **Android (`C:\Lifevuww-App`):** Capacitor 8 (`@capacitor/android ^8.5.3`), Java (`MainActivity.java`), `minSdkVersion 24`, `compileSdkVersion 36`, `targetSdkVersion 36`.
- **Persistensi cookie APK:** `MainActivity.java` saat ini masih `BridgeActivity` polos; implementasi harus sekalian menerapkan `CookieManager.getInstance().flush()` pada `onPageFinished` dan `onPause` sesuai `docs/apk-cookie-persistence.md` agar sesi login tidak hilang saat widget membuka aplikasi.
- **Sprite Companion:** 20 file PNG pixel art sudah ada di `C:\Lifevuww\lifevuww-deploy\assets\images\companions\*.png` (5 companion x 4 tahap: dasar, `_hatchling`, `_awakened`, `_sovereign`), berukuran ~1-2 KB per file. Untuk widget Android agar tajam dan tampil instan offline, seluruh sprite dibundel ke `res/drawable-nodpi/` dan di-scale nearest-neighbor (`filter = false`) saat dirender ke `RemoteViews`.

## 4. Arsitektur & Alur Data

```
[Web PHP: includes/footer.php + widget_snapshot.php]
   |  (1) Render / fetch snapshot JSON di WebView terautentikasi
   v
[Capacitor Plugin: LvWidgetPlugin (Java)]
   |  (2) Simpan JSON ke SharedPreferences ("lv_widget_prefs")
   |      + jadwalkan alarm midnight refresh
   v
[Android Home Screen Widgets]
   |-- CalendarWidgetProvider (Grid 7x6 + Strip Time-Block)
   |-- StreakTasksWidgetProvider + RemoteViewsService (List Habit & Quest)
   |
   |  (3) User tap item / tanggal / kotak cek
   v
[MainActivity (Java)]
   |-- Terima Intent extra (route URL + optional native action)
   |-- Simpan pending native action sementara di memori MainActivity
   |-- Arahkan WebView ke URL target (calendar / habits / quests)
   v
[WebView JS di halaman tujuan]
   |-- Ambil pending native action dari LvWidgetPlugin (khusus APK)
   |-- Eksekusi aksi POST dengan csrf_token halaman yang valid
   |-- Setelah selesai, otomatis sync snapshot terbaru kembali ke widget
```

## 5. Kontrak Data Snapshot (`lv_widget_snapshot`)

Setiap halaman yang dimuat oleh user terlogin (lewat `includes/header.php` atau `includes/footer.php`) menyisipkan data ringan atau memanggil endpoint terautentikasi `pages/widget_snapshot.php` (GET JSON di dalam WebView yang sama-origin dan sudah lolos cookie `42web.io`).

**Alasan memilih endpoint `pages/widget_snapshot.php` dibanding inline JSON di setiap halaman PHP:** tidak memperlambat render HTML normal di browser desktop, tetap satu origin/session dengan WebView, dan bisa dipanggil ulang kapan saja oleh JS setelah AJAX stepper/pin/complete/drag selesai tanpa reload penuh.

### Skema JSON Snapshot (versi 1)
```json
{
  "schema_version": 1,
  "synced_at": "2026-11-23T01:05:00+07:00",
  "synced_date": "2026-11-23",
  "logged_in": true,
  "user": {
    "username": "Fahmi",
    "level": 5,
    "streak_max": 12,
    "active_companion": "chrono_owl",
    "companion_stage_key": "awakened",
    "companion_sprite": "chrono_owl_awakened",
    "companion_bond_level": 4
  },
  "today_summary": {
    "done_count": 3,
    "total_count": 5
  },
  "habits": [
    {
      "id": 12,
      "name": "Hidrasi 3 Botol Air",
      "category": "Health",
      "exp": 10,
      "target_count": 3,
      "current_count": 2,
      "completed_today": false,
      "due_today": true,
      "schedule_type": "daily",
      "schedule_value": "1",
      "last_completed_at": "2026-11-22 20:10:00",
      "start_time": "08:00",
      "end_time": "09:00",
      "is_pinned": true,
      "sort_order": 0
    }
  ],
  "quests": [
    {
      "id": 44,
      "name": "Lari 5K Tanpa Henti",
      "category": "Physical",
      "exp": 25,
      "start_date": "2026-11-18",
      "end_date": "2026-11-26",
      "start_time": "19:00",
      "end_time": "20:00",
      "is_pinned": true,
      "is_boss": true,
      "completed_today": false,
      "subtasks_done": 1,
      "subtasks_total": 3,
      "sort_order": 0
    }
  ]
}
```

Catatan skema:
- `today_summary` (`done_count` dan `total_count`) hanya menghitung **habit yang jatuh tempo hari ini**.
- `quests[]` menyertakan quest yang sedang `In Progress` **ditambah** quest yang baru diselesaikan hari ini (`quest_logs` dengan `DATE(COALESCE(completed_at, completion_date, created_at)) = CURDATE()`, `status = 'Completed'`), ditandai dengan `"completed_today": true`.

### Aturan Sinkronisasi & Pergantian Hari (Midnight Rollover Lokal)
1. JS di `includes/footer.php` memeriksa apakah `window.Capacitor?.Plugins?.LvWidget` tersedia. Pada browser web biasa, script menjadi no-op.
2. Pada initial load halaman login-protected (dan setelah aksi AJAX seperti stepper habit, complete/undo, pin, atau Focus Rift selesai), JS memanggil `fetch('../pages/widget_snapshot.php')` (atau path relatif sesuai lokasi) lalu mengirim hasilnya ke `LvWidget.saveSnapshot({ json })`.
3. Jika user logout (`logout.php` atau halaman `login.php`), JS memanggil `LvWidget.clearSnapshot()` agar widget langsung menampilkan state "Masuk ke Lifevuww".
4. **Evaluasi ganti hari di Android:** Sebelum merender, helper jadwal di Java membandingkan `synced_date` dengan `LocalDate.now()` perangkat:
   - Jika `synced_date` sama dengan hari ini, widget memakai `completed_today`, `current_count`, dan `done_count` apa adanya.
   - Jika sudah lewat tengah malam (`LocalDate.now() > synced_date`), widget menghitung ulang habit mana yang jatuh tempo untuk tanggal hari ini berdasarkan `schedule_type`, `schedule_value`, dan `last_completed_at` (memakai aturan yang identik dengan `isHabitDueToday` di `config/game.php`), menganggap status hari baru masih belum selesai (`0 / total`), serta tetap menampilkan agenda quest yang rentang `start_date..end_date`-nya mencakup hari ini. Begitu app dibuka, data resmi server langsung menggantikan snapshot lokal.
5. Android menjadwalkan `AlarmManager` (non-exact `setInexactRepeating` / daily rollover broadcast tengah malam) untuk me-refresh kedua widget saat tanggal berubah meski app belum dibuka.

## 6. Spesifikasi Widget 1: Kalender Bulanan + Agenda Hari Ini

### Layout & Visual (`widget_calendar.xml`)
- **Header:**
  - Kiri: Nama bulan & tahun (contoh: `November 2026`, klik untuk kembali ke bulan berjalan) + sub-label jumlah agenda hari ini (contoh: `4 agenda hari ini`).
  - Kanan: dua tombol ikon taktil (`<` bulan lalu, `>` bulan depan) untuk navigasi bulan offline.
- **Grid Kalender (7 kolom x 6 baris):**
  - Dibangun dari 6 `LinearLayout` horizontal x 7 cell statis (`@id/cal_cell_0..41`) agar kompatibel penuh di semua launcher Android tanpa delay `RemoteViewsService` untuk grid.
  - Header hari: `S S R K J S M` (Senin sampai Minggu).
  - Tiap sel tanggal menampilkan angka tanggal dan 1 indikator titik/garis kecil (`#10b981`) di bawahnya jika pada tanggal tersebut ada minimal 1 habit jatuh tempo (berdasarkan `isHabitDueOnDate`) atau quest aktif (`start_date <= date <= end_date`).
  - Tanggal hari ini disorot dengan kotak solid Emerald `#10b981` + teks gelap kontras tinggi (`#042f1e`). Tanggal di luar bulan aktif diredupkan.
- **Strip Bawah (Agenda / Time-Block Hari Ini):**
  - Menampilkan 1 baris agenda teratas hari ini (prioritas: time-block yang sedang `LIVE` saat ini, atau time-block berikutnya, atau ringkasan jadwal hari ini seperti `08:00 Deep Work • 19:00 Lari 5K`). Jika belum ada jadwal berjam, tampilkan jumlah habit & quest yang aktif hari ini.
  - Ketuk strip bawah membuka `pages/calendar.php?view=day&date=YYYY-MM-DD`.
  - Ketuk salah satu tanggal pada grid membuka `pages/calendar.php?view=day&date=<tanggal_diklik>`.

## 7. Spesifikasi Widget 2: Companion Streak + Habit & Quest

### Layout & Visual (`widget_streak_tasks.xml` + `widget_task_row.xml`)
- **Header Duolingo-Energy:**
  - Kiri: kotak sprite companion `48x48dp` (`#1a1d24`, radius `12dp`, border hairline) memuat bitmap sprite companion aktif (`companion_sprite`) yang di-scale tanpa blur (`createScaledBitmap(..., false)`).
  - Tengah: ikon api vektor + angka `streak_max` besar (`22sp` bold monospace/tabular) dan 1 baris copy dinamis di bawahnya (berdasarkan `today_summary` habit jatuh tempo):
    - `total_count == 0` dan tidak ada quest: `"Belum ada tugas aktif"`
    - `total_count == 0` dan ada quest aktif: `"0 habit hari ini · X misi aktif"`
    - `done_count == total_count`: `"Semua tuntas! Streak aman"`
    - `done_count == 0`: `"Mulai 1 langkah hari ini"`
    - sisa `1` habit: `"1 langkah lagi tuntas!"`
    - selain itu: `"Jaga streak · X lagi tuntas"`
  - Kanan: pill taktil Emerald berisi rasio `done / total` habit jatuh tempo hari ini (contoh: `3 / 5`), tap membuka `pages/dashboard.php`.
- **Progress Bar 5 Segmen:**
  - 5 blok horizontal berjajar (`gap 4dp`, tinggi `7dp`, radius `4dp`). Jumlah blok terisi = `round((done / max(1, total)) * 5)` (minimal 1 jika `done > 0`, penuh 5 hanya jika `done == total`).
- **Daftar Scrollable Habit & Quest (`ListView` via `StreakTasksWidgetService`):**
  - Urutan daftar: habit/quest yang belum `completed_today` ditaruh di atas (mengikuti `is_pinned DESC`, lalu `sort_order ASC`, habit dan quest tampil bersama); item yang sudah `completed_today` (baik habit maupun quest yang selesai hari ini) tetap tampil di bagian bawah dengan tampilan dicoret dan redup sampai pergantian hari.
  - Setiap baris menampilkan:
    - Kiri: kotak centang fisik (`22x22dp`, radius `8dp`; kosong jika belum tuntas, terisi Emerald + centang jika sudah tuntas; untuk habit multi-count e.g. `2/3`, kotak masih terbuka sampai mencapai target).
    - Tengah: judul item (1 baris ellipsize) + sub-teks ringkas (`Kebiasaan · 2/3` atau `Misi · 19:00` / `Misi · H-3`).
    - Kanan: badge EXP ringkas (`+10`) atau `Tuntas`.
  - **Interaksi sentuh baris (`PendingIntent` template):**
    - Tap pada **kotak centang** (khusus habit belum tuntas): meluncurkan `MainActivity` membawa native action `{ type: "complete_or_increment_habit", id: habitId }`.
    - Tap pada **area teks baris** (atau baris quest / habit yang sudah tuntas): membuka `pages/habits.php?focus_habit=<id>` atau `pages/quests.php?focus_quest=<id>` agar user bisa cek sub-task, undo, atau selesaikan quest dengan aman.

## 8. Mekanisme Deep Link & One-Tap Check-In yang Aman
1. `CalendarWidgetProvider` dan `StreakTasksWidgetProvider` mengirim `Intent` ke `MainActivity` dengan extra `lv_route` (path relatif tervalidasi seperti `pages/calendar.php?view=day&date=2026-11-23` atau `pages/habits.php`) dan opsional `lv_widget_action` JSON (untuk aksi 1-tap centang habit).
2. Di `MainActivity` (`onCreate` dan `onNewIntent`), extra disimpan di field internal plugin (`LvWidgetPlugin`) dan WebView diarahkan ke `https://lifevuww.42web.io/<lv_route>` (hanya jika `lv_route` lolos allowlist prefix `pages/`).
3. Di `pages/habits.php`, saat halaman selesai dimuat di APK, JS memanggil `LvWidget.consumePendingAction()`.
   - Karena aksi hanya dikirim lewat Java native memory (bukan query string URL publik), link eksternal tidak bisa memicu mutasi CSRF.
   - Jika ada `{ type: "complete_or_increment_habit", id }`, JS mencari baris `.data-row[data-id="<id>"]` pada halaman `habits.php`:
     - Jika punya `.btn-stepper-inc` yang aktif, klik `.btn-stepper-inc` (menjalankan AJAX increment dengan `csrf_token` asli).
     - Jika punya tombol `button[name="complete_habit"]`, klik / submit form aslinya (menjalankan `completeHabit()` di server dengan `csrf_token` asli + muncul confetti/toast RPG!).
     - Setelah aksi berhasil dan snapshot tersinkron kembali, widget di home screen langsung terupdate otomatis.
4. Jika sesi ternyata belum login (diarahkan ke `login.php`), `pendingAction` dibiarkan aman atau dibersihkan setelah login berhasil, dan widget menampilkan state prompt login bila belum ada snapshot.

## 9. State Kosong, Error, & Belum Login
- **Belum ada snapshot / sudah logout:** kedua widget menampilkan panel matte dengan ikon Lifevuww, judul `"Masuk ke Lifevuww"`, subteks `"Buka aplikasi untuk menyinkronkan jadwal dan rutinitas"`, dan tombol tap ke `MainActivity`.
- **Semua tugas hari ini selesai:** daftar di Widget 2 tetap menampilkan item yang sudah dicentang (dicoret/redup) dan copy header berubah ke `"Semua tuntas! Streak aman"`, bar 5 segmen penuh menyala Emerald.
- **Belum ada habit atau quest aktif:** Widget 2 menampilkan pesan ringkas `"Belum ada rutinitas hari ini"` dengan tombol `"Tambah"` yang membuka `habits.php?open_modal=1`.
- **Widget di-resize lebih kecil:** `ListView` di Widget 2 tetap bisa di-scroll; strip agenda di Widget 1 memakai `singleLine` + `ellipsize="end"` agar tidak terpotong jelek.

## 10. Keputusan Desain & Hal yang Diserahkan ke Implementer
- **Java di Android (`C:\Lifevuww-App`):** implementasi native menggunakan Java murni mengikuti `MainActivity.java` dan `capacitor.config` yang sudah ada agar tidak perlu menambah konfigurasi plugin Kotlin di Gradle.
- **Asset bitmap & vector:** 20 sprite companion disalin ke `res/drawable-nodpi/` dengan nama lowercase valid Android (`companion_<key>.png` atau `<companion>_<stage>.png`); ikon kalender, panah, api, centang memakai Android Vector Drawables (`ic_lv_*.xml`) meniru ketebalan garis Tabler Icons (`2dp` stroke).
- **Detail yang diserahkan ke builder:** penentuan `minWidth`/`minHeight`/`targetCellWidth`/`targetCellHeight` di XML `appwidget-provider`, layout exact padding/spasi per breakpoint ukuran widget, dan struktur internal helper scheduler `AlarmManager` tengah malam beserta unit test Java/PHP-nya.
