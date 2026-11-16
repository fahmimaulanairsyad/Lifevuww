<div align="center">
  <img src="assets/images/logo.svg" alt="Lifevuww Logo" width="84" height="84" />
  <h1>Lifevuww: Lima Poros</h1>
  <p><strong>Pelacak kebiasaan dan tujuan dengan petualangan merawat duniamu.</strong></p>
  <p>
    <a href="https://github.com/fahmimaulanairsyad/Lifevuww/actions/workflows/ci.yml">
      <img src="https://github.com/fahmimaulanairsyad/Lifevuww/actions/workflows/ci.yml/badge.svg" alt="CI Status" />
    </a>
    <img src="https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version" />
    <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL" />
    <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License" />
  </p>
</div>

## Ringkasan

Lifevuww adalah aplikasi web PHP untuk menghubungkan tujuan, langkah kecil, kebiasaan, misi, dan waktu fokus. Aktivitas selesai memberi EXP serta Gold. Level, pendamping, dan tantangan mingguan memberi konteks permainan pada progres yang dicatat.

Dalam dunia orisinal **Nawasela**, kamu menjadi **Perawat Poros** yang menyambung kembali lima wilayah saat Kabut Kusut menutupi jalurnya. Pilih satu tujuan, rawat satu langkah, lalu lanjutkan sesuai kemampuan. Jeda dan pemulihan adalah bagian dari perjalanan.

| Wilayah | Atribut internal | Yang dirawat |
| --- | --- | --- |
| Arsip Aksarimba | `Intelligence` | Belajar, membaca, dan pemecahan masalah. |
| Punggung Lintasura | `Physical` | Gerak dan kebugaran sesuai kemampuan, bersifat opsional. |
| Mata Air Teduhara | `Health` | Tidur, nutrisi, perawatan, dan pemulihan. |
| Lumbung Bekalara | `Wealth` | Keuangan, pekerjaan, dan rencana bekal. |
| Jembatan Temuraya | `Social` | Hubungan, komunikasi, dan komunitas. |

Nama wilayah dan istilah display tersedia di [`config/world.php`](config/world.php). Katalog ini berupa array tanpa database dan tidak otomatis mengganti semua label aplikasi. Key internal dan data lama tetap dipertahankan. Lihat [panduan dunia dan istilah](docs/world-guide.md) untuk cerita, alias gelar, pendamping, dan cara integrasinya.

Halaman Perjalanan menampilkan peta pixel art Nawasela secara langsung. Lima pulau memakai aset berbeda dan tiga tahap visual berdasarkan `skills.progress`: Tenang (0), Terbangun (1-4), Berkembang (5+). Pilih pulau untuk melihat jumlah aktivitas, tingkat, dan akses kebiasaan terkait. Ilustrasi berhenti pada tahap ketiga; tingkat atribut tetap dapat bertambah. Peta tidak memberi reward tambahan dan tidak mewajibkan perkembangan seimbang.

## Fitur dan status

| Area | Perilaku | Status |
| --- | --- | --- |
| Dasbor | Menampilkan status karakter dan hingga tiga prioritas yang belum selesai. | Tersedia. |
| Kebiasaan | Target hitungan berulang, streak, dan jadwal harian, hari tertentu, atau interval hari/minggu/bulan. | Tersedia. |
| Misi | Tenggat, tingkat kesulitan, sub-checklist, dan progres misi besar. | Tersedia. |
| Perjalanan | Tujuan, langkah tujuan, target tanggal, tautan aktivitas, dan menit fokus per tujuan. Penyelesaian kebiasaan/misi yang ditautkan memajukan langkah yang dipilih sekali. | Tersedia, termasuk tes alur database dan HTTP lokal. |
| Pemulihan | HP nol dipulihkan tanpa kehilangan EXP, level, atau pencapaian. Ajakan kembali menawarkan satu tindakan kecil yang tersedia dan dapat ditutup sampai pergantian tanggal. | Tersedia. |
| Jeda dan evaluasi | Jeda terencana melindungi kebiasaan yang jatuh tempo dan memperpanjang tenggat misi sesuai hari yang beririsan. Evaluasi merangkum aktivitas, mood, serta energi. | Tersedia. |
| Kalender dan fokus | Rentang jam kegiatan, agenda, sesi fokus 15/25/45 menit, dan pilihan audio prosedural. | Tersedia. |
| Jurnal | Catatan refleksi, mood/energi, tag, pencarian, serta ringkasan catatan. | Tersedia. |
| Ekspedisi Poros (Dungeon) | Kapasitas ditetapkan saat raid dibuka dan dipakai sampai ekspedisi mingguan berakhir. Skala 35%-100%, minimum 100 HP, berdasarkan jumlah penyelesaian tujuh tanggal sebelumnya. | Tersedia. |
| Sahabat Poros (Companion) | Pendamping, bonus, EXP ikatan dari damage aktual dan riwayat awal pencatatan serta evolusi bertanggal. EXP ikatan masih milik karakter bersama, bukan terpisah per pendamping. | Tersedia. |
| Bakat dan hadiah | Pohon bakat, skill aktif, relik, item pemulihan, serta hadiah pribadi yang ditukar dengan Gold. | Tersedia. |
| Riwayat dan cadangan | Heatmap aktivitas, ekspor JSON/CSV, serta pemulihan data dari cadangan JSON. | Tersedia. |

Status di atas memisahkan fitur dasar dari pekerjaan penyelesaian yang masih berjalan. Perubahan narasi tidak berarti simulasi pemulihan peta wilayah sudah tersedia. README ini menjelaskan perilaku aplikasi dan arah pengembangannya, tanpa klaim hasil uji pengguna atau peningkatan produktivitas yang terukur.

