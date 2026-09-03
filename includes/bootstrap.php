<?php
/**
 * includes/bootstrap.php  — php54-debug overlay
 *
 * Same load order as production, but every step is recorded and any
 * exception/fatal is printed on the page (no server-log access).
 *
 * Do not merge this file back to php54-backport as-is.
 */

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$GLOBALS['php54_debug'] = true;
$GLOBALS['php54_debug_steps'] = array();
$GLOBALS['bootstrap_failed'] = false;

function debug_step($label)
{
    $GLOBALS['php54_debug_steps'][] = array(
        't' => microtime(true),
        'label' => (string)$label,
    );
}

function debug_steps_html()
{
    $steps = isset($GLOBALS['php54_debug_steps']) ? $GLOBALS['php54_debug_steps'] : array();
    $html = '<div class="php54-debug" style="margin:2rem 0;padding:1rem;border:2px solid #b00;background:#fff8f8;font:14px/1.4 monospace;white-space:pre-wrap;">';
    $html .= "<strong>php54-debug</strong>  PHP " . htmlspecialchars(PHP_VERSION) . "\n";
    $html .= "script: " . htmlspecialchars(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') . "\n";
    $html .= "request: " . htmlspecialchars(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') . "\n";
    $html .= "steps (" . count($steps) . "):\n";
    $t0 = isset($steps[0]['t']) ? $steps[0]['t'] : microtime(true);
    foreach ($steps as $i => $step) {
        $ms = (int)round(($step['t'] - $t0) * 1000);
        $html .= sprintf("  %02d  +%4dms  %s\n", $i + 1, $ms, htmlspecialchars($step['label']));
    }
    $html .= '</div>';
    return $html;
}

function debug_error_box($title, $lines)
{
    $html = '<div class="php54-debug" style="margin:2rem 0;padding:1rem;border:2px solid #b00;background:#fff8f8;font:14px/1.4 monospace;white-space:pre-wrap;">';
    $html .= '<strong>' . htmlspecialchars($title) . "</strong>\n";
    foreach ($lines as $line) {
        $html .= htmlspecialchars((string)$line) . "\n";
    }
    $html .= '</div>';
    return $html;
}

function bootstrap_fail_page($extraHtml)
{
    if (headers_sent()) {
        echo '<p class="empty-state">This page could not be completed'
            . ' because of a server error.</p>';
        echo $extraHtml;
        echo debug_steps_html();
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (function_exists('http_response_code')) {
        http_response_code(500);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Server error (php54-debug)</title>';
    echo '<link rel="stylesheet" href="assets/style.css"></head>';
    echo '<body><div class="wrap-wide">';
    echo '<h1>Server error <small>(php54-debug)</small></h1>';
    echo '<p class="empty-state">The page failed. Detail is on this page on purpose.</p>';
    echo $extraHtml;
    echo debug_steps_html();
    echo '<p><a href="index.php">« Back to Main Page</a>';
    echo ' · <a href="tools/php54_lint.php">Lint all PHP files</a>';
    echo ' · <a href="tools/db_probe.php">DB probe</a></p>';
    echo '</div></body></html>';
}

debug_step('bootstrap: start');

set_exception_handler(function (Exception $e) {
    debug_step('exception: ' . get_class($e));
    error_log(sprintf(
        'Unhandled %s: %s in %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    $GLOBALS['bootstrap_failed'] = true;
    $box = debug_error_box('Unhandled exception', array(
        'class: ' . get_class($e),
        'message: ' . $e->getMessage(),
        'file: ' . $e->getFile(),
        'line: ' . $e->getLine(),
    ));
    bootstrap_fail_page($box);
});

register_shutdown_function(function () {
    $last = error_get_last();
    $fatalMask = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    $isFatal = ($last !== null && (($last['type'] & $fatalMask) !== 0));

    if ($isFatal && empty($GLOBALS['bootstrap_failed'])) {
        $GLOBALS['bootstrap_failed'] = true;
        debug_step('fatal: ' . $last['message']);
        error_log(sprintf(
            'Fatal error: %s in %s:%d',
            $last['message'],
            $last['file'],
            $last['line']
        ));
        $box = debug_error_box('Fatal / parse error', array(
            'type: ' . $last['type'],
            'message: ' . $last['message'],
            'file: ' . $last['file'],
            'line: ' . $last['line'],
        ));
        bootstrap_fail_page($box);
        return;
    }

    if (!empty($GLOBALS['bootstrap_failed'])) {
        return;
    }

    // Successful HTML page: append the breadcrumb box so we can see how far it got.
    if (PHP_SAPI !== 'cli') {
        echo debug_steps_html();
    }
});

debug_step('bootstrap: load config.php');
$config = require __DIR__ . '/config.php';
debug_step('bootstrap: config.php ok');

debug_step('bootstrap: load dbconnect');
require_once __DIR__ . '/dbconnect-template.php';
debug_step('bootstrap: dbconnect ok (PDO ready)');

debug_step('bootstrap: load schema.php');
require_once __DIR__ . '/schema.php';
debug_step('bootstrap: schema.php ok');

debug_step('bootstrap: load render_helpers.php');
require_once __DIR__ . '/render_helpers.php';
debug_step('bootstrap: render_helpers.php ok — page may continue');
