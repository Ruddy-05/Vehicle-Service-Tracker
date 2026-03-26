<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama      = trim($_POST['nama'] ?? '');
  $plat      = strtoupper(trim($_POST['plat_nomor'] ?? ''));
  $jenis     = $_POST['jenis'] ?? 'motor';

  if (empty($nama) || empty($plat)) {
    $error = 'Nama kendaraan dan plat nomor wajib diisi.';
  } elseif (!in_array($jenis, ['motor', 'mobil'])) {
    $error = 'Jenis kendaraan tidak valid.';
  } else {
    $stmt = $conn->prepare("INSERT INTO vehicles (user_id, nama, plat_nomor, jenis) VALUES (?,?,?,?)");
    $stmt->bind_param('isss', $user_id, $nama, $plat, $jenis);
    if ($stmt->execute()) {
      header('Location: dashboard.php?added=1');
      exit;
    } else {
      $error = 'Gagal menyimpan kendaraan. Coba lagi.';
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
  <title>Tambah Kendaraan — ServisKu</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
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
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }

    .card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2.5rem;
      width: 100%;
      max-width: 480px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    }

    .back {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      color: var(--muted);
      text-decoration: none;
      font-size: 0.85rem;
      margin-bottom: 1.5rem;
      transition: color 0.15s;
    }

    .back:hover {
      color: var(--accent);
    }

    h1 {
      font-family: 'Syne', sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
      margin-bottom: 0.3rem;
    }

    .subtitle {
      color: var(--muted);
      font-size: 0.875rem;
      margin-bottom: 2rem;
    }

    .alert {
      padding: 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.875rem;
      margin-bottom: 1.25rem;
      border-left: 3px solid;
      background: rgba(239, 68, 68, 0.1);
      color: #fca5a5;
      border-color: var(--error);
    }

    .form-group {
      margin-bottom: 1.25rem;
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

    .form-input,
    .form-select {
      width: 100%;
      padding: 0.8rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      transition: all 0.2s;
      outline: none;
    }

    .form-input:focus,
    .form-select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    .form-select option {
      background: var(--panel);
    }

    .jenis-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
    }

    .jenis-card {
      border: 2px solid var(--border);
      border-radius: 12px;
      padding: 1rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.15s;
    }

    .jenis-card:hover {
      border-color: var(--accent);
    }

    .jenis-card input[type=radio] {
      display: none;
    }

    .jenis-card input:checked~.jenis-label {
      color: var(--accent);
    }

    .jenis-card:has(input:checked) {
      border-color: var(--accent);
      background: rgba(249, 115, 22, 0.08);
    }

    .jenis-icon {
      font-size: 2rem;
      margin-bottom: 0.4rem;
    }

    .jenis-label {
      font-weight: 500;
      font-size: 0.9rem;
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
      transition: all 0.2s;
      margin-top: 0.5rem;
    }

    .btn-primary:hover {
      background: var(--accent2);
      box-shadow: 0 4px 20px rgba(249, 115, 22, 0.3);
      transform: translateY(-1px);
    }
  </style>
</head>

<body>
  <div class="card">
    <a href="dashboard.php" class="back">← Kembali ke Dashboard</a>
    <h1>Tambah Kendaraan</h1>
    <p class="subtitle">Daftarkan motor atau mobil yang ingin kamu pantau.</p>

    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Nama Kendaraan</label>
        <input type="text" name="nama" class="form-input" placeholder="Contoh: Honda Beat 2020" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Plat Nomor</label>
        <input type="text" name="plat_nomor" class="form-input" placeholder="Contoh: B 1234 ABC" value="<?= htmlspecialchars($_POST['plat_nomor'] ?? '') ?>" required style="text-transform:uppercase">
      </div>
      <div class="form-group">
        <label class="form-label">Jenis Kendaraan</label>
        <div class="jenis-grid">
          <label class="jenis-card">
            <input type="radio" name="jenis" value="motor" <?= ($_POST['jenis'] ?? 'motor') === 'motor' ? 'checked' : '' ?>>
            <div class="jenis-icon">🏍️</div>
            <div class="jenis-label">Motor</div>
          </label>
          <label class="jenis-card">
            <input type="radio" name="jenis" value="mobil" <?= ($_POST['jenis'] ?? '') === 'mobil' ? 'checked' : '' ?>>
            <div class="jenis-icon">🚙</div>
            <div class="jenis-label">Mobil</div>
          </label>
        </div>
      </div>
      <button type="submit" class="btn-primary">Simpan Kendaraan →</button>
    </form>
  </div>
</body>

</html>