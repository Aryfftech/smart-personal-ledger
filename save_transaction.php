<?php
session_start();
require_once 'db.php';
require_once 'lib_categories.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$merchant = isset($_POST['merchant']) ? trim($_POST['merchant']) : '';
$amount   = isset($_POST['amount'])   ? floatval($_POST['amount']) : 0;
$date     = isset($_POST['date'])     ? $_POST['date'] : '';
$category = isset($_POST['category']) ? $_POST['category'] : 'Others';
$notes    = isset($_POST['notes'])    ? trim($_POST['notes']) : '';
$image    = isset($_POST['receipt_image']) ? $_POST['receipt_image'] : '';

// Fall back to today when no usable date was given
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if ($merchant === '') {
    $merchant = 'Unknown Merchant';
}

if ($amount <= 0) {
    die('Invalid amount. <a href="javascript:history.back()">Go back</a>');
}

// Only accept a category that exists in the categories table
if (!in_array($category, get_category_names($pdo), true)) {
    $category = 'Others';
}

// The image name comes back from the browser, so it must be checked:
// only the random names upload.php generates are accepted, and the file
// must actually exist. This blocks paths such as ../../db.php.
$receipt_image = null;
if ($image !== ''
    && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $image)
    && is_file(__DIR__ . '/uploads/' . $image)) {
    $receipt_image = $image;
}

// ---- Evaluation data (Section 4.2) ------------------------------------------

// How the transaction was entered. Anything other than the two known values
// is stored as NULL, which leaves the row out of the evaluation figures
// instead of polluting them.
$entry_method = null;
if (isset($_POST['entry_method']) && in_array($_POST['entry_method'], array('ocr', 'manual'), true)) {
    $entry_method = $_POST['entry_method'];
}

// How long entry took. Anything missing, negative or over 30 minutes is
// treated as not measured (for example, a form left open overnight).
$entry_seconds = null;
if (isset($_POST['entry_ms']) && ctype_digit((string) $_POST['entry_ms'])) {
    $ms = (int) $_POST['entry_ms'];
    if ($ms > 0 && $ms <= 30 * 60 * 1000) {
        $entry_seconds = round($ms / 1000, 1);
    }
}

// What OCR originally read, before the user corrected anything.
// Only recorded for scanned receipts.
$ocr_merchant = null;
$ocr_amount   = null;
$ocr_date     = null;
if ($entry_method === 'ocr') {
    $ocr_merchant = isset($_POST['ocr_merchant']) ? substr(trim($_POST['ocr_merchant']), 0, 255) : '';
    if (isset($_POST['ocr_amount']) && is_numeric($_POST['ocr_amount'])) {
        $ocr_amount = round((float) $_POST['ocr_amount'], 2);
    }
    if (isset($_POST['ocr_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['ocr_date'])) {
        $ocr_date = $_POST['ocr_date'];
    }
}

// What the rules suggest for the final, correct merchant name. Worked out
// here on the server rather than trusted from the browser. Comparing it with
// the category actually saved measures categorisation accuracy.
$suggested_category = categorise($pdo, $user_id, $merchant);

$stmt = $pdo->prepare("INSERT INTO transactions
                       (user_id, merchant, amount, date, category, notes, receipt_image,
                        entry_method, entry_seconds, ocr_merchant, ocr_amount, ocr_date, suggested_category)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute(array($user_id, $merchant, $amount, $date, $category, $notes, $receipt_image,
                     $entry_method, $entry_seconds, $ocr_merchant, $ocr_amount, $ocr_date, $suggested_category));

header('Location: transactions.php?msg=added');
exit;
?>