## Alur penggunaan

1. Buat satu tujuan di **Perjalanan**, lalu pecah menjadi langkah yang jelas.
2. Tambahkan kebiasaan atau misi yang mendukung tujuan. Tautkan aktivitas ke langkah yang ingin dimajukan.
3. Kerjakan prioritas yang tersedia. Gunakan Ruang Fokus bila perlu; menit fokus yang terkait tujuan dicatat terpisah dari penyelesaian langkah.
4. Tinjau catatan dan sesuaikan beban. Rencanakan jeda sebelum dimulai bila kamu membutuhkan waktu berhenti.

Menautkan aktivitas ke beberapa tujuan tidak memberikan hadiah aktivitas tambahan. Tautan langkah diproses sekali untuk penyelesaian yang sesuai; pembatalan aktivitas tidak otomatis membatalkan langkah tujuan yang sudah ditandai. Physical tetap menjadi pilihan kegiatan, bukan syarat wajib untuk maju. Mood dan energi membantu refleksi tanpa menambah atribut Mental.

## Aturan progres dan pemulihan

- Level mengikuti `floor(EXP / 100) + 1`. Gelar dan bonus memakai aturan di `config/game.php`; alias naratif di `config/world.php` hanya untuk display.
- Kebiasaan yang terlewat dikenai penalti dasar 5/10/15 HP untuk Easy/Medium/Hard. Penalti dasar misi kedaluwarsa adalah 10/20/30 HP. Bonus karakter atau item dapat memengaruhi hasil akhirnya.
- Saat HP mencapai nol atau negatif, HP dipulihkan ke batas maksimum. EXP, level, dan pencapaian tetap disimpan. Pemulihan tidak mereset seluruh streak dan tidak menghapus aturan absen pada kebiasaan yang jatuh tempo.
- Streak memberi pengganda hadiah 1,2 kali pada 3-6 penyelesaian beruntun dan 1,5 kali mulai 7. Jadwal kebiasaan tetap menentukan kapan aktivitas jatuh tempo.
- Gold adalah mata uang virtual untuk item dan hadiah pribadi. Pencatatan wilayah Wealth tidak mengubah Gold menjadi uang nyata.

Ringkasan mood dan energi menjelaskan catatan pengguna. Ringkasan tersebut tidak membuktikan hubungan sebab-akibat atau memberikan diagnosis.

## Tech stack

- **Backend:** PHP 8.1+ native, PDO dan prepared statements.
- **Database:** MySQL/MariaDB, baseline SQL dan runner migrasi.
- **Frontend:** CSS, JavaScript, Bootstrap utilities, Font Awesome, Chart.js, dan GSAP.
- **Audio:** Web Audio API untuk suara fokus prosedural.
- **Keamanan:** Hash password, token CSRF, pembatasan percobaan login, escaping output, dan header CSP. Periksa konfigurasi server sebelum digunakan di hosting.

## Panduan instalasi cepat

### Prasyarat

Web server lokal (XAMPP, Laragon, atau Docker) dengan PHP 8.1+ dan MySQL.

### Langkah setup

1. **Clone repositori:**

   ```bash
   git clone https://github.com/fahmimaulanairsyad/Lifevuww.git
   ```

2. **Pindahkan ke direktori web server:**

   - XAMPP: `C:\xampp\htdocs\Lifevuww`
   - Laragon: `C:\laragon\www\Lifevuww`

3. **Konfigurasi lingkungan (`.env`):**

   Salin `.env.example` menjadi `.env`, lalu sesuaikan kredensial database:

   ```env
   DB_HOST=localhost
   DB_NAME=habit_tracker_rpg
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Setup database:**

   - Import `database.sql` melalui phpMyAdmin atau MySQL CLI sebagai baseline instalasi baru. Baseline mencatat migrasi yang sudah tercakup di dalamnya; migrasi sesudah baseline tetap diperlukan.
   - Pada instalasi baru di MySQL 8, gunakan `database.sql` karena migrasi lama 002-007 memakai sintaks `IF NOT EXISTS` khusus MariaDB.
   - Migrasi baru di folder `migrations/` dipasang otomatis saat web dibuka setelah konstanta `LV_SCHEMA_VERSION` dinaikkan.
   - Perintah berikut bersifat opsional untuk hosting/server yang memiliki akses CLI:

   ```bash
   php migrate.php
   ```

5. **Jalankan pengujian CLI:**

   ```bash
   php tests/run_tests.php
   php tests/journey_test.php
   php tests/priorities_test.php
   php tests/recovery_test.php
   php tests/migration_version_test.php
    php tests/world_test.php
    php tests/chronicle_test.php
    php tests/followthrough_db_test.php --local
    python tests/followthrough_http_test.py
   ```

   Test katalog dunia tidak membutuhkan koneksi database. Test CLI memeriksa kontrak dan logika yang dicakupnya; pemeriksaan halaman dan alur database tetap dilakukan terpisah.

Lihat [panduan deployment](docs/deployment.md) untuk inventaris file, preview, dan perlindungan data lingkungan.

Migrasi terbaru: `018_raid_capacity.sql`, sesudah tabel Journey (013), sesi fokus (014), tautan langkah (016), dan jejak companion (017). `015` tidak dipakai. Unggah runner dan semua migrasi baru bersama-sama. Tes database/HTTP hanya dijalankan pada lingkungan lokal; fixture dibatalkan/dibersihkan. Deployment lokal bukan bukti fitur sudah terpasang di hosting.

## Lisensi

Didistribusikan di bawah lisensi MIT. Dibuat oleh **Fahmi Maulana Irsyad**.
