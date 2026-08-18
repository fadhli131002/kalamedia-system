<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$_SESSION['logged_in'] = true;
$_SESSION['active_role'] = 'admin';

$user = current_user();
echo "Current User in Session:\n";
print_r($user);

echo "\nGreeting Check: Halo, " . htmlspecialchars(explode(' ', $user['name'] ?? 'User')[0]) . "\n";
