<?php
/**
 * includes/render_helpers.php
 *
 * Column-grouping classifiers and field-list rendering shared between
 * detail pages, plus list-card rendering for index / detail_groups
 * (layouts live in includes/layouts/).
 */

/**
 * Month abbreviation (Linux date) → 1–12, or null if unknown.
 */
function month_abbr_number(string $abbr): ?int
{
    static $map = [
        'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
        'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8,
        'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
    ];
    return $map[$abbr] ?? null;
}

/**
 * Expand a 3-letter weekday/month from a Linux date string for headings.
 */
function expand_date_abbr(string $abbr, string $kind): string
{
    static $days = [
        'Sun' => 'Sunday', 'Mon' => 'Monday', 'Tue' => 'Tuesday',
        'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday',
    ];
    static $months = [
        'Jan' => 'January', 'Feb' => 'February', 'Mar' => 'March', 'Apr' => 'April',
        'May' => 'May', 'Jun' => 'June', 'Jul' => 'July', 'Aug' => 'August',
        'Sep' => 'September', 'Oct' => 'October', 'Nov' => 'November', 'Dec' => 'December',
    ];
    if ($kind === 'day') {
        return $days[$abbr] ?? $abbr;
    }
    return $months[$abbr] ?? $abbr;
}

/**
 * Calendar date as written in a stored timestamp string — no timezone conversion.
 * Supports Linux `date` text ("Fri Jul 31 16:54:21 EDT 2026") and "Y-m-d H:i:s".
 *
 * @return array{key: string, label: string}|null  key is Y-m-d for sorting/bucketing
 */
function parse_stored_calendar_date(?string $value): ?array
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
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $key, new DateTimeZone('UTC'));
        if ($dt === false) {
            return null;
        }
        return [
            'key'   => $key,
            'label' => $dt->format('l, F j, Y'),
        ];
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
        return [
            'key'   => sprintf('%s-%02d-%02d', $year, $monNum, $day),
            'label' => sprintf(
                '%s, %s %d, %s',
                expand_date_abbr($wday, 'day'),
                expand_date_abbr($monAbbr, 'month'),
                $day,
                $year
            ),
        ];
    }

    return null;
}

/**
 * SQL expression that yields a calendar DATE from a stored start stamp
 * (ISO `Y-m-d…` or Linux `date` text). $column is a trusted identifier,
 * optionally qualified as alias.column (e.g. r.run_start).
 */
function sql_expr_stamp_as_date(string $column): string
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
function parse_ymd_query_param($raw): ?string
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
 * Wall-clock time as written in the stored string (H:i:s), no timezone conversion.
 * Works for Linux date text and "Y-m-d H:i:s".
 */
function format_time_only(?string $value): string
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

/** Maps a quality code (or legacy ENUM label) to the stylesheet class suffix. */
function quality_slug(?string $quality): string
{
    if ($quality === null || $quality === '') {
        return 'pending';
    }
    $raw = (string)$quality;
    $slugs = get_lookup_layouts()['quality_slugs'] ?? [];
    if (isset($slugs[$raw]) && is_string($slugs[$raw]) && $slugs[$raw] !== '') {
        return $slugs[$raw];
    }
    $upper = strtoupper($raw);
    if (isset($slugs[$upper]) && is_string($slugs[$upper]) && $slugs[$upper] !== '') {
        return $slugs[$upper];
    }
    $slug = strtolower($raw);
    return preg_match('/^[a-z]+$/', $slug) ? $slug : 'pending';
}

function format_group_label($groupId): string
{
    if ($groupId === null || $groupId === '') {
        return '—';
    }
    return (string)(int)$groupId;
}

/**
 * DAQ_config / EPICS / Analysis / Grouped_Analysis column grouping rules and
 * detail-page row layouts live in includes/layouts/layout_sections.php. The functions
 * below are thin wrappers + a shared engine (prefix via strstr, or regex).
 */

/**
 * Load section layouts / classifiers (cached per request).
 */
function get_section_layouts(): array
{
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_sections.php';
    }
    return $layouts;
}

/**
 * Classify a column name from an ordered list of rules.
 * match prefix — strstr($name, '_', true) === key
 * match regex  — preg_match(pattern, $name)
 * First match wins; otherwise 'Other'.
 */
function classify_column_from_rules(string $name, array $rules): string
{
    foreach ($rules as $rule) {
        if (!is_array($rule) || !isset($rule['section'])) {
            continue;
        }
        $match = $rule['match'] ?? 'prefix';
        if ($match === 'prefix') {
            $key = $rule['key'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }
            $prefix = strstr($name, '_', true);
            if ($prefix !== false && $prefix === $key) {
                return (string)$rule['section'];
            }
        } elseif ($match === 'regex') {
            $pattern = $rule['pattern'] ?? null;
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }
            if (@preg_match($pattern, $name)) {
                return (string)$rule['section'];
            }
        }
    }
    return 'Other';
}

function classify_daq_column(string $name): string
{
    $rules = get_section_layouts()['daq']['classifier'] ?? [];
    return classify_column_from_rules($name, $rules);
}

function classify_analysis_column(string $name): string
{
    $rules = get_section_layouts()['analysis']['classifier'] ?? [];
    return classify_column_from_rules($name, $rules);
}

function classify_grouped_analysis_column(string $name): string
{
    $rules = get_section_layouts()['grouped_analysis']['classifier'] ?? [];
    return classify_column_from_rules($name, $rules);
}

function classify_epics_column(string $name): string
{
    $rules = get_section_layouts()['epics']['classifier'] ?? [];
    return classify_column_from_rules($name, $rules);
}

/**
 * Apply featured / layouts / duplicate_into from section_layouts.
 * Leftover section keys (not listed in featured or any layout band) go into a
 * reserved band 'unallocated', chunked into rows of at most 4 cards.
 *
 * featured may be a string, an array of section titles, or null.
 *
 * @return array{
 *   featured_rows: list<array{title: string, columns: array}>,
 *   sections: array,
 *   layouts: array<string, list<array>>
 * }
 */
