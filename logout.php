<?php
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    record_activity((int) $_SESSION['user_id'], 'User logged out');
}
logout_user();
redirect('index.php');
