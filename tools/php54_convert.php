<?php
/**
 * One-off mechanical PHP 7+ → 5.4 transforms for the backport branch.
 * Usage: php tools/php54_convert.php path/to/file.php
 */
if ($argc < 2) {
    fwrite(STDERR, "Usage: php php54_convert.php <file.php>\n");
    exit(1);
}
$path = $argv[1];
$content = file_get_contents($path);
if ($content === false) {
    fwrite(STDERR, "Cannot read $path\n");
    exit(1);
}

// Return types on functions and closures.
$content = preg_replace('/\)\s*:\s*\?string(\s*[{;])/u', ')$1', $content);
$content = preg_replace('/\)\s*:\s*\?int(\s*[{;])/u', ')$1', $content);
$content = preg_replace('/\)\s*:\s*\?array(\s*[{;])/u', ')$1', $content);
$content = preg_replace('/\)\s*:\s*(void|int|string|float|bool|array)(\s*[{;])/u', ')$2', $content);

// Parameter type hints (simple identifiers only).
$types = 'string|int|float|bool|array|callable|PDO';
$content = preg_replace('/\?(?:string|int|float|bool|array)\s+(\$)/u', '$1', $content);
$content = preg_replace('/\b(?:' . $types . ')\s+(\$[a-zA-Z_])/u', '$1', $content);

// Arrow functions → function (single-line common cases).
$content = preg_replace_callback(
    '/\bfn\s*\(([^)]*)\)\s*=>\s*([^;]+);/u',
    function ($m) {
        return 'function (' . $m[1] . ') { return ' . $m[2] . '; };';
    },
    $content
);

// Null coalesce: $var['key'] ?? default
$content = preg_replace_callback(
    '/\$([a-zA-Z_\x7f-\xff][\w\x7f-\xff]*(?:\[[^\]]+\])+)\s*\?\?\s*/u',
    function ($m) {
        return 'isset(' . $m[1] . ') ? ' . $m[1] . ' : ';
    },
    $content
);
// $var ?? default (no brackets)
$content = preg_replace_callback(
    '/\$([a-zA-Z_\x7f-\xff][\w\x7f-\xff]*)\s*\?\?\s*/u',
    function ($m) {
        return 'isset($' . $m[1] . ') ? $' . $m[1] . ' : ';
    },
    $content
);

// Throwable → Exception
$content = str_replace('Throwable', 'Exception', $content);

// DateTimeImmutable → DateTime
$content = str_replace('DateTimeImmutable', 'DateTime', $content);

// array_merge spread (PHP 5.6+) — manual follow-up may still be needed
if (strpos($content, '...$') !== false) {
    fwrite(STDERR, "Warning: variadic spread may remain in $path\n");
}

file_put_contents($path, $content);
echo "Converted $path\n";