function apply_section_layout(array $sections, array $layoutConfig): array
{
    // Optional: copy named columns into an additional section (still kept in home section).
    if (!empty($layoutConfig['duplicate_into']) && is_array($layoutConfig['duplicate_into'])) {
        $byName = [];
        foreach ($sections as $cols) {
            foreach ($cols as $col) {
                $byName[$col['name']] = $col;
            }
        }
        foreach ($layoutConfig['duplicate_into'] as $targetSection => $colNames) {
            if (!isset($sections[$targetSection]) || !is_array($colNames)) {
                continue;
            }
            foreach ($colNames as $name) {
                if (isset($byName[$name])) {
                    $sections[$targetSection][] = $byName[$name];
                }
            }
        }
    }

    $featuredRaw = $layoutConfig['featured'] ?? null;
    if (is_string($featuredRaw) && $featuredRaw !== '') {
        $featuredNames = [$featuredRaw];
    } elseif (is_array($featuredRaw)) {
        $featuredNames = [];
        foreach ($featuredRaw as $name) {
            if (is_string($name) && $name !== '') {
                $featuredNames[] = $name;
            }
        }
    } else {
        $featuredNames = [];
    }

    $featuredRows = [];
    foreach ($featuredNames as $featuredName) {
        if (!isset($sections[$featuredName])) {
            continue;
        }
        $featuredRows[] = [
            'title'   => $featuredName,
            'columns' => $sections[$featuredName],
        ];
        unset($sections[$featuredName]);
    }

    $layoutsRaw = $layoutConfig['layouts'] ?? [];
    $layouts = [];
    $layoutErrors = [];
    if (is_array($layoutsRaw)) {
        foreach ($layoutsRaw as $bandName => $rows) {
            if (!is_string($bandName) || $bandName === '' || $bandName === 'unallocated') {
                // 'unallocated' is engine-owned; ignore if present in config.
                continue;
            }
            if (!is_array($rows)) {
                $layoutErrors[] = [
                    'key'     => 'layout_band_invalid',
                    'summary' => "WARNING: layouts.{$bandName} must be an array of rows "
                        . "(each row an array of section titles), e.g. [ ['Section A', 'Section B'] ].",
                ];
                $layouts[$bandName] = [];
                continue;
            }
            $validRows = [];
            $bandMalformed = false;
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $validRows[] = $row;
                } else {
                    $bandMalformed = true;
                }
            }
            if ($bandMalformed) {
                $layoutErrors[] = [
                    'key'     => 'layout_flat_list',
                    'summary' => "WARNING: layouts.{$bandName} has a flat list of section titles. "
                        . "Use rows-of-rows, e.g. [ ['Section A', 'Section B'] ], "
                        . "not [ 'Section A', 'Section B' ].",
                ];
            }
            $layouts[$bandName] = $validRows;
        }
    }

    $allRows = [];
    foreach ($layouts as $rows) {
        foreach ($rows as $row) {
            if (is_array($row)) {
                $allRows[] = $row;
            }
        }
    }
    // Only spread real row arrays — never strings (avoids PHP TypeError).
    $placedKeys = $allRows !== [] ? array_merge([], ...$allRows) : [];
    $placedKeys = array_values(array_filter($placedKeys, fn($k) => $k !== null));
    $leftoverKeys = array_values(array_diff(array_keys($sections), $placedKeys));

    // Caretaker hide-list: only affects the unallocated band. Sections still
    // listed in featured / layouts render there as usual.
    $ignoreRaw = $layoutConfig['ignore_sections'] ?? [];
    $ignore = [];
    if (is_array($ignoreRaw)) {
        foreach ($ignoreRaw as $name) {
            if (is_string($name) && $name !== '') {
                $ignore[$name] = true;
            }
        }
    }
    if ($ignore) {
        $leftoverKeys = array_values(array_filter(
            $leftoverKeys,
            fn($k) => !isset($ignore[$k])
        ));
    }

    if ($leftoverKeys) {
        // Max 4 cards per row (matches .cols-1 … .cols-4 in style.css).
        $layouts['unallocated'] = array_chunk($leftoverKeys, 4);
    }

    return [
        'featured_rows'  => $featuredRows,
        'sections'       => $sections,
        'layouts'        => $layouts,
        'layout_errors'  => $layoutErrors,
    ];
}

/**
 * Map section_layouts keys to table / PK / classifier for load_section_view().
 */
function section_view_table_map(): array
{
    return [
        'analysis' => [
            'table'      => 'Analysis',
            'pk'         => 'run_number',
            'classifier' => 'classify_analysis_column',
        ],
        'daq' => [
            'table'      => 'DAQ_config',
            'pk'         => 'run_number',
            'classifier' => 'classify_daq_column',
        ],
        'epics' => [
            'table'      => 'EPICS_data',
            'pk'         => 'run_number',
            'classifier' => 'classify_epics_column',
        ],
        'grouped_analysis' => [
            'table'      => 'Grouped_Analysis',
            'pk'         => 'group_number',
            'classifier' => 'classify_grouped_analysis_column',
        ],
    ];
}

/**
 * Load one schema-driven detail table as a render pack.
 *
 * @return array{
 *   row: ?array,
 *   sections: array,
 *   featured_rows: list<array{title: string, columns: array}>,
 *   layouts: array<string, list<array>>,
 *   last_updated: mixed
 * }
 */
function load_section_view(PDO $pdo, string $layoutKey, $id): array
{
    $map = section_view_table_map();
    if (!isset($map[$layoutKey])) {
        throw new InvalidArgumentException("Unknown section layout key: {$layoutKey}");
    }
    $meta = $map[$layoutKey];
    $sectionCfg = get_section_layouts()[$layoutKey] ?? [];
    $exclude = $sectionCfg['exclude'] ?? [$meta['pk'], 'last_updated'];

    $row = fetch_row_by_key($pdo, $meta['table'], $meta['pk'], $id);
    $columns = array_values(array_filter(
        get_table_columns($pdo, $meta['table']),
        fn($col) => !in_array($col['name'], $exclude, true)
    ));
    $sections = group_columns_by_section($columns, $meta['classifier']);
    $applied = apply_section_layout($sections, $sectionCfg);

    return [
        'row'            => $row,
        'sections'       => $applied['sections'],
        'featured_rows'  => $applied['featured_rows'],
        'layouts'        => $applied['layouts'],
        'layout_errors'  => $applied['layout_errors'] ?? [],
        'last_updated'   => $row['last_updated'] ?? null,
    ];
}

/**
 * Require a positive integer query param or emit a short 400 page and exit.
 */
function require_positive_int_query(string $param): int
{
    $value = filter_input(INPUT_GET, $param, FILTER_VALIDATE_INT);
    if ($value === null || $value === false) {
        http_response_code(400);
        $label = htmlspecialchars($param);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invalid ' . $label . '</title>';
        echo '<link rel="stylesheet" href="assets/style.css"></head>';
        echo '<body><div class="wrap-wide">';
        echo '<p>Invalid or missing ' . $label . ' number. <a href="index.php">« Back to Main Page</a></p>';
        echo '</div></body></html>';
        exit;
    }
    return (int)$value;
}

/**
 * Require a row by primary key or emit a short 404 page and exit.
 */
function require_row(PDO $pdo, string $table, string $pkColumn, $id, string $label): array
{
    $row = fetch_row_by_key($pdo, $table, $pkColumn, $id);
    if ($row === null) {
        http_response_code(404);
        $safeLabel = htmlspecialchars($label);
        $safeId = htmlspecialchars((string)(int)$id);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $safeLabel . ' not found</title>';
        echo '<link rel="stylesheet" href="assets/style.css"></head>';
        echo '<body><div class="wrap-wide">';
        echo '<p>' . $safeLabel . ' ' . $safeId . ' was not found. <a href="index.php">« Back to Main Page</a></p>';
        echo '</div></body></html>';
        exit;
    }
    return $row;
}

/**
 * Section title bar; optional meta-right as "Label: value" (value — if null).
 */
function render_section_header(string $title, ?string $metaLabel = null, $metaValue = null): void
{
    echo '<div class="section-header">';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    if ($metaLabel !== null && $metaLabel !== '') {
        $display = $metaValue !== null && $metaValue !== ''
            ? htmlspecialchars((string)$metaValue)
            : '—';
        echo '<span class="meta-right">' . htmlspecialchars($metaLabel) . ' ' . $display . '</span>';
    }
    echo '</div>';
}

