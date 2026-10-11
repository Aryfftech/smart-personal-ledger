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
$error   = '';
$notice  = '';
$test_merchant = '';
$test_result   = null;

$categories = get_categories($pdo);
$category_ids = array();
foreach ($categories as $c) {
    $category_ids[(int) $c['id']] = $c['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // ---- Add a rule of the user's own ------------------------------------
    if ($action === 'add') {
        $keyword     = strtolower(trim(isset($_POST['keyword']) ? $_POST['keyword'] : ''));
        $keyword     = preg_replace('/\s+/', ' ', $keyword);
        $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

        if (strlen($keyword) < 2 || strlen($keyword) > 50) {
            $error = 'Keyword must be between 2 and 50 characters.';
        } elseif (!isset($category_ids[$category_id])) {
            $error = 'Please choose a valid category.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM category_rules WHERE user_id = ? AND keyword = ?");
            $stmt->execute(array($user_id, $keyword));
            if ($stmt->fetch()) {
                $error = 'You already have a rule for "' . $keyword . '". Delete it first to change its category.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO category_rules (category_id, user_id, keyword) VALUES (?, ?, ?)");
                $stmt->execute(array($category_id, $user_id, $keyword));
                $notice = 'Rule added: merchants containing "' . $keyword . '" go to ' . $category_ids[$category_id] . '.';
            }
        }
    }

    // ---- Delete one of the user's own rules -------------------------------
    // user_id in the WHERE clause means built-in rules (user_id NULL) and
    // other users' rules can never be deleted from here.
    if ($action === 'delete') {
        $rule_id = isset($_POST['rule_id']) ? (int) $_POST['rule_id'] : 0;
        $stmt = $pdo->prepare("DELETE FROM category_rules WHERE id = ? AND user_id = ?");
        $stmt->execute(array($rule_id, $user_id));
        $notice = $stmt->rowCount() ? 'Rule deleted.' : '';
    }

    // ---- Try a merchant name against the rules -----------------------------
    if ($action === 'test') {
        $test_merchant = trim(isset($_POST['merchant']) ? $_POST['merchant'] : '');
        if ($test_merchant !== '') {
            $test_result = categorise_merchant($pdo, $user_id, $test_merchant);
        }
    }
}

// ---- Load rules: this user's own, plus the built-in ones --------------------
$stmt = $pdo->prepare(
    "SELECT r.id, r.keyword, r.user_id, r.category_id
     FROM category_rules r
     WHERE r.user_id = ? OR r.user_id IS NULL
     ORDER BY r.keyword"
);
$stmt->execute(array($user_id));
$rules_by_category = array();
$own_count = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $rules_by_category[(int) $r['category_id']][] = $r;
    if ($r['user_id'] !== null) {
        $own_count++;
    }
}

