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

## Pemilihan file dan perlindungan data

- Daftar file berasal dari `git ls-files --stage -z`. Isi yang disalin berasal dari working tree, termasuk perubahan file tracked yang belum di-commit. File untracked tidak ikut. File baru harus masuk Git index sebelum bisa disalin.
- Runtime yang dipilih: file PHP di root, `.htaccess`, `manifest.json`, `sw.js`, `offline.html`, serta file tracked di `assets`, `config`, `cron`, `includes`, `pages`, dan `migrations`.
- `.git` dan metadata bernama `.git*`, seluruh nama `.env*`, direktori upload/uploads, avatar/avatars, storage, data, cache, log/logs, session/sessions, dan backup/backups selalu dikecualikan di setiap tingkat. Aset bawaan aplikasi yang tracked, seperti ikon dan gambar boss, tetap ikut jika bukan di direktori yang dikecualikan.
- `docs`, `tests`, `scripts`, `.github`, README, lisensi, dan `database.sql` di root tidak disalin. Skrip tidak menjalankan SQL, PHP, migrasi, Git commit, atau push.
- `.env` tujuan tidak pernah dibuat atau ditimpa. Konfigurasi tujuan harus sudah tersedia secara terpisah. File tambahan di tujuan, termasuk upload dan perubahan lain di luar daftar runtime terpilih, tetap ada. Tidak ada penghapusan file.
- File runtime tujuan yang berbeda **akan diganti** oleh sumber saat `-Apply`. Versi tujuan dibackup dahulu, termasuk perubahan lokalnya. Skrip tidak menggabungkan perubahan kode dari dua folder. Periksa `Replace` sebelum apply jika perubahan lokal tujuan perlu dipertahankan dalam versi aktif.
- Symlink, junction, Git submodule pada path runtime, konflik Git, path tidak aman, file sumber tracked yang hilang, dan benturan file/direktori akan menghentikan proses.

## Backup dan verifikasi

Setiap apply yang memiliki perubahan membuat folder unik di `%LOCALAPPDATA%\Temp\opencode\lifevuww-deploy-backups`. Lokasinya di luar source dan deployment. Skrip mencetak lokasi tersebut sebelum penyalinan. Folder berisi `manifest.json` dengan path, tindakan, hash SHA-256 sumber dan versi lama, serta `files` yang menyimpan struktur relatif file tujuan yang akan diganti.

Semua file berbeda dibackup dan diverifikasi sebelum aplikasi ditulis. File baru dicatat di manifest tanpa backup karena belum ada versi lama. Setiap hasil penyalinan diperiksa terhadap hash sumber. Apply berikutnya dengan isi yang sama menghasilkan `Unchanged` dan tidak membuat backup baru. Simpan backup penting di tempat permanen sebelum folder temporary dibersihkan.

Untuk pemulihan manual, hentikan penulisan aplikasi, lihat manifest, lalu salin file yang diperlukan dari subfolder `files` ke path relatif yang sama di tujuan. Tinjau hash dan perubahan tujuan sebelum menimpa. File `Add` tidak memiliki versi lama dan tidak dihapus otomatis. Tidak ada rollback otomatis.

## Batasan operasional

Deployment dilakukan file demi file, bukan pertukaran satu rilis secara atomik. Kegagalan izin, disk penuh, atau perubahan file saat proses berjalan dapat menyisakan sebagian file yang sudah diperbarui. Error menghentikan proses; gunakan manifest dan backup untuk meninjau kondisi sebelum menjalankan ulang. Pemeriksaan hash mendeteksi perubahan yang terlihat saat pemeriksaan, tetapi tidak mengunci sumber atau tujuan. Hentikan editor, proses deploy lain, dan penulisan aplikasi pada file terkait selama apply. Jangan menjalankan dua deployment bersamaan.

Backup ini hanya untuk file yang diganti, bukan backup database atau seluruh data pengguna. Skrip tidak mengakses database. Aplikasi ini dapat menjalankan migrasi dari bootstrap saat dibuka, sehingga backup database dan pemeriksaan kompatibilitas skema perlu dilakukan terpisah sebelum membuka versi baru. File lama yang tidak lagi ada di Git tetap berada di tujuan karena skrip tidak menghapus apa pun. Pengaturan Apache/PHP, izin akses, konfigurasi `.env`, dan pengujian HTTP juga perlu diperiksa terpisah.