function render_empty_state(string $message): void
{
    echo '<p class="empty-state">' . htmlspecialchars($message) . '</p>';
}

/**
 * Load on-page status help catalog (cached per request).
 */
function get_error_descriptions(): array
{
    static $catalog = null;
    if ($catalog === null) {
        $catalog = require __DIR__ . '/descriptions_errors.php';
    }
    return $catalog;
}

function render_status_info_link(string $key): void
{
    $catalog = get_error_descriptions();
    if (!isset($catalog[$key]) || !is_array($catalog[$key])) {
        return;
    }
    $href = 'help_errors.php?key=' . rawurlencode($key);
    echo ' <a class="status-info-link" href="' . htmlspecialchars($href) . '"'
        . ' target="_blank" rel="noopener noreferrer"'
        . ' title="More information" aria-label="More information about this message">i</a>';
}

/**
 * Inline status / warning with optional ⓘ link to help_errors.php (new tab, no JS).
 *
 * @param string      $key             Catalog key in descriptions_errors.php
 * @param string|null $summaryOverride Short line; defaults to catalog summary
 */
function render_status_message(string $key, ?string $summaryOverride = null): void
{
    $catalog = get_error_descriptions();
    $entry = $catalog[$key] ?? null;
    $summary = $summaryOverride;
    if ($summary === null || $summary === '') {
        $summary = is_array($entry) ? (string)($entry['summary'] ?? $key) : $key;
    }

    echo '<div class="status-message">';
    echo '<span class="status-message-text">' . htmlspecialchars($summary) . '</span>';
    if (is_array($entry)) {
        render_status_info_link($key);
    }
    echo '</div>';
}

/**
 * Caretaker “not a column” (and similar) warnings in a highlighted box.
 *
 * @param list<array{key?:string,summary?:string}|string> $warnings
 */
function render_status_warning_box(array $warnings): void
{
    if ($warnings === []) {
        return;
    }
    echo '<div class="status-warning-box" role="status">';
    foreach ($warnings as $warn) {
        $key = is_array($warn) ? (string)($warn['key'] ?? 'report_unknown_column') : 'report_unknown_column';
        $summary = is_array($warn) ? (string)($warn['summary'] ?? '') : (string)$warn;
        render_status_message($key, $summary !== '' ? $summary : null);
    }
    echo '</div>';
}

/**
 * Show caretaker-facing layout config errors (malformed layouts bands).
 * Soft failure — page still renders; does not TypeError.
 */
function render_layout_errors(array $pack): void
{
    foreach ($pack['layout_errors'] ?? [] as $err) {
        if (is_string($err) && $err !== '') {
            // Back-compat: plain string
            render_status_message('layout_flat_list', 'WARNING: ' . $err);
            continue;
        }
        if (!is_array($err) || empty($err['key'])) {
            continue;
        }
        $key = (string)$err['key'];
        $summary = isset($err['summary']) ? (string)$err['summary'] : null;
        render_status_message($key, $summary);
    }
}

/**
 * Render all featured rows from a load_section_view() pack.
 */
function render_featured_rows(array $pack): void
{
    $row = $pack['row'] ?? null;
    foreach ($pack['featured_rows'] ?? [] as $feat) {
        render_featured_row($feat['title'] ?? '', $feat['columns'] ?? [], $row);
    }
}

/**
 * Render one named layout band from a load_section_view() pack.
 * No-op if the band is missing or empty.
 */
function render_section_cards(array $pack, string $layoutName): void
{
    $rows = $pack['layouts'][$layoutName] ?? [];
    if (!is_array($rows) || $rows === []) {
        return;
    }
    $sections = $pack['sections'] ?? [];
    $row = $pack['row'] ?? null;
    foreach ($rows as $rowKeys) {
        if (!is_array($rowKeys)) {
            continue;
        }
        render_card_row($rowKeys, $sections, $row);
    }
}

/**
 * Render classifier sections not listed in featured/layouts (band 'unallocated').
 * No-op when empty — pages can call this unconditionally after their layout bands.
 */
function render_unallocated_sections(array $pack, string $heading): void
{
    $rows = $pack['layouts']['unallocated'] ?? [];
    if (!is_array($rows) || $rows === []) {
        return;
    }
    render_section_header($heading);
    render_section_cards($pack, 'unallocated');
}

/**
 * Group a column list into ordered sections; preserves column order within
 * each section. An 'Other' bucket (unmatched columns) is moved to the end
 * if present — and simply absent from the result, not rendered as an empty
 * section, if every column matched a real group.
 */
function group_columns_by_section(array $columns, callable $classifier): array
{
    $sections = [];
    foreach ($columns as $col) {
        $section = $classifier($col['name']);
        $sections[$section][] = $col;
    }
    if (isset($sections['Other'])) {
        $other = $sections['Other'];
        unset($sections['Other']);
        $sections['Other'] = $other;
    }
    return $sections;
}

/**
 * Render one row of cards for the given group keys. A `null` entry renders
 * as a blank spacer (reserves its column, shows no card) — use this to
 * align a shorter row with a longer one below/above it. An unknown string
 * key (a group that doesn't exist in $sections) is skipped silently.
 *
 * Column count comes from count($rowKeys) — the row's declared length —
 * not the number of real cards, so a null spacer genuinely holds its slot.
 */
