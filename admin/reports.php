<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$period = sanitize($_GET['period'] ?? '30');
$valid = ['7','30','90'];
if (!in_array($period, $valid, true)) $period = '30';
$stmt = db()->prepare('SELECT p.id AS parcel_id, p.tracking_number, p.address, p.status, u.name AS rider_name, u.id AS rider_user_id, p.updated_at FROM parcels p LEFT JOIN users u ON u.id = p.assigned_rider_id WHERE p.updated_at >= DATE_SUB(NOW(), INTERVAL :days DAY) ORDER BY p.updated_at DESC');
$stmt->execute(['days' => $period]);
$parcels = $stmt->fetchAll();
?>
    <section>
        <h1>Reports</h1>
        <p class="muted">Generate recent delivery and rider performance reports.</p>
        <div class="card section-gap layout-row">
            <div><h2>Delivery Summary</h2></div>
            <form method="get" class="layout-inline-form">
                <label for="period">Last</label>
                <select name="period" id="period">
                    <option value="7" <?= $period === '7' ? 'selected' : '' ?>>7 days</option>
                    <option value="30" <?= $period === '30' ? 'selected' : '' ?>>30 days</option>
                    <option value="90" <?= $period === '90' ? 'selected' : '' ?>>90 days</option>
                </select>
                <button type="submit">Show</button>
            </form>
        </div>
        <div class="card section-gap">
            <div class="table-responsive">
                <table class="table-list">
                    <thead><tr><th>Tracking</th><th>Address</th><th>Rider</th><th>Route</th><th>Status</th><th>Updated</th></tr></thead>
                    <tbody>
                        <?php if (empty($parcels)): ?>
                            <tr><td colspan="6">No record found for this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($parcels as $parcel): ?>
                            <tr>
                                <td><?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($parcel['rider_name'] ?? 'Unassigned', ENT_QUOTES) ?></td>
                                <td>
                                    <?php if (!empty($parcel['parcel_id'])): ?>
                                        <a href="parcel_view.php?id=<?= (int)$parcel['parcel_id'] ?>" class="input-button btn-secondary">View route</a>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span></td>
                                <td><?= htmlspecialchars($parcel['updated_at'], ENT_QUOTES) ?></td>
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
