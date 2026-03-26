<?php
// delete_service.php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$id      = (int)($_GET['id'] ?? 0);

if ($id) {
  $conn->query("DELETE FROM service_logs WHERE id=$id AND user_id=$user_id");
}

header('Location: dashboard.php');
exit;
