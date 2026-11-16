# Lifevuww: Lima Poros

## Dunia dan peranmu

Nawasela tumbuh di sekitar lima Poros yang menghubungkan arsip, jalur pegunungan, mata air, lumbung, dan jembatan. Kabut Kusut membuat jalan di antara tempat itu sulit terbaca. Kabut ini menggambarkan tumpukan urusan dan tujuan yang belum jelas.

Sebagai Perawat Poros, kamu menyambung jalur lewat tindakan nyata yang kamu pilih sendiri. Membaca beberapa halaman menerangi arsip. Menata pengeluaran mengisi peta bekal. Menghubungi teman memperbaiki jembatan. Tidak ada kewajiban mengerjakan semua wilayah dalam satu hari. Jeda dan pemulihan ikut menjaga Nawasela.

**Kalimat pembuka:** Pilih satu tujuan. Rawat satu langkah. Sambungkan kembali duniamu.

## Lima wilayah

| Key wilayah | Nama tampilan | Atribut internal | Tujuan dan contoh |
| --- | --- | --- | --- |
| `aksarimba` | Arsip Aksarimba | `Intelligence` | Pemahaman dan pemecahan masalah: membaca, belajar, menyelesaikan modul. |
| `lintasura` | Punggung Lintasura | `Physical` | Gerak dan kebugaran sesuai kemampuan: jalan singkat, peregangan, latihan pilihan. Opsional. |
| `teduhara` | Mata Air Teduhara | `Health` | Perawatan dan pemulihan: tidur, nutrisi, hidrasi, istirahat. |
| `bekalara` | Lumbung Bekalara | `Wealth` | Bekal dan penghidupan: mencatat pengeluaran, menata anggaran, mengerjakan pekerjaan. |
| `temuraya` | Jembatan Temuraya | `Social` | Hubungan dan komunitas: mendengarkan, menghubungi keluarga, membantu teman. |

Physical membahas gerak, sedangkan Health membahas perawatan dan pemulihan. Pengguna memilih kegiatan yang cocok untuk kondisinya. Wilayah Physical tetap tersedia tanpa menjadi syarat kemajuan di wilayah lain. Tidak ada atribut Mental tambahan. Mood dan energi tetap berupa catatan refleksi.

## Istilah yang dipakai

| Key konsep | Label tampilan | Arti dalam alur pengguna |
| --- | --- | --- |
| `goal` | Tujuan | Arah yang ingin dicapai di salah satu wilayah. |
| `milestone` | Langkah Tujuan | Bagian tujuan yang dapat ditandai selesai. |
| `habit` | Kebiasaan | Tindakan berulang yang mendukung perjalanan. |
| `quest` | Misi | Tindakan dengan hasil akhir dan tenggat. |
| `focus_rift` | Ruang Fokus | Waktu khusus untuk mengerjakan satu hal. |
| `dungeon` | Ekspedisi Poros | Tantangan mingguan yang maju lewat aktivitas nyata. |
| `boss` | Simpul Penghalang | Hambatan fiksi yang terurai saat progres terkumpul. |
| `companion` | Sahabat Poros | Pendamping dengan bonus dan perkembangan ikatan. |
| `recovery` | Kembali Bertahap | Ajakan memilih satu tindakan kecil saat kembali. |
| `planned_break` | Jeda Terencana | Waktu jeda yang dicatat sebelum dimulai. |

Menu utama tetap mudah dikenali: Dasbor, Kebiasaan, Misi, Perjalanan, Kalender, Jurnal, dan Hadiah. Pada pengenalan pertama, gunakan label seperti **Ekspedisi Poros (Dungeon)** agar istilah baru tetap terhubung dengan fitur yang sudah dikenal. EXP, HP, Gold, level, dan streak tetap ditampilkan bersama penjelasan singkat bila diperlukan.

## Gelar, jalur, dan sahabat

Urutan gelar display dari awal hingga akhir: Penyemai Poros, Penjaga Poros, Perawat Poros, Perintis Poros, Penyambung Poros, Perangkai Poros, lalu Penata Poros. Ambang level tetap mengikuti aturan yang sudah ada.

