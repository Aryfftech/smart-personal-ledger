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

$user_id    = $_SESSION['user_id'];
$this_month = date('Y-m');
$categories = get_category_names($pdo);
$colours    = get_category_colours($pdo);

// ---- Save -----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($categories as $cat) {
        $key    = md5($cat);                 // safe form field name (names contain spaces and &)
        $amount = isset($_POST[$key]) ? round(floatval($_POST[$key]), 2) : 0;

        if ($amount > 0) {
            // One row per user per category; update it if it already exists
            $stmt = $pdo->prepare("INSERT INTO budgets (user_id, category, amount)
                                   VALUES (?, ?, ?)
                                   ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
            $stmt->execute(array($user_id, $cat, $amount));
        } else {
            // Zero or blank means the user no longer wants a limit here
            $stmt = $pdo->prepare("DELETE FROM budgets WHERE user_id = ? AND category = ?");
            $stmt->execute(array($user_id, $cat));
        }
    }
    // Redirect so refreshing the page doesn't send the form again
    header('Location: budget.php?saved=1');
    exit;
}
$saved = isset($_GET['saved']);

// ---- Limits and this month's spending ---------------------------------------------
$stmt = $pdo->prepare("SELECT category, amount FROM budgets WHERE user_id = ?");
$stmt->execute(array($user_id));
$budgets = array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

$stmt = $pdo->prepare("SELECT category, SUM(amount) AS total
                       FROM transactions
                       WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
                       GROUP BY category");
$stmt->execute(array($user_id, $this_month));
$spent = array_map('floatval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

$budget_total = array_sum($budgets);
$spent_total  = array_sum($spent);
$left         = $budget_total - $spent_total;
$days_left    = (int) date('t') - (int) date('j') + 1;

function status_of($used, $limit)
{
    if ($used > $limit)        return array('bad',  'Over by ' . money($used - $limit));
    if ($used >= 0.8 * $limit) return array('warn', money($limit - $used) . ' left');
    return array('good', money($limit - $used) . ' left');
}

$icons = array(
    'bad'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V9h2v4Z"/></svg>',
    'warn' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z"/></svg>',
    'good' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>',
);

$extra_head = <<<'HTML'
<style>
.budget-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr); gap: 18px; align-items: start; }