$extra_head = <<<'HTML'
<style>
.rules-grid { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr); gap: 18px; align-items: start; }
.cat-block { padding: 16px 0; border-bottom: 1px solid var(--grid); }
.cat-block:first-of-type { padding-top: 4px; }
.cat-block:last-child { border-bottom: none; padding-bottom: 0; }
.cat-block h3 { display: flex; align-items: baseline; gap: 10px; font-size: 0.95rem; margin-bottom: 10px; }
.cat-block h3 .count { font-weight: 500; font-size: 0.8rem; color: var(--ink-muted); }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.rule {
    display: inline-flex; align-items: center; gap: 2px;
    padding: 4px 11px; border-radius: 999px;
    font-size: 0.84rem; color: var(--ink-soft);
    background: var(--field); border: 1px solid transparent;
}
.rule.own { background: #e3f5f9; border-color: #9fd6e3; color: var(--teal-deep); font-weight: 600; }
.rule form { display: inline; margin: 0; }
.rule button {
    border: 0; background: none; cursor: pointer;
    width: 20px; height: 20px; margin-right: -6px; border-radius: 50%;
    display: inline-grid; place-items: center; color: var(--ink-muted);
}
.rule button:hover { background: #fff; color: var(--bad); }
.rule button svg { width: 14px; height: 14px; fill: currentColor; }
.legend { display: flex; gap: 8px; align-items: center; font-size: 0.8rem; color: var(--ink-muted); }
.legend .rule { padding: 2px 9px; font-size: 0.76rem; }
.test-result {
    margin-top: 14px; padding: 14px 16px; border-radius: var(--radius);
    background: #f2fafc; border: 1px solid #cfe9ef;
}
.test-result .big { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1.05rem; }
.test-result p { font-size: 0.86rem; color: var(--ink-soft); margin-top: 4px; }
@media (max-width: 1000px) { .rules-grid { grid-template-columns: 1fr; } }
</style>
HTML;

$x_icon  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7l-1.4-1.4L9.2 12 2.9 5.7l1.4-1.4 6.3 6.3 6.3-6.3z"/></svg>';

layout_start('Category rules', 'rules', $extra_head);
?>

<header class="page-head">
    <div>
        <h1>Category rules</h1>
        <p>Smart Ledger picks a category by looking for keywords in the shop name. Use Find Category to check a shop, or Insert New Category to add your own keyword.</p>
    </div>
</header>

<?php if ($error): ?>
    <div class="notice bad" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V9h2v4Z"/></svg>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>
<?php if ($notice): ?>
    <div class="notice good" role="status">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>
        <div><?php echo htmlspecialchars($notice); ?></div>
    </div>
<?php endif; ?>

<div class="rules-grid">
    <section class="card">
        <div class="card-head">
            <h2>Keywords by category</h2>
            <span class="legend"><span class="rule own">yours</span><span class="rule">built-in</span></span>
        </div>
        <?php foreach ($categories as $c): ?>
            <?php $cid = (int) $c['id']; $list = isset($rules_by_category[$cid]) ? $rules_by_category[$cid] : array(); ?>
            <div class="cat-block">
                <h3>
                    <?php echo htmlspecialchars($c['name']); ?>
                    <span class="count"><?php echo count($list); ?> keyword<?php echo count($list) === 1 ? '' : 's'; ?></span>
                </h3>
                <?php if (!$list): ?>
                    <p class="help" style="margin:0">
                        <?php echo $c['name'] === 'Others'
                            ? 'Anything that matches no keyword ends up here.'
                            : 'No keywords yet.'; ?>
                    </p>
                <?php else: ?>
                    <div class="chips">
                        <?php foreach ($list as $r): ?>
                            <?php if ($r['user_id'] !== null): ?>
                                <span class="rule own">
                                    <?php echo htmlspecialchars($r['keyword']); ?>
                                    <form method="POST" onsubmit="return confirm('Delete the keyword &quot;<?php echo htmlspecialchars(addslashes($r['keyword']), ENT_QUOTES); ?>&quot;?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="rule_id" value="<?php echo (int) $r['id']; ?>">
                                        <button type="submit" title="Delete keyword" aria-label="Delete keyword <?php echo htmlspecialchars($r['keyword']); ?>"><?php echo $x_icon; ?></button>
                                    </form>
                                </span>
                            <?php else: ?>
                                <span class="rule"><?php echo htmlspecialchars($r['keyword']); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Find Category</h2></div>
            <form method="POST">
                <input type="hidden" name="action" value="test">
                <div class="form-row">
                    <label for="test-merchant">Category</label>
                    <input class="input" type="text" id="test-merchant" name="merchant"
                           value="<?php echo htmlspecialchars($test_merchant); ?>" required>
                </div>
                <button type="submit" class="btn btn-outline">Check category</button>
            </form>
            <?php if ($test_result !== null): ?>
                <div class="test-result" role="status">
                    <div class="big">
                        <?php echo htmlspecialchars($test_result['category']); ?>
                    </div>
                    <p>
                        <?php if ($test_result['keyword'] !== null): ?>
                            Matched the keyword <strong><?php echo htmlspecialchars($test_result['keyword']); ?></strong>
                            (<?php echo $test_result['source'] === 'yours' ? 'your rule' : 'built-in rule'; ?>).
                        <?php else: ?>
                            No keyword matched, so it falls back to Others.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h2>Insert New Category</h2></div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <label for="keyword">New Category</label>
                    <input class="input" type="text" id="keyword" name="keyword" maxlength="50" required>
                </div>
                <div class="form-row">
                    <label for="category_id">put it in</label>
                    <select class="select" id="category_id" name="category_id">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Insert</button>
            </form>
            <p class="help" style="margin-top:14px">
                You have <?php echo $own_count; ?> keyword<?php echo $own_count === 1 ? '' : 's'; ?> of your own.
                Yours are checked before the built-in ones, so a keyword of yours can override a default.
                Built-in keywords can't be deleted.
            </p>
        </section>
    </div>
</div>

<?php
layout_end();