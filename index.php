<?php
session_start();

// Land a signed-in user on the dashboard, everyone else on the login page.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>