.overall { padding-bottom: 18px; margin-bottom: 8px; border-bottom: 1px solid var(--line); }
.overall-top { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.overall-top .big { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; }
.overall-top .of { color: var(--ink-muted); }
.overall .bar-track { height: 12px; }
.overall .note { margin-top: 10px; font-size: 0.88rem; color: var(--ink-soft); }

.cat-rows { list-style: none; }
.cat-rows li { padding: 14px 0; border-bottom: 1px solid var(--grid); }
.cat-rows li:last-child { border-bottom: none; }
.cat-rows .top { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
.cat-rows .name { display: flex; align-items: center; gap: 10px; font-weight: 600; }
.cat-rows .fig { font-size: 0.88rem; color: var(--ink-soft); }
.cat-rows .fig strong { color: var(--ink); }
.cat-rows .nolimit { font-size: 0.84rem; color: var(--ink-muted); }

.limits { display: grid; gap: 10px; }
.limit-row { display: grid; grid-template-columns: 1fr 140px; gap: 12px; align-items: center; }
.limit-row label { display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 0.92rem; }
.money-input { position: relative; }
.money-input .prefix { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; font-size: 0.85rem; color: var(--ink-soft); pointer-events: none; }
.money-input .input { padding: 10px 12px 10px 42px; text-align: right; font-weight: 700; }
.limits-total { display: flex; justify-content: space-between; padding-top: 12px; margin-top: 4px; border-top: 1px solid var(--line); font-weight: 700; }

@media (max-width: 1000px) { .budget-grid { grid-template-columns: 1fr; } }
</style>
HTML;

layout_start('Budget', 'budget', $extra_head);
?>

<header class="page-head">
    <div>
        <h1>Budget</h1>
        <p>Set a monthly spending limit for each category and track how much of it you have used in <?php echo date('F'); ?>.</p>
    </div>
</header>

<?php if ($saved): ?>
    <div class="notice good" role="status">
        <?php echo $icons['good']; ?><div>Budget saved.</div>
    </div>
<?php endif; ?>

<div class="budget-grid">
    <!-- ======== Progress this month ======== -->
    <section class="card">
        <div class="card-head">
            <h2><?php echo date('F Y'); ?></h2>
            <span class="sub"><?php echo $days_left; ?> day<?php echo $days_left === 1 ? '' : 's'; ?> left</span>
        </div>

        <?php if ($budget_total <= 0): ?>
            <div class="empty">
                <p>No limits set yet. Enter a monthly amount for the categories you want to keep an eye on, then save.</p>
            </div>
        <?php else: ?>
            <?php
                $pct = min(100, 100 * $spent_total / $budget_total);
                $overall_colour = $left < 0 ? 'var(--bad)' : ($pct >= 80 ? '#d99a2b' : 'var(--teal)');
            ?>
            <div class="overall">
                <div class="overall-top">
                    <span class="big num"><?php echo $left >= 0 ? money($left) . ' left' : money(-$left) . ' over'; ?></span>
                    <span class="of num"><?php echo money($spent_total); ?> spent of <?php echo money($budget_total); ?></span>
                </div>
                <div class="bar-track" role="meter" aria-label="Total budget used"
                     aria-valuemin="0" aria-valuemax="<?php echo $budget_total; ?>" aria-valuenow="<?php echo $spent_total; ?>">
                    <div class="bar-fill" style="width: <?php echo round($pct, 1); ?>%; background: <?php echo $overall_colour; ?>"></div>
                </div>
                <div class="note">
                    <?php if ($left > 0): ?>
                        About <strong class="num"><?php echo money($left / $days_left); ?> a day</strong> for the rest of the month.
                    <?php else: ?>
                        This month's total budget is used up.
                    <?php endif; ?>
                    Spending in categories without a limit still counts toward the total.
                </div>
            </div>

            <ul class="cat-rows">
                <?php foreach ($categories as $cat): ?>
                    <?php
                        $limit = isset($budgets[$cat]) ? $budgets[$cat] : 0;
                        $used  = isset($spent[$cat]) ? $spent[$cat] : 0;
                        if ($limit <= 0 && $used <= 0) continue;
                        $colour = isset($colours[$cat]) ? $colours[$cat] : '#898781';
                    ?>
                    <li>
                        <div class="top">
                            <span class="name"><span class="dot" style="background: <?php echo $colour; ?>"></span><?php echo htmlspecialchars($cat); ?></span>
                            <?php if ($limit > 0): ?>
                                <?php list($level, $text) = status_of($used, $limit); ?>
                                <span class="status <?php echo $level; ?>"><?php echo $icons[$level] . $text; ?></span>
                            <?php else: ?>
                                <span class="nolimit">No limit set</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($limit > 0): ?>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: <?php echo min(100, round(100 * $used / $limit, 1)); ?>%; background: <?php echo $used > $limit ? 'var(--bad)' : $colour; ?>"></div>
                            </div>
                            <div class="fig num" style="margin-top:6px"><strong><?php echo money($used); ?></strong> of <?php echo money($limit); ?></div>
                        <?php else: ?>
                            <div class="fig num"><strong><?php echo money($used); ?></strong> spent this month</div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ======== Set limits ======== -->
    <section class="card">
        <div class="card-head"><h2>Monthly limits</h2></div>
        <p class="help" style="margin:-8px 0 16px">Leave a box empty for no limit on that category.</p>
        <form method="POST" action="budget.php">
            <div class="limits">
                <?php foreach ($categories as $cat): ?>
                    <?php $key = md5($cat); $colour = isset($colours[$cat]) ? $colours[$cat] : '#898781'; ?>
                    <div class="limit-row">
                        <label for="b-<?php echo $key; ?>"><span class="dot" style="background: <?php echo $colour; ?>"></span><?php echo htmlspecialchars($cat); ?></label>
                        <div class="money-input">
                            <span class="prefix" aria-hidden="true">RM</span>
                            <input class="input num" type="number" step="0.01" min="0" inputmode="decimal"
                                   id="b-<?php echo $key; ?>" name="<?php echo $key; ?>" placeholder="&ndash;"
                                   value="<?php echo isset($budgets[$cat]) ? number_format($budgets[$cat], 2, '.', '') : ''; ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="limits-total"><span>Total per month</span><span class="num" id="limitsTotal"><?php echo money($budget_total); ?></span></div>
            <div class="form-actions" style="margin-top:18px">
                <button type="submit" class="btn btn-primary">Save budget</button>
            </div>
        </form>
    </section>
</div>

<?php
ob_start();
?>
<script>
// Keep the total under the limits in step with what's typed
(function () {
    const inputs = document.querySelectorAll('.limits input');
    const out = document.getElementById('limitsTotal');
    const update = () => {
        let sum = 0;
        inputs.forEach(i => { const v = parseFloat(i.value); if (v > 0) sum += v; });
        out.textContent = 'RM ' + sum.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    inputs.forEach(i => i.addEventListener('input', update));
})();
</script>
<?php
layout_end(ob_get_clean());