<?php
/**
 * datebalazs.com — PHP API
 * Upload this file to your hosting alongside index.html
 * Update the DB credentials below, then it just works.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── DB CONFIG — change these! ──────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'datebala_techuser');
define('DB_PASS', '9Vu6V4Jma4DAW-l}');
define('DB_NAME', 'datebala_datebalazs');
// ──────────────────────────────────────────────────────────

function logError($message) {
    $logFile = __DIR__ . '/api_error.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    error_log($entry, 3, $logFile);
}

function ensureSchema($db) {
    $sql = file_get_contents(__DIR__ . '/setup.sql');
    if ($sql === false) {
        logError('Could not read setup.sql from ' . __DIR__ . '/setup.sql');
        return;
    }

    if ($db->multi_query($sql)) {
        do {
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());
    } else {
        logError('Schema initialization failed: ' . $db->error);
    }
}

function getDB() {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($db->connect_error) {
        $msg = 'DB connection failed: ' . $db->connect_error;
        logError($msg);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB connection failed', 'debug' => $db->connect_error]);
        exit;
    }

    $db->set_charset('utf8mb4');
    if (!$db->select_db(DB_NAME)) {
        $msg = 'Select database failed: ' . $db->error;
        logError($msg);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Select database failed', 'debug' => $db->error]);
        exit;
    }

    ensureSchema($db);
    return $db;
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$action = basename($path); // 'event', 'reviews', 'contacts'

// ── POST /api.php?action=event ────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) { echo json_encode(['ok' => false, 'error' => 'Invalid JSON']); exit; }

    $type = $body['type'] ?? '';
    $data = json_encode($body['data'] ?? []);
    $ts   = $body['ts']   ?? date('c');
    $ts   = date('Y-m-d H:i:s', strtotime($ts));

    $db = getDB();

    // 1. Log everything to events table
    $stmt = $db->prepare('INSERT INTO events (type, data, ts) VALUES (?, ?, ?)');
    if (!$stmt) {
        $msg = 'Prepare events failed: ' . $db->error;
        logError($msg);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Event insert failed', 'debug' => $db->error]);
        exit;
    }
    $stmt->bind_param('sss', $type, $data, $ts);
    if (!$stmt->execute()) {
        $msg = 'Execute events failed: ' . $stmt->error;
        logError($msg);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Event insert failed', 'debug' => $stmt->error]);
        exit;
    }

    if ($type === 'contact') {
        $d               = $body['data'] ?? [];
        $instagram       = $d['instagram'] ?? '';
        $facebook        = $d['facebook']  ?? '';
        $whatsapp        = $d['whatsapp']  ?? '';
        $secure_chat     = $d['secure_chat'] ?? $d['signal'] ?? '';
        $bumble_profile  = $d['bumble_profile'] ?? $d['bumble'] ?? '';
        $lang            = $d['lang']      ?? '';

        $stmt2 = $db->prepare(
            'INSERT INTO contacts (instagram, facebook, whatsapp, secure_chat, bumble_profile, lang, ts)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt2) {
            $msg = 'Prepare contacts failed: ' . $db->error;
            logError($msg);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Contact insert failed', 'debug' => $db->error]);
            exit;
        }
        $stmt2->bind_param('sssssss', $instagram, $facebook, $whatsapp, $secure_chat, $bumble_profile, $lang, $ts);
        if (!$stmt2->execute()) {
            $msg = 'Execute contacts failed: ' . $stmt2->error;
            logError($msg);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Contact insert failed', 'debug' => $stmt2->error]);
            exit;
        }
    }

    if ($type === 'review') {
        $d     = $body['data'] ?? [];
        $name  = $d['name']  ?? 'Anonymous';
        $stars = (int)($d['stars'] ?? 0);
        $text  = $d['text']  ?? '';
        $lang  = $d['lang']  ?? '';

        $stmt3 = $db->prepare(
            'INSERT INTO reviews (name, stars, review, lang, ts) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt3) {
            $msg = 'Prepare reviews failed: ' . $db->error;
            logError($msg);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Review insert failed', 'debug' => $db->error]);
            exit;
        }
        $stmt3->bind_param('sisss', $name, $stars, $text, $lang, $ts);
        if (!$stmt3->execute()) {
            $msg = 'Execute reviews failed: ' . $stmt3->error;
            logError($msg);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Review insert failed', 'debug' => $stmt3->error]);
            exit;
        }
    }

    $db->close();
    echo json_encode(['ok' => true]);
    exit;
}

// ── GET ?action=reviews ───────────────────────────────────
if ($method === 'GET' && ($_GET['action'] ?? '') === 'reviews') {
    $db = getDB();
    $result = $db->query('SELECT name, stars, review AS text, ts FROM reviews ORDER BY ts DESC LIMIT 50');
    if (!$result) {
        $msg = 'Query reviews failed: ' . $db->error;
        logError($msg);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Review load failed', 'debug' => $db->error]);
        exit;
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $db->close();
    echo json_encode($rows);
    exit;
}

// ── GET ?action=contacts ──────────────────────────────────
if ($method === 'GET' && ($_GET['action'] ?? '') === 'contacts') {
    $db = getDB();
    $result = $db->query('SELECT * FROM contacts ORDER BY ts DESC');
    if (!$result) {
        $msg = 'Query contacts failed: ' . $db->error;
        logError($msg);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Contacts load failed', 'debug' => $db->error]);
        exit;
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $db->close();
    echo json_encode($rows);
    exit;
}


// -- GET ?action=kpis -------------------------------------------------------
if ($method === 'GET' && ($_GET['action'] ?? '') === 'kpis') {
    $db = getDB();

    function scalar($db, $sql) {
        $res = $db->query($sql);
        if (!$res) return 0;
        $row = $res->fetch_row();
        return $row ? (int)$row[0] : 0;
    }

    $summary = [
        'total_events'    => scalar($db, "SELECT COUNT(*) FROM events"),
        'page_loads'      => scalar($db, "SELECT COUNT(*) FROM events WHERE type='page_load'"),
        'unique_sessions' => scalar($db, "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(data,'$.sessionId'))) FROM events WHERE JSON_EXTRACT(data,'$.sessionId') IS NOT NULL"),
        'contacts'        => scalar($db, "SELECT COUNT(*) FROM contacts"),
        'reviews'         => scalar($db, "SELECT COUNT(*) FROM reviews"),
        'yes_clicks'      => scalar($db, "SELECT COUNT(*) FROM events WHERE type='yes'"),
        'no_clicks'       => scalar($db, "SELECT COUNT(*) FROM events WHERE type='no'"),
        'avg_stay_seconds'=> scalar($db, "SELECT COALESCE(ROUND(AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.durationMs')) AS UNSIGNED))/1000),0) FROM events WHERE type='page_duration'"),
    ];

    $types = [];
    $res = $db->query("SELECT type, COUNT(*) AS total FROM events GROUP BY type ORDER BY total DESC");
    while ($res && ($row = $res->fetch_assoc())) $types[] = $row;

    $daily = [];
    $res = $db->query("SELECT DATE(ts) AS day, COUNT(*) AS total, SUM(type='page_load') AS page_loads, SUM(type='contact') AS contacts, SUM(type='review') AS reviews FROM events GROUP BY DATE(ts) ORDER BY day DESC LIMIT 30");
    while ($res && ($row = $res->fetch_assoc())) $daily[] = $row;

    $recent = [];
    $res = $db->query("SELECT id, type, data, ts, created_at FROM events ORDER BY id DESC LIMIT 100");
    while ($res && ($row = $res->fetch_assoc())) {
        $row['data'] = json_decode($row['data'], true);
        $recent[] = $row;
    }

    $db->close();
    echo json_encode(['ok'=>true, 'summary'=>$summary, 'types'=>$types, 'daily'=>$daily, 'recent'=>$recent]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
