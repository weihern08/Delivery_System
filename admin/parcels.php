<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$errors = [];
$search = sanitize($_GET['search'] ?? '');
$action = $_GET['action'] ?? '';
$parcelId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$editParcel = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_parcel'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }
    $trackingNumber = sanitize($_POST['tracking_number'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $assignedRiderId = filter_input(INPUT_POST, 'assigned_rider_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? 'pending';

    if ($trackingNumber === '') {
        $trackingNumber = 'PARC' . strtoupper(bin2hex(random_bytes(4)));
    }
    if ($address === '') {
        $errors[] = 'Destination address is required.';
    }
    if (!in_array($status, ['pending', 'out_for_delivery', 'delivered', 'failed_delivery'], true)) {
        $status = 'pending';
    }

    if (empty($errors)) {
        if (!empty($_POST['id']) && filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)) {
            $stmt = db()->prepare('UPDATE parcels SET tracking_number = :tracking_number, address = :address, status = :status, assigned_rider_id = :assigned_rider_id, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'tracking_number' => $trackingNumber,
                'address' => $address,
                'status' => $status,
                'assigned_rider_id' => $assignedRiderId ?: null,
                'id' => (int) $_POST['id'],
            ]);
            record_activity($_SESSION['user_id'], 'Updated parcel ' . $trackingNumber);
        } else {
            $stmt = db()->prepare('INSERT INTO parcels (tracking_number, address, status, assigned_rider_id, created_at, updated_at) VALUES (:tracking_number, :address, :status, NULL, NOW(), NOW())');
            $stmt->execute([
                'tracking_number' => $trackingNumber,
                'address' => $address,
                'status' => $status,
            ]);
            record_activity($_SESSION['user_id'], 'Created parcel ' . $trackingNumber);
        }
        redirect('parcels.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_parcel'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $deleteParcelId = filter_input(INPUT_POST, 'delete_parcel', FILTER_VALIDATE_INT);
        if ($deleteParcelId) {
            $stmt = db()->prepare('DELETE FROM parcels WHERE id = :id');
            $stmt->execute(['id' => $deleteParcelId]);
            record_activity($_SESSION['user_id'], 'Deleted parcel ID ' . $deleteParcelId);
            redirect('parcels.php');
        }
    }
}

if ($action === 'delete' && $parcelId) {
    // keep redirect from GET-based delete only for backwards compatibility
    redirect('parcels.php');
}

if ($action === 'edit' && $parcelId) {
    $stmt = db()->prepare('SELECT * FROM parcels WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $parcelId]);
    $editParcel = $stmt->fetch();
}

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE tracking_number LIKE :search OR address LIKE :search';
    $params['search'] = '%' . $search . '%';
}
$parcels = db()->prepare('SELECT p.*, u.name AS rider_name FROM parcels p LEFT JOIN users u ON u.id = p.assigned_rider_id ' . $where . ' ORDER BY p.created_at DESC');
$parcels->execute($params);
$parcels = $parcels->fetchAll();
$riders = db()->query('SELECT u.id, u.name FROM users u WHERE u.role = "rider" ORDER BY u.name')->fetchAll();
?>
    <section>
        <h1>Parcel Management</h1>
        <p class="muted">Create, assign, and update parcel delivery records.</p>

        <?php if ($errors): ?>
            <div class="alert error"><?= implode('<br>', $errors) ?></div>
        <?php endif; ?>

        <div class="card section-gap">
            <h2><?= $editParcel ? 'Edit Parcel' : 'Create Parcel' ?></h2>
            <form method="post">
                <input type="hidden" name="save_parcel" value="1">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="id" value="<?= $editParcel['id'] ?? '' ?>">
                <label for="tracking_number">Tracking Number</label>
                <input type="text" id="tracking_number" name="tracking_number" value="<?= htmlspecialchars($editParcel['tracking_number'] ?? '', ENT_QUOTES) ?>" placeholder="Auto-generated if blank">
                <label for="address">Destination Address</label>
                <input type="text" id="address-autocomplete" name="address" value="<?= htmlspecialchars($editParcel['address'] ?? '', ENT_QUOTES) ?>" autocomplete="off" required>
                <div id="address-suggestions" style="display:none; position:relative; max-width:520px; margin-top:8px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; box-shadow:0 12px 24px rgba(15,23,42,0.08); overflow:hidden; max-height:240px; overflow-y:auto;"></div>
                <label for="assigned_rider_id">Assigned Rider</label>
                <select id="assigned_rider_id" name="assigned_rider_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($riders as $rider): ?>
                        <option value="<?= $rider['id'] ?>" <?= isset($editParcel['assigned_rider_id']) && $editParcel['assigned_rider_id'] == $rider['id'] ? 'selected' : '' ?>><?= htmlspecialchars($rider['name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="muted">This parcel is open for any rider to claim when unassigned. Admin can still reassign it here.</p>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="pending" <?= ($editParcel['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="out_for_delivery" <?= ($editParcel['status'] ?? '') === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                    <option value="delivered" <?= ($editParcel['status'] ?? '') === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="failed_delivery" <?= ($editParcel['status'] ?? '') === 'failed_delivery' ? 'selected' : '' ?>>Failed Delivery</option>
                </select>
                <button type="submit"><?= $editParcel ? 'Update Parcel' : 'Create Parcel' ?></button>
            </form>
        </div>

        <div class="card section-gap-large">
            <div class="layout-row">
                <div><h2>Parcel List</h2></div>
                <form method="get" class="layout-inline-form">
                    <input class="input-search" type="text" name="search" placeholder="Search parcels" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            <div class="table-responsive section-gap">
                <table class="table-list">
                    <thead><tr><th>Tracking</th><th>Destination</th><th>Rider</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($parcels)): ?>
                            <tr><td colspan="5">No parcel records found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($parcels as $parcel): ?>
                            <tr>
                                <td><?= htmlspecialchars($parcel['tracking_number'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($parcel['address'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($parcel['rider_name'] ?? 'Open claim', ENT_QUOTES) ?></td>
                                <td><span class="status-pill status-<?= $parcel['status'] ?>"><?= ucfirst(str_replace('_', ' ', $parcel['status'])) ?></span></td>
                                <td class="button-row">
                                    <a href="parcels.php?action=edit&id=<?= $parcel['id'] ?>" class="input-button btn-dark">Edit</a>
                                    <a href="parcel_view.php?id=<?= $parcel['id'] ?>" class="input-button btn-secondary">View</a>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <button type="submit" name="delete_parcel" value="<?= $parcel['id'] ?>" class="input-button btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const addressInput = document.getElementById('address-autocomplete');
        const suggestionsBox = document.getElementById('address-suggestions');
        if (!addressInput || !suggestionsBox) return;

        let timer = null;
        const apiUrl = new URL('../api/search.php', window.location.href);

        const hideSuggestions = () => {
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
        };

        const showSuggestions = (items) => {
            if (!Array.isArray(items) || !items.length) {
                hideSuggestions();
                return;
            }

            suggestionsBox.innerHTML = items.map((item) => {
                const label = item.display_name || 'Address';
                return `
                    <button type="button" class="address-suggestion-item" data-address="${label.replace(/"/g, '&quot;')}" style="display:block; width:100%; text-align:left; border:0; border-bottom:1px solid #f3f4f6; background:#fff; padding:10px 12px; cursor:pointer; font-size:14px; color:#111827;">
                        <strong style="display:block; margin-bottom:3px;">${label}</strong>
                        <span style="color:#6b7280; font-size:12px;">${item.type || 'Address'}</span>
                    </button>
                `;
            }).join('');

            suggestionsBox.style.display = 'block';
            suggestionsBox.querySelectorAll('.address-suggestion-item').forEach((button) => {
                button.addEventListener('click', () => {
                    addressInput.value = button.dataset.address;
                    hideSuggestions();
                });
            });
        };

        const fetchSuggestions = async () => {
            const query = addressInput.value.trim();
            if (query.length < 2) {
                hideSuggestions();
                return;
            }

            const url = new URL(apiUrl.href);
            url.searchParams.set('q', query);

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Lookup failed');
                const data = await response.json();
                if (Array.isArray(data)) {
                    showSuggestions(data);
                } else {
                    hideSuggestions();
                }
            } catch (error) {
                hideSuggestions();
            }
        };

        addressInput.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(fetchSuggestions, 250);
        });

        document.addEventListener('click', (event) => {
            if (!suggestionsBox.contains(event.target) && event.target !== addressInput) {
                hideSuggestions();
            }
        });
    });
</script>
</body>
</html>
