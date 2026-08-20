<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$search = sanitize($_GET['search'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE u.name LIKE :search OR l.action LIKE :search';
    $params['search'] = '%' . $search . '%';
}
$stmt = db()->prepare('SELECT l.*, u.name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ' . $where . ' ORDER BY l.created_at DESC LIMIT 80');
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
    <section>
        <h1>Activity Logs</h1>
        <p class="muted">Monitor user actions and system events.</p>
        <div class="card section-gap layout-row">
            <div><h2>Recent Activity</h2></div>
            <form method="get" class="layout-inline-form">
                <input type="text" name="search" placeholder="Search logs" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                <button type="submit">Search</button>
            </form>
        </div>
        <div class="card section-gap">
            <div class="table-responsive">
                <table class="table-list">
                    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4">No activity logs available.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['created_at'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($log['name'] ?? 'System', ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($log['action'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($log['ip_address'], ENT_QUOTES) ?></td>
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