function render_card_row(array $rowKeys, array $sections, ?array $row): void
{
    $hasAnyContent = array_filter($rowKeys, fn($k) => $k !== null && isset($sections[$k]));
    if (!$hasAnyContent) {
        return;
    }
    echo '<div class="card-row cols-' . count($rowKeys) . '">';
    foreach ($rowKeys as $key) {
        if ($key === null) {
            echo '<div class="card-spacer" aria-hidden="true"></div>';
            continue;
        }
        if (!isset($sections[$key])) {
            continue;
        }
        echo '<div class="section-group card"><h3>' . htmlspecialchars($key) . '</h3>';
        render_field_list($sections[$key], $row);
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Render a group as a single featured full-width row: each field gets its
 * own column, label over value. Used for a page's one standout card
 * (Analysis's Asymmetries & Polarization, EPICS's Polarization & Wien).
 */
function render_featured_row(string $title, array $columns, ?array $row): void
{
    if (!$columns) {
        return;
    }
    echo '<div class="section-group card featured-row">';
    if ($title !== '') {
        echo '<h3>' . htmlspecialchars($title) . '</h3>';
    }
    render_field_list($columns, $row);
    echo '</div>';
}

function fmt_value($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return htmlspecialchars((string)$value);
}

/**
 * Render a flat (non-grouped) columns/row pair as a definition list, skipping
 * the FK column. When both `foo` and `foo_err` appear in $columns, they render
 * as one row: "value ± error" under the base column's label; the `_err` column
 * is suppressed. An orphan `_err` with no base sibling still renders alone.
 */
function render_field_list(array $columns, ?array $row, string $skipColumn = 'run_number'): void
{
    if ($row === null) {
        render_status_message('empty_table_row');
        return;
    }
    $colNameSet = array_flip(array_column($columns, 'name'));
    echo '<dl class="fields">';
    foreach ($columns as $col) {
        $name = $col['name'];
        if ($name === $skipColumn) {
            continue;
        }
        // Paired away: base column will absorb this as "value ± error".
        if (str_ends_with($name, '_err')) {
            $base = substr($name, 0, -4);
            if (isset($colNameSet[$base])) {
                continue;
            }
        }
        $val = $row[$name] ?? null;
        if (lookup_table_for_column($name) !== null && $val !== null && $val !== '') {
            $dd = htmlspecialchars(lookup_label_for_field($name, $val));
        } else {
            $dd = fmt_value($val);
        }
        $errName = $name . '_err';
        if (isset($colNameSet[$errName])) {
            $err = $row[$errName] ?? null;
            if ($err !== null && $err !== '') {
                $dd .= ' ± ' . fmt_value($err);
            }
        }
        $title = $col['pv'] ? ' title="PV: ' . htmlspecialchars($col['pv']) . '"' : '';
        echo '<dt' . $title . '>' . htmlspecialchars($col['label']) . '</dt>';
        echo '<dd>' . $dd . '</dd>';
    }
    echo '</dl>';
}

/**
 * Load lookup-table maps from includes/layouts/layout_lookups.php (cached per request).
 */
function get_lookup_layouts(): array
{
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_lookups.php';
    }
    return $layouts;
}

function lookup_table_for_column(string $column): ?string
{
    $name = get_lookup_layouts()['columns'][$column] ?? null;
    return is_string($name) && $name !== '' ? $name : null;
}

/**
 * code => display_label for one lookup table. Empty if the table is missing.
 *
 * @return array<string, string>
 */
function load_lookup_map(PDO $pdo, string $table): array
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (!table_exists($pdo, $table)) {
        return $cache[$table] = [];
    }
    $cfg = get_lookup_layouts()['tables'][$table] ?? [];
    $codeCol = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)($cfg['code'] ?? 'code'))
        ? (string)($cfg['code'] ?? 'code') : 'code';
    $labelCol = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)($cfg['label'] ?? 'display_label'))
        ? (string)($cfg['label'] ?? 'display_label') : 'display_label';
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
        return $cache[$table] = [];
    }
    try {
        $stmt = $pdo->query("SELECT `{$codeCol}`, `{$labelCol}` FROM `{$table}` ORDER BY `{$labelCol}`");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_NUM) : [];
    } catch (PDOException $e) {
        return $cache[$table] = [];
    }
    $map = [];
    foreach ($rows as $row) {
        $map[(string)$row[0]] = (string)$row[1];
    }
    return $cache[$table] = $map;
}

function lookup_label_for_field(string $field, $code): string
{
    if ($code === null || $code === '') {
        return '';
    }
    $code = (string)$code;
    $table = lookup_table_for_column($field);
    if ($table === null) {
        return $code;
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) {
        return $code;
    }
    $map = load_lookup_map($pdo, $table);
    return $map[$code] ?? $code;
}

/**
 * Load master navbar layout from includes/layouts/layout_navbar.php (cached).
 */
function get_navbar_layout(): array
{
    static $layout = null;
    if ($layout === null) {
        $layout = require __DIR__ . '/layouts/layout_navbar.php';
        if (!is_array($layout)) {
            $layout = ['links' => []];
        }
    }
    return $layout;
}

/**
 * Render the optional site-wide top navbar.
 * No output when layout_navbar.php has an empty links list.
 * Colors come from CSS variables in assets/style.css (see layout_navbar.php header).
 */
function render_site_navbar(): void
{
    $nav = get_navbar_layout();
    $rawLinks = $nav['links'] ?? [];
    if (!is_array($rawLinks) || $rawLinks === []) {
        return;
    }

    $links = [];
    foreach ($rawLinks as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $href  = trim((string)($entry['href'] ?? ''));
        $label = trim((string)($entry['label'] ?? ''));
        if ($href === '' || $label === '') {
            continue;
        }
        $links[] = ['href' => $href, 'label' => $label];
    }
    if ($links === []) {
        return;
    }

    echo '<nav class="site-navbar" aria-label="Site">';
    echo '<ul class="site-navbar-list">';
    foreach ($links as $link) {
        echo '<li><a href="' . htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8')
            . '</a></li>';
    }
    echo '</ul>';
    echo '</nav>';
}

/**
 * Load list-card layouts from includes/layouts/layout_cards.php (cached per request).
 */
function get_card_layouts(): array
{
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_cards.php';
    }
    return $layouts;
}

/**
 * Load run/group summary layouts from includes/layouts/layout_run_summary.php.
 */
function get_run_summary_layouts(): array
{
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_run_summary.php';
    }
    return $layouts;
}

/**
 * Value HTML for one summary-layout cell (already escaped). Null = skip cell.
 *
 * @param array{empty_start?:string,empty_end?:string} $options
 */
function summary_cell_value_html(array $cell, array $data, array $options = []): ?string
{
    $kind = $cell['kind'] ?? '';
    switch ($kind) {
        case 'text':
            $raw = $data[$cell['field'] ?? ''] ?? null;
            if ($raw === null || $raw === '') {
                $text = '—';
            } else {
                $field = (string)($cell['field'] ?? '');
                $label = lookup_label_for_field($field, $raw);
                $text = $label !== '' ? $label : (string)$raw;
            }
            return htmlspecialchars($text);

        case 'quality':
            $field = (string)($cell['field'] ?? '');
            $raw = $data[$field] ?? null;
            if ($raw === null || $raw === '') {
                return '—';
            }
            $qSlug = quality_slug((string)$raw);
            $label = lookup_label_for_field($field, $raw);
            $text = $label !== '' ? $label : (string)$raw;
            return '<span class="quality-' . htmlspecialchars($qSlug) . '">'
                . htmlspecialchars($text) . '</span>';

        case 'id':
            $field = $cell['field'] ?? '';
            $raw = $data[$field] ?? null;
            if ($raw === null || $raw === '') {
                return '—';
            }
            $prefix = $cell['prefix'] ?? '';
            $text = htmlspecialchars($prefix . (string)$raw);
            $linkKey = $cell['link'] ?? null;
            if (is_string($linkKey) && $linkKey !== '') {
                $href = list_table_column_href($linkKey, $cell, $data);
                if ($href !== null) {
                    return '<a href="' . htmlspecialchars($href) . '">' . $text . '</a>';
                }
            }
            return $text;

        case 'time_range':
            $startRaw = $data[$cell['start'] ?? ''] ?? null;
            $endRaw   = $data[$cell['end'] ?? ''] ?? null;
            $start = ($startRaw !== null && trim((string)$startRaw) !== '')
                ? (string)$startRaw
                : (string)($options['empty_start'] ?? '—');
            $end = ($endRaw !== null && trim((string)$endRaw) !== '')
                ? (string)$endRaw
                : (string)($options['empty_end'] ?? '—');
            return '<span class="run-summary-time">'
                . htmlspecialchars($start)
                . ' &rarr; '
                . htmlspecialchars($end)
                . '</span>';

        case 'comment':
            $raw = $data[$cell['field'] ?? ''] ?? null;
            if ($raw === null || trim((string)$raw) === '') {
                return null;
            }
            return nl2br(htmlspecialchars((string)$raw));

        default:
            return null;
    }
}

