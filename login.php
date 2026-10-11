<?php
// ---------------------------------------------------------------------------
// Sign in and create account, on one page with a sliding panel.
// register.php now simply redirects here with ?mode=register.
// ---------------------------------------------------------------------------
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$mode            = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';
$login_error     = '';
$register_error  = '';
$login_email     = '';
$reg_name        = '';
$reg_email       = '';

function start_session_for($user)
{
    session_regenerate_id(true);          // new session id on sign-in
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // ---- Sign in --------------------------------------------------------------
    if ($action === 'login') {
        $mode        = 'login';
        $login_email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password    = isset($_POST['password']) ? $_POST['password'] : '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(array($login_email));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            start_session_for($user);
        }
        $login_error = 'That email and password don\'t match an account. Check them and try again.';
    }

    // ---- Create account --------------------------------------------------------
    if ($action === 'register') {
        $mode      = 'register';
        $reg_name  = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $reg_email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password  = isset($_POST['password']) ? $_POST['password'] : '';

        if ($reg_name === '' || $reg_email === '' || $password === '') {
            $register_error = 'Fill in your name, email and a password.';
        } elseif (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
            $register_error = 'Enter a valid email address, like name@example.com.';
        } elseif (strlen($password) < 6) {
            $register_error = 'Use a password of at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute(array($reg_email));
            if ($stmt->fetch()) {
                $register_error = 'An account with this email already exists. Sign in instead.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute(array($reg_name, $reg_email, password_hash($password, PASSWORD_DEFAULT)));
                start_session_for(array('id' => $pdo->lastInsertId(), 'name' => $reg_name));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $mode === 'register' ? 'Create account' : 'Sign in'; ?> - Smart Ledger</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink:        #0f2a36;   /* text */
            --ink-soft:   #4a6572;   /* secondary text */
            --teal-deep:  #0b4f6c;   /* dark end of the gradient */
            --teal:       #0a7f99;
            --cyan:       #00c8e6;   /* bright end of the gradient */
            --field:      #eef5f7;   /* input background */
            --line:       #d6e4e9;
            --error-bg:   #fdecea;
            --error-ink:  #a1260f;
            --radius:     30px;
            --swing:      150px;     /* how round the sliding panel is */
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(90deg, #e3f3f7, #c9eaf2);
        }

        /* ---- The card ------------------------------------------------------ */
        .auth {
            position: relative;
            width: 850px;
            height: 560px;
            background: #fff;
            border-radius: var(--radius);
            box-shadow: 0 24px 60px rgba(11, 79, 108, 0.18);
            overflow: hidden;
        }

        /* ---- The two forms ---------------------------------------------------- */
        .form-box {
            position: absolute;
            right: 0;
            width: 50%;
            height: 100%;
            display: flex;
            align-items: center;
            padding: 40px;
            background: #fff;
            z-index: 1;
            transition: 0.6s ease-in-out 1.2s, visibility 0s 1s;
        }
        .auth.active .form-box { right: 50%; }
        .form-box.register { visibility: hidden; }
        .auth.active .form-box.register { visibility: visible; }

        .form-box form { width: 100%; }

        .form-box h1 {
            font-size: 2.1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
            text-align: center;
        }
        .form-box .lead {
            color: var(--ink-soft);
            font-size: 0.92rem;
            text-align: center;
            margin-bottom: 26px;
        }

        .field { position: relative; margin-bottom: 16px; }
        .field label {
            position: absolute; width: 1px; height: 1px; overflow: hidden;
            clip: rect(0 0 0 0); white-space: nowrap;          /* for screen readers */
        }
        .field input {
            width: 100%;
            padding: 13px 48px 13px 18px;
            background: var(--field);
            border: 1.5px solid transparent;
            border-radius: 10px;
            font: inherit;
            font-size: 0.95rem;
            color: var(--ink);
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .field input::placeholder { color: #7f98a3; }
        .field input:focus { border-color: var(--teal); background: #fff; }
        .field svg {
            position: absolute; right: 18px; top: 50%;
            transform: translateY(-50%);
            width: 19px; height: 19px;
            fill: var(--ink-soft);
            pointer-events: none;
        }

        .alert {
            background: var(--error-bg);
            color: var(--error-ink);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.86rem;
            line-height: 1.45;
            margin-bottom: 16px;
        }

        .btn-primary {
            width: 100%;
            height: 50px;
            margin-top: 6px;
            border: none;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--teal-deep), var(--cyan));
            color: #fff;
            font: inherit;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(0, 200, 230, 0.28);
            transition: filter 0.2s, transform 0.1s;
        }
        .btn-primary:hover  { filter: brightness(1.06); }
        .btn-primary:active { transform: translateY(1px); }

        .hint {
            margin-top: 16px;
            font-size: 0.82rem;
            color: var(--ink-soft);
            text-align: center;
        }

        /* ---- The sliding colour panel ------------------------------------------ */
        .toggle-box { position: absolute; width: 100%; height: 100%; }

        /* One very wide rounded shape, three cards wide. It rests mostly off to
           the left, showing its cyan end on the left half; on "active" it slides
           right, showing its deep-teal end on the right half. */
        .toggle-box::before {
            content: '';
            position: absolute;
            left: -250%;
            width: 300%;
            height: 100%;
            background: linear-gradient(90deg, var(--teal-deep) 0%, var(--teal) 55%, var(--cyan) 100%);
            border-radius: var(--swing);
            z-index: 2;
            transition: 1.8s ease-in-out;
        }
        .auth.active .toggle-box::before { left: 50%; }

        .toggle-panel {
            position: absolute;
            width: 50%;
            height: 100%;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 44px;
            z-index: 2;
            transition: 0.6s ease-in-out;
        }
        .toggle-panel.toggle-left  { left: 0;     transition-delay: 1.2s; }
        .auth.active .toggle-panel.toggle-left  { left: -50%; transition-delay: 0.6s; }
        .toggle-panel.toggle-right { right: -50%; transition-delay: 0.6s; }
        .auth.active .toggle-panel.toggle-right { right: 0;   transition-delay: 1.2s; }

        .brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 700; font-size: 0.95rem;
            margin-bottom: 34px;
            opacity: 0.95;
        }
        .brand svg { width: 30px; height: 30px; }

        .toggle-panel h2 {
            font-size: 2.1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .toggle-panel p {
            margin: 10px 0 22px;
            font-size: 0.93rem;
            line-height: 1.5;
            opacity: 0.92;
            max-width: 280px;
        }
        .btn-ghost {
            width: 160px;
            height: 46px;
            background: transparent;
            border: 2px solid #fff;
            border-radius: 999px;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .btn-ghost:hover { background: #fff; color: var(--teal-deep); }

        /* ---- Keyboard focus that can be seen ------------------------------------ */
        .btn-primary:focus-visible { outline: 3px solid var(--teal-deep); outline-offset: 3px; }
        .btn-ghost:focus-visible   { outline: 3px solid #fff; outline-offset: 3px; }

        /* ---- Phones: panel on top, form below ------------------------------------ */
        @media (max-width: 650px) {
            body { padding: 12px; }
            .auth { height: calc(100vh - 24px); min-height: 620px; }
            .form-box { bottom: 0; width: 100%; height: 70%; padding: 28px 24px; }
            .auth.active .form-box { right: 0; bottom: 30%; }
            .form-box h1 { font-size: 1.7rem; }

            .toggle-box::before {
                left: 0; top: -270%;
                width: 100%; height: 300%;
                border-radius: 20vw;
                background: linear-gradient(180deg, var(--teal-deep) 0%, var(--teal) 55%, var(--cyan) 100%);
            }
            .auth.active .toggle-box::before { left: 0; top: 70%; }

            .toggle-panel { width: 100%; height: 30%; padding: 0 24px; }
            .toggle-panel.toggle-left  { top: 0; }
            .auth.active .toggle-panel.toggle-left  { left: 0; top: -30%; }
            .toggle-panel.toggle-right { right: 0; bottom: -30%; }
            .auth.active .toggle-panel.toggle-right { bottom: 0; }

            .brand { margin-bottom: 10px; }
            .toggle-panel h2 { font-size: 1.5rem; }
            .toggle-panel p  { margin: 4px 0 12px; font-size: 0.85rem; }
            .btn-ghost { height: 40px; }
        }

        @media (max-width: 400px) {
            .form-box { padding: 22px 18px; }
            .form-box .lead { margin-bottom: 18px; }
        }

        /* ---- Respect "reduce motion" -------------------------------------------- */
        @media (prefers-reduced-motion: reduce) {
            .form-box, .toggle-box::before, .toggle-panel {
                transition-duration: 0s !important;
                transition-delay: 0s !important;
            }
        }
        /* No slide on first load when the page opens straight into "register" */
        .auth.no-anim .form-box,
        .auth.no-anim .toggle-box::before,
        .auth.no-anim .toggle-panel { transition: none !important; }
    </style>
</head>
<body>

<?php
// Small inline icons, so the page needs no icon library.
$icon_user = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5Z"/></svg>';
$icon_mail = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm9 7.2L4.4 7H4v.9l8 5.4 8-5.4V7h-.4L12 12.2Z"/></svg>';
$icon_lock = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9V7A5 5 0 0 0 7 7v2H5v13h14V9h-2Zm-8-2a3 3 0 0 1 6 0v2H9V7Zm4 10.7V19h-2v-1.3a2 2 0 1 1 2 0Z"/></svg>';
$logo      = '<svg viewBox="0 0 32 32" aria-hidden="true"><path fill="#fff" d="M8 3h16a1 1 0 0 1 1 1v25l-3-2-3 2-3-2-3 2-3-2-3 2V4a1 1 0 0 1 1-1Z" opacity=".95"/><path fill="#0b4f6c" d="M11 9h10v2H11zm0 5h10v2H11zm0 5h6v2h-6z"/></svg>';
?>

<div class="auth<?php echo $mode === 'register' ? ' active no-anim' : ''; ?>" id="auth">

    <!-- Sign in -->
    <div class="form-box login">
        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="action" value="login">
            <h1>Sign in</h1>
            <p class="lead">Pick up where you left off.</p>

            <?php if ($login_error): ?>
                <div class="alert" role="alert"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>

            <div class="field">
                <label for="login-email">Email</label>
                <input type="email" id="login-email" name="email" placeholder="Email"
                       value="<?php echo htmlspecialchars($login_email); ?>" autocomplete="email" required>
                <?php echo $icon_mail; ?>
            </div>
            <div class="field">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" placeholder="Password"
                       autocomplete="current-password" required>
                <?php echo $icon_lock; ?>
            </div>

            <button type="submit" class="btn-primary">Sign in</button>
        </form>
    </div>

    <!-- Create account -->
    <div class="form-box register">
        <form method="POST" action="login.php?mode=register" novalidate>
            <input type="hidden" name="action" value="register">
            <h1>Create account</h1>
            <p class="lead">It takes less than a minute.</p>

            <?php if ($register_error): ?>
                <div class="alert" role="alert"><?php echo htmlspecialchars($register_error); ?></div>
            <?php endif; ?>

            <div class="field">
                <label for="reg-name">Full name</label>
                <input type="text" id="reg-name" name="name" placeholder="Full name"
                       value="<?php echo htmlspecialchars($reg_name); ?>" autocomplete="name" maxlength="100" required>
                <?php echo $icon_user; ?>
            </div>
            <div class="field">
                <label for="reg-email">Email</label>
                <input type="email" id="reg-email" name="email" placeholder="Email"
                       value="<?php echo htmlspecialchars($reg_email); ?>" autocomplete="email" required>
                <?php echo $icon_mail; ?>
            </div>
            <div class="field">
                <label for="reg-password">Password</label>
                <input type="password" id="reg-password" name="password" placeholder="Password (6 characters or more)"
                       autocomplete="new-password" minlength="6" required>
                <?php echo $icon_lock; ?>
            </div>

            <button type="submit" class="btn-primary">Create account</button>
        </form>
    </div>

    <!-- Sliding colour panel -->
    <div class="toggle-box">
        <div class="toggle-panel toggle-left">
            <div class="brand"><?php echo $logo; ?> Smart Ledger</div>
            <h2>Hello, welcome!</h2>
            <p>Snap a receipt and Smart Ledger fills in the shop, amount and date for you.</p>
            <button type="button" class="btn-ghost" data-show="register">Create account</button>
        </div>
        <div class="toggle-panel toggle-right">
            <div class="brand"><?php echo $logo; ?> Smart Ledger</div>
            <h2>Welcome back!</h2>
            <p>Already have an account? Sign in to see where your money went this month.</p>
            <button type="button" class="btn-ghost" data-show="login">Sign in</button>
        </div>
    </div>
</div>

<script>
(function () {
    const auth = document.getElementById('auth');

    // Let the panel animate on clicks, even if the page opened on "register"
    requestAnimationFrame(() => requestAnimationFrame(() => auth.classList.remove('no-anim')));

    document.querySelectorAll('[data-show]').forEach(btn => {
        btn.addEventListener('click', () => {
            const toRegister = btn.dataset.show === 'register';
            auth.classList.toggle('active', toRegister);
            document.title = (toRegister ? 'Create account' : 'Sign in') + ' - Smart Ledger';
            history.replaceState(null, '', toRegister ? 'login.php?mode=register' : 'login.php');
            // Move keyboard focus to the first field of the form now showing,
            // once the slide has finished
            const first = document.getElementById(toRegister ? 'reg-name' : 'login-email');
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            setTimeout(() => first.focus({ preventScroll: true }), reduce ? 0 : 1300);
        });
    });
})();
</script>
</body>
</html>