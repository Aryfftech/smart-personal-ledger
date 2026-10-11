<?php
session_start();
require_once 'db.php';
require_once 'lib_categories.php';
require_once 'layout.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Only the owner of the transaction may see its receipt
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
$stmt->execute(array($id, $user_id));
$t = $stmt->fetch(PDO::FETCH_ASSOC);

function receipt_not_found($message)
{
    if (isset($_GET['raw'])) {          // an <img> asked for it: plain 404
        http_response_code(404);
        exit;
    }
    http_response_code(404);
    layout_start('Receipt not found', 'transactions');
    echo '<header class="page-head"><div><h1>Receipt not found</h1><p>' . $message
       . ' <a href="transactions.php">Back to transactions</a></p></div></header>';
    layout_end();
    exit;
}

if (!$t || empty($t['receipt_image'])) {
    receipt_not_found('There is no receipt photo for this transaction.');
}

$path = __DIR__ . '/uploads/' . basename($t['receipt_image']);
if (!is_file($path)) {
    receipt_not_found('The receipt photo file is missing from the uploads folder.');
}

// ?raw=1 streams the image itself; used by the <img> tag below.
// The uploads folder is locked to direct browser access, so this
// ownership-checked route is the only way to reach an image.
if (isset($_GET['raw'])) {
    $types = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

$colours = get_category_colours($pdo);
$colour  = isset($colours[$t['category']]) ? $colours[$t['category']] : '#898781';

$extra_head = <<<'HTML'
<style>
.view-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(280px, 1fr); gap: 18px; align-items: start; }
.receipt-frame { background: #f6fafb; border-radius: 16px; padding: 14px; display: flex; justify-content: center; }
.receipt-frame img { max-width: 100%; max-height: 78vh; object-fit: contain; border-radius: 10px; }
.details { display: grid; grid-template-columns: auto 1fr; gap: 12px 18px; margin: 4px 0 20px; }
.details dt { color: var(--ink-muted); font-size: 0.88rem; }
.details dd { font-weight: 600; text-align: right; }
.details .big { font-size: 1.5rem; font-weight: 800; }
.stack-actions { display: grid; gap: 10px; }
@media (max-width: 1000px) { .view-grid { grid-template-columns: 1fr; } }
</style>
HTML;

layout_start('Receipt', 'transactions', $extra_head);
?>

<header class="page-head">
    <div>
        <h1><?php echo htmlspecialchars((string) $t['merchant']); ?></h1>
        <p>Receipt from <?php echo date('d F Y', strtotime($t['date'])); ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-outline" href="transactions.php"><?php echo nav_icon('back'); ?>Back to transactions</a>
    </div>
</header>

<div class="view-grid">
    <section class="card" aria-label="Receipt photo">
        <div class="receipt-frame">
            <img src="view_receipt.php?id=<?php echo (int) $t['id']; ?>&amp;raw=1"
                 alt="Receipt from <?php echo htmlspecialchars((string) $t['merchant']); ?>">
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2>Recorded details</h2></div>
        <dl class="details">
            <dt>Amount</dt>
            <dd class="big num"><?php echo money($t['amount']); ?></dd>
            <dt>Shop</dt>
            <dd><?php echo htmlspecialchars((string) $t['merchant']); ?></dd>
            <dt>Date</dt>
            <dd class="num"><?php echo date('d M Y', strtotime($t['date'])); ?></dd>
            <dt>Category</dt>
            <dd><span class="cat" style="color:var(--ink)"><span class="dot" style="background: <?php echo $colour; ?>"></span><?php echo htmlspecialchars((string) $t['category']); ?></span></dd>
            <?php if (!empty($t['notes'])): ?>
                <dt>Notes</dt>
                <dd style="font-weight:500"><?php echo htmlspecialchars((string) $t['notes']); ?></dd>
            <?php endif; ?>
        </dl>
        <div class="stack-actions">
            <a class="btn btn-primary" href="edit_transaction.php?id=<?php echo (int) $t['id']; ?>"><?php echo nav_icon('edit'); ?>Edit details</a>
            <a class="btn btn-outline" href="view_receipt.php?id=<?php echo (int) $t['id']; ?>&amp;raw=1" download><?php echo nav_icon('download'); ?>Download photo</a>
        </div>
    </section>
</div>

<?php
layout_end();