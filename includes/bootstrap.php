<?php
/**
 * includes/bootstrap.php
 *
 * Common page bootstrap: site config, PDO, schema helpers, render helpers.
 * Pages should require this once instead of each include separately.
 *
 * Provides: $config (array), $pdo (PDO)
 *
 * Also installs the site's last-resort failure handling. A page that dies
 * unexpectedly must not show PHP's own output: it carries absolute file paths
 * and the failing SQL, and PHP sends it with HTTP 200, so monitoring never
 * sees the failure. Log the detail, send a real 500, show the visitor nothing
 * specific — the same posture the dbconnect template already takes for a failed
 * connection.
 */

/**
 * Keep PHP's own error text off the page. The handlers below cover anything
 * throwable, but warnings and notices are printed by PHP before any handler
 * runs — a failed require, for example, names the absolute path on its way
 * out. display_errors is PHP_INI_ALL, so this works without access to
 * php.ini or the vhost, which is the situation on the deployment host.
 *
 * Detail is not lost: it goes to the server error log instead. When
 * debugging locally, read it with `docker compose logs -f site-web`, or
 * comment out these two lines temporarily.
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Generic 500 page. Deliberately self-contained: no layout files, no status
 * catalog, no render helpers — whatever this needs may be the thing that just
 * failed.
 */
function bootstrap_fail_page(): void
{
    if (headers_sent()) {
        // Response already on the wire; a status code is no longer possible,
        // so close the page out visibly instead of truncating it silently.
        echo '<p class="empty-state">This page could not be completed'
            . ' because of a server error.</p>';
        return;
    }

    // Discard the half-rendered page so the 500 is the entire response.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Server error</title>';
    echo '<link rel="stylesheet" href="assets/style.css"></head>';
    echo '<body><div class="wrap-wide">';
    echo '<h1>Server error</h1>';
    echo '<p class="empty-state">This page could not be loaded. The problem'
        . ' has been logged for the site caretaker.</p>';
    echo '<p><a href="index.php">« Back to Main Page</a></p>';
    echo '</div></body></html>';
}

/**
 * Uncaught throwables — most often a PDOException after an upstream schema
 * change. Registered before the connection so every query is covered.
 */
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf(
        'Unhandled %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    $GLOBALS['bootstrap_failed'] = true;
    bootstrap_fail_page();
});

/**
 * Fatal errors are not throwables, so the handler above never sees them — a
 * missing require (a layout file moved or renamed) is the realistic case.
 * Shutdown is the only hook left at that point.
 */
register_shutdown_function(static function (): void {
    if (!empty($GLOBALS['bootstrap_failed'])) {
        return; // Already handled as an exception; don't render twice.
    }
    $last = error_get_last();
    if ($last === null) {
        return;
    }
    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if (($last['type'] & $fatal) === 0) {
        return;
    }
    error_log(sprintf(
        'Fatal error: %s in %s:%d',
        $last['message'],
        $last['file'],
        $last['line']
    ));
    bootstrap_fail_page();
});

$config = require __DIR__ . '/config.php';
// tools/init_site.sh rewrites this line to point at the deployed credentials
// copy (dbconnect-<random>.local.php). The template is the repo default so a
// fresh clone and the Docker stack work with no setup. A deploy restores this
// line — re-run tools/init_site.sh afterward.
require_once __DIR__ . '/dbconnect-template.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/render_helpers.php';
