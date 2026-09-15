<div align="center">
  <img src="assets/images/logo.svg" alt="Lifevuww Logo" width="80" height="80" />
  <h1>Lifevuww</h1>
  <p><strong>A Gamified Habit Tracker & RPG Productivity Platform</strong></p>

  <p>
    <img src="https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version" />
    <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL" />
    <img src="https://img.shields.io/badge/Design-Linear--Style-000000?style=flat-square" alt="Linear Design" />
    <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License" />
  </p>
</div>

---

## 📖 Overview

**Lifevuww** adalah platform pelacak kebiasaan (habit tracker) berbasis web yang mentransformasikan kedisiplinan dan produktivitas dunia nyata menjadi sebuah **Game RPG (Role Playing Game)**.

Setiap rutinitas positif yang Anda selesaikan memberikan **Experience Points (EXP)** untuk menaikkan level karakter Anda serta mata uang **Gold** yang bisa dibelanjakan untuk menebus hadiah dunia nyata di Reward Shop. Sebaliknya, kemalasan dan inkonsistensi memiliki konsekuensi nyata berupa pengurangan **Health Points (HP)** hingga kematian karakter (*Game Over / EXP Penalty*).

---

## 🎮 Core Game Mechanics

### 1. ⚔️ Character Progression & Ranks
Karakter Anda bertumbuh seiring kedisiplinan Anda di dunia nyata:
* **Level Formula:** `Level = floor(EXP / 1000) + 1`
* **Rank Tiers:**
  * Level 30+: **S-Rank Sovereign**
  * Level 25+: **A-Rank Master**
  * Level 20+: **B-Rank Elite**
  * Level 15+: **C-Rank Vanguard**
  * Level 10+: **D-Rank Explorer**
  * Level 5+: **E-Rank Rookie**
  * Level 1–4: **F-Rank Novice**

### 2. ❤️ Health System & Death Penalty
* Pengguna memiliki **100 Max HP**.
* Melewatkan tugas harian atau gagal menyelesaikan quest akan mengurangi HP sesuai tingkat kesulitan (*Easy: -5 HP, Medium: -10 HP, Hard: -15 HP*).
* **Game Over (HP = 0):** Karakter kehilangan **50% total EXP**, seluruh *streak* di-reset, dan karakter dibangkitkan kembali dengan 100 HP.

### 3. 🔥 Streaks & Multiplier Boost
* Menyelesaikan rutinitas setiap hari secara berturut-turut akan membangun **Streak**.
* **Streak 3–6 Hari:** Mendapat bonus **1.2x Multiplier** untuk EXP & Gold.
* **Streak 7+ Hari:** Mendapat bonus **1.5x Multiplier**.

### 4. 🪙 Virtual Economy & Reward Shop
* Dapatkan Gold dari setiap penyelesaian Habit dan Quest.
* **System Consumables:**
  * 🧪 **Minor Health Potion (25 Gold):** Memulihkan +25 HP.
  * ⚗️ **Full Elixir of Life (75 Gold):** Memulihkan 100 HP penuh.
  * 🧊 **Streak Freeze Shield (50 Gold):** Melindungi streak dan HP dari 1 hari libur/absen.
* **Custom Treats:** Tambahkan hadiah buatan Anda sendiri (misal: *Main Game 2 Jam*, *Beli Kopi*, dll).

### 5. 📊 Skill Tree Attributes
Setiap kebiasaan dikelompokkan ke dalam 5 pilar atribut kehidupan yang divisualisasikan dalam bentuk **Radar Chart dinamis**:
* 🧠 **Intelligence** (Belajar, Membaca, Koding)
* 💪 **Physical** (Olahraga, Gym, Lari)
* 💰 **Wealth** (Menabung, Investasi, Bisnis)
* ❤️ **Health** (Tidur teratur, Minum air, Pola makan)
* 🤝 **Social** (Networking, Keluarga, Teman)

---

## 🛠️ Tech Stack

* **Backend:** PHP 8.x (Native, PDO with Prepared Statements)
* **Database:** MySQL / MariaDB
* **Frontend:** Vanilla CSS (Custom Design System / Linear-style Dark Theme), Bootstrap 5 Grid Utilities, FontAwesome 6, Chart.js, Canvas Confetti
* **Security:** CSRF Protection Token pada setiap mutasi form, Password Hashing via `password_verify()`, XSS Sanitization, Environment Variables `.env`.

---

## 🚀 Quickstart & Installation

### Prasyarat
* Web server lokal (XAMPP, Laragon, atau Docker) dengan PHP 8.0+ dan MySQL.

### Langkah Instalasi
1. **Clone repository ini:**
   ```bash
   git clone https://github.com/USERNAME/Lifevuww.git
   ```
2. **Pindahkan ke folder server web Anda:**
   * Di XAMPP: Pindahkan ke `C:\xampp\htdocs\Lifevuww`
3. **Konfigurasi Lingkungan (`.env`):**
   * Salin file `.env.example` menjadi `.env`:
     ```env
     DB_HOST=localhost
     DB_NAME=habit_tracker_rpg
     DB_USERNAME=root
     DB_PASSWORD=
     ```
4. **Import Database:**
   * Buka **phpMyAdmin** (`http://localhost/phpmyadmin/`).
   * Buat database baru bernama `habit_tracker_rpg`.
   * Import file `database.sql` yang berada di root folder proyek ini.
5. **Jalankan Aplikasi:**
   * Buka browser dan akses: `http://localhost/Lifevuww/`
   * Daftarkan akun baru melalui halaman Register, dan mulai petualangan produktivitas Anda!

---

## 📜 License
Distributed under the MIT License. Created with passion by **Mivuww**.
