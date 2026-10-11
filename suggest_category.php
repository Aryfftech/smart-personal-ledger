<?php
// Returns the rule-based category suggestion for a merchant name as JSON.
// Used by add_transaction.php to suggest a category while the user types.
session_start();
require_once 'db.php';
require_once 'lib_categories.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array('error' => 'Not logged in'));
    exit;
}

$merchant = isset($_GET['merchant']) ? substr(trim($_GET['merchant']), 0, 255) : '';
echo json_encode(categorise_merchant($pdo, $_SESSION['user_id'], $merchant));