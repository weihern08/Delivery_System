<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    $userRole = $_SESSION['user_role'] ?? null;
    if ($userRole === 'admin') {
        redirect('admin/index.php');
    }
    if ($userRole === 'rider') {
        redirect('rider/index.php');
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            record_activity((int)$user['id'], 'User logged in');
            redirect($user['role'] === 'admin' ? 'admin/index.php' : 'rider/index.php');
        }

        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcel Delivery System - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1>Delivery System Login</h1>
        <?php if ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Sign In</button>
        </form>
        <div class="form-note" style="margin-top: 14px; font-size: 0.9rem; line-height: 1.6;">
            <strong>Demo Login:</strong><br>
            Admin: admin / admin123<br>
            User: weihern / 123
        </div>
        <p class="form-note">
            Don't have an account? <a href="register.php">Register as Rider</a>
        </p>
    </div>
</body>
</html>
