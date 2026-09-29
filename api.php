<?php
/**
 * datebalazs.com — PHP API
 * Upload this file to your hosting alongside index.html.
 * Emails every button press (with the current results) to NOTIFY_TO.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('NOTIFY_TO', 'sztrokayb@gmail.com');
// Must be a real mailbox in cPanel → Email Accounts: the host's outgoing spam filter rejects senders that don't exist.
define('NOTIFY_FROM', 'website@datebalazs.com');

function logError($message) {
    $logFile = __DIR__ . '/api_error.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    error_log($entry, 3, $logFile);
}

function sendNotification($d, $ts) {
    $action = substr(strip_tags((string)($d['action'] ?? 'button')), 0, 200);
    $list = function ($v) { return is_array($v) && $v ? implode("\n  - ", array_map('strval', $v)) : '-'; };

    $text = "Action: $action\n"
          . "Time: $ts\n"
          . "Language: " . ($d['lang'] ?? '-') . "\n\n"
          . "Spin result: " . (($d['spin'] ?? '') ?: '-') . "\n"
          . "Travel picks:\n  - " . $list($d['travel'] ?? []) . "\n"
          . "Quiz answers:\n  - " . $list($d['quiz'] ?? []) . "\n"
          . "Quiz score: " . (($d['quizScore'] ?? '') ?: '-') . "\n\n"
          . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n"
          . "Browser: " . ($_SERVER['HTTP_USER_AGENT'] ?? '-') . "\n";

    $headers = "From: Date Balazs <" . NOTIFY_FROM . ">\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    $subject = '=?UTF-8?B?' . base64_encode('💙 ' . $action) . '?=';

    if (!function_exists('mail')) {
        logError('mail() is disabled on this server');
        return 'mail() disabled on server';
    }
    if (!mail(NOTIFY_TO, $subject, $text, $headers, '-f' . NOTIFY_FROM)) {
        logError('mail() failed for action: ' . $action);
        return 'mail() returned false';
    }
    return 'sent';
}

// ── POST /api.php  {type:'button', data:{...}, ts} ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) { echo json_encode(['ok' => false, 'error' => 'Invalid JSON']); exit; }

    if (($body['type'] ?? '') !== 'button') {
        echo json_encode(['ok' => false, 'error' => 'Unknown type']);
        exit;
    }

    $ts   = date('Y-m-d H:i:s', strtotime($body['ts'] ?? 'now'));
    $mail = sendNotification($body['data'] ?? [], $ts);
    echo json_encode(['ok' => $mail === 'sent', 'mail' => $mail]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
