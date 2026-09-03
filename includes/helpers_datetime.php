<?php
/**
 * includes/helpers_datetime.php
 *
 * Date/time parsing and SQL date expressions for run/group stamps.
 */

/**
 * Month abbreviation (Linux date) → 1–12, or null if unknown.
 *
 * @return int|null
 */
function month_abbr_number($abbr)
{
    static $map = array(
        'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
        'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8,
        'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
    );
    return isset($map[$abbr]) ? $map[$abbr] : null;
}

/**
 * Expand a 3-letter weekday/month from a Linux date string for headings.
 */
function expand_date_abbr($abbr, $kind)
{
    static $days = array(
        'Sun' => 'Sunday', 'Mon' => 'Monday', 'Tue' => 'Tuesday',
        'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday',
    );
    static $months = array(
        'Jan' => 'January', 'Feb' => 'February', 'Mar' => 'March', 'Apr' => 'April',
        'May' => 'May', 'Jun' => 'June', 'Jul' => 'July', 'Aug' => 'August',
        'Sep' => 'September', 'Oct' => 'October', 'Nov' => 'November', 'Dec' => 'December',
    );
    if ($kind === 'day') {
        return isset($days[$abbr]) ? $days[$abbr] : $abbr;
    }
    return isset($months[$abbr]) ? $months[$abbr] : $abbr;
}

/**
 * Calendar date as written in a stored timestamp string — no timezone conversion.
 * Supports Linux `date` text ("Fri Jul 31 16:54:21 EDT 2026") and "Y-m-d H:i:s".
 *
 * @return array{key: string, label: string}|null  key is Y-m-d for sorting/bucketing
 */
function parse_stored_calendar_date($value)
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    // MySQL / ISO date at start: 2026-07-31 16:54:21
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\b/', $value, $m)) {
        $key = $m[1] . '-' . $m[2] . '-' . $m[3];
        // Date-only in UTC: label calendar components without shifting the day.
        $dt = DateTime::createFromFormat('!Y-m-d', $key);
        if ($dt === false) {
            return null;
        }
        return array(
            'key'   => $key,
            'label' => $dt->format('l, F j, Y'),
        );
    }

    // Linux date: Fri Jul 31 16:54:21 EDT 2026
    if (preg_match(
        '/^(\w{3})\s+(\w{3})\s+(\d{1,2})\s+\d{1,2}:\d{2}:\d{2}\s+\S+\s+(\d{4})$/',
        $value,
        $m
    )) {
        $wday = $m[1];
        $monAbbr = $m[2];
        $day = (int)$m[3];
        $year = $m[4];
        $monNum = month_abbr_number($monAbbr);
        if ($monNum === null || $day < 1 || $day > 31) {
            return null;
        }
        return array(
            'key'   => sprintf('%s-%02d-%02d', $year, $monNum, $day),
            'label' => sprintf(
                '%s, %s %d, %s',
                expand_date_abbr($wday, 'day'),
                expand_date_abbr($monAbbr, 'month'),
                $day,
                $year
            ),
        );
    }

    return null;
}

/**
 * SQL expression that yields a calendar DATE from a stored start stamp
 * (ISO `Y-m-d…` or Linux `date` text). $column is a trusted identifier,
 * optionally qualified as alias.column (e.g. r.run_start).
 */
function sql_expr_stamp_as_date($column)
{
    if (!preg_match('/^(?:([A-Za-z_][A-Za-z0-9_]*)\.)?([A-Za-z_][A-Za-z0-9_]*)$/', $column, $m)) {
        return 'NULL';
    }
    $qual = ($m[1] !== '') ? ('`' . $m[1] . '`.') : '';
    $col = $m[2];
    $ref = "{$qual}`{$col}`";
    $norm = "TRIM(REPLACE(REPLACE({$ref}, '  ', ' '), '  ', ' '))";
    return "(CASE
        WHEN {$ref} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN DATE({$ref})
        ELSE STR_TO_DATE(
            CONCAT(
                SUBSTRING_INDEX(SUBSTRING_INDEX({$norm}, ' ', 2), ' ', -1), ' ',
                SUBSTRING_INDEX(SUBSTRING_INDEX({$norm}, ' ', 3), ' ', -1), ' ',
                SUBSTRING_INDEX({$norm}, ' ', -1)
            ),
            '%b %e %Y'
        )
    END)";
}

/** YYYY-MM-DD from a query param, or null if missing/invalid. */
function parse_ymd_query_param($raw)
{
    $raw = trim((string)$raw);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        return null;
    }
    $y = (int)$m[1];
    $mo = (int)$m[2];
    $d = (int)$m[3];
    if (!checkdate($mo, $d, $y)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/**
 * First candidate column that exists on $table, or null.
 * Used so index/report follow Don's run_start_datetime without 500ing on
 * an older clone that still has run_start.
 */
function first_present_column($pdo, $table, $candidates)
{
    foreach ($candidates as $col) {
        if (table_has_column($pdo, $table, $col)) {
            return $col;
        }
    }
    return null;
}

/**
 * Wall-clock time as written in the stored string (H:i:s), no timezone conversion.
 * Works for Linux date text and "Y-m-d H:i:s".
 */
function format_time_only($value)
{
    if ($value === null) {
        return '—';
    }
    $value = trim($value);
    if ($value === '') {
        return '—';
    }
    if (preg_match('/\b(\d{1,2}:\d{2}:\d{2})\b/', $value, $m)) {
        return $m[1];
    }
    if (preg_match('/\b(\d{1,2}:\d{2})\b/', $value, $m)) {
        return $m[1];
    }
    return '—';
}
