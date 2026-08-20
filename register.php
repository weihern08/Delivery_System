<?php
require_once __DIR__ . '/includes/functions.php';
if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || $username === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (username_exists($username)) {
        $error = 'This username is already taken.';
    } else {
        $user = create_user($name, $username, $password, 'rider');
        $stmt = db()->prepare('INSERT INTO riders (user_id, status, updated_at) VALUES (:user_id, :status, NOW())');
        $stmt->execute(['user_id' => $user['id'], 'status' => 'offline']);
        login_user($user);
        record_activity($user['id'], 'Rider registered');
        redirect('rider/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Rider - Parcel Delivery</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <h1>Register Rider</h1>
        <?php if ($error): ?>
            <div class="alert error"><?= $error ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) ?>" required>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <button type="submit">Create Account</button>
        </form>
        <p class="form-note">
            Already have an account? <a href="index.php">Login</a>
        </p>
    </div>
</body>
</html>
