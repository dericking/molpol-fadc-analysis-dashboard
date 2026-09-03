<?php
/**
 * includes/helpers_report.php
 *
 * Report column catalog, joins, HTML table, and CSV emission.
 */

/**
 * Load report column catalog from includes/layouts/layout_report.php (cached).
 */
function get_report_layouts(){
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
function report_source_tables(){
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
function report_schema_field_sets($pdo){
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
function report_layout_raw_rows($pack){
    $rows = isset($pack['section_rows']) ? $pack['section_rows'] : null;
    if (is_array($rows) && $rows !== []) {
        return $rows;
    }
    $sections = isset($pack['sections']) ? $pack['sections'] : null;
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
function report_layout_column_warnings($pdo, $view){
    $key = $view === 'groups' ? 'group' : 'run';
    $pack = (($__rl = get_report_layouts()) && isset($__rl[$key]) ? $__rl[$key] : []);
    $sets = report_schema_field_sets($pdo);
    $warnings = [];
    $seenMissing = [];

    $sourceOk = static function ($field, $source) use ($sets){
        return isset($sets[$source][$field]);
    };

    $noteMissing = static function ($field, $where, $detail) use (&$warnings, &$seenMissing){
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
                $title = trim((string)(isset($section['title']) ? $section['title'] : 'Columns'));
                $columns = isset($section['columns']) ? $section['columns'] : [];
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
        $available = isset($pack['available']) ? $pack['available'] : [];
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

    $defaults = isset($pack['defaults']) ? $pack['defaults'] : [];
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
function report_available_columns($pdo, $view){
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
function report_available_column_sections($pdo, $view){
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
function report_available_column_rows($pdo, $view){
    $key = $view === 'groups' ? 'group' : 'run';
    $pack = (($__rl = get_report_layouts()) && isset($__rl[$key]) ? $__rl[$key] : []);
    $sets = report_schema_field_sets($pdo);
    $sourceMap = report_source_tables();

    $fieldOk = static function ($entry) use ($sets, $sourceMap){
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
        $available = isset($pack['available']) ? $pack['available'] : [];
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
            $title = trim((string)(isset($slot['title']) ? $slot['title'] : ''));
            $columns = isset($slot['columns']) ? $slot['columns'] : [];
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
function report_extra_join_sql($pdo, $selectedColumns){
    $bySource = [];
    foreach ($selectedColumns as $col) {
        $src = (string)(isset($col['source']) ? $col['source'] : '');
        $field = (string)(isset($col['field']) ? $col['field'] : '');
        $spec = (($__rt = report_source_tables()) && isset($__rt[$src]) ? $__rt[$src] : null);
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
function report_selected_columns($pdo, $view, $requestedFields){
    $available = report_available_columns($pdo, $view);
    $byField = [];
    foreach ($available as $entry) {
        $byField[(string)$entry['field']] = $entry;
    }

    $key = $view === 'groups' ? 'group' : 'run';
    $defaults = (($__rl = get_report_layouts()) && isset($__rl[$key]['defaults']) ? $__rl[$key]['defaults'] : []);
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
            $field = (string)(isset($entry['field']) ? $entry['field'] : '');
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
function render_report_column_picker_row($row, $selectedFields){
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
function report_cell_text($col, $row){
    $field = (string)(isset($col['field']) ? $col['field'] : '');
    $raw = isset($row[$field]) ? $row[$field] : null;
    if ($raw === null || $raw === '') {
        return '';
    }
    $kind = isset($col['kind']) ? $col['kind'] : 'text';
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
function render_report_table($columns, $rows){
    if ($columns === [] || $rows === []) {
        return;
    }
    echo '<div class="list-table-wrap report-table-wrap"><table class="list-table report-table">';
    echo '<thead><tr>';
    foreach ($columns as $col) {
        echo '<th>' . htmlspecialchars((string)(isset($col['header']) ? $col['header'] : '')) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $data) {
        echo '<tr class="list-table-row">';
        foreach ($columns as $col) {
            $kind = isset($col['kind']) ? $col['kind'] : 'text';
            $cell = [
                'kind'  => ($kind === 'id' || $kind === 'quality') ? $kind : 'text',
                'field' => isset($col['field']) ? $col['field'] : '',
            ];
            if (isset($col['link'])) {
                $cell['link'] = $col['link'];
            }
            $html = list_cell_with_optional_link($cell, $data);
            echo '<td>' . (isset($html) ? $html : '—') . '</td>';
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
function csv_safe_cell($value){
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
function emit_report_csv($view, $columns, $rows){
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
    $headers = array_map(function ($c) { return (string)(isset($c['header']) ? $c['header'] : ''); }, $columns);
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
