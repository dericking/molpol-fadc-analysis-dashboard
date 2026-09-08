<?php
/**
 * List-table layouts for the run/group index (Cards / Table toggle).
 *
 * =============================================================================
 * CARETAKER INSTRUCTIONS
 * =============================================================================
 *
 * File to edit: includes/layouts/layout_tables.php only (for table columns).
 * Card layouts stay in includes/layouts/layout_cards.php.
 *
 * Do not put URLs here — the app owns row links.
 *
 * --- Add, remove, or reorder a column ---
 * Edit the columns array under 'run' or 'group'. Each entry needs a header
 * (column title) plus the same cell kinds as card layouts:
 *
 *   id         Big identifier. Keys: field, optional prefix, optional class.
 *   quality    Colored quality pill. Keys: field.
 *   text       Plain text. Keys: field, optional class.
 *   time_range Start–end times. Keys: start, end, optional class.
 *   value_err  "value ± error". Keys: field (uses field + field_err).
 *
 * Optional link (no URL — app resolves it):
 *   'link' => 'run'    → detail_runs.php?run={field value}
 *   'link' => 'group'  → detail_groups.php?group={field value}
 * Empty field values stay unlinked ("—").
 *
 * Example — linked run + group numbers:
 *
 *   'columns' => [
 *     ['header' => 'Run Number', 'kind' => 'id', 'field' => 'run_number', 'link' => 'run'],
 *     ['header' => 'Group Number', 'kind' => 'id', 'field' => 'run_group', 'link' => 'group'],
 *     ['header' => 'Run Time', 'kind' => 'time_range', 'start' => 'run_start_datetime', 'end' => 'run_end_datetime'],
 *     ['header' => 'Run Type', 'kind' => 'text', 'field' => 'run_type'],
 *     ['header' => 'Quality', 'kind' => 'quality', 'field' => 'run_quality'],
 *   ],
 *
 * --- Optional quality row accent ---
 * Left-border quality accents are not used (quality tag / column only).
 * The optional 'quality_border' key is ignored in the default layouts.
 *
 * --- Fields ---
 * Index uses SELECT * from Run_info / Grouped_Analysis, so table columns can
 * reference any real field on those rows. Missing values show "—".
 *
 * After editing, reload the index; no rebuild step.
 * =============================================================================
 */

return [
    'run' => [
        'columns' => [
            ['header' => 'Quality',    'kind' => 'quality',    'field' => 'run_quality'],
            ['header' => 'Run Number', 'kind' => 'id',         'field' => 'run_number',  'class' => 'run-number', 'link' => 'run'],
            ['header' => 'Group',      'kind' => 'id',         'field' => 'run_group',   'class' => 'run-group',  'link' => 'group'],
            ['header' => 'Run Time',   'kind' => 'time_range', 'start' => 'run_start_datetime',   'end' => 'run_end_datetime',      'class' => 'run-time'],
            ['header' => 'Run Type',   'kind' => 'text',       'field' => 'run_type',    'class' => 'run-type'],
        ],
    ],
    'group' => [
        'columns' => [
            ['header' => 'Group', 'kind' => 'id', 'field' => 'group_number', 'class' => 'run-number', 'link' => 'group'],
            ['header' => 'Group Earliest/Latest Time', 'kind' => 'time_range', 'start' => 'group_start', 'end' => 'group_end', 'class' => 'run-time'],
            ['header' => 'Group Type', 'kind' => 'text', 'field' => 'group_type', 'class' => 'run-type'],
            ['header' => 'Quality', 'kind' => 'quality', 'field' => 'group_quality'],
        ],
    ],
];
