<?php
// ModerUtills License Server — PHP Backend
// Залей этот файл на PHP-хостинг (не GitHub Pages)
// Он будет принимать verify-запросы от мода и от index.html
//
// После заливки URL будет вида: https://твой-сайт/api/index.php
// Этот URL укажи в index.html как API_URL

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$DB_PATH = __DIR__ . '/../data/license.db';
$ADMIN_PASS = 'admin123';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function getDB($path) {
    global $DB_PATH;
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
    return $db;
}

function jsonOut($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function genKey() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $chars[rand(0, strlen($chars) - 1)];
    return 'MA-' . $s;
}

// ---- VERIFY ----
if (strpos($path, 'verify') !== false) {
    $hwid = trim($_GET['hwid'] ?? '');
    $key = trim($_GET['key'] ?? '');
    $username = trim($_GET['username'] ?? '');

    if ($hwid === 'ping') jsonOut(['success' => false, 'ping' => true]);
    if (!$hwid) jsonOut(['success' => false, 'error' => 'Missing HWID']);

    $db = getDB($path);

    // Если передан ключ — ищем по ключу (формат от Java-мода)
    if ($key) {
        $stmt = $db->prepare("SELECT * FROM users WHERE license_key = ?");
        $stmt->bindValue(1, $key, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$row) jsonOut(['success' => false, 'error' => 'License key not found']);
        if (!$row['subscription_active']) jsonOut(['success' => false, 'error' => 'Subscription inactive']);

        if (empty($row['hwid'])) {
            $upd = $db->prepare("UPDATE users SET hwid = ?, username = ? WHERE id = ?");
            $upd->bindValue(1, $hwid, SQLITE3_TEXT);
            $upd->bindValue(2, $username ?: $row['username'], SQLITE3_TEXT);
            $upd->bindValue(3, $row['id'], SQLITE3_INTEGER);
            $upd->execute();
            jsonOut(['success' => true, 'message' => 'Activated', 'license_key' => $row['license_key']]);
        } elseif ($row['hwid'] === $hwid) {
            $upd = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
            $upd->bindValue(1, $username ?: $row['username'], SQLITE3_TEXT);
            $upd->bindValue(2, $row['id'], SQLITE3_INTEGER);
            $upd->execute();
            jsonOut(['success' => true, 'message' => 'Verified', 'license_key' => $row['license_key']]);
        } else {
            jsonOut(['success' => false, 'error' => 'HWID mismatch']);
        }
    }

    // Поиск по HWID (формат с сайта)
    $stmt = $db->prepare("SELECT * FROM users WHERE hwid = ? AND hwid != ''");
    $stmt->bindValue(1, $hwid, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$row) jsonOut(['success' => false, 'error' => 'HWID not found']);
    if (!$row['subscription_active']) jsonOut(['success' => false, 'error' => 'Subscription inactive']);

    jsonOut([
        'success' => true,
        'message' => 'Доступ разрешён',
        'license_key' => $row['license_key'],
        'username' => $row['username'],
    ]);
}

// ---- ADMIN ----
if (strpos($path, 'admin') !== false) {
    $db = getDB($path);

    // GET list
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'list') {
            $users = [];
            $res = $db->query("SELECT * FROM users ORDER BY id DESC");
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $users[] = $row;
            }
            jsonOut(['users' => $users]);
        }
        jsonOut(['error' => 'Unknown action']);
    }

    // POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($auth !== $ADMIN_PASS) {
            http_response_code(401);
            jsonOut(['error' => 'Unauthorized']);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        $id = (int)($data['id'] ?? 0);

        switch ($action) {
            case 'add':
                $key = genKey();
                $stmt = $db->prepare("INSERT INTO users (username, hwid, license_key, subscription_active) VALUES (?, ?, ?, ?)");
                $stmt->bindValue(1, $data['username'] ?? 'unknown', SQLITE3_TEXT);
                $stmt->bindValue(2, $data['hwid'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(3, $key, SQLITE3_TEXT);
                $stmt->bindValue(4, ($data['subscription_active'] ?? 0) ? 1 : 0, SQLITE3_INTEGER);
                $stmt->execute();
                jsonOut(['success' => true]);
                break;

            case 'delete':
                $db->exec("DELETE FROM users WHERE id = $id");
                jsonOut(['success' => true]);
                break;

            case 'toggle':
                $row = $db->querySingle("SELECT subscription_active FROM users WHERE id = $id", true);
                if ($row) {
                    $new = $row['subscription_active'] ? 0 : 1;
                    $db->exec("UPDATE users SET subscription_active = $new WHERE id = $id");
                }
                jsonOut(['success' => true]);
                break;

            case 'regen':
                $newKey = genKey();
                $stmt = $db->prepare("UPDATE users SET license_key = ? WHERE id = ?");
                $stmt->bindValue(1, $newKey, SQLITE3_TEXT);
                $stmt->bindValue(2, $id, SQLITE3_INTEGER);
                $stmt->execute();
                jsonOut(['success' => true]);
                break;

            case 'resethwid':
                $db->exec("UPDATE users SET hwid = '' WHERE id = $id");
                jsonOut(['success' => true]);
                break;

            default:
                jsonOut(['error' => 'Unknown action']);
        }
    }
}

jsonOut(['error' => 'Not found']);
