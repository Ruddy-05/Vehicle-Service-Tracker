<?php
// edit_service.php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$id      = (int)($_GET['id'] ?? 0);
$error   = '';

// Ambil data log servis (pastikan milik user ini)
$res = $conn->prepare("SELECT sl.* FROM service_logs sl WHERE sl.id = ? AND sl.user_id = ?");
$res->bind_param('ii', $id, $user_id);
$res->execute();
$log = $res->get_result()->fetch_assoc();
$res->close();

if (!$log) {
  header('Location: dashboard.php');
  exit;
}

// Ambil daftar kendaraan user
$veh_res = $conn->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY nama");
$veh_res->bind_param('i', $user_id);
$veh_res->execute();
$vehicles = $veh_res->get_result()->fetch_all(MYSQLI_ASSOC);
$veh_res->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
  $tgl        = $_POST['tanggal_servis'] ?? '';
  $km         = (int)($_POST['km_saat_servis'] ?? 0);
  $jenis      = trim($_POST['jenis_servis'] ?? '');
  $biaya      = (float)str_replace(['.', ','], ['', '.'], $_POST['biaya'] ?? '0');
  $catatan    = trim($_POST['catatan'] ?? '');
  $km_next    = $_POST['km_berikutnya'] ? (int)$_POST['km_berikutnya'] : null;
  $tgl_next   = $_POST['tanggal_berikutnya'] ?: null;

  if (!$vehicle_id || empty($tgl) || !$km || empty($jenis)) {
    $error = 'Kendaraan, tanggal, KM, dan jenis servis wajib diisi.';
  } else {
    $km_next_val  = $km_next  ? $km_next  : 'NULL';
    $tgl_next_val = $tgl_next ? "'" . $conn->real_escape_string($tgl_next) . "'" : 'NULL';
    $q = "UPDATE service_logs SET
              vehicle_id=$vehicle_id,
              tanggal_servis='" . $conn->real_escape_string($tgl) . "',
              km_saat_servis=$km,
              jenis_servis='" . $conn->real_escape_string($jenis) . "',
              biaya=$biaya,
              catatan='" . $conn->real_escape_string($catatan) . "',
              km_berikutnya=$km_next_val,
              tanggal_berikutnya=$tgl_next_val
              WHERE id=$id AND user_id=$user_id";
    if ($conn->query($q)) {
      header('Location: dashboard.php?updated=1');
      exit;
    } else {
      $error = 'Gagal update: ' . $conn->error;
    }
  }
}

$jenis_list = ['Ganti Oli Mesin', 'Ganti Oli Gardan', 'Ganti Ban Depan', 'Ganti Ban Belakang', 'Servis Berkala', 'Tune Up', 'Ganti Filter Udara', 'Ganti Busi', 'Ganti Kampas Rem', 'Ganti Rantai & Gear', 'Lainnya'];
$is_custom  = !in_array($log['jenis_servis'], $jenis_list);
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Servis — ServisKu</title>
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
      padding: 2rem 1rem;
    }

    .container {
      max-width: 640px;
      margin: 0 auto;
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

    .card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2rem;
    }

    h1 {
      font-family: 'Syne', sans-serif;
      font-size: 1.5rem;
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

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .form-full {
      grid-column: 1/-1;
    }

    label.form-label {
      display: block;
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 0.45rem;
    }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      padding: 0.75rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      transition: all 0.2s;
      outline: none;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
    }

    .form-select option {
      background: var(--panel);
    }

    .form-textarea {
      resize: vertical;
      min-height: 80px;
    }

    .section-label {
      font-size: 0.78rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--muted);
      margin: 1rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .section-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .optional-tag {
      font-size: 0.65rem;
      background: rgba(107, 114, 128, 0.2);
      color: var(--muted);
      padding: 0.1rem 0.4rem;
      border-radius: 4px;
      text-transform: none;
      letter-spacing: 0;
    }

    .hint {
      font-size: 0.74rem;
      color: var(--muted);
      margin-top: 0.3rem;
    }

    .btn-row {
      display: flex;
      gap: 0.75rem;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      padding: 0.8rem 1.5rem;
      border-radius: 10px;
      font-family: 'Syne', sans-serif;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      flex: 1;
    }

    .btn-primary {
      background: var(--accent);
      color: #fff;
    }

    .btn-primary:hover {
      background: var(--accent2);
    }

    .btn-ghost {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
    }

    .btn-ghost:hover {
      border-color: var(--accent);
      color: var(--accent);
    }
  </style>
</head>

<body>
  <div class="container">
    <a href="dashboard.php" class="back">← Kembali ke Dashboard</a>
    <div class="card">
      <h1>✏️ Edit Catatan Servis</h1>
      <p class="subtitle">Perbarui informasi servis kendaraan.</p>

      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST">
        <div class="form-grid">
          <div class="form-full">
            <label class="form-label">Pilih Kendaraan</label>
            <select name="vehicle_id" class="form-select" required>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $log['vehicle_id'] == $v['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v['nama']) ?> (<?= htmlspecialchars($v['plat_nomor']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Tanggal Servis</label>
            <input type="date" name="tanggal_servis" class="form-input" value="<?= $log['tanggal_servis'] ?>" required>
          </div>
          <div>
            <label class="form-label">KM Saat Servis</label>
            <input type="number" name="km_saat_servis" class="form-input" value="<?= $log['km_saat_servis'] ?>" min="0" required>
          </div>
          <div>
            <label class="form-label">Jenis Servis</label>
            <select name="jenis_servis" class="form-select" id="jenis_servis" required>
              <?php foreach ($jenis_list as $j): ?>
                <option value="<?= $j ?>" <?= (!$is_custom && $log['jenis_servis'] === $j) ? 'selected' : '' ?>><?= $j ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="custom_group" style="<?= $is_custom ? '' : 'display:none' ?>">
            <label class="form-label">Jenis (Custom)</label>
            <input type="text" name="jenis_servis_custom" class="form-input" value="<?= $is_custom ? htmlspecialchars($log['jenis_servis']) : '' ?>">
          </div>
          <div class="form-full">
            <label class="form-label">Biaya (Rp)</label>
            <input type="number" name="biaya" class="form-input" value="<?= $log['biaya'] ?>" min="0" step="500" required>
          </div>
          <div class="form-full">
            <label class="form-label">Catatan <span class="optional-tag">opsional</span></label>
            <textarea name="catatan" class="form-textarea"><?= htmlspecialchars($log['catatan'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="section-label">Pengingat <span class="optional-tag">opsional</span></div>
        <div class="form-grid" style="margin-bottom:1.5rem">
          <div>
            <label class="form-label">Tanggal Berikutnya</label>
            <input type="date" name="tanggal_berikutnya" class="form-input" value="<?= $log['tanggal_berikutnya'] ?? '' ?>">
          </div>
          <div>
            <label class="form-label">KM Berikutnya</label>
            <input type="number" name="km_berikutnya" class="form-input" value="<?= $log['km_berikutnya'] ?? '' ?>" min="0">
            <p class="hint">Estimasi KM servis berikutnya</p>
          </div>
        </div>

        <div class="btn-row">
          <a href="dashboard.php" class="btn btn-ghost">Batal</a>
          <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</body>

</html>