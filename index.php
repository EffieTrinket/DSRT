<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'volunteer') {
        header('Location: pages/disasters.php');
    } else {
        header('Location: pages/dashboard.php');
    }
} else {
    header('Location: login.php');
}
exit;
?>