/**
 * Render a summary panel from includes/layouts/layout_run_summary.php ('run' or 'group').
 * Used inside the Run Info / Group Info CSS :target modals.
 *
 * @param array{empty_start?:string,empty_end?:string} $options
 */
function render_run_summary(array $data, string $layoutKey = 'run', array $options = []): void
{
    $layouts = get_run_summary_layouts();
    if (!isset($layouts[$layoutKey])) {
        return;
    }
    $layout = $layouts[$layoutKey];
    $rows = $layout['rows'] ?? [];
    $footer = $layout['footer'] ?? [];

    echo '<div class="run-summary">';

    foreach ($rows as $row) {
        if (!is_array($row) || $row === []) {
            continue;
        }
        $cells = [];
        foreach ($row as $cell) {
            if (!is_array($cell)) {
                continue;
            }
            $html = summary_cell_value_html($cell, $data, $options);
            if ($html === null) {
                continue;
            }
            $cells[] = ['label' => (string)($cell['label'] ?? ''), 'html' => $html];
        }
        if ($cells === []) {
            continue;
        }
        $n = count($cells);
        echo '<div class="run-summary-row cols-' . $n . '">';
        foreach ($cells as $cell) {
            echo '<div class="run-summary-cell">';
            if ($cell['label'] !== '') {
                echo '<div class="run-summary-label">' . htmlspecialchars($cell['label']) . '</div>';
            }
            echo '<div class="run-summary-value">' . $cell['html'] . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    foreach ($footer as $cell) {
        if (!is_array($cell)) {
            continue;
        }
        $html = summary_cell_value_html($cell, $data, $options);
        if ($html === null) {
            continue;
        }
        $label = (string)($cell['label'] ?? '');
        echo '<div class="run-summary-comment">';
        if ($label !== '') {
            echo '<div class="run-summary-comment-label">' . htmlspecialchars($label) . '</div>';
        }
        echo '<div class="run-summary-comment-body">' . $html . '</div>';
        echo '</div>';
    }

    echo '</div>';
}

/**
 * Render one list card from a layout key in includes/layouts/layout_cards.php.
 * Optional extra CSS class is owned by the caller (app), not the layout.
 * Cell links use optional 'link' => 'run'|'group' on the cell (same as tables).
 */
function render_list_card(string $layoutKey, array $data, string $extraClass = ''): void
{
    $layouts = get_card_layouts();
    if (!isset($layouts[$layoutKey])) {
        return;
    }
    $layout = $layouts[$layoutKey];
    $rows = $layout['rows'] ?? [];

    $classes = ['run-card', 'card'];
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    $borderField = $layout['quality_border'] ?? null;
    if (is_string($borderField) && $borderField !== '' && array_key_exists($borderField, $data)) {
        $classes[] = 'quality-border-' . quality_slug($data[$borderField] ?? null);
    }

    echo '<div class="' . htmlspecialchars(implode(' ', $classes)) . '">';
    foreach ($rows as $rowCells) {
        if (!is_array($rowCells)) {
            continue;
        }
        echo '<div class="run-card-row">';
        foreach ($rowCells as $cell) {
            if (!is_array($cell) || !isset($cell['kind'])) {
                continue;
            }
            render_list_card_cell($cell, $data);
        }
        echo '</div>';
    }
    echo '</div>';
}

function render_list_card_cell(array $cell, array $data): void
{
    $html = list_cell_with_optional_link($cell, $data);
    if ($html === null) {
        return;
    }
    echo $html;
}

/**
 * Shared cell HTML for list cards and list tables. Returns null for unknown kinds.
 * Output is already escaped.
 */
function list_cell_html(array $cell, array $data): ?string
{
    if (!isset($cell['kind'])) {
        return null;
    }
    $kind = $cell['kind'];
    $class = isset($cell['class']) ? ' class="' . htmlspecialchars($cell['class']) . '"' : '';

    switch ($kind) {
        case 'id':
            $field = $cell['field'] ?? '';
            $raw = $data[$field] ?? null;
            if ($raw === null || $raw === '') {
                $text = '—';
            } else {
                $prefix = $cell['prefix'] ?? '';
                $text = $prefix . $raw;
            }
            return '<span' . $class . '>' . htmlspecialchars((string)$text) . '</span>';

        case 'quality':
            $field = $cell['field'] ?? '';
            $raw = $data[$field] ?? null;
            if ($raw === null || $raw === '') {
                return '<span' . $class . '>—</span>';
            }
            $qSlug = quality_slug((string)$raw);
            $label = lookup_label_for_field($field, $raw);
            return '<span class="quality-tag quality-tag-' . htmlspecialchars($qSlug) . '">'
                . htmlspecialchars($label !== '' ? $label : (string)$raw) . '</span>';

        case 'text':
            $field = $cell['field'] ?? '';
            $raw = $data[$field] ?? null;
            if ($raw === null || $raw === '') {
                $text = '—';
            } else {
                $label = lookup_label_for_field($field, $raw);
                $text = $label !== '' ? $label : (string)$raw;
            }
            return '<span' . $class . '>' . htmlspecialchars($text) . '</span>';

        case 'time_range':
            $start = $data[$cell['start'] ?? ''] ?? null;
            $end   = $data[$cell['end'] ?? ''] ?? null;
            return '<span' . $class . '>'
                . htmlspecialchars(format_time_only($start !== null ? (string)$start : null))
                . '&nbsp;&ndash;&nbsp;'
                . htmlspecialchars(format_time_only($end !== null ? (string)$end : null))
                . '</span>';

        case 'value_err':
            $field = $cell['field'] ?? '';
            $val = $data[$field] ?? null;
            $dd = fmt_value($val);
            $err = $data[$field . '_err'] ?? null;
            if ($err !== null && $err !== '') {
                $dd .= ' ± ' . fmt_value($err);
            }
            return '<span' . $class . '>' . $dd . '</span>';

        default:
            return null;
    }
}

function render_run_card(array $run): void
{
    render_list_card('run', $run);
}

function render_group_card(array $card): void
{
    render_list_card('group', $card, 'group-card');
}

/**
 * Load list-table layouts from includes/layouts/layout_tables.php (cached per request).
 */
function get_table_layouts(): array
{
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_tables.php';
    }
    return $layouts;
}

/**
 * Resolve a table-column link key to an href, or null if not linkable.
 * Keys: 'run' → detail_runs, 'group' → detail_groups. Value comes from the column's field.
 */
function list_table_column_href(string $linkKey, array $col, array $data): ?string
{
    $field = $col['field'] ?? '';
    $raw = $data[$field] ?? null;
    if ($raw === null || $raw === '') {
        return null;
    }
    $id = (int)$raw;
    return match ($linkKey) {
        'run'   => 'detail_runs.php?run=' . $id,
        'group' => 'detail_groups.php?group=' . $id,
        default => null,
    };
}

/**
 * list_cell_html() plus optional 'link' => 'run'|'group' wrapper.
 * Empty / unlinkable values stay unwrapped ("—").
 */
function list_cell_with_optional_link(array $cell, array $data): ?string
{
    $html = list_cell_html($cell, $data);
    if ($html === null) {
        return null;
    }
    $linkKey = $cell['link'] ?? null;
    $href = (is_string($linkKey) && $linkKey !== '')
        ? list_table_column_href($linkKey, $cell, $data)
        : null;
    if ($href === null) {
        return $html;
    }
    return '<a class="list-table-link" href="' . htmlspecialchars($href) . '">' . $html . '</a>';
}

/**
 * Render a date-bucket (or any list) as a table from includes/layouts/layout_tables.php.
 * Columns with link => 'run'|'group' become anchors; URLs are resolved here.
 */
function render_list_table(string $layoutKey, array $rows): void
{
    $layouts = get_table_layouts();
    if (!isset($layouts[$layoutKey]) || !$rows) {
        return;
    }
    $layout = $layouts[$layoutKey];
    $columns = $layout['columns'] ?? [];
    if (!$columns) {
        return;
    }

    $borderField = $layout['quality_border'] ?? null;

    echo '<div class="list-table-wrap"><table class="list-table">';
    echo '<thead><tr>';
    foreach ($columns as $col) {
        $header = $col['header'] ?? '';
        echo '<th>' . htmlspecialchars((string)$header) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $data) {
        $classes = ['list-table-row'];
        if (is_string($borderField) && $borderField !== '' && array_key_exists($borderField, $data)) {
            $classes[] = 'quality-border-' . quality_slug($data[$borderField] ?? null);
        }

        echo '<tr class="' . htmlspecialchars(implode(' ', $classes)) . '">';
        foreach ($columns as $col) {
            $html = list_cell_with_optional_link($col, $data);
            if ($html === null) {
                echo '<td>—</td>';
                continue;
            }
            echo '<td>' . $html . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

function render_run_table(array $rows): void
{
    render_list_table('run', $rows);
}

function render_group_table(array $rows): void
{
    render_list_table('group', $rows);
}

/**
 * Normalize a path/URL base to end with a single trailing slash (or empty).
 */
function plots_base_slash(string $base): string
{
    $base = trim($base);
    if ($base === '') {
        return '';
    }
    return rtrim($base, '/') . '/';
}

/**
 * Resolve the on-disk plots location: {base}/{id}/.
 * If fs_base is empty and web_base is site-relative, try DOCUMENT_ROOT + web_base/{id}/.
 *
 * @return array{status: 'ok'|'missing_id'|'bad_base', dir: ?string}
 *   ok         — id directory exists and is readable (dir set)
 *   missing_id — plots base is OK; this id's subfolder was never created
 *   bad_base   — base unusable / missing / unreadable, or id dir unreadable
 */
function resolve_plots_directory(string $fsBase, string $webBase, int $id): array
{
    $subdir = (string)$id;
    $fsBase = trim($fsBase);
    $baseDir = null;
    $dir = null;

    if ($fsBase !== '') {
        $baseDir = rtrim($fsBase, "/\\");
        $dir = $baseDir . DIRECTORY_SEPARATOR . $subdir;
    } else {
        $webBase = trim($webBase);
        if ($webBase === '' || preg_match('#^https?://#i', $webBase)) {
            return ['status' => 'bad_base', 'dir' => null];
        }
        $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), "/\\");
        if ($docRoot === '') {
            return ['status' => 'bad_base', 'dir' => null];
        }
        $path = parse_url($webBase, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $webBase;
        }
        $rel = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $baseDir = $docRoot . DIRECTORY_SEPARATOR . $rel;
        $dir = $baseDir . DIRECTORY_SEPARATOR . $subdir;
    }

    if ($baseDir === null || $dir === null || !is_dir($baseDir) || !is_readable($baseDir)) {
        return ['status' => 'bad_base', 'dir' => null];
    }
    if (!is_dir($dir)) {
        return ['status' => 'missing_id', 'dir' => null];
    }
    if (!is_readable($dir)) {
        return ['status' => 'bad_base', 'dir' => null];
    }
    return ['status' => 'ok', 'dir' => $dir];
}

/**
 * List image files in a plots directory, alphanumeric (natural) order.
 * @return list<string> basenames only
 */
function list_plot_image_files(string $dir): array
{
    // Raster formats only. SVG is deliberately absent: it can carry script,
    // and the gallery links each plot directly, so clicking one would open it
    // as a same-origin document. Apache types these files from the extension,
    // so a renamed SVG is declared image/* and never executes. ROOT can emit
    // SVG (SaveAs("plot.svg")) — do not add it back without also changing how
    // the plot is linked.
    $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    $files = [];
    $entries = @scandir($dir);
    if ($entries === false) {
        return [];
    }
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $files[] = $name;
    }
    natcasesort($files);
    return array_values($files);
}

/**
 * Render analysis plots for a run or group.
 * $kind: 'run' | 'group' — selects config keys *_plots_web_base / *_plots_fs_base.
 *
 * - bad_base (config / parent root) → hard WARNING
 * - missing id subfolder → softer “not been created”
 * - id dir OK but no images → soft “No plots available.”
 * - Otherwise images in alphanumeric order under web_base/{id}/
 */
function render_analysis_plots(array $config, string $kind, int $id): void
{
    $webKey = $kind === 'group' ? 'group_plots_web_base' : 'run_plots_web_base';
    $fsKey  = $kind === 'group' ? 'group_plots_fs_base' : 'run_plots_fs_base';
    $webBase = plots_base_slash((string)($config[$webKey] ?? ''));
    $fsBase  = (string)($config[$fsKey] ?? '');

    $resolved = resolve_plots_directory($fsBase, $webBase, $id);
    if ($resolved['status'] === 'bad_base') {
        render_status_message('plot_bad_base');
        return;
    }
    if ($resolved['status'] === 'missing_id') {
        render_status_message('plot_missing_id');
        return;
    }

    $dir = $resolved['dir'] ?? null;
    if ($dir === null) {
        render_status_message('plot_bad_base');
        return;
    }

    $files = list_plot_image_files($dir);
    if (!$files) {
        render_status_message('plot_empty');
        return;
    }

    if ($webBase === '') {
        render_status_message('plot_bad_base');
        return;
    }

    echo '<div class="plot-gallery">';
    foreach ($files as $name) {
        $src = $webBase . $id . '/' . rawurlencode($name);
        $alt = pathinfo($name, PATHINFO_FILENAME);
        echo '<figure class="plot-item">';
        echo '<a href="' . htmlspecialchars($src) . '" target="_blank" rel="noopener">';
        echo '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '" loading="lazy">';
        echo '</a>';
        echo '<figcaption>' . htmlspecialchars($name) . '</figcaption>';
        echo '</figure>';
    }
    echo '</div>';
}

/**
 * Load report column catalog from includes/layouts/layout_report.php (cached).
 */
function get_report_layouts(): array
{
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_report.php';
    }
    return $layouts;
}

