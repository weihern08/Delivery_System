<?php
require_once __DIR__ . '/../includes/rider_layout.php';
$user = current_user();
$rider = get_rider_profile((int)$user['id']);
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }
    $name = sanitize($_POST['name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $vehicle = sanitize($_POST['vehicle_number'] ?? '');

    if ($name === '' || $username === '') {
        $errors[] = 'Name and username are required.';
    } elseif (username_exists($username, $user['id'])) {
        $errors[] = 'This username is already taken.';
    }
    if (empty($errors)) {
        $stmt = db()->prepare('UPDATE users SET name = :name, username = :username, email = :email WHERE id = :id');
        $stmt->execute([
            'name' => $name,
            'username' => $username,
            'email' => $username,
            'id' => $user['id'],
        ]);

        $stmt = db()->prepare('UPDATE riders SET phone = :phone, vehicle_number = :vehicle_number WHERE user_id = :user_id');
        $stmt->execute([
            'phone' => $phone,
            'vehicle_number' => $vehicle,
            'user_id' => $user['id'],
        ]);
        $message = 'Profile updated successfully.';
    }
}
?>
    <section>
        <h1>Profile</h1>
        <p class="muted">Update your rider profile and contact information.</p>
        <?php if ($message): ?>
            <div class="alert success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert error"><?= implode('<br>', $errors) ?></div>
        <?php endif; ?>
        <div class="card section-gap">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>" required>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES) ?>" required>
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($rider['phone'] ?? '', ENT_QUOTES) ?>">
                <label for="vehicle_number">Vehicle Number</label>
                <input type="text" id="vehicle_number" name="vehicle_number" value="<?= htmlspecialchars($rider['vehicle_number'] ?? '', ENT_QUOTES) ?>">
                <button type="submit">Save Profile</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
