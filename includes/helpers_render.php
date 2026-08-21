<?php
/**
 * includes/helpers_render.php
 *
 * Status messages, detail cards/fields, lookups, navbar, list cards/tables, summaries.
 */

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
    // Letter-only codes may already match a .quality-tag-{slug} rule; anything
    // else is unrecognized — distinct from empty/unset ("pending").
    $slug = strtolower($raw);
    return preg_match('/^[a-z]+$/', $slug) ? $slug : 'unknown';
}

function format_group_label($groupId): string
{
    if ($groupId === null || $groupId === '') {
        return '—';
    }
    return (string)(int)$groupId;
}

/**
 * Require a positive integer query param or emit a short 400 page and exit.
 */
function require_positive_int_query(string $param): int
{
    $value = filter_input(INPUT_GET, $param, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
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
