<?php
// Database connection check for datebalazs.com
// Upload this file to public_html and open it in your browser.

$dbHost = 'localhost';
$dbUser = 'datebala_techuser';
$dbPass = '9Vu6V4Jma4DAW-l}';
$dbName = 'datebala_datebalazs';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli($dbHost, $dbUser, $dbPass);
if ($mysqli->connect_error) {
    echo 'Connection failed: ' . htmlspecialchars($mysqli->connect_error) . "\n";
    exit;
}

echo "Connected to MySQL server.\n";

if (!$mysqli->select_db($dbName)) {
    echo 'Select database failed: ' . htmlspecialchars($mysqli->error) . "\n";
    echo "\nChecking if the database exists:\n";
    $result = $mysqli->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . $mysqli->real_escape_string($dbName) . "'");
    if ($result) {
        if ($result->num_rows > 0) {
            echo 'Database exists, but permission is denied.\n';
        } else {
            echo 'Database does not exist or cannot be seen.\n';
        }
    } else {
        echo 'Could not query information_schema: ' . htmlspecialchars($mysqli->error) . "\n";
    }
    $mysqli->close();
    exit;
}

echo 'OK: connected and selected database ' . htmlspecialchars($dbName) . ".\n";

$result = $mysqli->query('SHOW TABLES');
if ($result) {
    echo "Tables in $dbName:\n";
    while ($row = $result->fetch_array()) {
        echo ' - ' . htmlspecialchars($row[0]) . "\n";
    }
} else {
    echo 'Show tables failed: ' . htmlspecialchars($mysqli->error) . "\n";
}

$mysqli->close();
