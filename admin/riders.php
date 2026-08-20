<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$search = sanitize($_GET['search'] ?? '');
$errors = [];
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_rider'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }
    $name = sanitize($_POST['name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');
    $vehicle = sanitize($_POST['vehicle_number'] ?? '');

    if ($name === '' || $username === '' || $password === '') {
        $errors[] = 'Name, username, and password are required.';
    }
    if ($username !== '' && username_exists($username)) {
        $errors[] = 'Username already exists.';
    }
    if (empty($errors)) {
        $newUser = create_user($name, $username, $password, 'rider');
        $stmt = db()->prepare('INSERT INTO riders (user_id, phone, vehicle_number, status, updated_at) VALUES (:user_id, :phone, :vehicle_number, :status, NOW())');
        $stmt->execute([
            'user_id' => $newUser['id'],
            'phone' => $phone ?: null,
            'vehicle_number' => $vehicle ?: null,
            'status' => 'offline',
        ]);
        $message = 'Rider account created successfully.';
    }
}
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE u.name LIKE :search OR u.username LIKE :search';
    $params['search'] = '%' . $search . '%';
}
$stmt = db()->prepare('SELECT u.id, u.name, u.username, r.status, r.phone, r.vehicle_number FROM users u JOIN riders r ON r.user_id = u.id ' . $where . ' ORDER BY u.name');
$stmt->execute($params);
$riders = $stmt->fetchAll();
?>
    <section>
        <h1>Rider Directory</h1>
        <p class="muted">Review rider availability and contact details.</p>
        <?php if ($message): ?>
            <div class="alert success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert error"><?= implode('<br>', $errors) ?></div>
        <?php endif; ?>
        <div class="card section-gap panel-grid">
            <div>
                <h2>Create Rider Account</h2>
                <p class="muted">Add a new delivery rider and their mobile details.</p>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="create_rider" value="1">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone">
                <label for="vehicle_number">Vehicle Number</label>
                <input type="text" id="vehicle_number" name="vehicle_number">
                <button type="submit">Create Rider</button>
            </form>
        </div>
        <div class="card section-gap layout-row">
            <div><h2>Rider List</h2></div>
            <form method="get" class="layout-inline-form">
                <input type="text" name="search" placeholder="Search riders" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                <button type="submit">Search</button>
            </form>
        </div>
        <div class="card section-gap">
            <div class="table-responsive">
                <table class="table-list">
                    <thead><tr><th>Name</th><th>Username</th><th>Phone</th><th>Vehicle</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($riders)): ?>
                            <tr><td colspan="5">No riders found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($riders as $rider): ?>
                            <tr>
                                <td><?= htmlspecialchars($rider['name'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($rider['username'] ?? '', ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($rider['phone'] ?? '-', ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($rider['vehicle_number'] ?? '-', ENT_QUOTES) ?></td>
                                <td><span class="badge <?= strtolower((string) $rider['status']) === 'online' ? 'online' : 'offline' ?>"><?= htmlspecialchars($rider['status'], ENT_QUOTES) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>
