<?php
session_start();
require_once 'db.php';
require_once 'lib_categories.php';
require_once 'layout.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id    = $_SESSION['user_id'];
$id         = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error      = '';
$categories = get_category_names($pdo);
$posted     = null;

// ---- Save -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = intval(isset($_POST['id']) ? $_POST['id'] : 0);
    $posted = array(
        'merchant' => trim(isset($_POST['merchant']) ? $_POST['merchant'] : ''),
        'amount'   => isset($_POST['amount']) ? $_POST['amount'] : '',
        'date'     => isset($_POST['date']) ? $_POST['date'] : '',
        'category' => isset($_POST['category']) ? $_POST['category'] : 'Others',
        'notes'    => trim(isset($_POST['notes']) ? $_POST['notes'] : ''),
    );

    if ($posted['merchant'] === '') {
        $error = 'Enter the shop name.';
    } elseif (!is_numeric($posted['amount']) || (float) $posted['amount'] <= 0) {
        $error = 'Enter an amount greater than RM 0.00.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $posted['date'])) {
        $error = 'Choose a valid date.';
    } else {
        if (!in_array($posted['category'], $categories, true)) {
            $posted['category'] = 'Others';
        }
        // user_id in the WHERE clause stops one user editing another's record
        $stmt = $pdo->prepare("UPDATE transactions
                               SET merchant = ?, amount = ?, date = ?, category = ?, notes = ?
                               WHERE id = ? AND user_id = ?");
        $stmt->execute(array($posted['merchant'], round((float) $posted['amount'], 2), $posted['date'],
                             $posted['category'], $posted['notes'], $id, $user_id));
        header('Location: transactions.php?msg=updated');
        exit;
    }
}

// ---- Load the record ---------------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
$stmt->execute(array($id, $user_id));
$t = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$t) {
    layout_start('Transaction not found', 'transactions');
    ?>
    <header class="page-head"><div><h1>Transaction not found</h1>
        <p>It may have been deleted. <a href="transactions.php">Back to transactions</a></p></div></header>
    <?php
    layout_end();
    exit;
}

// After a failed save, show what the user typed rather than the stored values
$v = $posted ? array_merge($t, $posted) : $t;

$extra_head = <<<'HTML'
<style>
.edit-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 18px; align-items: start; }
.edit-grid.single { grid-template-columns: minmax(0, 720px); }
.receipt-frame { background: #f6fafb; border-radius: 16px; padding: 12px; display: flex; justify-content: center; }
.receipt-frame img { max-width: 100%; max-height: 620px; object-fit: contain; border-radius: 10px; }
.money-input { position: relative; }
.money-input .prefix { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--ink-soft); pointer-events: none; }
.money-input .input { padding-left: 50px; font-weight: 700; }
.origin { font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 16px; }
@media (max-width: 1000px) { .edit-grid { grid-template-columns: 1fr; } }
</style>
HTML;

layout_start('Edit transaction', 'transactions', $extra_head);
?>

<header class="page-head">
    <div>
        <h1>Edit transaction</h1>
        <p><?php echo htmlspecialchars((string) $t['merchant']); ?>, <?php echo date('d M Y', strtotime($t['date'])); ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-outline" href="transactions.php"><?php echo nav_icon('back'); ?>Back to transactions</a>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice bad" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V9h2v4Z"/></svg>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<div class="edit-grid <?php echo empty($t['receipt_image']) ? 'single' : ''; ?>">
    <section class="card">
        <div class="origin">
            <?php if ($t['entry_method'] === 'ocr' || !empty($t['receipt_image'])): ?>
                Added by scanning a receipt.
            <?php elseif ($t['entry_method'] === 'manual'): ?>
                Added manually.
            <?php endif; ?>
        </div>
        <form method="POST" action="edit_transaction.php?id=<?php echo (int) $t['id']; ?>">
            <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">

            <div class="form-row">
                <label for="merchant">Shop</label>
                <input class="input" type="text" id="merchant" name="merchant" maxlength="255"
                       value="<?php echo htmlspecialchars((string) $v['merchant']); ?>" required>
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label for="amount">Amount</label>
                    <div class="money-input">
                        <span class="prefix" aria-hidden="true">RM</span>
                        <input class="input num" type="number" step="0.01" min="0.01" inputmode="decimal"
                               id="amount" name="amount" value="<?php echo htmlspecialchars((string) $v['amount']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <label for="date">Date</label>
                    <input class="input" type="date" id="date" name="date"
                           value="<?php echo htmlspecialchars((string) $v['date']); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <label for="category">Category</label>
                <select class="select" id="category" name="category">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $c === $v['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="notes">Notes <span style="font-weight:400;color:var(--ink-muted)">(optional)</span></label>
                <textarea class="textarea" id="notes" name="notes" rows="2"><?php echo htmlspecialchars((string) $v['notes']); ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="transactions.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </section>

    <?php if (!empty($t['receipt_image'])): ?>
        <section class="card" aria-label="Receipt">
            <div class="card-head">
                <h2>Receipt</h2>
                <a class="sub" href="view_receipt.php?id=<?php echo (int) $t['id']; ?>">Open full size</a>
            </div>
            <div class="receipt-frame">
                <img src="view_receipt.php?id=<?php echo (int) $t['id']; ?>&amp;raw=1" alt="Receipt from <?php echo htmlspecialchars((string) $t['merchant']); ?>">
            </div>
        </section>
    <?php endif; ?>
</div>

<?php
layout_end();