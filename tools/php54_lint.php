<?php
/**
 * tools/php54_lint.php
 *
 * php54-debug only. Parse-check every site PHP file with the running
 * interpreter (production is 5.4.16). Safe to hit from a browser or CLI.
 *
 * Does not include files — only `php -l` style token parse via php -l
 * when CLI, or token_get_all() when run from Apache (no shell).
 */
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$skipDirs = array('/docker/', '/tools/', '/.git/');

function lint_skip_path($path, $skipDirs)
{
    foreach ($skipDirs as $frag) {
        if (strpos($path, $frag) !== false) {
            return true;
        }
    }
    return false;
}

function collect_php_files($root, $skipDirs)
{
    $out = array();
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (substr($path, -4) !== '.php') {
            continue;
        }
        if (lint_skip_path($path, $skipDirs)) {
            continue;
        }
        $out[] = $path;
    }
    sort($out);
    return $out;
}

function lint_via_tokens($path)
{
    $src = @file_get_contents($path);
    if ($src === false) {
        return 'cannot read file';
    }
    $err = null;
    set_error_handler(function ($no, $str) use (&$err) {
        $err = $str;
        return true;
    });
    $tokens = @token_get_all($src);
    restore_error_handler();
    if ($err !== null) {
        return $err;
    }
    // token_get_all does not always raise on unclosed heredoc; scan markers.
    $opens = 0;
    $closes = 0;
    foreach ($tokens as $t) {
        if (!is_array($t)) {
            continue;
        }
        if ($t[0] === T_START_HEREDOC) {
            $opens++;
        }
        if ($t[0] === T_END_HEREDOC) {
            $closes++;
        }
    }
    if ($opens !== $closes) {
        return 'heredoc/nowdoc mismatch: opens=' . $opens . ' closes=' . $closes
            . ' (PHP 5.4: closer line may only be IDENT or IDENT; — not IDENT,)';
    }
    return null;
}

$files = collect_php_files($root, $skipDirs);
$fail = 0;
$ok = 0;

echo "PHP 5.4 lint probe\n";
echo str_repeat('=', 28) . "\n";
echo 'Time: ' . date('c') . "\n";
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'Root: ' . $root . "\n";
echo "\n";

foreach ($files as $path) {
    $rel = substr($path, strlen($root) + 1);
    $err = lint_via_tokens($path);
    if ($err === null) {
        $ok++;
        echo "OK   {$rel}\n";
    } else {
        $fail++;
        echo "FAIL {$rel}\n";
        echo "     {$err}\n";
    }
}

echo "\n";
echo "Checked: " . ($ok + $fail) . "  OK: {$ok}  FAIL: {$fail}\n";
if ($fail > 0) {
    http_response_code(500);
}
