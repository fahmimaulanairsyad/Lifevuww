# Deployment lokal yang bisa diulang

`scripts/deploy.ps1` menyalin file aplikasi dari working tree Git ke `C:\Lifevuww\lifevuww-deploy`. Windows PowerShell 5.1 dan Git harus tersedia. Perintah berikut dijalankan dari `C:\xampp\htdocs\Lifevuww`.

Preview adalah default dan tidak menulis file atau membuat direktori:

```powershell
powershell -NoProfile -File .\scripts\deploy.ps1
```

Periksa daftar `Add`, `Replace`, dan `Unchanged`, kemudian jalankan penyalinan secara eksplisit:

```powershell
powershell -NoProfile -File .\scripts\deploy.ps1 -Apply
```

Tujuan lain, misalnya untuk pengujian lokal:

```powershell
powershell -NoProfile -File .\scripts\deploy.ps1 -Destination C:\path\deployment-fixture
powershell -NoProfile -File .\scripts\deploy.ps1 -Destination C:\path\deployment-fixture -Apply
```

Parameter `-Source` dapat menunjuk root repository Git lain untuk fixture. Default sumber adalah folder induk `scripts`, sehingga lokasi terminal tidak menentukan sumber. Sumber dan tujuan harus terpisah dan tidak boleh saling berada di dalam folder lainnya.

## File pendukung dan file baru sebelum commit

Default hanya menyalin runtime. Untuk menyamakan file pendukung di folder deploy lokal, gunakan opsi berikut pada preview dan apply:

```powershell
powershell -NoProfile -File .\scripts\deploy.ps1 -IncludeSupportFiles
powershell -NoProfile -File .\scripts\deploy.ps1 -IncludeSupportFiles -Apply
```

Opsi ini menambahkan file tracked `README.md`, `docs/deployment.md`, `docs/world-guide.md`, `scripts/deploy.ps1`, `scripts/check*.py`, `scripts/generate*.py`, dan isi `tests`. Pola script Python hanya berlaku langsung di folder `scripts`. Test tetap untuk CLI. Penyalinan test membutuhkan `tests/.htaccess` yang tracked dan tersedia di sumber, dan file proteksi tersebut selalu ikut daftar salinan. Konfigurasi Apache tujuan tetap harus mengizinkan aturan `.htaccess` agar proteksi akses HTTP berlaku.

Rilis Lima Poros memerlukan migrasi 013, 014, 016, 017, dan 018 serta runner dengan marker `018_raid_capacity.sql`. Nomor 015 tidak dipakai. Salin `config/world.php` dan `includes/companion_chronicle.php` bersama perubahan halaman, jangan mengunggah halaman saja. File migrasi membuat tabel/kolom tambahan tanpa menghapus akun, EXP, atau aktivitas. Backup database hosting sebelum menjalankan migrasi di hosting. Pengujian lokal tidak otomatis memperbarui database hosting.

File baru yang belum masuk Git index hanya bisa ikut jika disebut satu per satu lewat `-AdditionalFiles`. Gunakan path relatif repository dengan `/`, tanpa wildcard atau path direktori. Untuk beberapa file, jalankan dari sesi PowerShell memakai `&` agar array diteruskan sebagai array:

```powershell
& .\scripts\deploy.ps1 -AdditionalFiles @('migrations/016_goal_activity_progress.sql', 'config/pending.php')
& .\scripts\deploy.ps1 -AdditionalFiles @('migrations/016_goal_activity_progress.sql', 'config/pending.php') -Apply
```

Contoh tersebut hanya berlaku jika kedua file memang tersedia. Opsi ini tidak memindai file untracked lain, tidak menambahkan file ke index, dan tidak membutuhkan commit. Setiap nama tetap harus masuk allowlist runtime. File pendukung tambahan juga harus masuk allowlist pendukung dan memerlukan `-IncludeSupportFiles` pada perintah yang sama. Misalnya, untuk menyertakan fixture baru yang belum tracked:

```powershell
& .\scripts\deploy.ps1 -IncludeSupportFiles -AdditionalFiles @('tests/deploy_test.ps1')
```

Tidak ada opsi `-IncludePendingFiles` yang mengambil seluruh file untracked. `.env`, upload, metadata Git, dan semua path terlarang tetap ditolak meskipun disebut dalam `-AdditionalFiles`. Nama tidak aman atau file tambahan yang hilang menghentikan proses sebelum backup maupun salinan dibuat. Selalu gunakan pilihan file yang sama pada preview dan apply setelah memeriksa hasil preview.

## Pemilihan file dan perlindungan data