/**
 * Report column source → table / alias.
 * join=true means LEFT JOIN on run_number for the run report (selected cols only).
 *
 * @return array<string, array{table:string,alias:string,join:bool}>
 */
function report_source_tables(): array
{
    return [
        'run'      => ['table' => 'Run_info',          'alias' => 'r', 'join' => false],
        'analysis' => ['table' => 'Analysis',          'alias' => 'a', 'join' => false],
        'epics'    => ['table' => 'EPICS_data',        'alias' => 'e', 'join' => true],
        'daq'      => ['table' => 'DAQ_config',        'alias' => 'd', 'join' => true],
        'group'    => ['table' => 'Grouped_Analysis',  'alias' => 'g', 'join' => false],
    ];
}

/**
 * @return array<string, array<string,true>>
 */
function report_schema_field_sets(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $out = [];
    foreach (report_source_tables() as $source => $spec) {
        $fields = [];
        if (table_exists($pdo, $spec['table'])) {
            foreach (get_table_columns($pdo, $spec['table']) as $col) {
                $fields[$col['name']] = true;
            }
        }
        $out[$source] = $fields;
    }
    return $cache = $out;
}

/**
 * Raw picker rows from includes/layouts/layout_report.php (section_rows, else one row from sections).
 *
 * @return list<list<array<string,mixed>|null>>
 */
