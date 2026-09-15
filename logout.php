<?php
// Memulai atau melanjutkan sesi
session_start();

// Hapus semua data sesi
session_destroy();

// Hapus cache untuk memastikan halaman sebelumnya tidak dapat diakses
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect ke halaman login
header("Location: login.php");
exit();
?>
