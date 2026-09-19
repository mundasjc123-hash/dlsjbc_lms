<?php
/**
 * Run this once after importing schema.sql to add starting data.
 * From the command line:  php database/seed.php
 * This script is safe to run more than once (it will not create duplicates).
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../core/autoload.php';

$pdo = Database::connection();

// Roles
foreach (['admin', 'librarian', 'staff', 'faculty', 'student'] as $role) {
    $pdo->prepare("INSERT IGNORE INTO roles (name) VALUES (?)")->execute([$role]);
}

// Patron categories (used later to decide loan periods, fine rates, etc.)
foreach (['Undergraduate', 'Graduate', 'Faculty', 'Staff'] as $cat) {
    $pdo->prepare("INSERT IGNORE INTO patron_categories (name) VALUES (?)")->execute([$cat]);
}

// Material types
$materials = [
    ['Book', 1],
    ['Thesis', 0],
    ['Periodical', 0],
    ['AV Material', 1],
];
foreach ($materials as [$name, $circulating]) {
    $pdo->prepare("INSERT IGNORE INTO material_types (name, is_circulating) VALUES (?, ?)")
        ->execute([$name, $circulating]);
}

// Collections
foreach (['General Circulation', 'Reference', 'Course Reserve', 'Filipiniana', 'Thesis'] as $c) {
    $pdo->prepare("INSERT IGNORE INTO collections (name) VALUES (?)")->execute([$c]);
}

// One admin account so you can log in
$adminRoleId = $pdo->query("SELECT id FROM roles WHERE name = 'admin'")->fetchColumn();

$check = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
$check->execute(['ADMIN-0001']);

if (!$check->fetch()) {
    $pdo->prepare(
        "INSERT INTO users (role_id, id_number, full_name, email, password_hash)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $adminRoleId,
        'ADMIN-0001',
        'System Administrator',
        'admin@example.edu',
        password_hash('admin123', PASSWORD_ARGON2ID),
    ]);
    echo "Admin account created.\n";
    echo "  ID Number: ADMIN-0001\n";
    echo "  Password:  admin123\n";
} else {
    echo "Admin account already exists, skipped.\n";
}

echo "Seeding complete.\n";
