<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function username_exists(string $username, ?int $excludeId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
    $params = ['username' => $username];
    if ($excludeId !== null) {
        $sql .= ' AND id != :exclude_id';
        $params['exclude_id'] = $excludeId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

function create_user(string $name, string $username, string $password, string $role = 'rider'): array
{
    $stmt = db()->prepare('INSERT INTO users (name, username, email, password_hash, role, created_at) VALUES (:name, :username, :email, :password_hash, :role, NOW())');
    $stmt->execute([
        'name' => $name,
        'username' => $username,
        'email' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
    return ['id' => (int) db()->lastInsertId(), 'name' => $name, 'username' => $username, 'role' => $role];
}

function create_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('../index.php');
    }
}

function require_role(string $role): void
{
    require_login();
    if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

function fetch_counts(): array
{
    $pdo = db();
    $summary = [];

    $summary['total_riders'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'rider'")->fetchColumn();
    $summary['online_riders'] = (int) $pdo->query("SELECT COUNT(*) FROM riders WHERE status = 'online'")->fetchColumn();
    $summary['offline_riders'] = $summary['total_riders'] - $summary['online_riders'];
    $summary['total_parcels'] = (int) $pdo->query('SELECT COUNT(*) FROM parcels')->fetchColumn();
    $summary['delivered_parcels'] = (int) $pdo->query("SELECT COUNT(*) FROM parcels WHERE status = 'delivered'")->fetchColumn();
    $summary['pending_parcels'] = (int) $pdo->query("SELECT COUNT(*) FROM parcels WHERE status IN ('pending','out_for_delivery')")->fetchColumn();

    return $summary;
}

function record_activity(int $userId, string $action): void
{
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, ip_address, created_at) VALUES (:user_id, :action, :ip_address, NOW())');
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
}

function get_rider_profile(int $userId): ?array
{
    $stmt = db()->prepare('SELECT r.*, u.name, u.username FROM riders r JOIN users u ON u.id = r.user_id WHERE r.user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetch() ?: null;
}

function get_assigned_parcels(int $riderId): array
{
    $stmt = db()->prepare('SELECT p.*, u.name AS rider_name FROM parcels p LEFT JOIN users u ON u.id = p.assigned_rider_id WHERE p.assigned_rider_id = :rider_id ORDER BY p.created_at DESC');
    $stmt->execute(['rider_id' => $riderId]);
    return $stmt->fetchAll();
}

function get_available_parcels(): array
{
    $stmt = db()->prepare('SELECT p.*, u.name AS rider_name FROM parcels p LEFT JOIN users u ON u.id = p.assigned_rider_id WHERE p.assigned_rider_id IS NULL AND p.status != "delivered" ORDER BY p.created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}
