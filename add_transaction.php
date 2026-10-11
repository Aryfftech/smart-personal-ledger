<?php
session_start();
require_once 'db.php';
require_once 'lib_categories.php';
require_once 'layout.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Kuala_Lumpur');

$categories = get_category_names($pdo);
$colours    = get_category_colours($pdo);

// The last few entries, so the user can see whether this purchase is
// already recorded before typing it in again.
$stmt = $pdo->prepare("SELECT id, date, merchant, category, amount, entry_method, receipt_image
                       FROM transactions WHERE user_id = ?
                       ORDER BY date DESC, id DESC LIMIT 6");
$stmt->execute(array($_SESSION['user_id']));
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extra_head = <<<'HTML'
<style>
/* ---- Add manually page ---------------------------------------------------- */
.add-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(260px, 1fr); gap: 18px; align-items: start; }
.money-input { position: relative; }
.money-input .prefix {
    position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
    font-weight: 700; color: var(--ink-soft); pointer-events: none;
}
.money-input .input { padding-left: 50px; font-weight: 700; font-size: 1.05rem; }

.recent-list { list-style: none; }
.recent-list li {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2px 12px;
    padding: 11px 0;
    border-bottom: 1px solid var(--grid);
}
.recent-list li:last-child { border-bottom: none; }
.recent-list .rl-name { font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.recent-list .amt { font-weight: 700; text-align: right; }
.recent-list .meta { grid-column: 1 / -1; display: flex; gap: 10px; align-items: center; font-size: 0.8rem; color: var(--ink-muted); }
.recent-list .how {
    padding: 1px 8px; border-radius: 999px; background: var(--field);
    font-weight: 600; font-size: 0.72rem; color: var(--teal);
}
.side-note { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); font-size: 0.88rem; color: var(--ink-soft); }

@media (max-width: 1000px) { .add-grid { grid-template-columns: 1fr; } }
</style>
HTML;

layout_start('Add manually', 'add', $extra_head);
?>

<header class="page-head">
    <div>
        <h1>Add a transaction</h1>
        <p>Type in a purchase yourself. Use this for cash payments, lost receipts, or receipts too faded to scan.</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline" href="upload.php">Scan a receipt instead</a>
    </div>
</header>

<div class="add-grid">
    <section class="card">
        <form id="entryForm" method="POST" action="save_transaction.php">
            <input type="hidden" name="entry_method" value="manual">
            <input type="hidden" name="entry_ms" id="entryMs" value="">

            <div class="form-row">
                <label for="merchant">Shop</label>
                <input class="input" type="text" name="merchant" id="merchant"
                       maxlength="255" autocomplete="off" required>
            </div>

            <div class="form-grid">
                <div class="form-row">
                    <label for="amount">Amount</label>
                    <div class="money-input">
                        <span class="prefix" aria-hidden="true">RM</span>
                        <input class="input num" type="number" step="0.01" min="0.01" inputmode="decimal"
                               name="amount" id="amount" required>
                    </div>
                </div>
                <div class="form-row">
                    <label for="date">Date</label>
                    <input class="input" type="date" name="date" id="date"
                           value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <label for="category">Category</label>
                <select class="select" name="category" id="category">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $c === 'Others' ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help" id="categoryHint">Type the shop name and a category will be suggested.</div>
            </div>

            <div class="form-row">
                <label for="notes">Notes <span style="font-weight:400;color:var(--ink-muted)">(optional)</span></label>
                <textarea class="textarea" name="notes" id="notes" rows="2"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save transaction</button>
                <a href="dashboard.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </section>

    <aside class="card" aria-label="Recently added">
        <div class="card-head">
            <h2>Recently added</h2>
            <a href="transactions.php" class="sub">View all</a>
        </div>
        <?php if ($recent): ?>
            <ul class="recent-list">
                <?php foreach ($recent as $t): ?>
                    <li>
                        <span class="rl-name"><?php echo htmlspecialchars((string) $t['merchant']); ?></span>
                        <span class="amt num"><?php echo money($t['amount']); ?></span>
                        <span class="meta">
                            <span class="num"><?php echo date('d M Y', strtotime($t['date'])); ?></span>
                            <span class="cat"><span class="dot" style="background: <?php echo isset($colours[$t['category']]) ? $colours[$t['category']] : '#898781'; ?>"></span><?php echo htmlspecialchars((string) $t['category']); ?></span>
                            <?php if ($t['entry_method'] === 'ocr' || !empty($t['receipt_image'])): ?>
                                <span class="how">Scanned</span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="side-note">Check this list first so the same purchase isn't recorded twice.</div>
        <?php else: ?>
            <p class="help" style="margin:0">Nothing recorded yet. Your entries will show up here.</p>
        <?php endif; ?>
    </aside>
</div>

<?php
ob_start();
?>
<script>
// ---- Entry timer (Section 4.2 evaluation) ---------------------------------
// Starts at the user's first interaction with the form, stops when they
// press Save. The scan page is timed the same way, so the two compare fairly.
(function () {
    const form = document.getElementById('entryForm');
    let startedAt = null;
    const start = () => { if (startedAt === null) startedAt = Date.now(); };
    ['focusin', 'pointerdown', 'keydown'].forEach(ev => form.addEventListener(ev, start));
    form.addEventListener('submit', () => {
        if (startedAt !== null) {
            document.getElementById('entryMs').value = Date.now() - startedAt;
        }
    });
})();

// ---- Live category suggestion ---------------------------------------------
// Asks the server which rule matches the shop name. Stops overriding once
// the user picks a category themselves.
(function () {
    const merchant = document.getElementById('merchant');
    const select   = document.getElementById('category');
    const hint     = document.getElementById('categoryHint');
    let userPicked = false;
    let timer = null;

    select.addEventListener('change', () => { userPicked = true; hint.textContent = 'Category chosen by you.'; });

    merchant.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const name = merchant.value.trim();
            if (userPicked || name === '') return;
            try {
                const res = await fetch('suggest_category.php?merchant=' + encodeURIComponent(name));
                if (!res.ok) return;
                const s = await res.json();
                select.value = s.category;
                hint.innerHTML = '';
                if (s.keyword) {
                    hint.append('Suggested because the shop name contains ');
                    const b = document.createElement('strong');
                    b.textContent = s.keyword;
                    hint.append(b, ' (' + (s.source === 'yours' ? 'your rule' : 'built-in rule') + ').');
                } else {
                    hint.textContent = 'No rule matched this shop, so it’s set to Others.';
                }
            } catch (e) { /* the suggestion is a convenience; ignore network errors */ }
        }, 250);
    });
})();
</script>
<?php
layout_end(ob_get_clean());