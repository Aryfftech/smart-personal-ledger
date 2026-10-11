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

// ---- Filters -------------------------------------------------------------------
$filter_month    = isset($_GET['month'])    ? $_GET['month']          : '';
$filter_category = isset($_GET['category']) ? $_GET['category']       : '';
$search          = isset($_GET['q'])        ? trim($_GET['q'])        : '';

$sql = "SELECT * FROM transactions WHERE user_id = ?";
$params = array($user_id);

if ($filter_month !== '' && preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $sql .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $filter_month;
}
if ($filter_category !== '') {
    $sql .= " AND category = ?";
    $params[] = $filter_category;
}
if ($search !== '') {
    $sql .= " AND (merchant LIKE ? OR notes LIKE ?)";
    $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search) . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= " ORDER BY date DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtered_total = 0;
foreach ($transactions as $t) {
    $filtered_total += $t['amount'];
}
$filtering = ($filter_month !== '' || $filter_category !== '' || $search !== '');

// Months that have any transactions, for the month filter
$mstmt = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') AS ym
                        FROM transactions WHERE user_id = ? ORDER BY ym DESC");
$mstmt->execute(array($user_id));
$months = $mstmt->fetchAll(PDO::FETCH_COLUMN);

$categories = get_category_names($pdo);
$colours    = get_category_colours($pdo);

$flash = isset($_GET['msg']) ? $_GET['msg'] : '';
$flash_text = array(
    'added'   => 'Transaction saved.',
    'updated' => 'Changes saved.',
    'deleted' => 'Transaction deleted.',
);

$extra_head = <<<'HTML'
<style>
.summary { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px; margin: 4px 0 14px; }
.summary .count { color: var(--ink-soft); }
.summary .total { font-weight: 800; font-size: 1.15rem; }
.notes-cell { color: var(--ink-muted); font-size: 0.85rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tx-table td.amount { font-weight: 700; }
@media (max-width: 760px) {
    .filters .form-row { flex-basis: 100%; }
    .filters .btn { flex: 1; }
}
</style>
HTML;

layout_start('Transactions', 'transactions', $extra_head);
?>

<header class="page-head">
    <div>
        <h1>Transactions</h1>
        <p>Every purchase you have recorded, newest first. Search by shop, filter by month or category, and edit or delete any entry.</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline" href="add_transaction.php"><?php echo nav_icon('add'); ?>Add manually</a>
        <a class="btn btn-primary" href="upload.php"><?php echo nav_icon('scan'); ?>Scan receipt</a>
    </div>
</header>

<?php if (isset($flash_text[$flash])): ?>
    <div class="notice good" role="status">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>
        <div><?php echo $flash_text[$flash]; ?></div>
    </div>
<?php endif; ?>

<section class="card" style="margin-bottom:18px">
    <form method="GET" class="filters" role="search">
        <div class="form-row">
            <label for="q">Search</label>
            <input class="input" type="search" id="q" name="q" placeholder="Shop or note"
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="form-row">
            <label for="month">Month</label>
            <select class="select" id="month" name="month">
                <option value="">All months</option>
                <?php foreach ($months as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m === $filter_month ? 'selected' : ''; ?>>
                        <?php echo date('F Y', strtotime($m . '-01')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="category">Category</label>
            <select class="select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $c === $filter_category ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($filtering): ?>
            <a href="transactions.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>
</section>

<section class="card">
    <div class="summary">
        <span class="count">
            <?php echo count($transactions); ?> transaction<?php echo count($transactions) === 1 ? '' : 's'; ?>
            <?php echo $filtering ? 'match your filters' : 'in total'; ?>
        </span>
        <span class="total num"><?php echo money($filtered_total); ?></span>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="empty">
            <?php if ($filtering): ?>
                <p>No transactions match these filters. Try a different month or category, or clear the search.</p>
                <a class="btn btn-outline" href="transactions.php">Clear filters</a>
            <?php else: ?>
                <p>No transactions yet. Scan a receipt, or add one by hand if you paid cash.</p>
                <div class="actions">
                    <a class="btn btn-primary" href="upload.php">Scan receipt</a>
                    <a class="btn btn-outline" href="add_transaction.php">Add manually</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table tx-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shop</th>
                        <th class="hide-sm">Category</th>
                        <th class="hide-sm">Notes</th>
                        <th class="right">Amount</th>
                        <th class="right"><span class="hide-sm">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <?php $colour = isset($colours[$t['category']]) ? $colours[$t['category']] : '#898781'; ?>
                    <tr>
                        <td class="num nowrap"><?php echo date('d M', strtotime($t['date'])); ?><span class="hide-sm"> <?php echo date('Y', strtotime($t['date'])); ?></span></td>
                        <td>
                            <span class="dot show-sm" style="background: <?php echo $colour; ?>" title="<?php echo htmlspecialchars((string) $t['category']); ?>"></span>
                            <span class="merchant"><?php echo htmlspecialchars((string) $t['merchant']); ?></span>
                            <?php if (!empty($t['receipt_image'])): ?>
                                <a class="receipt-link" href="view_receipt.php?id=<?php echo (int) $t['id']; ?>" title="View receipt">
                                    <?php echo nav_icon('receipt'); ?><span>Receipt</span>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="hide-sm"><span class="cat"><span class="dot" style="background: <?php echo $colour; ?>"></span><?php echo htmlspecialchars((string) $t['category']); ?></span></td>
                        <td class="hide-sm notes-cell" title="<?php echo htmlspecialchars((string) $t['notes']); ?>"><?php echo htmlspecialchars((string) $t['notes']); ?></td>
                        <td class="right num nowrap amount"><?php echo money($t['amount']); ?></td>
                        <td class="right">
                            <span class="row-actions">
                                <a class="icon-btn" href="edit_transaction.php?id=<?php echo (int) $t['id']; ?>"
                                   title="Edit" aria-label="Edit <?php echo htmlspecialchars((string) $t['merchant']); ?>"><?php echo nav_icon('edit'); ?></a>
                                <a class="icon-btn danger" href="delete_transaction.php?id=<?php echo (int) $t['id']; ?>"
                                   title="Delete" aria-label="Delete <?php echo htmlspecialchars((string) $t['merchant']); ?>"
                                   onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes((string) $t['merchant']), ENT_QUOTES); ?> (<?php echo money($t['amount']); ?>)? This can\'t be undone.');"><?php echo nav_icon('delete'); ?></a>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
layout_end();