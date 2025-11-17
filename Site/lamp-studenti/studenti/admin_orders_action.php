<?php
session_start();
require __DIR__ . '/config.php';

// verificăm login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// verificăm rolul din DB
$stmtUser = $mysqli->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmtUser->bind_param('i', $currentUserId);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
$userRow = $resUser ? $resUser->fetch_assoc() : null;
$stmtUser->close();

if (!$userRow || $userRow['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

// acceptăm doar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders_admin.php');
    exit;
}

$orderId   = (int)($_POST['order_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

$allowedStatuses = ['in_proces', 'livrat', 'anulat'];

if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    header('Location: orders_admin.php?err=1');
    exit;
}

$stmt = $mysqli->prepare("
    UPDATE orders
    SET status = ?, updated_at = NOW()
    WHERE id = ?
");
if ($stmt) {
    $stmt->bind_param('si', $newStatus, $orderId);
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: orders_admin.php?ok=1');
        exit;
    } else {
        $stmt->close();
        header('Location: orders_admin.php?err=1');
        exit;
    }
} else {
    header('Location: orders_admin.php?err=1');
    exit;
}
