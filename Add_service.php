<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$error   = '';
$preselect_vid = (int)($_GET['vid'] ?? 0);

// Ambil kendaraan user
$veh_res = $conn->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY nama");
$veh_res->bind_param('i', $user_id);
$veh_res->execute();
$vehicles = $veh_res->get_result()->fetch_all(MYSQLI_ASSOC);
$veh_res->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $vehicle_id  = (int)($_POST['vehicle_id'] ?? 0);
  $tgl         = $_POST['tanggal_servis'] ?? '';
  $km          = (int)($_POST['km_saat_servis'] ?? 0);
  $jenis       = trim($_POST['jenis_servis'] ?? '');
  $biaya       = (float)str_replace(['.', ','], ['', '.'], $_POST['biaya'] ?? '0');
  $catatan     = trim($_POST['catatan'] ?? '');
  $km_next     = $_POST['km_berikutnya'] ? (int)$_POST['km_berikutnya'] : null;
  $tgl_next    = $_POST['tanggal_berikutnya'] ?: null;

  if (!$vehicle_id || empty($tgl) || !$km || empty($jenis)) {
    $error = 'Kendaraan, tanggal, KM, dan jenis servis wajib diisi.';
  } else {
    // Validasi vehicle milik user
    $chk = $conn->prepare("SELECT id FROM vehicles WHERE id=? AND user_id=?");
    $chk->bind_param('ii', $vehicle_id, $user_id);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
      $error = 'Kendaraan tidak valid.';
    }
    $chk->close();

    if (!$error) {
      $stmt = $conn->prepare("INSERT INTO service_logs
                (vehicle_id, user_id, tanggal_servis, km_saat_servis, jenis_servis, biaya, catatan, km_berikutnya, tanggal_berikutnya)
                VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param(
        'iisisdssi',
        // wait — fix types
        $vehicle_id,
        $user_id,
        $tgl,
        $km,
        $jenis,
        $biaya,
        $catatan,
        $km_next,
        $tgl_next
      );
      // Redo with correct bind
      $stmt->close();
      $stmt2 = $conn->prepare("INSERT INTO service_logs
                (vehicle_id, user_id, tanggal_servis, km_saat_servis, jenis_servis, biaya, catatan, km_berikutnya, tanggal_berikutnya)
                VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt2->bind_param(
        'iisissds',
        $vehicle_id,
        $user_id,
        $tgl,
        $km,
        $jenis,
        $biaya,
        $catatan,
        $km_next
      );
      // Correct approach using NULL-safe binding
      $stmt2->close();

      // Direct approach
      $biaya_val   = $biaya;
      $km_next_val = $km_next;
      $tgl_next_val = $tgl_next;

      $ins = $conn->prepare("INSERT INTO service_logs
                (vehicle_id, user_id, tanggal_servis, km_saat_servis, jenis_servis, biaya, catatan, km_berikutnya, tanggal_berikutnya)
                VALUES (?,?,?,?,?,?,?,?,?)");
      $ins->bind_param(
        'iisissds',
        $vehicle_id,
        $user_id,
        $tgl,
        $km,
        $jenis,
        $biaya_val,
        $catatan,
        $km_next_val
      );
      // Final clean version:
      $ins->close();

      $q = "INSERT INTO service_logs
                  (vehicle_id, user_id, tanggal_servis, km_saat_servis, jenis_servis, biaya, catatan, km_berikutnya, tanggal_berikutnya)
                  VALUES ($vehicle_id, $user_id, '$tgl', $km, '" . $conn->real_escape_string($jenis) . "',
                  $biaya_val, '" . $conn->real_escape_string($catatan) . "',
                  " . ($km_next_val ? $km_next_val : 'NULL') . ",
                  " . ($tgl_next_val ? "'" . $conn->real_escape_string($tgl_next_val) . "'" : 'NULL') . ")";

      if ($conn->query($q)) {
        header('Location: dashboard.php?servis=1');
        exit;
      } else {
        $error = 'Gagal menyimpan. Error: ' . $conn->error;
      }
    }
  }
}

$jenis_list = [
  'Ganti Oli Mesin',
  'Ganti Oli Gardan',
  'Ganti Ban Depan',
  'Ganti Ban Belakang',
  'Servis Berkala',
  'Tune Up',
  'Ganti Filter Udara',
  'Ganti Busi',
  'Ganti Kampas Rem',
  'Ganti Rantai & Gear',
  'Lainnya',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catat Servis — ServisKu</title>
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
      --green: #22c55e;
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
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
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

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .form-full {
      grid-column: 1/-1;
    }

    .form-group {
      margin-bottom: 0;
    }

    .form-group+.form-group {
      margin-top: 0;
    }

    .form-section {
      margin-bottom: 1rem;
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

    .divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 1.5rem 0;
    }

    .section-label {
      font-size: 0.78rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--muted);
      margin-bottom: 1rem;
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
    }

    .btn-primary:hover {
      background: var(--accent2);
      box-shadow: 0 4px 20px rgba(249, 115, 22, 0.3);
      transform: translateY(-1px);
    }

    .hint {
      font-size: 0.74rem;
      color: var(--muted);
      margin-top: 0.3rem;
    }
  </style>
</head>

<body>
  <div class="container">
    <a href="dashboard.php" class="back">← Kembali ke Dashboard</a>

    <div class="card">
      <h1>🔩 Catat Servis</h1>
      <p class="subtitle">Rekam riwayat servis kendaraan kamu.</p>

      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if (empty($vehicles)): ?>
        <div style="text-align:center;padding:2rem;color:var(--muted)">
          <p style="font-size:2rem">🚗</p>
          <p>Kamu belum punya kendaraan.<br>
            <a href="add_vehicle.php" style="color:var(--accent)">Tambah kendaraan dulu →</a>
          </p>
        </div>
      <?php else: ?>
        <form method="POST">
          <div class="form-grid" style="margin-bottom:1rem">
            <div class="form-group form-full">
              <label class="form-label">Pilih Kendaraan</label>
              <select name="vehicle_id" class="form-select" required>
                <option value="">-- Pilih Kendaraan --</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?= $v['id'] ?>"
                    <?= ((int)($_POST['vehicle_id'] ?? $preselect_vid) === $v['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($v['nama']) ?> (<?= htmlspecialchars($v['plat_nomor']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Tanggal Servis</label>
              <input type="date" name="tanggal_servis" class="form-input"
                value="<?= htmlspecialchars($_POST['tanggal_servis'] ?? date('Y-m-d')) ?>"
                max="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">KM Saat Servis</label>
              <input type="number" name="km_saat_servis" class="form-input"
                placeholder="Contoh: 15000" min="0"
                value="<?= htmlspecialchars($_POST['km_saat_servis'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label">Jenis Servis</label>
              <select name="jenis_servis" class="form-select" id="jenis_servis" required>
                <option value="">-- Pilih Jenis --</option>
                <?php foreach ($jenis_list as $j): ?>
                  <option value="<?= $j ?>" <?= ($_POST['jenis_servis'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" id="jenis_custom_group" style="display:none">
              <label class="form-label">Tulis Jenis Servis</label>
              <input type="text" name="jenis_servis_custom" class="form-input"
                placeholder="Contoh: Ganti Kopling"
                value="<?= htmlspecialchars($_POST['jenis_servis_custom'] ?? '') ?>">
            </div>

            <div class="form-group form-full">
              <label class="form-label">Biaya (Rp)</label>
              <input type="number" name="biaya" class="form-input"
                placeholder="Contoh: 75000" min="0" step="500"
                value="<?= htmlspecialchars($_POST['biaya'] ?? '') ?>" required>
            </div>

            <div class="form-group form-full">
              <label class="form-label">Catatan <span class="optional-tag">opsional</span></label>
              <textarea name="catatan" class="form-textarea"
                placeholder="Contoh: Ganti oli Shell Helix 10W-40, kondisi mesin baik..."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="section-label">Pengingat Servis Berikutnya <span class="optional-tag">opsional</span></div>

          <div class="form-grid" style="margin-bottom:1.5rem">
            <div class="form-group">
              <label class="form-label">Tanggal Berikutnya</label>
              <input type="date" name="tanggal_berikutnya" class="form-input"
                value="<?= htmlspecialchars($_POST['tanggal_berikutnya'] ?? '') ?>"
                min="<?= date('Y-m-d') ?>">
              <p class="hint">Kosongkan jika tidak perlu</p>
            </div>
            <div class="form-group">
              <label class="form-label">KM Berikutnya</label>
              <input type="number" name="km_berikutnya" class="form-input"
                placeholder="Contoh: 16000" min="0"
                value="<?= htmlspecialchars($_POST['km_berikutnya'] ?? '') ?>">
              <p class="hint">Estimasi KM saat servis berikutnya</p>
            </div>
          </div>

          <button type="submit" class="btn-primary">💾 Simpan Catatan Servis</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    document.getElementById('jenis_servis')?.addEventListener('change', function() {
      const customGroup = document.getElementById('jenis_custom_group');
      if (this.value === 'Lainnya') {
        customGroup.style.display = 'block';
        customGroup.querySelector('input').required = true;
      } else {
        customGroup.style.display = 'none';
        customGroup.querySelector('input').required = false;
      }
    });
  </script>
</body>

</html>