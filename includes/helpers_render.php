<?php
/**
 * includes/helpers_render.php
 *
 * Status messages, detail cards/fields, lookups, navbar, list cards/tables, summaries.
 */

/** Maps a quality code (or legacy ENUM label) to the stylesheet class suffix. */
function quality_slug($quality){
    if ($quality === null || $quality === '') {
        return 'pending';
    }
    $raw = (string)$quality;
    $slugs = (($__lk = get_lookup_layouts()) && isset($__lk['quality_slugs']) ? $__lk['quality_slugs'] : []);
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

function format_group_label($groupId){
    if ($groupId === null || $groupId === '') {
        return '—';
    }
    return (string)(int)$groupId;
}

/**
 * Require a positive integer query param or emit a short 400 page and exit.
 */
function require_positive_int_query($param){
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
function require_row($pdo, $table, $pkColumn, $id, $label){
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
function render_section_header($title, $metaLabel = null, $metaValue = null){
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

function render_empty_state($message){
    echo '<p class="empty-state">' . htmlspecialchars($message) . '</p>';
}

/**
 * Load on-page status help catalog (cached per request).
 */
function get_error_descriptions(){
    static $catalog = null;
    if ($catalog === null) {
        $catalog = require __DIR__ . '/descriptions_errors.php';
    }
    return $catalog;
}

function render_status_info_link($key){
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
 * @param $key             Catalog key in descriptions_errors.php
 * @param string|null $summaryOverride Short line; defaults to catalog summary
 */
function render_status_message($key, $summaryOverride = null){
    $catalog = get_error_descriptions();
    $entry = isset($catalog[$key]) ? $catalog[$key] : null;
    $summary = $summaryOverride;
    if ($summary === null || $summary === '') {
        $summary = is_array($entry) ? (string)(isset($entry['summary']) ? $entry['summary'] : $key) : $key;
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
function render_status_warning_box($warnings){
    if ($warnings === []) {
        return;
    }
    echo '<div class="status-warning-box" role="status">';
    foreach ($warnings as $warn) {
        $key = is_array($warn) ? (string)(isset($warn['key']) ? $warn['key'] : 'report_unknown_column') : 'report_unknown_column';
        $summary = is_array($warn) ? (string)(isset($warn['summary']) ? $warn['summary'] : '') : (string)$warn;
        render_status_message($key, $summary !== '' ? $summary : null);
    }
    echo '</div>';
}

/**
 * Show caretaker-facing layout config errors (malformed layouts bands).
 * Soft failure — page still renders; does not TypeError.
 */
function render_layout_errors($pack){
    foreach (isset($pack['layout_errors']) ? $pack['layout_errors'] : [] as $err) {
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
function render_featured_rows($pack){
    $row = isset($pack['row']) ? $pack['row'] : null;
    foreach (isset($pack['featured_rows']) ? $pack['featured_rows'] : [] as $feat) {
        render_featured_row(isset($feat['title']) ? $feat['title'] : '', isset($feat['columns']) ? $feat['columns'] : [], $row);
    }
}

/**
 * Render one named layout band from a load_section_view() pack.
 * No-op if the band is missing or empty.
 */
function render_section_cards($pack, $layoutName){
    $rows = isset($pack['layouts'][$layoutName]) ? $pack['layouts'][$layoutName] : [];
    if (!is_array($rows) || $rows === []) {
        return;
    }
    $sections = isset($pack['sections']) ? $pack['sections'] : [];
    $row = isset($pack['row']) ? $pack['row'] : null;
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
function render_unallocated_sections($pack, $heading){
    $rows = isset($pack['layouts']['unallocated']) ? $pack['layouts']['unallocated'] : [];
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
function render_card_row($rowKeys, $sections, $row){
    $hasAnyContent = array_filter($rowKeys, function ($k) { return $k !== null && isset($sections[$k]); });
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
function render_featured_row($title, $columns, $row){
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

/**
 * Insert a space after each comma so long CSV-like strings (masks, ped lists)
 * can wrap in the UI. Collapses ",  " → ", ". Display-only — not for CSV.
 */
function soft_wrap_commas($text){
    $__sw = preg_replace('/,\s*/', ', ', $text); return $__sw !== null ? $__sw : $text;
}

function fmt_value($value){
    if ($value === null || $value === '') {
        return '—';
    }
    return htmlspecialchars(soft_wrap_commas((string)$value));
}

/**
 * Render a flat (non-grouped) columns/row pair as a definition list, skipping
 * the FK column. When both `foo` and `foo_err` appear in $columns, they render
 * as one row: "value ± error" under the base column's label; the `_err` column
 * is suppressed. An orphan `_err` with no base sibling still renders alone.
 */
function render_field_list($columns, $row, $skipColumn = 'run_number'){
    if ($row === null) {
        render_status_message('empty_table_row');
        return;
    }
    $colNameSet = array();
    foreach ($columns as $_col) { $colNameSet[$_col['name']] = true; }
    echo '<dl class="fields">';
    foreach ($columns as $col) {
        $name = $col['name'];
        if ($name === $skipColumn) {
            continue;
        }
        // Paired away: base column will absorb this as "value ± error".
        if (substr($name, -4) === '_err') {
            $base = substr($name, 0, -4);
            if (isset($colNameSet[$base])) {
                continue;
            }
        }
        $val = isset($row[$name]) ? $row[$name] : null;
        if (lookup_table_for_column($name) !== null && $val !== null && $val !== '') {
            $dd = htmlspecialchars(lookup_label_for_field($name, $val));
        } else {
            $dd = fmt_value($val);
        }
        $errName = $name . '_err';
        if (isset($colNameSet[$errName])) {
            $err = isset($row[$errName]) ? $row[$errName] : null;
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
function get_lookup_layouts(){
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_lookups.php';
    }
    return $layouts;
}

function lookup_table_for_column($column){
    $name = (($__lk = get_lookup_layouts()) && isset($__lk['columns'][$column]) ? $__lk['columns'][$column] : null);
    return is_string($name) && $name !== '' ? $name : null;
}

/**
 * code => display_label for one lookup table. Empty if the table is missing.
 *
 * @return array<string, string>
 */
function load_lookup_map($pdo, $table){
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (!table_exists($pdo, $table)) {
        return $cache[$table] = [];
    }
    $cfg = (($__lk = get_lookup_layouts()) && isset($__lk['tables'][$table]) ? $__lk['tables'][$table] : []);
    $codeCol = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)(isset($cfg['code']) ? $cfg['code'] : 'code'))
        ? (string)(isset($cfg['code']) ? $cfg['code'] : 'code') : 'code';
    $labelCol = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)(isset($cfg['label']) ? $cfg['label'] : 'display_label'))
        ? (string)(isset($cfg['label']) ? $cfg['label'] : 'display_label') : 'display_label';
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

function lookup_label_for_field($field, $code){
    if ($code === null || $code === '') {
        return '';
    }
    $code = (string)$code;
    $table = lookup_table_for_column($field);
    if ($table === null) {
        return $code;
    }
    $pdo = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null;
    if (!($pdo instanceof PDO)) {
        return $code;
    }
    $map = load_lookup_map($pdo, $table);
    return isset($map[$code]) ? $map[$code] : $code;
}

/**
 * Load master navbar layout from includes/layouts/layout_navbar.php (cached).
 */
function get_navbar_layout(){
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
function render_site_navbar(){
    $nav = get_navbar_layout();
    $rawLinks = isset($nav['links']) ? $nav['links'] : [];
    if (!is_array($rawLinks) || $rawLinks === []) {
        return;
    }

    $links = [];
    foreach ($rawLinks as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $href  = trim((string)(isset($entry['href']) ? $entry['href'] : ''));
        $label = trim((string)(isset($entry['label']) ? $entry['label'] : ''));
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
function get_card_layouts(){
    static $layouts = null;
    if ($layouts === null) {
        $layouts = require __DIR__ . '/layouts/layout_cards.php';
    }
    return $layouts;
}

/**
 * Load run/group summary layouts from includes/layouts/layout_run_summary.php.
 */
function get_run_summary_layouts(){
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
function summary_cell_value_html($cell, $data, $options = []){
    $kind = isset($cell['kind']) ? $cell['kind'] : '';
    switch ($kind) {
        case 'text':
            $raw = isset($data[isset($cell['field']) ? $cell['field'] : '']) ? $data[isset($cell['field']) ? $cell['field'] : ''] : null;
            if ($raw === null || $raw === '') {
                $text = '—';
            } else {
                $field = (string)(isset($cell['field']) ? $cell['field'] : '');
                $label = lookup_label_for_field($field, $raw);
                $text = $label !== '' ? $label : soft_wrap_commas((string)$raw);
            }
            return htmlspecialchars($text);

        case 'quality':
            $field = (string)(isset($cell['field']) ? $cell['field'] : '');
            $raw = isset($data[$field]) ? $data[$field] : null;
            if ($raw === null || $raw === '') {
                return '—';
            }
            $qSlug = quality_slug((string)$raw);
            $label = lookup_label_for_field($field, $raw);
            $text = $label !== '' ? $label : (string)$raw;
            return '<span class="quality-' . htmlspecialchars($qSlug) . '">'
                . htmlspecialchars($text) . '</span>';

        case 'id':
            $field = isset($cell['field']) ? $cell['field'] : '';
            $raw = isset($data[$field]) ? $data[$field] : null;
            if ($raw === null || $raw === '') {
                return '—';
            }
            $prefix = isset($cell['prefix']) ? $cell['prefix'] : '';
            $text = htmlspecialchars($prefix . (string)$raw);
            $linkKey = isset($cell['link']) ? $cell['link'] : null;
            if (is_string($linkKey) && $linkKey !== '') {
                $href = list_table_column_href($linkKey, $cell, $data);
                if ($href !== null) {
                    return '<a href="' . htmlspecialchars($href) . '">' . $text . '</a>';
                }
            }
            return $text;

        case 'time_range':
            $startRaw = isset($data[isset($cell['start']) ? $cell['start'] : '']) ? $data[isset($cell['start']) ? $cell['start'] : ''] : null;
            $endRaw   = isset($data[isset($cell['end']) ? $cell['end'] : '']) ? $data[isset($cell['end']) ? $cell['end'] : ''] : null;
            $start = ($startRaw !== null && trim((string)$startRaw) !== '')
                ? (string)$startRaw
                : (string)(isset($options['empty_start']) ? $options['empty_start'] : '—');
            $end = ($endRaw !== null && trim((string)$endRaw) !== '')
                ? (string)$endRaw
                : (string)(isset($options['empty_end']) ? $options['empty_end'] : '—');
            return '<span class="run-summary-time">'
                . htmlspecialchars($start)
                . ' &rarr; '
                . htmlspecialchars($end)
                . '</span>';

        case 'comment':
            $raw = isset($data[isset($cell['field']) ? $cell['field'] : '']) ? $data[isset($cell['field']) ? $cell['field'] : ''] : null;
            if ($raw === null || trim((string)$raw) === '') {
                return null;
            }
            return nl2br(htmlspecialchars(soft_wrap_commas((string)$raw)));

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
function render_run_summary($data, $layoutKey = 'run', $options = []){
    $layouts = get_run_summary_layouts();
    if (!isset($layouts[$layoutKey])) {
        return;
    }
    $layout = $layouts[$layoutKey];
    $rows = isset($layout['rows']) ? $layout['rows'] : [];
    $footer = isset($layout['footer']) ? $layout['footer'] : [];

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
            $cells[] = ['label' => (string)(isset($cell['label']) ? $cell['label'] : ''), 'html' => $html];
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
        $label = (string)(isset($cell['label']) ? $cell['label'] : '');
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
function render_list_card($layoutKey, $data, $extraClass = ''){
    $layouts = get_card_layouts();
    if (!isset($layouts[$layoutKey])) {
        return;
    }
    $layout = $layouts[$layoutKey];
    $rows = isset($layout['rows']) ? $layout['rows'] : [];

    $classes = ['run-card', 'card'];
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    $borderField = isset($layout['quality_border']) ? $layout['quality_border'] : null;
    if (is_string($borderField) && $borderField !== '' && array_key_exists($borderField, $data)) {
        $classes[] = 'quality-border-' . quality_slug(isset($data[$borderField]) ? $data[$borderField] : null);
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

function render_list_card_cell($cell, $data){
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
function list_cell_html($cell, $data){
    if (!isset($cell['kind'])) {
        return null;
    }
    $kind = $cell['kind'];
    $class = isset($cell['class']) ? ' class="' . htmlspecialchars($cell['class']) . '"' : '';

    switch ($kind) {
        case 'id':
            $field = isset($cell['field']) ? $cell['field'] : '';
            $raw = isset($data[$field]) ? $data[$field] : null;
            if ($raw === null || $raw === '') {
                $text = '—';
            } else {
                $prefix = isset($cell['prefix']) ? $cell['prefix'] : '';
                $text = $prefix . $raw;
            }
            return '<span' . $class . '>' . htmlspecialchars((string)$text) . '</span>';

        case 'quality':
            $field = isset($cell['field']) ? $cell['field'] : '';
            $raw = isset($data[$field]) ? $data[$field] : null;
            if ($raw === null || $raw === '') {
                return '<span' . $class . '>—</span>';
            }
            $qSlug = quality_slug((string)$raw);
            $label = lookup_label_for_field($field, $raw);
            return '<span class="quality-tag quality-tag-' . htmlspecialchars($qSlug) . '">'
                . htmlspecialchars($label !== '' ? $label : (string)$raw) . '</span>';

        case 'text':
            $field = isset($cell['field']) ? $cell['field'] : '';
            $raw = isset($data[$field]) ? $data[$field] : null;
            if ($raw === null || $raw === '') {
                $text = '—';
            } else {
                $label = lookup_label_for_field($field, $raw);
                $text = $label !== '' ? $label : soft_wrap_commas((string)$raw);
            }
            return '<span' . $class . '>' . htmlspecialchars($text) . '</span>';

        case 'time_range':
            $start = isset($data[isset($cell['start']) ? $cell['start'] : '']) ? $data[isset($cell['start']) ? $cell['start'] : ''] : null;
            $end   = isset($data[isset($cell['end']) ? $cell['end'] : '']) ? $data[isset($cell['end']) ? $cell['end'] : ''] : null;
            return '<span' . $class . '>'
                . htmlspecialchars(format_time_only($start !== null ? (string)$start : null))
                . '&nbsp;&ndash;&nbsp;'
                . htmlspecialchars(format_time_only($end !== null ? (string)$end : null))
                . '</span>';

        case 'value_err':
            $field = isset($cell['field']) ? $cell['field'] : '';
            $val = isset($data[$field]) ? $data[$field] : null;
            $dd = fmt_value($val);
            $err = isset($data[$field . '_err']) ? $data[$field . '_err'] : null;
            if ($err !== null && $err !== '') {
                $dd .= ' ± ' . fmt_value($err);
            }
            return '<span' . $class . '>' . $dd . '</span>';

        default:
            return null;
    }
}

function render_run_card($run){
    render_list_card('run', $run);
}

function render_group_card($card){
    render_list_card('group', $card, 'group-card');
}

/**
 * Load list-table layouts from includes/layouts/layout_tables.php (cached per request).
 */
function get_table_layouts(){
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
function list_table_column_href($linkKey, $col, $data){
    $field = isset($col['field']) ? $col['field'] : '';
    $raw = isset($data[$field]) ? $data[$field] : null;
    if ($raw === null || $raw === '') {
        return null;
    }
    $id = (int)$raw;
    switch ($linkKey) {
        case 'run':
            return 'detail_runs.php?run=' . $id;
        case 'group':
            return 'detail_groups.php?group=' . $id;
        default:
            return null;
    }
}

/**
 * list_cell_html() plus optional 'link' => 'run'|'group' wrapper.
 * Empty / unlinkable values stay unwrapped ("—").
 */
function list_cell_with_optional_link($cell, $data){
    $html = list_cell_html($cell, $data);
    if ($html === null) {
        return null;
    }
    $linkKey = isset($cell['link']) ? $cell['link'] : null;
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
function render_list_table($layoutKey, $rows){
    $layouts = get_table_layouts();
    if (!isset($layouts[$layoutKey]) || !$rows) {
        return;
    }
    $layout = $layouts[$layoutKey];
    $columns = isset($layout['columns']) ? $layout['columns'] : [];
    if (!$columns) {
        return;
    }

    $borderField = isset($layout['quality_border']) ? $layout['quality_border'] : null;

    echo '<div class="list-table-wrap"><table class="list-table">';
    echo '<thead><tr>';
    foreach ($columns as $col) {
        $header = isset($col['header']) ? $col['header'] : '';
        echo '<th>' . htmlspecialchars((string)$header) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $data) {
        $classes = ['list-table-row'];
        if (is_string($borderField) && $borderField !== '' && array_key_exists($borderField, $data)) {
            $classes[] = 'quality-border-' . quality_slug(isset($data[$borderField]) ? $data[$borderField] : null);
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

function render_run_table($rows){
    render_list_table('run', $rows);
}

function render_group_table($rows){
    render_list_table('group', $rows);
}