function report_layout_raw_rows(array $pack): array
{
    $rows = $pack['section_rows'] ?? null;
    if (is_array($rows) && $rows !== []) {
        return $rows;
    }
    $sections = $pack['sections'] ?? null;
    if (is_array($sections) && $sections !== []) {
        return [$sections];
    }
    return [];
}

/**
 * Soft warnings for unknown / mis-sourced fields in includes/layouts/layout_report.php.
 * Page keeps rendering; bad entries stay skipped.
 *
 * @return list<array{key:string,summary:string}>
 */
function report_layout_column_warnings(PDO $pdo, string $view): array
{
    $key = $view === 'groups' ? 'group' : 'run';
    $pack = get_report_layouts()[$key] ?? [];
    $sets = report_schema_field_sets($pdo);
    $warnings = [];
    $seenMissing = [];

    $sourceOk = static function (string $field, string $source) use ($sets): bool {
        return isset($sets[$source][$field]);
    };

    $noteMissing = static function (string $field, string $where, string $detail) use (&$warnings, &$seenMissing): void {
        $dedupe = $field . "\0" . $where;
        if (isset($seenMissing[$dedupe])) {
            return;
        }
        $seenMissing[$dedupe] = true;
        $warnings[] = [
            'key' => 'report_unknown_column',
            'summary' => "WARNING: layout_report {$where} references `{$field}`{$detail}; skipped.",
        ];
    };

    $catalogFields = [];
    $rawEntries = [];
    $layoutRows = report_layout_raw_rows($pack);
    if ($layoutRows !== []) {
        foreach ($layoutRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $title = trim((string)($section['title'] ?? 'Columns'));
                $columns = $section['columns'] ?? [];
                if (!is_array($columns)) {
                    continue;
                }
                foreach ($columns as $entry) {
                    if (is_array($entry)) {
                        $rawEntries[] = ['entry' => $entry, 'where' => "section \"{$title}\""];
                    }
                }
            }
        }
    } else {
        $available = $pack['available'] ?? [];
        if (is_array($available)) {
            foreach ($available as $entry) {
                if (is_array($entry)) {
                    $rawEntries[] = ['entry' => $entry, 'where' => 'available list'];
                }
            }
        }
    }

    foreach ($rawEntries as $item) {
        $entry = $item['entry'];
        $where = $item['where'];
        $field = isset($entry['field']) ? (string)$entry['field'] : '';
        $source = isset($entry['source']) ? (string)$entry['source'] : '';
        if ($field === '') {
            $noteMissing('(empty)', $where, ' (missing field key)');
            continue;
        }
        $sourceMap = report_source_tables();
        if (!isset($sourceMap[$source])) {
            $noteMissing($field, $where, ' (invalid source "' . $source . '")');
            continue;
        }
        if (!$sourceOk($field, $source)) {
            $noteMissing($field, $where, ' (not a column in source)');
            continue;
        }
        $catalogFields[$field] = true;
    }

    $defaults = $pack['defaults'] ?? [];
    if (is_array($defaults)) {
        foreach ($defaults as $name) {
            $name = (string)$name;
            if ($name === '') {
                continue;
            }
            if (!isset($catalogFields[$name])) {
                $noteMissing(
                    $name,
                    'defaults',
                    ' (not in the live column catalog — unknown name, wrong source, or omitted from sections)'
                );
            }
        }
    }

    return $warnings;
}

/**
 * Catalog entries that exist on the live schema for this report view.
 * Flat list in row / section / column order (for selection + CSV).
 *
 * @return list<array{field:string,header:string,source:string,kind?:string,link?:string}>
 */
function report_available_columns(PDO $pdo, string $view): array
{
    $flat = [];
    foreach (report_available_column_rows($pdo, $view) as $row) {
        foreach ($row as $section) {
            if (!is_array($section)) {
                continue;
            }
            foreach ($section['columns'] as $entry) {
                $flat[] = $entry;
            }
        }
    }
    return $flat;
}

/**
 * Sectioned catalog for the Columns picker UI (flat; no spacers).
 *
 * @return list<array{title:string,columns:list<array{field:string,header:string,source:string,kind?:string,link?:string}>}>
 */
function report_available_column_sections(PDO $pdo, string $view): array
{
    $flat = [];
    foreach (report_available_column_rows($pdo, $view) as $row) {
        foreach ($row as $section) {
            if (is_array($section)) {
                $flat[] = $section;
            }
        }
    }
    return $flat;
}

/**
 * Picker rows (null slots preserved). Empty extra rows are omitted.
 *
 * @return list<list<array{title:string,columns:list<array{field:string,header:string,source:string,kind?:string,link?:string}>}|null>>
 */
function report_available_column_rows(PDO $pdo, string $view): array
{
    $key = $view === 'groups' ? 'group' : 'run';
    $pack = get_report_layouts()[$key] ?? [];
    $sets = report_schema_field_sets($pdo);
    $sourceMap = report_source_tables();

    $fieldOk = static function (array $entry) use ($sets, $sourceMap): bool {
        if (!isset($entry['field'], $entry['header'], $entry['source'])) {
            return false;
        }
        $source = (string)$entry['source'];
        if (!isset($sourceMap[$source])) {
            return false;
        }
        return isset($sets[$source][(string)$entry['field']]);
    };

    $rawRows = report_layout_raw_rows($pack);
    if ($rawRows === []) {
        $available = $pack['available'] ?? [];
        $cols = [];
        if (is_array($available)) {
            foreach ($available as $entry) {
                if (is_array($entry) && $fieldOk($entry)) {
                    $cols[] = $entry;
                }
            }
        }
        return $cols === [] ? [] : [[['title' => 'Columns', 'columns' => $cols]]];
    }

    $out = [];
    foreach ($rawRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $built = [];
        $hasLive = false;
        foreach ($row as $slot) {
            if ($slot === null) {
                $built[] = null;
                continue;
            }
            if (!is_array($slot)) {
                continue;
            }
            $title = trim((string)($slot['title'] ?? ''));
            $columns = $slot['columns'] ?? [];
            if (!is_array($columns)) {
                $built[] = null;
                continue;
            }
            $kept = [];
            foreach ($columns as $entry) {
                if (is_array($entry) && $fieldOk($entry)) {
                    $kept[] = $entry;
                }
            }
            if ($kept === []) {
                $built[] = null;
                continue;
            }
            $hasLive = true;
            $built[] = [
                'title' => $title !== '' ? $title : 'Columns',
                'columns' => $kept,
            ];
        }
        if ($hasLive) {
            $out[] = $built;
        }
    }
    return $out;
}

