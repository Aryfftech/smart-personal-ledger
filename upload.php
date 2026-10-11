<?php
session_start();
require_once 'db.php';
require_once 'lib_categories.php';
require_once 'layout.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$ocr_result   = null;
// Entry timer start (browser clock, ms), carried from the scan form to the
// confirm form so the whole scan-and-check process is timed as one entry.
$started_at   = (isset($_POST['started_at']) && ctype_digit($_POST['started_at'])) ? $_POST['started_at'] : '';
$error        = '';
$saved_name   = '';
$preview_data = '';

// Accepted image types, mapped to the extension we save them with.
// The type is read from the file's own bytes, never from its name,
// so a renamed .php file cannot slip through as a "receipt".
$ALLOWED = array(
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
);
$MAX_BYTES = 10 * 1024 * 1024;   // 10 MB, enough for a phone photo

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (error code ' . $file['error'] . '). Try a smaller image.';
    } elseif ($file['size'] > $MAX_BYTES) {
        $error = 'Image is larger than 10 MB. Please use a smaller photo.';
    } else {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info ? $info['mime'] : '';

        if (!isset($ALLOWED[$mime])) {
            $error = 'Only JPG, PNG or WEBP receipt images are accepted.';
        } else {
            // Random file name: unguessable, and never taken from user input
            $saved_name = bin2hex(random_bytes(16)) . '.' . $ALLOWED[$mime];
            $upload_dir = __DIR__ . '/uploads';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $saved_path = $upload_dir . '/' . $saved_name;

            if (!move_uploaded_file($file['tmp_name'], $saved_path)) {
                $error = 'Could not save the receipt image on the server.';
                $saved_name = '';
            } else {
                // Send the stored copy to the Flask OCR service
                $ch = curl_init('http://127.0.0.1:5000/ocr');
                $postData = array(
                    'image' => new CURLFile($saved_path, $mime, $saved_name)
                );
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);   // the service reads each receipt four ways
                $response  = curl_exec($ch);
                $curlError = curl_error($ch);
                curl_close($ch);

                $decoded = $curlError ? null : json_decode($response, true);
                if ($curlError) {
                    $error = 'The receipt scanner isn\'t running. Start the OCR service (start_ocr.bat), then try again.';
                } elseif (!is_array($decoded) || isset($decoded['error'])) {
                    $error = 'The scanner couldn\'t read that image'
                           . (isset($decoded['error']) ? ': ' . $decoded['error'] : '.')
                           . ' Try another photo, or add the transaction manually.';
                }
                if ($error) {
                    @unlink($saved_path);   // no transaction will point to it
                    $saved_name = '';
                } else {
                    $ocr_result = $decoded;
                    // Inline preview so the user can check fields against the receipt
                    $preview_data = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($saved_path));
                }
            }
        }
    }
}


// ---- Work out the fields to show on the "check" step ---------------------------
if ($ocr_result) {
    $merchant = isset($ocr_result['merchant']) ? $ocr_result['merchant'] : '';
    $total    = isset($ocr_result['total'])    ? $ocr_result['total']    : '';
    $date     = isset($ocr_result['date'])     ? $ocr_result['date']     : '';
    $match    = categorise_merchant($pdo, $_SESSION['user_id'], $merchant);
    $category = $match['category'];

    // OCR returns day/month/year. Keep it only if it is a real calendar
    // date; otherwise fall back to today and record that OCR missed it.
    $ocr_date       = '';
    $formatted_date = date('Y-m-d');
    if ($date) {
        $parts = preg_split('/[\/\-\.]/', $date);
        if (count($parts) === 3 && ctype_digit($parts[0]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
            $d = (int) $parts[0];
            $m = (int) $parts[1];
            $y = (int) (strlen($parts[2]) === 2 ? '20' . $parts[2] : $parts[2]);
            if (checkdate($m, $d, $y)) {
                $ocr_date = sprintf('%04d-%02d-%02d', $y, $m, $d);
                $formatted_date = $ocr_date;
            }
        }
    }
    $cats = get_category_names($pdo);
}

$extra_head = <<<'HTML'
<style>
/* ---- Upload page ---------------------------------------------------------- */
.scan-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(260px, 1fr); gap: 18px; align-items: start; }
.check-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); gap: 18px; align-items: start; }

.drop {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 320px;
    padding: 32px 20px;
    border: 2px dashed #b8d3dc;
    border-radius: 18px;
    background: #f6fafb;
    text-align: center;
    color: var(--ink-soft);
    transition: border-color 0.15s, background 0.15s;
}
.drop.is-over { border-color: var(--cyan); background: #ecf9fc; }
.drop:focus-within { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(0, 200, 230, 0.25); }
.drop h2 { font-size: 1.15rem; color: var(--ink); }
.drop p { font-size: 0.9rem; max-width: 340px; }
.drop input[type=file] { position: absolute; width: 1px; height: 1px; opacity: 0; }
.drop .file-note { font-size: 0.8rem; color: var(--ink-muted); }

.chosen { display: none; gap: 18px; align-items: center; }
.chosen.show { display: flex; }
.chosen img {
    width: 120px; height: 160px; object-fit: cover;
    border-radius: 12px; border: 1px solid var(--line); background: #fff; flex: none;
}
.chosen .meta { min-width: 0; }
.chosen .meta strong { display: block; word-break: break-all; }
.chosen .meta > span { font-size: 0.85rem; color: var(--ink-muted); }   /* file size only, not the button text */
.scan-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }

