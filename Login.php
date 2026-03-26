<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (empty($email) || empty($password)) {
    $error = 'Email dan password wajib diisi.';
  } else {
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['user_id']  = $user['id'];
      $_SESSION['username'] = $user['username'];
      header('Location: dashboard.php');
      exit;
    } else {
      $error = 'Email atau password salah.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — ServisKu</title>
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
      padding: 1rem;
      overflow: hidden;
    }

    /* Animated background */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 60% 40% at 20% 80%, rgba(249, 115, 22, 0.12) 0%, transparent 60%),
        radial-gradient(ellipse 40% 60% at 80% 20%, rgba(251, 146, 60, 0.08) 0%, transparent 60%);
      pointer-events: none;
    }

    .page-wrap {
      display: flex;
      width: 100%;
      max-width: 960px;
      min-height: 560px;
      border-radius: 24px;
      overflow: hidden;
      border: 1px solid var(--border);
      box-shadow: 0 40px 80px rgba(0, 0, 0, 0.5);
      position: relative;
      z-index: 1;
    }

    /* Left panel — brand */
    .brand-panel {
      flex: 1;
      background: linear-gradient(135deg, #111827 0%, #0d0f14 100%);
      padding: 3rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-right: 1px solid var(--border);
      position: relative;
      overflow: hidden;
    }

    .brand-panel::after {
      content: '';
      position: absolute;
      bottom: -80px;
      right: -80px;
      width: 280px;
      height: 280px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(249, 115, 22, 0.2), transparent 70%);
    }

    .brand-logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .logo-icon {
      width: 44px;
      height: 44px;
      background: var(--accent);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
    }

    .logo-text {
      font-family: 'Syne', sans-serif;
      font-size: 1.5rem;
      font-weight: 800;
      letter-spacing: -0.5px;
    }

    .brand-tagline {
      font-size: 2.2rem;
      font-family: 'Syne', sans-serif;
      font-weight: 700;
      line-height: 1.2;
      color: var(--text);
    }

    .brand-tagline span {
      color: var(--accent);
    }

    .brand-features {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .brand-features li {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      font-size: 0.9rem;
      color: var(--muted);
    }

    .brand-features li::before {
      content: '✓';
      width: 20px;
      height: 20px;
      background: rgba(249, 115, 22, 0.15);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--accent);
      font-size: 0.7rem;
      flex-shrink: 0;
      line-height: 20px;
      text-align: center;
    }

    /* Right panel — form */
    .form-panel {
      width: 420px;
      background: var(--panel);
      padding: 3rem 2.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .form-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.8rem;
      font-weight: 800;
      margin-bottom: 0.4rem;
    }

    .form-subtitle {
      color: var(--muted);
      font-size: 0.9rem;
      margin-bottom: 2rem;
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

    .alert-success {
      background: rgba(34, 197, 94, 0.1);
      color: #86efac;
      border-color: var(--success);
    }

    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 0.5rem;
    }

    .form-input {
      width: 100%;
      padding: 0.8rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
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

    .btn-primary {
      width: 100%;
      padding: 0.9rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: 'Syne', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: 0.03em;
      transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
      margin-top: 0.5rem;
    }

    .btn-primary:hover {
      background: var(--accent2);
      box-shadow: 0 4px 20px rgba(249, 115, 22, 0.35);
      transform: translateY(-1px);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .form-footer {
      text-align: center;
      margin-top: 1.5rem;
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

    @media (max-width: 700px) {
      .brand-panel {
        display: none;
      }

      .page-wrap {
        max-width: 440px;
      }

      .form-panel {
        width: 100%;
        padding: 2rem 1.5rem;
      }
    }
  </style>
</head>

<body>
  <div class="page-wrap">

    <!-- Brand Panel -->
    <div class="brand-panel">
      <div class="brand-logo">
        <div class="logo-icon">🔧</div>
        <span class="logo-text">ServisKu</span>
      </div>
      <div>
        <p class="brand-tagline">Kelola <span>servis</span><br>kendaraanmu<br>dengan mudah.</p>
      </div>
      <ul class="brand-features">
        <li>Catat riwayat ganti oli & ban</li>
        <li>Pengingat jadwal servis berikutnya</li>
        <li>Pantau biaya perawatan kendaraan</li>
        <li>Dukung motor & mobil</li>
      </ul>
    </div>

    <!-- Form Panel -->
    <div class="form-panel">
      <h1 class="form-title">Masuk Akun</h1>
      <p class="form-subtitle">Selamat datang kembali! Masukkan data Anda.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Akun berhasil dibuat! Silakan login.</div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-input"
            placeholder="email@kamu.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input"
            placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-primary">Masuk →</button>
      </form>

      <p class="form-footer">
        Belum punya akun? <a href="register.php">Daftar sekarang</a>
      </p>
    </div>

  </div>
</body>

</html>