<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Look up the image first, so the file can be removed with the record
$stmt = $pdo->prepare("SELECT receipt_image FROM transactions WHERE id = ? AND user_id = ?");
$stmt->execute(array($id, $user_id));
$image = $stmt->fetchColumn();

// user_id in the WHERE clause stops one user deleting another's record
$stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
$stmt->execute(array($id, $user_id));

if ($stmt->rowCount() > 0 && $image) {
    $path = __DIR__ . '/uploads/' . basename($image);
    if (is_file($path)) {
        unlink($path);
    }
}

header('Location: transactions.php?msg=deleted');
exit;
?>