<?php
// ============================================
// db.php — Koneksi ke database MySQL
// Letakkan file ini di root folder proyek
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // sesuaikan dengan user MySQL Anda
define('DB_PASS', '');            // kosong jika pakai XAMPP default
define('DB_NAME', 'service_tracker');

// Buat koneksi menggunakan MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
  die('<div style="font-family:sans-serif;padding:2rem;color:red;">
        <h2>❌ Koneksi Database Gagal</h2>
        <p>' . $conn->connect_error . '</p>
        <p>Pastikan XAMPP/MySQL sudah berjalan dan database sudah dibuat.</p>
    </div>');
}

// Set charset UTF-8
$conn->set_charset('utf8mb4');
