<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$key = trim($_GET['key'] ?? '');
$hwid = trim($_GET['hwid'] ?? '');
$username = trim($_GET['username'] ?? '');

if (!$key || !$hwid || !$username) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

if (!preg_match('/^MA-[A-Z0-9]{8}$/', $key)) {
    echo json_encode(['success' => false, 'error' => 'Invalid key format']);
    exit;
}

try {
    $DB_PATH = __DIR__ . '/../data/license.db';
    $dir = dirname($DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $db = new SQLite3($DB_PATH);
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        hwid TEXT DEFAULT '',
        license_key TEXT NOT NULL UNIQUE,
        subscription_active INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $db->prepare("SELECT * FROM users WHERE license_key = ?");
    $stmt->bindValue(1, $key, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'License key not found']);
        exit;
    }

    if (!$row['subscription_active']) {
        echo json_encode(['success' => false, 'error' => 'Subscription is inactive']);
        exit;
    }

    if ($row['hwid'] === '') {
        $upd = $db->prepare("UPDATE users SET hwid = ?, username = ? WHERE id = ?");
        $upd->bindValue(1, $hwid, SQLITE3_TEXT);
        $upd->bindValue(2, $username, SQLITE3_TEXT);
        $upd->bindValue(3, $row['id'], SQLITE3_INTEGER);
        $upd->execute();
        echo json_encode(['success' => true, 'message' => 'Activated']);
    } elseif ($row['hwid'] === $hwid) {
        $upd = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
        $upd->bindValue(1, $username, SQLITE3_TEXT);
        $upd->bindValue(2, $row['id'], SQLITE3_INTEGER);
        $upd->execute();
        echo json_encode(['success' => true, 'message' => 'Verified']);
    } else {
        echo json_encode(['success' => false, 'error' => 'HWID mismatch']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
