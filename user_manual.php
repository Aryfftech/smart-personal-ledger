<?php
session_start();
require_once 'db.php';
require_once 'layout.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$extra_head = <<<'HTML'
<style>
.manual-toc { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
.manual-toc a {
    padding: 8px 15px; border-radius: 999px;
    background: var(--surface); border: 1px solid var(--line);
    color: var(--teal-deep); font-size: 0.87rem; font-weight: 600;
}
.manual-toc a:hover { background: var(--field); text-decoration: none; }

.manual-sec { scroll-margin-top: 18px; }
.manual-sec + .manual-sec { margin-top: 18px; }
.manual-sec .lead { color: var(--ink-soft); margin-bottom: 18px; max-width: 62ch; }

.steps { list-style: none; counter-reset: step; }
.steps > li {
    counter-increment: step;
    position: relative;
    padding: 0 0 18px 48px;
    border-left: 2px solid var(--grid);
    margin-left: 15px;
}
.steps > li:last-child { border-left-color: transparent; padding-bottom: 0; }
.steps > li::before {
    content: counter(step);
    position: absolute; left: -16px; top: -2px;
    width: 32px; height: 32px; border-radius: 50%;
    display: grid; place-items: center;
    background: var(--teal-deep); color: #fff;
    font-size: 0.86rem; font-weight: 700;
}
.steps h3 { font-size: 0.98rem; font-weight: 700; margin-bottom: 3px; }
.steps p { color: var(--ink-soft); font-size: 0.91rem; max-width: 62ch; }
.steps code {
    background: var(--field); padding: 1px 7px; border-radius: 6px;
    font-size: 0.86em; color: var(--teal-deep); font-weight: 600;
}

.tip {
    display: flex; gap: 12px; align-items: flex-start;
    margin-top: 12px; padding: 12px 16px;
    border-radius: var(--radius); background: #f2fafc; border: 1px solid #cfe9ef;
    font-size: 0.88rem; color: var(--ink-soft); max-width: 70ch;
}
.tip svg { width: 18px; height: 18px; flex: none; margin-top: 2px; fill: var(--teal); }
.tip strong { color: var(--ink); font-weight: 700; }

.faq { border-top: 1px solid var(--grid); padding: 14px 0; }
.faq:first-of-type { border-top: none; padding-top: 0; }
.faq:last-child { padding-bottom: 0; }
.faq h3 { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
.faq p { color: var(--ink-soft); font-size: 0.91rem; max-width: 70ch; }
</style>
HTML;

$bulb = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21h6v-1H9v1Zm3-19A7 7 0 0 0 7 13.9c.6.7 1.3 1.6 1.6 2.6h6.8c.3-1 1-1.9 1.6-2.6A7 7 0 0 0 12 2Zm0 2a5 5 0 0 1 3.9 8.1c-.5.6-1 1.3-1.4 2h-5c-.4-.7-.9-1.4-1.4-2A5 5 0 0 1 12 4Zm-3 13h6v1H9v-1Z"/></svg>';

layout_start('User manual', 'manual', $extra_head);
?>

<header class="page-head">
    <div>
        <h1>User manual</h1>
        <p>Everything Smart Ledger does, step by step. Jump to the part you need.</p>
    </div>
</header>

<nav class="manual-toc" aria-label="Manual sections">
    <a href="#start">Getting started</a>
    <a href="#scan">Scanning a receipt</a>
    <a href="#manual-entry">Adding manually</a>
    <a href="#transactions">Transactions</a>
    <a href="#budget">Budget</a>
    <a href="#rules">Category rules</a>
    <a href="#trouble">If something goes wrong</a>
</nav>

<section class="card manual-sec" id="start">
    <div class="card-head"><h2>Getting started</h2></div>
    <p class="lead">Smart Ledger keeps one ledger per account. Nobody else can see your transactions.</p>
    <ol class="steps">
        <li>
            <h3>Create an account</h3>
            <p>On the sign-in page, choose <strong>Register</strong> and enter your name, email and a password. Your password is hashed before it is stored, so it is never kept as readable text.</p>
        </li>
        <li>
            <h3>Sign in</h3>
            <p>Enter the same email and password. You land on the Dashboard.</p>
        </li>
        <li>
            <h3>Read the Dashboard</h3>
            <p>The top panel shows what you have spent this month and how much of your budget is left. Below it are a category breakdown, a spending trend, and your most recent transactions.</p>
        </li>
    </ol>
</section>

<section class="card manual-sec" id="scan">
    <div class="card-head"><h2>Scanning a receipt</h2></div>
    <p class="lead">This is the fastest way to record a transaction. The system reads the shop name, date and amount from a photo so you do not have to type them.</p>
    <ol class="steps">
        <li>
            <h3>Take a photo of the receipt</h3>
            <p>Lay the receipt flat on a plain surface in good light and photograph it straight from above. Make sure the shop name at the top and the total at the bottom are both inside the frame.</p>
        </li>
        <li>
            <h3>Open Scan receipt</h3>
            <p>Choose <strong>Scan receipt</strong> in the sidebar, then pick your photo. JPG and PNG are both accepted.</p>
        </li>
        <li>
            <h3>Wait for the reading</h3>
            <p>The image is cleaned up, read, and the three fields are pulled out of the text. This takes a few seconds.</p>
        </li>
        <li>
            <h3>Check the three fields</h3>
            <p>The shop name, date and amount appear filled in, along with a suggested category. Any field the system was unsure about is highlighted so you know where to look first.</p>
            <div class="tip">
                <?php echo $bulb; ?>
                <div><strong>Always read before saving.</strong> The amount is the field worth double-checking &mdash; a faded or creased receipt can turn a 3 into an 8.</div>
            </div>
        </li>
        <li>
            <h3>Correct anything wrong, then save</h3>
            <p>Edit any field directly, change the category if the suggestion is off, then choose <strong>Save transaction</strong>. The receipt image is kept with the record, so you can open it again later from the Transactions list.</p>
        </li>
    </ol>
</section>

<section class="card manual-sec" id="manual-entry">
    <div class="card-head"><h2>Adding a transaction manually</h2></div>
    <p class="lead">For spending with no receipt &mdash; cash to a friend, an online payment, a parking fee.</p>
    <ol class="steps">
        <li>
            <h3>Open Add manually</h3>
            <p>Choose <strong>Add manually</strong> in the sidebar.</p>
        </li>
        <li>
            <h3>Fill in the details</h3>
            <p>Enter the shop or description, the amount, and the date. The category fills itself in from the name you typed, and you can change it.</p>
        </li>
        <li>
            <h3>Save</h3>
            <p>The transaction appears on the Dashboard and in the Transactions list straight away.</p>
        </li>
    </ol>
</section>

<section class="card manual-sec" id="transactions">
    <div class="card-head"><h2>Browsing and editing transactions</h2></div>
    <p class="lead">Every transaction you have saved, newest first.</p>
    <ol class="steps">
        <li>
            <h3>Filter what you see</h3>
            <p>Narrow the list by month, by category, or both, using the controls at the top of the page.</p>
        </li>
        <li>
            <h3>View the receipt</h3>
            <p>Rows that came from a scan carry a small receipt tag. Choose it to open the original image.</p>
        </li>
        <li>
            <h3>Edit or delete</h3>
            <p>Each row has an edit and a delete button on the right. Editing opens the same fields you saw when saving. Deleting asks for confirmation first, and cannot be undone.</p>
        </li>
    </ol>
</section>

<section class="card manual-sec" id="budget">
    <div class="card-head"><h2>Setting a budget</h2></div>
    <p class="lead">A monthly limit, either overall or per category, so you find out before you overspend rather than after.</p>
    <ol class="steps">
        <li>
            <h3>Open Budget</h3>
            <p>Choose <strong>Budget</strong> in the sidebar.</p>
        </li>
        <li>
            <h3>Set your limit</h3>
            <p>Enter an amount for the month and save. You can set a separate limit for individual categories.</p>
        </li>
        <li>
            <h3>Watch the bar</h3>
            <p>The bar fills as you spend. The marker on it shows how far through the month you are, so a fill that runs ahead of the marker means you are spending faster than the month is passing.</p>
            <div class="tip">
                <?php echo $bulb; ?>
                <div>Set the limit slightly below what you can actually afford. The warning then arrives while you still have room to react.</div>
            </div>
        </li>
    </ol>
</section>

<section class="card manual-sec" id="rules">
    <div class="card-head"><h2>Teaching the category rules</h2></div>
    <p class="lead">Categories are chosen by matching keywords against the shop name. When the system guesses wrong, you teach it once and it stays right.</p>
    <ol class="steps">
        <li>
            <h3>Open Category rules</h3>
            <p>Choose <strong>Category rules</strong> in the sidebar. You will see every keyword grouped by category. The highlighted ones are yours; the plain ones are built in.</p>
        </li>
        <li>
            <h3>Test a shop name first</h3>
            <p>Type a shop name into <strong>Try a shop name</strong> to see which category it lands in and which keyword matched. This tells you whether you need a new rule at all.</p>
        </li>
        <li>
            <h3>Add your keyword</h3>
            <p>Enter a distinctive part of the shop name and choose the category it belongs to. Short and specific works best: <code>pak ali</code> rather than <code>kedai makan pak ali sdn bhd</code>.</p>
        </li>
        <li>
            <h3>Check it worked</h3>
            <p>Test the same shop name again. It should now land in the category you chose.</p>
            <div class="tip">
                <?php echo $bulb; ?>
                <div><strong>Your rules win.</strong> Your own keywords are checked before the built-in ones, so you can override a default. Built-in keywords cannot be deleted, only overridden.</div>
            </div>
        </li>
    </ol>
</section>

<section class="card manual-sec" id="trouble">
    <div class="card-head"><h2>If something goes wrong</h2></div>

    <div class="faq">
        <h3>The scan did not read anything</h3>
        <p>The photo is usually the cause. Retake it with more light, hold the camera square to the receipt, and keep the whole receipt inside the frame. Crumpled and faded receipts are the hardest to read. You can always type the fields in by hand instead &mdash; the transaction is saved the same way.</p>
    </div>

    <div class="faq">
        <h3>The amount came out wrong</h3>
        <p>Correct it on the confirmation screen before saving. If you have already saved, open the transaction from the Transactions list and edit it there.</p>
    </div>

    <div class="faq">
        <h3>The category is wrong every time for one shop</h3>
        <p>Add a keyword rule for that shop. Once added, every future transaction from it is categorised correctly, and you only have to do it once.</p>
    </div>

    <div class="faq">
        <h3>Scanning is unavailable</h3>
        <p>The reading service runs separately from the website. If scanning fails while the rest of the app works normally, that service has stopped. Adding transactions manually continues to work in the meantime.</p>
    </div>

    <div class="faq">
        <h3>I forgot my password</h3>
        <p>There is no self-service reset in this version. Contact the system administrator.</p>
    </div>
</section>

<?php
layout_end();