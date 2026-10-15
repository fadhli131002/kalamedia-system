<?php
// Test creating a new user via api/users.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Simulate owner logged in
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Owner',
    'email' => 'owner@kalamedia.id',
    'role' => 'owner'
];

// Test 1: Create Karyawan
$_POST = [
    'action' => 'create',
    'name' => 'Rian Hidayat',
    'email' => 'rian@kalamedia.id',
    'password' => 'password123',
    'role' => 'karyawan',
    'position' => 'Motion Designer',
    'phone_wa' => '08123456789'
];

ob_start();
include __DIR__ . '/../api/users.php';
$output = ob_get_clean();
echo "CREATE KARYAWAN RESULT:\n" . $output . "\n\n";

// Test 2: Create Freelancer
$_POST = [
    'action' => 'create',
    'name' => 'Siti Rahma',
    'email' => 'siti@kalamedia.id',
    'password' => 'password123',
    'role' => 'freelancer',
    'position' => 'Copywriter',
    'phone_wa' => '08129876543'
];

ob_start();
include __DIR__ . '/../api/users.php';
$output = ob_get_clean();
echo "CREATE FREELANCER RESULT:\n" . $output . "\n\n";

// Test 3: Create Client
$_POST = [
    'action' => 'create',
    'name' => 'Hendra Wijaya',
    'email' => 'hendra@autolux.id',
    'password' => 'password123',
    'role' => 'client',
    'client_id' => 1
];

ob_start();
include __DIR__ . '/../api/users.php';
$output = ob_get_clean();
echo "CREATE CLIENT RESULT:\n" . $output . "\n\n";

// Verify DB
$db = Database::getConnection();
$users = $db->query("SELECT id, name, email, role, client_id FROM users WHERE email IN ('rian@kalamedia.id', 'siti@kalamedia.id', 'hendra@autolux.id')")->fetchAll(PDO::FETCH_ASSOC);
echo "VERIFIED IN DB:\n";
print_r($users);
