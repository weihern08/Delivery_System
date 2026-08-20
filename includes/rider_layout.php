<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_role('rider');
$user = current_user();
$csrf = create_csrf_token();
$section = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - Parcel Delivery</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script defer src="../assets/js/rider.js?t=<?= time() ?>"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <h2>Rider Panel</h2>
            <p><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></p>
        </div>
        <nav>
            <a href="index.php" class="<?= $section === 'index.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="history.php" class="<?= $section === 'history.php' ? 'active' : '' ?>">Delivery History</a>
            <a href="profile.php" class="<?= $section === 'profile.php' ? 'active' : '' ?>">Profile</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </aside>
    <main class="page-content">
