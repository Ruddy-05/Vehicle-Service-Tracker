<?php
session_start();
require_once 'db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm'] ?? '';

  // Validasi
  if (empty($username) || empty($email) || empty($password)) {
    $error = 'Semua field wajib diisi.';
  } elseif (strlen($username) < 3) {
    $error = 'Username minimal 3 karakter.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Format email tidak valid.';
  } elseif (strlen($password) < 6) {
    $error = 'Password minimal 6 karakter.';
  } elseif ($password !== $confirm) {
    $error = 'Konfirmasi password tidak cocok.';
  } else {
    // Cek duplikat email/username
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param('ss', $email, $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $error = 'Email atau username sudah digunakan.';
    } else {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $ins  = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
      $ins->bind_param('sss', $username, $email, $hash);
      if ($ins->execute()) {
        header('Location: login.php?registered=1');
        exit;
      } else {
        $error = 'Gagal membuat akun. Coba lagi.';
      }
      $ins->close();
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Akun — ServisKu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --bg: #0d0f14;
      --panel: #13161e;
      --border: #1f2433;
      --accent: #f97316;
      --accent2: #fb923c;
      --text: #e8eaf0;
      --muted: #6b7280;
      --input-bg: #1a1e2a;
      --error: #ef4444;
      --success: #22c55e;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 50% 50% at 10% 90%, rgba(249, 115, 22, 0.1) 0%, transparent 60%),
        radial-gradient(ellipse 40% 40% at 90% 10%, rgba(251, 146, 60, 0.07) 0%, transparent 60%);
      pointer-events: none;
    }

    .card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 24px;
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 460px;
      box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
      position: relative;
      z-index: 1;
    }

    .card-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .logo {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      margin-bottom: 1.5rem;
    }

    .logo-icon {
      width: 40px;
      height: 40px;
      background: var(--accent);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
    }

    .logo-text {
      font-family: 'Syne', sans-serif;
      font-size: 1.4rem;
      font-weight: 800;
    }

    h1 {
      font-family: 'Syne', sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
    }

    .subtitle {
      color: var(--muted);
      font-size: 0.875rem;
      margin-top: 0.3rem;
    }

    .alert {
      padding: 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.875rem;
      margin-bottom: 1.25rem;
      border-left: 3px solid;
    }

    .alert-error {
      background: rgba(239, 68, 68, 0.1);
      color: #fca5a5;
      border-color: var(--error);
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .form-group {
      margin-bottom: 1.1rem;
    }

    .form-label {
      display: block;
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 0.45rem;
    }

    .form-input {
      width: 100%;
      padding: 0.75rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      transition: border-color 0.2s, box-shadow 0.2s;
      outline: none;
    }

    .form-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    .form-input::placeholder {
      color: #3d4451;
    }

    .hint {
      font-size: 0.75rem;
      color: var(--muted);
      margin-top: 0.3rem;
    }

    .btn-primary {
      width: 100%;
      padding: 0.85rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: 'Syne', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: 0.03em;
      transition: all 0.2s;
      margin-top: 0.5rem;
    }

    .btn-primary:hover {
      background: var(--accent2);
      box-shadow: 0 4px 20px rgba(249, 115, 22, 0.35);
      transform: translateY(-1px);
    }

    .form-footer {
      text-align: center;
      margin-top: 1.25rem;
      font-size: 0.875rem;
      color: var(--muted);
    }

    .form-footer a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
    }

    .form-footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <div class="card">
    <div class="card-header">
      <div class="logo">
        <div class="logo-icon">🔧</div>
        <span class="logo-text">ServisKu</span>
      </div>
      <h1>Buat Akun Baru</h1>
      <p class="subtitle">Daftar gratis, mulai lacak servis kendaraanmu.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-input"
            placeholder="nama_kamu"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            required>
          <p class="hint">Min. 3 karakter</p>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-input"
            placeholder="email@kamu.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input"
          placeholder="Min. 6 karakter" required>
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi Password</label>
        <input type="password" name="confirm" class="form-input"
          placeholder="Ulangi password" required>
      </div>
      <button type="submit" class="btn-primary">Daftar Sekarang →</button>
    </form>

    <p class="form-footer">
      Sudah punya akun? <a href="login.php">Masuk di sini</a>
    </p>
  </div>
</body>

</html>