/**
 * LEFT JOIN + SELECT fragments for extra run-report sources (epics, daq).
 *
 * @param list<array{field:string,source:string}> $selectedColumns
 * @return array{select:string,join:string}
 */
function report_extra_join_sql(PDO $pdo, array $selectedColumns): array
{
    $bySource = [];
    foreach ($selectedColumns as $col) {
        $src = (string)($col['source'] ?? '');
        $field = (string)($col['field'] ?? '');
        $spec = report_source_tables()[$src] ?? null;
        if ($spec === null || empty($spec['join'])) {
            continue;
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
            continue;
        }
        if ($field === 'run_number' || $field === 'last_updated') {
            continue;
        }
        $bySource[$src][] = $field;
    }

    $selectParts = [];
    $joinParts = [];
    foreach ($bySource as $src => $fields) {
        $spec = report_source_tables()[$src];
        $table = $spec['table'];
        $alias = $spec['alias'];
        if (!table_exists($pdo, $table) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            continue;
        }
        $cols = [];
        foreach (array_unique($fields) as $field) {
            if (!table_has_column($pdo, $table, $field)) {
                continue;
            }
            $cols[] = $alias . '.`' . $field . '`';
        }
        if ($cols === []) {
            continue;
        }
        $selectParts[] = implode(', ', $cols);
        $joinParts[] = 'LEFT JOIN `' . $table . '` ' . $alias
            . ' ON ' . $alias . '.run_number = r.run_number';
    }

    return [
        'select' => $selectParts === [] ? '' : ', ' . implode(', ', $selectParts),
        'join'   => $joinParts === [] ? '' : ' ' . implode(' ', $joinParts),
    ];
}

/**
 * Resolve selected report columns (defaults if cols omitted / empty after filter).
 *
 * @param list<string>|null $requestedFields
 * @return list<array{field:string,header:string,source:string,kind?:string,link?:string}>
 */
function report_selected_columns(PDO $pdo, string $view, ?array $requestedFields): array
{
    $available = report_available_columns($pdo, $view);
    $byField = [];
    foreach ($available as $entry) {
        $byField[(string)$entry['field']] = $entry;
    }

    $key = $view === 'groups' ? 'group' : 'run';
    $defaults = get_report_layouts()[$key]['defaults'] ?? [];
    if (!is_array($defaults)) {
        $defaults = [];
    }

    $pick = [];
    if (is_array($requestedFields) && $requestedFields !== []) {
        foreach ($requestedFields as $name) {
            $name = (string)$name;
            if (isset($byField[$name])) {
                $pick[] = $byField[$name];
            }
        }
    }
    if ($pick === []) {
        $defaultSet = [];
        foreach ($defaults as $name) {
            if (is_string($name) && $name !== '') {
                $defaultSet[$name] = true;
            }
        }
        foreach ($available as $entry) {
            $field = (string)($entry['field'] ?? '');
            if ($field !== '' && isset($defaultSet[$field])) {
                $pick[] = $entry;
            }
        }
    }
    return $pick;
}

/**
 * One Available Data checkbox picker row (null = empty grid slot).
 *
 * @param list<array{title:string,columns:list<array{field:string,header:string}>}|null> $row
 * @param list<string> $selectedFields
 */
function render_report_column_picker_row(array $row, array $selectedFields): void
{
    echo '<div class="report-column-row">';
    foreach ($row as $section) {
        if ($section === null) {
            echo '<div class="report-column-section report-column-section-spacer" aria-hidden="true"></div>';
            continue;
        }
        echo '<div class="report-column-section">';
        echo '<h3 class="report-column-section-title">' . htmlspecialchars((string)$section['title']) . '</h3>';
        echo '<div class="report-column-section-options">';
        foreach ($section['columns'] as $col) {
            $field = (string)$col['field'];
            $checked = in_array($field, $selectedFields, true);
            echo '<label class="report-column-option">';
            echo '<input type="checkbox" name="cols[]" value="' . htmlspecialchars($field) . '"'
                . ($checked ? ' checked' : '') . '>';
            echo ' ' . htmlspecialchars((string)$col['header']);
            echo '</label>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}

/**
 * Plain-text cell value for report table / CSV (lookup labels for type/quality).
 */
function report_cell_text(array $col, array $row): string
{
    $field = (string)($col['field'] ?? '');
    $raw = $row[$field] ?? null;
    if ($raw === null || $raw === '') {
        return '';
    }
    $kind = $col['kind'] ?? 'text';
    if ($kind === 'quality' || lookup_table_for_column($field) !== null) {
        $label = lookup_label_for_field($field, $raw);
        return $label !== '' ? $label : (string)$raw;
    }
    return (string)$raw;
}

/**
 * Render the report HTML table for the selected columns.
 *
 * @param list<array{field:string,header:string,source:string,kind?:string,link?:string}> $columns
 * @param list<array<string,mixed>> $rows
 */
function render_report_table(array $columns, array $rows): void
{
    if ($columns === [] || $rows === []) {
        return;
    }
    echo '<div class="list-table-wrap report-table-wrap"><table class="list-table report-table">';
    echo '<thead><tr>';
    foreach ($columns as $col) {
        echo '<th>' . htmlspecialchars((string)($col['header'] ?? '')) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $data) {
        echo '<tr class="list-table-row">';
        foreach ($columns as $col) {
            $kind = $col['kind'] ?? 'text';
            $cell = [
                'kind'  => ($kind === 'id' || $kind === 'quality') ? $kind : 'text',
                'field' => $col['field'] ?? '',
            ];
            if (isset($col['link'])) {
                $cell['link'] = $col['link'];
            }
            $html = list_cell_with_optional_link($cell, $data);
            echo '<td>' . ($html ?? '—') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

/**
 * Neutralize a CSV cell that a spreadsheet would read as a formula.
 *
 * Excel and LibreOffice evaluate a cell beginning with = + - @ (or a leading
 * tab / carriage return), so a value like "=cmd|'/c calc'!A1" arriving from a
 * free-text DB column becomes code execution on the analyst's machine when
 * the download is opened. A leading apostrophe forces the cell to text.
 *
 * Numbers are left alone — negative measurements such as -472.99 are ordinary
 * data, not formulas, and must not gain a stray quote.
 */
function csv_safe_cell(string $value): string
{
    if ($value === '' || is_numeric($value)) {
        return $value;
    }
    return strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
}

/**
 * Stream a CSV download for the selected report columns, then exit.
 *
 * @param list<array{field:string,header:string,source:string,kind?:string,link?:string}> $columns
 * @param list<array<string,mixed>> $rows
 */
function emit_report_csv(string $view, array $columns, array $rows): void
{
    $stamp = date('Ymd');
    $kind = $view === 'groups' ? 'groups' : 'runs';
    $filename = "molpol-{$kind}-{$stamp}.csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        echo 'CSV output failed.';
        exit;
    }
    // UTF-8 BOM helps Excel
    fwrite($out, "\xEF\xBB\xBF");
    $headers = array_map(fn($c) => (string)($c['header'] ?? ''), $columns);
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = csv_safe_cell(report_cell_text($col, $row));
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

