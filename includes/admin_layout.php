<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_role('admin');
$user = current_user();
$csrf = create_csrf_token();
$section = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Parcel Delivery</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script defer src="../assets/js/admin.js"></script>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <h2>Parcel Admin</h2>
            <p>Welcome, <?= htmlspecialchars($user['name'], ENT_QUOTES) ?></p>
        </div>
        <nav>
            <a href="index.php" class="<?= $section === 'index.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="parcels.php" class="<?= $section === 'parcels.php' ? 'active' : '' ?>">Parcels</a>
            <a href="riders.php" class="<?= $section === 'riders.php' ? 'active' : '' ?>">Riders</a>
            <a href="reports.php" class="<?= $section === 'reports.php' ? 'active' : '' ?>">Reports</a>
            <a href="logs.php" class="<?= $section === 'logs.php' ? 'active' : '' ?>">Activity Logs</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </aside>
    <main class="page-content">
