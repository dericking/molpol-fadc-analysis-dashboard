<?php
/**
 * tools/db_probe.php
 *
 * Lightweight DB connectivity probe for production troubleshooting.
 * PHP 5.4 compatible. Safe to run from browser or CLI.
 *
 * What it does:
 *  1) Finds the dbconnect file currently referenced by includes/bootstrap.php
 *  2) Reads deploy values from that file's $siteDb array
 *  3) Applies the same env fallback order used by the site
 *  4) Tries PDO connect + SELECT 1
 *
 * What it does NOT do:
 *  - Print passwords or full exception text
 */

header('Content-Type: text/plain; charset=utf-8');
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

function out($line)
{
    echo $line . "\n";
}

function mask_value($value)
{
    $value = (string)$value;
    $len = strlen($value);
    if ($len <= 0) {
        return '(empty)';
    }
    if ($len <= 2) {
        return str_repeat('*', $len);
    }
    return substr($value, 0, 1) . str_repeat('*', $len - 2) . substr($value, -1);
}

function parse_bootstrap_dbconnect_file($bootstrapText)
{
    if (preg_match("/require_once\\s+__DIR__\\s*\\.\\s*'\\/([^']+\\.php)'\\s*;/", $bootstrapText, $m)) {
        return $m[1];
    }
    return null;
}

function parse_site_db_values($dbconnectText)
{
    $keys = array('host', 'port', 'name', 'user', 'pass');
    $vals = array(
        'host' => '',
        'port' => '',
        'name' => '',
        'user' => '',
        'pass' => '',
    );

    foreach ($keys as $k) {
        if (preg_match("/'" . preg_quote($k, "/") . "'\\s*=>\\s*'([^']*)'/", $dbconnectText, $m)) {
            $vals[$k] = $m[1];
        }
    }
    return $vals;
}

$root = dirname(__DIR__);
$includesDir = $root . '/includes';
$bootstrapPath = $includesDir . '/bootstrap.php';

out('DB Probe (PHP 5.4-safe)');
out(str_repeat('=', 28));
out('Time: ' . date('c'));
out('PHP: ' . PHP_VERSION);
out('');

if (!is_file($bootstrapPath)) {
    http_response_code(500);
    out('FAIL: includes/bootstrap.php not found.');
    exit(1);
}

$bootstrapText = @file_get_contents($bootstrapPath);
if ($bootstrapText === false) {
    http_response_code(500);
    out('FAIL: Could not read includes/bootstrap.php.');
    exit(1);
}

$dbconnectFile = parse_bootstrap_dbconnect_file($bootstrapText);
if ($dbconnectFile === null) {
    http_response_code(500);
    out('FAIL: Could not parse dbconnect require line in bootstrap.php.');
    exit(1);
}

$dbconnectPath = $includesDir . '/' . $dbconnectFile;
if (!is_file($dbconnectPath)) {
    http_response_code(500);
    out('FAIL: Referenced dbconnect file not found: ' . $dbconnectFile);
    exit(1);
}

$dbconnectText = @file_get_contents($dbconnectPath);
if ($dbconnectText === false) {
    http_response_code(500);
    out('FAIL: Could not read dbconnect file: ' . $dbconnectFile);
    exit(1);
}

$siteDb = parse_site_db_values($dbconnectText);

$defaultHost = '127.0.0.1';
$dbHost = ($siteDb['host'] !== '') ? $siteDb['host'] : (getenv('SITE_DB_HOST') ? getenv('SITE_DB_HOST') : $defaultHost);
$dbPort = ($siteDb['port'] !== '') ? $siteDb['port'] : (getenv('SITE_DB_PORT') ? getenv('SITE_DB_PORT') : '3306');
$dbName = ($siteDb['name'] !== '') ? $siteDb['name'] : (getenv('SITE_DB_NAME') ? getenv('SITE_DB_NAME') : 'app_db');
$dbUser = ($siteDb['user'] !== '') ? $siteDb['user'] : (getenv('SITE_DB_USER') ? getenv('SITE_DB_USER') : 'readonly_user');
$dbPass = ($siteDb['pass'] !== '') ? $siteDb['pass'] : (getenv('SITE_DB_PASS') ? getenv('SITE_DB_PASS') : 'changeme');

out('Config source: includes/' . $dbconnectFile);
out('Target host: ' . $dbHost);
out('Target port: ' . $dbPort);
out('Target db:   ' . $dbName);
out('Target user: ' . mask_value($dbUser));
out('');

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
        $dbUser,
        $dbPass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        )
    );
} catch (PDOException $e) {
    http_response_code(500);
    out('FAIL: PDO connect failed.');
    out('SQLSTATE: ' . (string)$e->getCode());
    out('Hint: check host/port/firewall, user grants, or password.');
    exit(1);
}

try {
    $stmt = $pdo->query('SELECT 1 AS ok');
    $row = $stmt->fetch();
    $ok = is_array($row) && isset($row['ok']) && (string)$row['ok'] === '1';
    if (!$ok) {
        http_response_code(500);
        out('FAIL: Connected, but SELECT 1 did not return expected result.');
        exit(1);
    }
} catch (Exception $e) {
    http_response_code(500);
    out('FAIL: Connected, but SELECT 1 query failed.');
    out('SQLSTATE: ' . (string)$e->getCode());
    exit(1);
}

try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS n
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :db'
    );
    $stmt->execute(array(':db' => $dbName));
    $countRow = $stmt->fetch();
    $tableCount = (is_array($countRow) && isset($countRow['n'])) ? (int)$countRow['n'] : -1;
    out('PASS: DB connection and query succeeded.');
    out('Visible tables in schema: ' . $tableCount);
} catch (Exception $e) {
    out('PASS: DB connection and SELECT 1 succeeded.');
    out('WARN: Could not count INFORMATION_SCHEMA tables (permissions may be restricted).');
}

