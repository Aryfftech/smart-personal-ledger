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
$last_month = date('Y-m', strtotime('first day of last month'));
$month_name = date('F');
$last_name  = date('F', strtotime('first day of last month'));

// ---- This month and last month ----------------------------------------------
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n
                       FROM transactions
                       WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
$stmt->execute(array($user_id, $this_month));
$now = $stmt->fetch(PDO::FETCH_ASSOC);
$spent = (float) $now['total'];
$count = (int) $now['n'];

$stmt->execute(array($user_id, $last_month));
$prev = $stmt->fetch(PDO::FETCH_ASSOC);
$spent_last = (float) $prev['total'];
$count_last = (int) $prev['n'];

// ---- By category, this month ---------------------------------------------------
$stmt = $pdo->prepare("SELECT category, SUM(amount) AS total, COUNT(*) AS n
                       FROM transactions
                       WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
                       GROUP BY category
                       ORDER BY total DESC");
$stmt->execute(array($user_id, $this_month));
$by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Last six months ---------------------------------------------------------------
$start = date('Y-m-01', strtotime('first day of -5 months'));
$stmt = $pdo->prepare("SELECT DATE_FORMAT(date, '%Y-%m') AS ym, SUM(amount) AS total
                       FROM transactions
                       WHERE user_id = ? AND date >= ?
                       GROUP BY ym");
$stmt->execute(array($user_id, $start));
$by_month = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$trend_labels = array();
$trend_values = array();
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("first day of -$i months");
    $key = date('Y-m', $ts);
    $trend_labels[] = date('M', $ts);
    $trend_values[] = isset($by_month[$key]) ? round((float) $by_month[$key], 2) : 0;
}

// ---- Budgets ---------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT category, amount FROM budgets WHERE user_id = ?");
$stmt->execute(array($user_id));
$budgets = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$budget_total = array_sum(array_map('floatval', $budgets));

$spent_by_cat = array();
foreach ($by_category as $row) {
    $spent_by_cat[$row['category']] = (float) $row['total'];
}
$over = array();          // categories past their limit
$near = array();          // categories past 80% of their limit
foreach ($budgets as $cat => $limit) {
    $limit = (float) $limit;
    $used  = isset($spent_by_cat[$cat]) ? $spent_by_cat[$cat] : 0;
    if ($limit > 0 && $used > $limit) {
        $over[] = array($cat, $used - $limit);
    } elseif ($limit > 0 && $used >= 0.8 * $limit) {
        $near[] = array($cat, $limit - $used);
    }
}

// Days left in the month, today included, and what that allows per day
$days_in_month = (int) date('t');
$days_left     = $days_in_month - (int) date('j') + 1;
$left          = $budget_total - $spent;
$per_day       = ($budget_total > 0 && $left > 0) ? $left / $days_left : 0;
$used_pct      = $budget_total > 0 ? min(100, 100 * $spent / $budget_total) : 0;
$month_pct     = 100 * ((int) date('j') - 1) / $days_in_month;   // how far through the month we are

// ---- Recent transactions -----------------------------------------------------------
$stmt = $pdo->prepare("SELECT id, date, merchant, category, amount, receipt_image
                       FROM transactions WHERE user_id = ?
                       ORDER BY date DESC, id DESC LIMIT 8");
$stmt->execute(array($user_id));
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

$colours = get_category_colours($pdo);
$colour_of = function ($cat) use ($colours) {
    return isset($colours[$cat]) ? $colours[$cat] : '#898781';
};

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$first_name = strtok(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '', ' ');

layout_start('Dashboard', 'dashboard');
?>

<header class="page-head">
    <div>
        <h1><?php echo $greeting . ($first_name ? ', ' . htmlspecialchars($first_name) : ''); ?></h1>
        <p>Here's where your money went in <?php echo $month_name . ' ' . date('Y'); ?>: your total spending, the budget you have left, and a breakdown by category and month.</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline" href="add_transaction.php"><?php echo nav_icon('add'); ?>Add manually</a>
        <a class="btn btn-primary" href="upload.php"><?php echo nav_icon('scan'); ?>Scan receipt</a>
    </div>
</header>

<?php foreach ($over as $o): ?>
    <div class="notice bad" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22L12 2Zm1 15h-2v-2h2v2Zm0-4h-2V9h2v4Z"/></svg>
        <div><strong><?php echo htmlspecialchars($o[0]); ?> is over budget</strong> by <?php echo money($o[1]); ?> this month.
            <a href="budget.php">Review budget</a></div>
    </div>
<?php endforeach; ?>
<?php foreach ($near as $n): ?>
    <div class="notice warn">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z"/></svg>
        <div><strong><?php echo htmlspecialchars($n[0]); ?> is close to its limit</strong>, with <?php echo money($n[1]); ?> left this month.</div>
    </div>
<?php endforeach; ?>

<!-- ============ Hero: this month ============ -->
<section class="hero" aria-label="This month">
    <div class="hero-main">
        <div class="hero-label">Spent in <?php echo $month_name; ?></div>
        <div class="hero-amount num"><?php echo money($spent); ?></div>
        <div class="hero-compare">
            <?php if ($count_last === 0): ?>
                Nothing recorded in <?php echo $last_name; ?> to compare with.
            <?php elseif (abs($spent - $spent_last) < 0.005): ?>
                The same as <?php echo $last_name; ?>.
            <?php elseif ($spent < $spent_last): ?>
                <?php echo money($spent_last - $spent); ?> less than <?php echo $last_name; ?> (<?php echo money($spent_last); ?>).
            <?php else: ?>
                <?php echo money($spent - $spent_last); ?> more than <?php echo $last_name; ?> (<?php echo money($spent_last); ?>).
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-budget">
        <?php if ($budget_total > 0): ?>
            <div class="meter-head">
                <span><?php echo $left >= 0 ? money($left) . ' left' : money(-$left) . ' over'; ?></span>
                <span class="num">of <?php echo money($budget_total); ?></span>
            </div>
            <div class="meter" role="meter" aria-valuemin="0" aria-valuemax="<?php echo $budget_total; ?>"
                 aria-valuenow="<?php echo $spent; ?>" aria-label="Budget used">
                <div class="meter-fill <?php echo $left < 0 ? 'is-over' : ''; ?>" style="width: <?php echo round($used_pct, 1); ?>%"></div>
                <div class="meter-today" style="left: <?php echo round($month_pct, 1); ?>%" title="Today"></div>
            </div>
            <div class="hero-perday">
                <?php if ($left > 0): ?>
                    You can spend about <strong class="num"><?php echo money($per_day); ?> a day</strong>
                    for the <?php echo $days_left; ?> day<?php echo $days_left === 1 ? '' : 's'; ?> left in <?php echo $month_name; ?>.
                <?php elseif ($left == 0): ?>
                    You've used this month's budget exactly.
                <?php else: ?>
                    This month's budget is used up, with <?php echo $days_left; ?> day<?php echo $days_left === 1 ? '' : 's'; ?> still to go.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="hero-perday">
                Set a monthly budget and this card will show how much you can still spend each day.
            </div>
            <a class="btn btn-hero" href="budget.php">Set a budget</a>
        <?php endif; ?>
    </div>
</section>

<!-- ============ Month in numbers ============ -->
<section class="facts card" aria-label="This month in numbers">
    <div class="fact">
        <div class="fact-label">Transactions</div>
        <div class="fact-value num"><?php echo $count; ?></div>
    </div>
    <div class="fact">
        <div class="fact-label">Average per transaction</div>
        <div class="fact-value num"><?php echo $count ? money($spent / $count) : '&ndash;'; ?></div>
    </div>
    <div class="fact">
        <div class="fact-label">Biggest category</div>
        <div class="fact-value">
            <?php if ($by_category): ?>
                <span class="cat"><span class="dot" style="background: <?php echo $colour_of($by_category[0]['category']); ?>"></span><?php echo htmlspecialchars($by_category[0]['category']); ?></span>
            <?php else: ?>&ndash;<?php endif; ?>
        </div>
    </div>
</section>

<!-- ============ Charts ============ -->
<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <h2>Spending by category</h2>
            <span class="sub"><?php echo $month_name; ?></span>
        </div>
        <?php if ($by_category): ?>
            <div class="cat-split">
                <div class="donut-box"><canvas id="categoryChart" aria-label="Spending by category" role="img"></canvas></div>
                <ul class="cat-list">
                    <?php foreach ($by_category as $row): ?>
                        <?php
                            $share = $spent > 0 ? 100 * $row['total'] / $spent : 0;
                            $limit = isset($budgets[$row['category']]) ? (float) $budgets[$row['category']] : 0;
                        ?>
                        <li>
                            <span class="cat"><span class="dot" style="background: <?php echo $colour_of($row['category']); ?>"></span><?php echo htmlspecialchars($row['category']); ?></span>
                            <span class="num amount"><?php echo money($row['total']); ?></span>
                            <span class="num share"><?php echo round($share); ?>%</span>
                            <?php if ($limit > 0): ?>
                                <span class="mini-meter" title="<?php echo money($row['total']) . ' of ' . money($limit) . ' budget'; ?>">
                                    <span style="width: <?php echo min(100, round(100 * $row['total'] / $limit)); ?>%; background: <?php echo $row['total'] > $limit ? 'var(--bad)' : $colour_of($row['category']); ?>"></span>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="empty">
                <p>Nothing recorded for <?php echo $month_name; ?> yet. Scan your first receipt and it will appear here.</p>
                <a class="btn btn-primary" href="upload.php">Scan receipt</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>Monthly spending</h2>
            <span class="sub">Last 6 months</span>
        </div>
        <?php if (array_sum($trend_values) > 0): ?>
            <div class="trend-box"><canvas id="trendChart" aria-label="Monthly spending, last 6 months" role="img"></canvas></div>
        <?php else: ?>
            <div class="empty">
                <p>Your spending for each month will build up here as you record transactions.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- ============ Recent ============ -->
<section class="card">
    <div class="card-head">
        <h2>Recent transactions</h2>
        <a href="transactions.php" class="sub">View all</a>
    </div>
    <?php if ($recent): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Merchant</th><th class="hide-sm">Category</th><th class="right">Amount</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $t): ?>
                    <tr>
                        <td class="num nowrap"><?php echo date('d M', strtotime($t['date'])); ?><span class="hide-sm"> <?php echo date('Y', strtotime($t['date'])); ?></span></td>
                        <td>
                            <span class="dot show-sm" style="background: <?php echo $colour_of($t['category']); ?>" title="<?php echo htmlspecialchars((string) $t['category']); ?>"></span>
                            <span class="merchant"><?php echo htmlspecialchars((string) $t['merchant']); ?></span>
                            <?php if (!empty($t['receipt_image'])): ?>
                                <a class="receipt-link" href="view_receipt.php?id=<?php echo (int) $t['id']; ?>" title="View receipt">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h12a1 1 0 0 1 1 1v19l-2.5-1.5L14 22l-2-1.5L10 22l-2.5-1.5L5 22V3a1 1 0 0 1 1-1Zm2 5v2h8V7H8Zm0 4v2h8v-2H8Zm0 4v2h5v-2H8Z"/></svg>
                                    <span>Receipt</span>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="hide-sm"><span class="cat"><span class="dot" style="background: <?php echo $colour_of($t['category']); ?>"></span><?php echo htmlspecialchars((string) $t['category']); ?></span></td>
                        <td class="right num nowrap"><?php echo money($t['amount']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <p>No transactions yet. Scan a receipt, or add one by hand if you paid cash.</p>
            <div class="actions">
                <a class="btn btn-primary" href="upload.php">Scan receipt</a>
                <a class="btn btn-outline" href="add_transaction.php">Add manually</a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php
$cat_labels  = array_column($by_category, 'category');
$cat_values  = array_map(function ($r) { return round((float) $r['total'], 2); }, $by_category);
$cat_colours = array_map($colour_of, $cat_labels);

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    // Change only the family: replacing the whole font object drops its size
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#7f98a3';
    const money = v => 'RM ' + Number(v).toFixed(2);
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const cat = document.getElementById('categoryChart');
    if (cat) {
        new Chart(cat, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($cat_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($cat_values); ?>,
                    backgroundColor: <?php echo json_encode($cat_colours); ?>,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                cutout: '68%',
                responsive: true,
                maintainAspectRatio: false,
                animation: reduce ? false : undefined,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + money(c.parsed) } }
                }
            }
        });
    }

    const trend = document.getElementById('trendChart');
    if (!trend) return;
    const labels = <?php echo json_encode($trend_labels); ?>;
    new Chart(trend, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Spending',
                data: <?php echo json_encode($trend_values); ?>,
                // This month in deep teal, earlier months lighter
                backgroundColor: labels.map((_, i) => i === labels.length - 1 ? '#0b4f6c' : '#7cc7d6'),
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 42
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduce ? false : undefined,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' ' + money(c.parsed.y) } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: '#e6eef1' },
                    ticks: { callback: v => 'RM ' + v, maxTicksLimit: 5 }
                },
                x: { grid: { display: false }, border: { color: '#dbe7ec' } }
            }
        }
    });
})();
</script>
<?php
layout_end(ob_get_clean());