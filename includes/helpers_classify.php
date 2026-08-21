<?php
/**
 * includes/helpers_classify.php
 *
 * Column classifiers, section layouts, and load_section_view().
 *
 * DAQ_config / EPICS / Analysis / Grouped_Analysis column grouping rules and
 * detail-page row layouts live in includes/layouts/layout_sections.php. The
 * functions below are thin wrappers + a shared engine (prefix via strstr, or regex).
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
