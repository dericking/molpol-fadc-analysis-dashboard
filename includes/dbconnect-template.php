<?php
/**
 * includes/dbconnect-template.php
 * Read-only PDO connection for this site — REPO TEMPLATE.
 *
 * DEPLOYMENT
 * ----------
 * Never put real credentials in this file: it is tracked in git.
 *
 * `tools/init_site.sh` copies it to `includes/dbconnect-<random>.local.php`,
 * writes the real values into that copy, and repoints bootstrap.php at it.
 * `*.local.php` is git-ignored, so the copy is never committed, never shipped
 * by `git archive`, and never overwritten by a deploy.
 *
 * The copy is self-contained: credentials and PDO setup in one file. Nothing
 * else on the server needs editing, which is the point — a file nobody opens
 * in an editor cannot leave a `~` or `.swp` copy beside it, and those are
 * served as plain text.
 *
 * A deploy overwrites bootstrap.php and restores the require line to this
 * template, which breaks the site until `tools/init_site.sh` is re-run. It
 * detects the existing copy and only repairs that line.
 *
 * The DB user should be granted SELECT only, and scoped to the web server
 * host so a leaked password is useless from anywhere else:
 *   CREATE USER 'readonly_user'@'webserver.example' IDENTIFIED BY '...';
 *   GRANT SELECT ON app_db.* TO 'readonly_user'@'webserver.example';
 *
 * Use 127.0.0.1, not 'localhost' — PDO's mysql driver treats the literal
 * string 'localhost' as "use a Unix socket" and silently ignores the port.
 * A real hostname (a separate DB server) is not affected by this.
 */

const DB_HOST = '127.0.0.1';

// === BEGIN DEPLOY VALUES (rewritten by tools/init_site.sh) ===
$siteDb = [
    'host' => '',
    'port' => '',
    'name' => '',
    'user' => '',
    'pass' => '',
];
// === END DEPLOY VALUES ===

/*
 * Empty entries fall through to the SITE_DB_* environment variables used by
 * the Docker test stack, then to the repo placeholders below — which are not
 * real values. That ordering lets an untouched clone run against Docker with
 * no setup at all.
 */
$dbHost = $siteDb['host'] ?: (getenv('SITE_DB_HOST') ?: DB_HOST);
$dbPort = $siteDb['port'] ?: (getenv('SITE_DB_PORT') ?: '3306');
$dbName = $siteDb['name'] ?: (getenv('SITE_DB_NAME') ?: 'app_db');
$dbUser = $siteDb['user'] ?: (getenv('SITE_DB_USER') ?: 'readonly_user');
$dbPass = $siteDb['pass'] ?: (getenv('SITE_DB_PASS') ?: 'changeme');

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // use real prepared statements
        ]
    );
} catch (PDOException $e) {
    // Never echo $e->getMessage() to the browser — it can leak host/user details.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection error.');
}
