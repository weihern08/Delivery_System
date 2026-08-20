<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=delivery_system;charset=utf8mb4', 'root', '081101');
foreach ($pdo->query('SELECT username, role, password_hash FROM users') as $row) {
    echo $row['username'], ' | ', $row['role'], ' | ', $row['password_hash'], PHP_EOL;
}

$hash1 = '$2y$10$.jybGnOavhJchBJE3F.eGuIzZi.8ZhvMhyI1WOM5mvh9jTbUjZSaG';
$hash2 = '$2y$10$/gFpe0W9hEl2Ayko/WRZZeXMDzqLxoFWrfSsN7HBlLpy1cK02P6r6';
foreach (['admin','admin123','password','123456','secret','pass','rider','delivery','Delivery123','admin@123','abc123','letmein','welcome','adminpass','demo','pass123','qwerty'] as $word) {
    if (password_verify($word, $hash1)) echo "ADMIN_MATCH: $word\n";
    if (password_verify($word, $hash2)) echo "RIDER_MATCH: $word\n";
}