.btn[disabled] { opacity: 0.85; cursor: progress; }
.spinner {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2.5px solid rgba(255,255,255,0.45); border-top-color: #fff;
    animation: spin 0.8s linear infinite; display: none;
}
.btn.loading .spinner { display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

.tips h2 { font-size: 1rem; margin-bottom: 12px; }
.tips ul { list-style: none; display: grid; gap: 14px; }
.tips li { font-size: 0.9rem; color: var(--ink-soft); }
.tips li strong { display: block; color: var(--ink); font-weight: 700; }
.tips .other { margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--line); font-size: 0.88rem; color: var(--ink-soft); }

.receipt-frame {
    background: #f6fafb;
    border-radius: 16px;
    padding: 12px;
    display: flex; justify-content: center;
}
.receipt-frame img { max-width: 100%; max-height: 640px; object-fit: contain; border-radius: 10px; }

.scan-summary { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
.chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 999px;
    font-size: 0.8rem; font-weight: 600;
    background: #e6f6ee; color: var(--good);
}
.chip.miss { background: #fdf5e6; color: #8a5a12; }

@media (max-width: 1000px) { .scan-grid, .check-grid { grid-template-columns: 1fr; } }
@media (max-width: 560px) {
    .drop { min-height: 240px; }
    .chosen { flex-direction: column; align-items: flex-start; }
}
</style>
HTML;

layout_start($ocr_result ? 'Check receipt' : 'Scan receipt', 'scan', $extra_head);
?>

<?php if ($ocr_result): ?>
    <!-- ================= Step 2: check the details ================= -->
    <header class="page-head">
        <div>
            <h1>Check the details</h1>
            <p>Compare each field with the receipt and fix anything the scanner got wrong, then save.</p>
        </div>
    </header>

    <div class="check-grid">
        <section class="card" aria-label="Your receipt">
            <div class="card-head"><h2>Your receipt</h2></div>
            <div class="receipt-frame">
                <img src="<?php echo $preview_data; ?>" alt="The receipt you uploaded">
            </div>
        </section>

        <section class="card">
            <div class="scan-summary" aria-label="What the scanner found">
                <?php foreach (array('Shop' => $merchant !== '', 'Total' => $total !== '', 'Date' => $ocr_date !== '') as $label => $found): ?>
                    <span class="chip <?php echo $found ? '' : 'miss'; ?>">
                        <?php echo $label . ($found ? ' read' : ' not read'); ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="save_transaction.php" id="confirmForm">
                <input type="hidden" name="receipt_image" value="<?php echo htmlspecialchars($saved_name); ?>">
                <!-- Evaluation data (Section 4.2): how it was entered, how long it
                     took, and what OCR originally read before the user corrected it -->
                <input type="hidden" name="entry_method" value="ocr">
                <input type="hidden" name="entry_ms" id="entryMs" value="">
                <input type="hidden" name="ocr_merchant" value="<?php echo htmlspecialchars($merchant); ?>">
                <input type="hidden" name="ocr_amount" value="<?php echo htmlspecialchars($total); ?>">
                <input type="hidden" name="ocr_date" value="<?php echo htmlspecialchars($ocr_date); ?>">

                <div class="form-row">
                    <label for="merchant">Shop</label>
                    <input class="input <?php echo $merchant === '' ? 'needs-check' : ''; ?>" type="text" id="merchant" name="merchant"
                           value="<?php echo htmlspecialchars($merchant); ?>" maxlength="255" required>
                    <?php if ($merchant === ''): ?>
                        <div class="help check">The shop name couldn't be read. Type it from the top of the receipt.</div>
                    <?php endif; ?>
                </div>

                <div class="form-grid">
                    <div class="form-row">
                        <label for="amount">Total (RM)</label>
                        <input class="input num <?php echo $total === '' ? 'needs-check' : ''; ?>" type="number" step="0.01" min="0.01"
                               id="amount" name="amount" value="<?php echo htmlspecialchars($total); ?>" required>
                        <?php if ($total === ''): ?>
                            <div class="help check">The total couldn't be read. Type the amount you paid.</div>
                        <?php endif; ?>
                    </div>
                    <div class="form-row">
                        <label for="date">Date</label>
                        <input class="input <?php echo $ocr_date === '' ? 'needs-check' : ''; ?>" type="date" id="date" name="date"
                               value="<?php echo $formatted_date; ?>" required>
                        <?php if ($ocr_date === ''): ?>
                            <div class="help check">No date found on the receipt, so it's set to today. Change it if needed.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <label for="category">Category</label>
                    <select class="select" id="category" name="category">
                        <?php foreach ($cats as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($c === $category ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help">
                        <?php if ($match['keyword'] !== null): ?>
                            Suggested because the shop name contains <strong><?php echo htmlspecialchars($match['keyword']); ?></strong>
                            (<?php echo $match['source'] === 'yours' ? 'your rule' : 'built-in rule'; ?>).
                        <?php else: ?>
                            No rule matched this shop, so it's set to Others.
                            <a href="manage_rules.php" target="_blank">Add a rule</a> to teach it.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <label for="notes">Notes <span style="font-weight:400;color:var(--ink-muted)">(optional)</span></label>
                    <textarea class="textarea" id="notes" name="notes" rows="2"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save transaction</button>
                    <a href="upload.php" class="btn btn-outline">Scan a different receipt</a>
                </div>
            </form>
        </section>
    </div>

<?php else: ?>
    <!-- ================= Step 1: choose a photo ================= -->
    <header class="page-head">
        <div>
            <h1>Scan a receipt</h1>
            <p>Upload a photo of your receipt. Smart Ledger reads the shop name, total and date, then you check the details before saving.</p>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="notice bad" role="alert">
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <div class="scan-grid">
        <section class="card">
            <form method="POST" enctype="multipart/form-data" id="scanForm">
                <input type="hidden" name="started_at" id="startedAt" value="">

                <label class="drop" id="drop" for="image">
                    <h2>Choose a receipt photo</h2>
                    <p>Click to pick a photo, or drag one here.</p>
                    <span class="btn btn-outline" aria-hidden="true">Browse files</span>
                    <span class="file-note">JPG, PNG or WEBP, up to 10 MB</span>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required>
                </label>

                <div class="chosen" id="chosen" aria-live="polite">
                    <img id="chosenImg" alt="Preview of the chosen receipt">
                    <div class="meta">
                        <strong id="chosenName"></strong>
                        <span id="chosenSize"></span>
                        <div class="scan-actions">
                            <button type="submit" class="btn btn-primary" id="scanBtn">
                                <span class="spinner" aria-hidden="true"></span>
                                <span id="scanLabel">Scan receipt</span>
                            </button>
                            <button type="button" class="btn btn-outline" id="changeBtn">Choose another</button>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <aside class="card tips" aria-label="Tips for a clear scan">
            <h2>Tips for a clear scan</h2>
            <ul>
                <li>
                    <div><strong>Fill the frame</strong>Get close so the receipt takes up most of the photo. Small text is the most common reason a total can't be read.</div>
                </li>
                <li>
                    <div><strong>Keep it flat and straight</strong>Smooth out folds and hold the phone level above the receipt.</div>
                </li>
                <li>
                    <div><strong>Use good light</strong>Avoid shadows across the paper, and skip the flash if it causes glare.</div>
                </li>
            </ul>
            <div class="other">No receipt, or it's too faded? <a href="add_transaction.php">Add it manually</a>.</div>
        </aside>
    </div>
<?php endif; ?>

<?php
ob_start();
?>
<script>
(function () {
    // ---- Step 1: preview, drag and drop, and a clear "reading" state ----------
    const form = document.getElementById('scanForm');
    if (form) {
        const input   = document.getElementById('image');
        const drop    = document.getElementById('drop');
        const chosen  = document.getElementById('chosen');
        const img     = document.getElementById('chosenImg');
        const btn     = document.getElementById('scanBtn');
        const started = document.getElementById('startedAt');

        // Entry timer (Section 4.2): starts at the first interaction with the
        // form and is carried through the scan to the Save button.
        const start = () => { if (started.value === '') started.value = Date.now(); };
        ['focusin', 'pointerdown', 'keydown', 'change', 'dragenter'].forEach(ev => form.addEventListener(ev, start));

        const show = file => {
            if (!file) return;
            img.src = URL.createObjectURL(file);
            document.getElementById('chosenName').textContent = file.name;
            document.getElementById('chosenSize').textContent = (file.size / 1048576).toFixed(1) + ' MB';
            drop.style.display = 'none';
            chosen.classList.add('show');
            btn.focus();
        };
        input.addEventListener('change', () => show(input.files[0]));

        ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
        drop.addEventListener('drop', e => {
            if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(input.files[0]); }
        });

        document.getElementById('changeBtn').addEventListener('click', () => {
            input.value = '';
            chosen.classList.remove('show');
            drop.style.display = '';
            input.click();
        });

        form.addEventListener('submit', () => {
            btn.classList.add('loading');
            btn.disabled = true;
            document.getElementById('scanLabel').textContent = 'Reading receipt…';
        });
    }

    // ---- Step 2: stop the entry timer when the user saves ----------------------
    const confirmForm = document.getElementById('confirmForm');
    if (confirmForm) {
        const startedAt = <?php echo $started_at === '' ? 'null' : $started_at; ?>;
        confirmForm.addEventListener('submit', () => {
            if (startedAt !== null) {
                document.getElementById('entryMs').value = Date.now() - startedAt;
            }
        });
    }
})();
</script>
<?php
layout_end(ob_get_clean());