<?php
// delete_vehicle.php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$id      = (int)($_GET['id'] ?? 0);

if ($id) {
  // service_logs akan terhapus otomatis via ON DELETE CASCADE
  $conn->query("DELETE FROM vehicles WHERE id=$id AND user_id=$user_id");
}

header('Location: dashboard.php');
exit;
