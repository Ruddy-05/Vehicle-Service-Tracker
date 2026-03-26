<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db.php';

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ─── Ambil daftar kendaraan user ────────────────────────────────────────────
$veh_res = $conn->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
$veh_res->bind_param('i', $user_id);
$veh_res->execute();
$vehicles = $veh_res->get_result()->fetch_all(MYSQLI_ASSOC);
$veh_res->close();

// ─── Ambil log servis terbaru (semua kendaraan user) ───────────────────────
$log_res = $conn->prepare("
    SELECT sl.*, v.nama AS nama_kendaraan, v.plat_nomor, v.jenis
    FROM service_logs sl
    JOIN vehicles v ON sl.vehicle_id = v.id
    WHERE sl.user_id = ?
    ORDER BY sl.tanggal_servis DESC
    LIMIT 20
");
$log_res->bind_param('i', $user_id);
$log_res->execute();
$logs = $log_res->get_result()->fetch_all(MYSQLI_ASSOC);
$log_res->close();

// ─── Statistik ringkasan ─────────────────────────────────────────────────────
$stat_res = $conn->prepare("
    SELECT
        COUNT(*) AS total_servis,
        SUM(biaya) AS total_biaya,
        MAX(tanggal_servis) AS servis_terakhir
    FROM service_logs WHERE user_id = ?
");
$stat_res->bind_param('i', $user_id);
$stat_res->execute();
$stats = $stat_res->get_result()->fetch_assoc();
$stat_res->close();

// ─── Cek pengingat: servis yang sudah lewat jadwal ─────────────────────────
$remind_res = $conn->prepare("
    SELECT sl.*, v.nama AS nama_kendaraan, v.plat_nomor
    FROM service_logs sl
    JOIN vehicles v ON sl.vehicle_id = v.id
    WHERE sl.user_id = ?
      AND sl.tanggal_berikutnya IS NOT NULL
      AND sl.tanggal_berikutnya <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY sl.tanggal_berikutnya ASC
    LIMIT 5
");
$remind_res->bind_param('i', $user_id);
$remind_res->execute();
$reminders = $remind_res->get_result()->fetch_all(MYSQLI_ASSOC);
$remind_res->close();

function rupiah($n)
{
  return 'Rp ' . number_format($n, 0, ',', '.');
}

function tgl_id($date)
{
  if (!$date) return '-';
  $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
  [$y, $m, $d] = explode('-', $date);
  return "$d {$bulan[(int)$m]} $y";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — ServisKu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
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
      --sidebar: #111318;
      --panel: #13161e;
      --card: #161a24;
      --border: #1f2433;
      --accent: #f97316;
      --accent2: #fb923c;
      --green: #22c55e;
      --blue: #3b82f6;
      --red: #ef4444;
      --yellow: #eab308;
      --text: #e8eaf0;
      --muted: #6b7280;
      --input-bg: #1a1e2a;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
    }

    /* ── Sidebar ── */
    .sidebar {
      width: 240px;
      min-height: 100vh;
      background: var(--sidebar);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 1.5rem 1rem;
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      z-index: 100;
    }

    .sidebar-logo {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.25rem 0.5rem;
      margin-bottom: 2rem;
    }

    .logo-icon {
      width: 36px;
      height: 36px;
      background: var(--accent);
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }

    .logo-text {
      font-family: 'Syne', sans-serif;
      font-size: 1.3rem;
      font-weight: 800;
    }

    .nav-section {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--muted);
      padding: 0 0.5rem;
      margin-bottom: 0.5rem;
      margin-top: 1rem;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      padding: 0.65rem 0.75rem;
      border-radius: 10px;
      color: var(--muted);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: all 0.15s;
      margin-bottom: 0.15rem;
    }

    .nav-link:hover,
    .nav-link.active {
      background: rgba(249, 115, 22, 0.12);
      color: var(--accent);
    }

    .nav-link .icon {
      font-size: 1.1rem;
      width: 22px;
      text-align: center;
    }

    .sidebar-footer {
      margin-top: auto;
      border-top: 1px solid var(--border);
      padding-top: 1rem;
    }

    .user-card {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      padding: 0.5rem;
    }

    .user-avatar {
      width: 34px;
      height: 34px;
      background: var(--accent);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 0.9rem;
      color: #fff;
      flex-shrink: 0;
    }

    .user-name {
      font-size: 0.85rem;
      font-weight: 500;
    }

    .user-role {
      font-size: 0.72rem;
      color: var(--muted);
    }

    .btn-logout {
      display: block;
      text-align: center;
      margin-top: 0.75rem;
      padding: 0.5rem;
      background: rgba(239, 68, 68, 0.1);
      color: #fca5a5;
      border-radius: 8px;
      text-decoration: none;
      font-size: 0.8rem;
      transition: all 0.15s;
    }

    .btn-logout:hover {
      background: rgba(239, 68, 68, 0.2);
    }

    /* ── Main Content ── */
    .main {
      margin-left: 240px;
      flex: 1;
      padding: 2rem 2.5rem;
      min-height: 100vh;
    }

    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
    }

    .page-title {
      font-family: 'Syne', sans-serif;
      font-size: 1.8rem;
      font-weight: 800;
    }

    .page-date {
      color: var(--muted);
      font-size: 0.875rem;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.6rem 1.2rem;
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.15s;
      border: none;
    }

    .btn-primary {
      background: var(--accent);
      color: #fff;
    }

    .btn-primary:hover {
      background: var(--accent2);
      box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
    }

    .btn-sm {
      padding: 0.35rem 0.75rem;
      font-size: 0.8rem;
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

    .btn-danger {
      background: rgba(239, 68, 68, 0.15);
      color: #fca5a5;
    }

    .btn-danger:hover {
      background: rgba(239, 68, 68, 0.25);
    }

    /* ── Stat Cards ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 1.25rem 1.5rem;
      position: relative;
      overflow: hidden;
    }

    .stat-card::after {
      content: attr(data-icon);
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 2.5rem;
      opacity: 0.12;
    }

    .stat-label {
      font-size: 0.75rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin-bottom: 0.5rem;
    }

    .stat-value {
      font-family: 'Syne', sans-serif;
      font-size: 1.7rem;
      font-weight: 800;
    }

    .stat-value.orange {
      color: var(--accent);
    }

    .stat-value.green {
      color: var(--green);
    }

    .stat-value.blue {
      color: var(--blue);
    }

    /* ── Reminders ── */
    .reminder-banner {
      background: linear-gradient(135deg, rgba(234, 179, 8, 0.12), rgba(249, 115, 22, 0.08));
      border: 1px solid rgba(234, 179, 8, 0.25);
      border-radius: 14px;
      padding: 1rem 1.25rem;
      margin-bottom: 2rem;
    }

    .reminder-title {
      font-weight: 600;
      font-size: 0.9rem;
      color: #fef08a;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .reminder-item {
      font-size: 0.85rem;
      color: var(--text);
      padding: 0.2rem 0;
    }

    .reminder-item span {
      color: var(--yellow);
      font-weight: 500;
    }

    /* ── Two Column ── */
    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    /* ── Section ── */
    .section {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow: hidden;
    }

    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.1rem 1.5rem;
      border-bottom: 1px solid var(--border);
    }

    .section-title {
      font-family: 'Syne', sans-serif;
      font-size: 1rem;
      font-weight: 700;
    }

    /* ── Vehicle List ── */
    .vehicle-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }

    .vehicle-item:last-child {
      border-bottom: none;
    }

    .vehicle-item:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .vehicle-icon {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      flex-shrink: 0;
    }

    .vehicle-icon.motor {
      background: rgba(249, 115, 22, 0.15);
    }

    .vehicle-icon.mobil {
      background: rgba(59, 130, 246, 0.15);
    }

    .vehicle-name {
      font-weight: 500;
      font-size: 0.9rem;
    }

    .vehicle-plate {
      font-size: 0.78rem;
      color: var(--muted);
      font-family: monospace;
    }

    .vehicle-actions {
      margin-left: auto;
      display: flex;
      gap: 0.4rem;
    }

    .badge {
      padding: 0.2rem 0.5rem;
      border-radius: 6px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .badge-motor {
      background: rgba(249, 115, 22, 0.15);
      color: var(--accent);
    }

    .badge-mobil {
      background: rgba(59, 130, 246, 0.15);
      color: var(--blue);
    }

    /* ── Log Table ── */
    .section.full {
      margin-bottom: 2rem;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th {
      text-align: left;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--muted);
      padding: 0.75rem 1.5rem;
      border-bottom: 1px solid var(--border);
      font-weight: 500;
    }

    td {
      padding: 0.85rem 1.5rem;
      border-bottom: 1px solid var(--border);
      font-size: 0.875rem;
      vertical-align: middle;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: rgba(255, 255, 255, 0.015);
    }

    .service-type {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
    }

    .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .dot-oli {
      background: var(--accent);
    }

    .dot-ban {
      background: var(--blue);
    }

    .dot-lain {
      background: var(--green);
    }

    .biaya-cell {
      font-family: 'Syne', sans-serif;
      font-weight: 600;
      color: var(--green);
    }

    .empty-state {
      padding: 3rem;
      text-align: center;
      color: var(--muted);
    }

    .empty-state .emoji {
      font-size: 2.5rem;
      margin-bottom: 0.75rem;
    }

    @media (max-width: 1100px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .two-col {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        display: none;
      }

      .main {
        margin-left: 0;
        padding: 1.25rem;
      }
    }
  </style>
</head>

<body>

  <!-- ── Sidebar ─────────────────────────────────────────────────────────────── -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🔧</div>
      <span class="logo-text">ServisKu</span>
    </div>

    <span class="nav-section">Menu</span>
    <a href="dashboard.php" class="nav-link active">
      <span class="icon">📊</span> Dashboard
    </a>
    <a href="Add vehicle.php" class="nav-link">
      <span class="icon">🚗</span> Tambah Kendaraan
    </a>
    <a href="add_service.php" class="nav-link">
      <span class="icon">🔩</span> Catat Servis
    </a>

    <div class="sidebar-footer">
      <div class="user-card">
        <div class="user-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($username) ?></div>
          <div class="user-role">Member</div>
        </div>
      </div>
      <a href="logout.php" class="btn-logout">🚪 Keluar</a>
    </div>
  </aside>

  <!-- ── Main ─────────────────────────────────────────────────────────────────── -->
  <main class="main">

    <div class="page-header">
      <div>
        <h1 class="page-title">Halo, <?= htmlspecialchars($username) ?>! 👋</h1>
        <p class="page-date"><?= date('l, d F Y') ?></p>
      </div>
      <a href="Add_service.php" class="btn btn-primary">+ Catat Servis</a>
    </div>

    <!-- ── Stats ── -->
    <div class="stats-grid">
      <div class="stat-card" data-icon="🚗">
        <div class="stat-label">Total Kendaraan</div>
        <div class="stat-value orange"><?= count($vehicles) ?></div>
      </div>
      <div class="stat-card" data-icon="🔧">
        <div class="stat-label">Total Servis</div>
        <div class="stat-value blue"><?= (int)$stats['total_servis'] ?></div>
      </div>
      <div class="stat-card" data-icon="💰">
        <div class="stat-label">Total Biaya</div>
        <div class="stat-value green" style="font-size:1.2rem"><?= rupiah($stats['total_biaya'] ?? 0) ?></div>
      </div>
      <div class="stat-card" data-icon="📅">
        <div class="stat-label">Servis Terakhir</div>
        <div class="stat-value" style="font-size:1.1rem;color:var(--text)"><?= tgl_id($stats['servis_terakhir']) ?></div>
      </div>
    </div>

    <!-- ── Reminders ── -->
    <?php if (!empty($reminders)): ?>
      <div class="reminder-banner">
        <div class="reminder-title">⚠️ Pengingat Servis</div>
        <?php foreach ($reminders as $r): ?>
          <?php $sisa = (strtotime($r['tanggal_berikutnya']) - time()) / 86400; ?>
          <div class="reminder-item">
            • <?= htmlspecialchars($r['nama_kendaraan']) ?> (<?= $r['plat_nomor'] ?>)
            — <b><?= htmlspecialchars($r['jenis_servis']) ?></b> jadwal
            <span><?= tgl_id($r['tanggal_berikutnya']) ?></span>
            <?php if ($sisa < 0): ?>
              <span style="color:var(--red)"> (Terlambat <?= abs((int)$sisa) ?> hari!)</span>
            <?php elseif ($sisa <= 7): ?>
              <span style="color:var(--yellow)"> (<?= (int)$sisa ?> hari lagi)</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- ── Two Column: Kendaraan + Servis Terakhir ── -->
    <div class="two-col">

      <!-- Daftar Kendaraan -->
      <div class="section">
        <div class="section-header">
          <span class="section-title">Kendaraan Saya</span>
          <a href="Add_vehicle.php" class="btn btn-ghost btn-sm">+ Tambah</a>
        </div>
        <?php if (empty($vehicles)): ?>
          <div class="empty-state">
            <div class="emoji">🚗</div>
            <p>Belum ada kendaraan.<br>Tambah kendaraan dulu!</p>
          </div>
        <?php else: ?>
          <?php foreach ($vehicles as $v): ?>
            <div class="vehicle-item">
              <div class="vehicle-icon <?= $v['jenis'] ?>">
                <?= $v['jenis'] === 'motor' ? '🏍️' : '🚙' ?>
              </div>
              <div>
                <div class="vehicle-name"><?= htmlspecialchars($v['nama']) ?></div>
                <div class="vehicle-plate"><?= htmlspecialchars($v['plat_nomor']) ?></div>
              </div>
              <span class="badge badge-<?= $v['jenis'] ?>"><?= $v['jenis'] ?></span>
              <div class="vehicle-actions">
                <a href="Add_service.php?vid=<?= $v['id'] ?>" class="btn btn-sm btn-primary">+ Servis</a>
                <a href="delete_vehicle.php?id=<?= $v['id'] ?>"
                  class="btn btn-sm btn-danger"
                  onclick="return confirm('Hapus kendaraan ini? Semua riwayat servisnya juga akan terhapus.')">🗑</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Quick Summary per kendaraan -->
      <div class="section">
        <div class="section-header">
          <span class="section-title">KM Terakhir per Kendaraan</span>
        </div>
        <?php if (empty($vehicles)): ?>
          <div class="empty-state">
            <div class="emoji">📊</div>
            <p>Belum ada data.</p>
          </div>
        <?php else: ?>
          <?php foreach ($vehicles as $v):
            $last = $conn->prepare("SELECT km_saat_servis, jenis_servis, tanggal_servis FROM service_logs WHERE vehicle_id = ? ORDER BY tanggal_servis DESC LIMIT 1");
            $last->bind_param('i', $v['id']);
            $last->execute();
            $lastRow = $last->get_result()->fetch_assoc();
            $last->close();
          ?>
            <div class="vehicle-item">
              <div class="vehicle-icon <?= $v['jenis'] ?>"><?= $v['jenis'] === 'motor' ? '🏍️' : '🚙' ?></div>
              <div>
                <div class="vehicle-name"><?= htmlspecialchars($v['nama']) ?></div>
                <?php if ($lastRow): ?>
                  <div class="vehicle-plate">KM <?= number_format($lastRow['km_saat_servis'], 0, ',', '.') ?> &bull; <?= tgl_id($lastRow['tanggal_servis']) ?></div>
                <?php else: ?>
                  <div class="vehicle-plate" style="color:var(--muted)">Belum ada catatan servis</div>
                <?php endif; ?>
              </div>
              <?php if ($lastRow): ?>
                <span style="font-size:0.78rem;color:var(--muted);"><?= htmlspecialchars($lastRow['jenis_servis']) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

    <!-- ── Riwayat Servis Lengkap ── -->
    <div class="section full">
      <div class="section-header">
        <span class="section-title">Riwayat Servis Terbaru</span>
      </div>
      <?php if (empty($logs)): ?>
        <div class="empty-state">
          <div class="emoji">📋</div>
          <p>Belum ada catatan servis.<br>
            <a href="Add_service.php" style="color:var(--accent)">Catat servis pertama kamu →</a>
          </p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto">
          <table>
            <thead>
              <tr>
                <th>Kendaraan</th>
                <th>Tanggal</th>
                <th>KM</th>
                <th>Jenis Servis</th>
                <th>Biaya</th>
                <th>Servis Berikutnya</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $l):
                $dotClass = str_contains(strtolower($l['jenis_servis']), 'oli') ? 'dot-oli' : (str_contains(strtolower($l['jenis_servis']), 'ban') ? 'dot-ban' : 'dot-lain');
              ?>
                <tr>
                  <td>
                    <div style="font-weight:500"><?= htmlspecialchars($l['nama_kendaraan']) ?></div>
                    <div style="font-size:0.75rem;color:var(--muted);font-family:monospace"><?= htmlspecialchars($l['plat_nomor']) ?></div>
                  </td>
                  <td><?= tgl_id($l['tanggal_servis']) ?></td>
                  <td style="font-family:'Syne',sans-serif;font-weight:600"><?= number_format($l['km_saat_servis'], 0, ',', '.') ?> km</td>
                  <td>
                    <div class="service-type">
                      <span class="dot <?= $dotClass ?>"></span>
                      <?= htmlspecialchars($l['jenis_servis']) ?>
                    </div>
                  </td>
                  <td class="biaya-cell"><?= rupiah($l['biaya']) ?></td>
                  <td style="font-size:0.8rem;color:var(--muted)">
                    <?php if ($l['tanggal_berikutnya']): ?>
                      <?= tgl_id($l['tanggal_berikutnya']) ?>
                      <?php if ($l['km_berikutnya']): ?>
                        <br>KM <?= number_format($l['km_berikutnya'], 0, ',', '.') ?>
                      <?php endif; ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex;gap:0.4rem">
                      <a href="edit_service.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-ghost">✏️ Edit</a>
                      <a href="delete_service.php?id=<?= $l['id'] ?>"
                        class="btn btn-sm btn-danger"
                        onclick="return confirm('Hapus catatan servis ini?')">🗑</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </main>
</body>

</html>