- Daftar default berasal dari `git ls-files --stage -z`. Isi yang disalin berasal dari working tree, termasuk perubahan file tracked yang belum di-commit. File untracked tidak ikut kecuali namanya diberikan lewat `-AdditionalFiles` dan lolos seluruh pemeriksaan.
- Runtime yang dipilih: file PHP di root, `.htaccess`, `manifest.json`, `sw.js`, `offline.html`, serta file tracked di `assets`, `config`, `cron`, `includes`, `pages`, dan `migrations`.
- `.git` dan metadata bernama `.git*`, seluruh nama `.env*`, direktori upload/uploads, avatar/avatars, storage, data, cache, log/logs, session/sessions, dan backup/backups selalu dikecualikan di setiap tingkat. Aset bawaan aplikasi yang tracked, seperti ikon dan gambar boss, tetap ikut jika bukan di direktori yang dikecualikan.
- `docs`, `tests`, `scripts`, dan README tidak disalin secara default. `-IncludeSupportFiles` hanya membuka daftar pendukung yang disebut di atas. `.github`, lisensi, dokumen lain, script lain, dan `database.sql` di root tetap tidak disalin. Skrip tidak menjalankan SQL, PHP, migrasi, Git commit, atau push.
- `.env` tujuan tidak pernah dibuat atau ditimpa. Konfigurasi tujuan harus sudah tersedia secara terpisah. File tambahan di tujuan, termasuk upload dan perubahan lain di luar daftar runtime terpilih, tetap ada. Tidak ada penghapusan file.
- Setiap file terpilih di tujuan yang berbeda **akan diganti** oleh sumber saat `-Apply`, termasuk file pendukung bila opsi tersebut aktif. Preview dan apply mencetak peringatan beserta jumlah `Replace`. Semua versi tujuan tersebut dibackup dahulu, termasuk perubahan lokalnya. Skrip tidak membandingkan dengan baseline Git HEAD dan tidak dapat membedakan edit pengguna dari versi deploy lama. Peringatan berlaku untuk semua penggantian agar perubahan tujuan tidak terlewat. Periksa setiap `Replace` dan pindahkan edit yang masih dibutuhkan ke repository sumber sebelum apply. Backup mempertahankan versi lama, tetapi tidak mempertahankannya sebagai kode aktif.
- Symlink, junction, Git submodule pada path runtime, konflik Git, path tidak aman, file sumber tracked yang hilang, dan benturan file/direktori akan menghentikan proses.

## Backup dan verifikasi

Setiap apply yang memiliki perubahan membuat folder unik di `%LOCALAPPDATA%\Temp\opencode\lifevuww-deploy-backups`. Lokasinya di luar source dan deployment. Skrip mencetak lokasi tersebut sebelum penyalinan. Folder berisi `manifest.json` dengan path, tindakan, hash SHA-256 sumber dan versi lama, serta `files` yang menyimpan struktur relatif file tujuan yang akan diganti.

Semua file berbeda dibackup dan diverifikasi sebelum aplikasi ditulis. File baru dicatat di manifest tanpa backup karena belum ada versi lama. Setiap hasil penyalinan diperiksa terhadap hash sumber. Apply berikutnya dengan isi yang sama menghasilkan `Unchanged` dan tidak membuat backup baru. Simpan backup penting di tempat permanen sebelum folder temporary dibersihkan.

Untuk pemulihan manual, hentikan penulisan aplikasi, lihat manifest, lalu salin file yang diperlukan dari subfolder `files` ke path relatif yang sama di tujuan. Tinjau hash dan perubahan tujuan sebelum menimpa. File `Add` tidak memiliki versi lama dan tidak dihapus otomatis. Tidak ada rollback otomatis.

## Batasan operasional

Deployment dilakukan file demi file, bukan pertukaran satu rilis secara atomik. Kegagalan izin, disk penuh, atau perubahan file saat proses berjalan dapat menyisakan sebagian file yang sudah diperbarui. Error menghentikan proses; gunakan manifest dan backup untuk meninjau kondisi sebelum menjalankan ulang. Pemeriksaan hash mendeteksi perubahan yang terlihat saat pemeriksaan, tetapi tidak mengunci sumber atau tujuan. Hentikan editor, proses deploy lain, dan penulisan aplikasi pada file terkait selama apply. Jangan menjalankan dua deployment bersamaan.

Backup ini hanya untuk file yang diganti, bukan backup database atau seluruh data pengguna. Skrip tidak mengakses database. Aplikasi ini dapat menjalankan migrasi dari bootstrap saat dibuka, sehingga backup database dan pemeriksaan kompatibilitas skema perlu dilakukan terpisah sebelum membuka versi baru. File lama yang tidak lagi ada di Git tetap berada di tujuan karena skrip tidak menghapus apa pun. Pengaturan Apache/PHP, izin akses, konfigurasi `.env`, dan pengujian HTTP juga perlu diperiksa terpisah.

Folder tujuan adalah mirror file untuk menjalankan aplikasi, bukan sumber pengembangan mandiri. Jika tujuan sudah memiliki `.git`, metadata dan riwayatnya sengaja tetap lama karena tidak pernah disalin atau diperbarui. `git status` di tujuan dapat menunjukkan banyak perubahan terhadap riwayat lama tersebut dan tidak membuktikan apakah deploy sudah sesuai sumber. Gunakan repository sumber untuk commit, review, dan riwayat kode. Verifikasi kesesuaian file terpilih melalui preview script yang sama, hash salinan, dan manifest backup. File tujuan di luar daftar pilihan tetap dipertahankan, sehingga mirror ini bukan salinan identik seluruh repository.

## Fixture test deploy

```powershell
powershell -NoProfile -File .\tests\deploy_test.ps1
```

Fixture membuat repository Git dan tujuan sementara yang unik di `%LOCALAPPDATA%\Temp\opencode`, lalu menghapus fixture miliknya setelah selesai. Lokasi backup dalam pengujian juga diarahkan ke fixture. Test tidak memakai `C:\Lifevuww\lifevuww-deploy`, tidak memerlukan database, dan memeriksa preview tanpa penulisan, working tree, opt-in pendukung, daftar file tambahan eksplisit, backup edit tujuan, serta perlindungan environment dan upload.