Lima spesialisasi memakai alias Perawat Serba, Penjaga Aksara, Penuntun Gerak, Perangkai Bekal, dan Pengurai Simpul. Alias ini memberi konteks cerita pada pilihan lama tanpa mengganti bonusnya.

Sahabat Poros terdiri dari Ramu si Serigala yang menemani rutinitas, Tilik si Burung Hantu yang menjaga waktu fokus, Bara si Naga Kecil yang mengurai hambatan misi, Pundi si Grifon yang membantu bekal, serta Tedu si Kura-Kura yang menemani pemulihan. Tahap ikatan ditampilkan sebagai Tunas, Tumbuh, dan Berakar. Riwayat perkembangan harus memakai peristiwa yang benar-benar dicatat sistem; jangan menciptakan tanggal atau perjalanan lama.

Simpul Penghalang berurutan: Simpul Kusut, Penimbun Detik, Dinding Tunda, Pusaran Beban, dan Jalinan Buntu. Nama tersebut menggambarkan hambatan di jalur Nawasela. Hadiah dan HP tetap berasal dari aturan dungeon yang berlaku. Ruang Awal, Ruang Tekun, dan Ruang Mendalam menjadi alias tiga pilihan fokus yang ada.

## Alur dan nada tulisan

Tujuan dipecah menjadi langkah. Kebiasaan atau misi dapat ditautkan ke langkah yang relevan. Penyelesaian aktivitas memperbarui langkah melalui aturan aplikasi, tanpa memberi hadiah tambahan hanya karena aktivitas memiliki beberapa tautan. Menit fokus dicatat sebagai dukungan tujuan dan tidak otomatis berarti sebuah langkah selesai.

Pemulihan HP nol mengembalikan HP ke batas maksimum tanpa mengurangi EXP, level, atau pencapaian. Aturan absen pada kebiasaan yang jatuh tempo tetap terpisah. Ajakan kembali cukup menawarkan satu tindakan yang tersedia. Gunakan kalimat seperti **Mulai lagi dari satu langkah yang terasa ringan.** Hindari mempermalukan pengguna, menyebut absen sebagai kematian, atau menjanjikan bahwa semua streak dipulihkan.

Jeda Terencana adalah bagian dari ritme perjalanan. Ekspedisi mingguan mengikuti kemampuan aktivitas, dengan target yang disimpan untuk pertemuan berjalan. Narasi pemulihan wilayah adalah bingkai cerita, bukan klaim adanya simulasi peta atau skor kesehatan dunia yang sudah dibangun.

## Kontrak integrasi

Sumber katalog adalah [`config/world.php`](../config/world.php). File mengembalikan array biasa melalui `require`, tanpa database, fungsi global, atau perubahan state:

```php
$world = require __DIR__ . '/../config/world.php';
$region = $world['regions']['aksarimba'];
$label = $world['companions'][$companionKey] ?? $companionName;
echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
```

`title`, `intro`, `regions`, dan `terms` memuat bingkai dunia. `ranks`, `specializations`, `companions`, `companion_stages`, `bosses`, dan `focus_rifts` memetakan key lama ke alias display. Setiap wilayah memiliki `label`, `attribute`, dan `purpose`. Kelima atribut harus muncul tepat satu kali.

Nama/key internal, ID kategori, nama aset, nilai yang disimpan database, parameter form, dan aturan hadiah tetap dipakai apa adanya. Khusus rank dan spesialisasi, string nama lama juga merupakan key internal. Jangan menyimpan alias display sebagai penggantinya. Katalog ini belum dengan sendirinya mengganti tulisan pada halaman aplikasi. Konsumen perlu memuatnya secara eksplisit dan memakai nama lama sebagai fallback untuk data yang belum dikenal.

Jalankan `php tests/world_test.php` untuk memeriksa kontrak katalog. Test ini tidak memerlukan database. Panduan ini menjelaskan arah narasi dan penggunaan katalog; status penyelesaian fitur aplikasi dicatat di README.
