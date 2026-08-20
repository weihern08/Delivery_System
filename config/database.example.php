<?php
declare(strict_types=1);

session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'your_database_name';
const DB_USER = 'your_database_user';
const DB_PASS = 'your_database_password';
const DB_CHARSET = 'utf8mb4';

function migrate_schema(PDO $pdo): void
{
    $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table AND column_name = :column');
    $columnCheck->execute(['schema' => DB_NAME, 'table' => 'users', 'column' => 'username']);
    if ((int) $columnCheck->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(120) NULL AFTER name');
        $pdo->exec('UPDATE users SET username = CASE WHEN email IS NOT NULL AND email != "" THEN email ELSE CONCAT("user", id) END');
        $pdo->exec('ALTER TABLE users MODIFY COLUMN username VARCHAR(120) NOT NULL, ADD UNIQUE INDEX username_unique (username)');
    }
}

function ensure_schema(PDO $pdo): void
{
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table');
    $check->execute(['schema' => DB_NAME, 'table' => 'users']);
    if ((int) $check->fetchColumn() > 0) {
        migrate_schema($pdo);
        return;
    }

    $schemaFile = __DIR__ . '/../sql/database.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException('Database schema file is missing: ' . $schemaFile);
    }

    $sql = file_get_contents($schemaFile);
    $lines = preg_split('/\r?\n/', $sql);
    $statements = [];
    $current = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $current .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $statements[] = trim($current);
            $current = '';
        }
    }
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function db(): PDO
{
    static $pdo;

    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $connectionAttempts = [
            ['host' => DB_HOST, 'user' => DB_USER, 'pass' => DB_PASS],
            ['host' => DB_HOST, 'user' => DB_USER, 'pass' => ''],
            ['host' => 'localhost', 'user' => DB_USER, 'pass' => DB_PASS],
            ['host' => 'localhost', 'user' => DB_USER, 'pass' => ''],
        ];

        $lastException = null;
        foreach ($connectionAttempts as $attempt) {
            try {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $attempt['host'], DB_NAME, DB_CHARSET);
                $pdo = new PDO($dsn, $attempt['user'], $attempt['pass'], $options);
                break;
            } catch (PDOException $e) {
                $lastException = $e;
                if (str_contains($e->getMessage(), 'Unknown database')) {
                    $dsn = sprintf('mysql:host=%s;charset=%s', $attempt['host'], DB_CHARSET);
                    $pdo = new PDO($dsn, $attempt['user'], $attempt['pass'], $options);
                    $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci', DB_NAME, DB_CHARSET, DB_CHARSET));
                    $pdo->exec(sprintf('USE `%s`', DB_NAME));
                    break;
                }
            }
        }

        if ($pdo === null) {
            throw $lastException;
        }

        ensure_schema($pdo);
    }

    return $pdo;
}

function base_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    $script = rtrim(str_replace('\\', '/', $script), '/');
    return $scheme . '://' . $host . $script . '/' . ltrim($path, '/');
}
