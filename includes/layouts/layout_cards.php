<?php
/**
 * List-card layouts for the run/group index (and run cards on detail_groups).
 *
 * =============================================================================
 * CARETAKER INSTRUCTIONS
 * =============================================================================
 *
 * File to edit: includes/layouts/layout_cards.php only.
 *
 * Do not edit for layout: index.php, detail_groups.php, or URL/href logic.
 * Optional link (no URL — app resolves it), same as tables:
 *   'link' => 'run'    → detail_runs.php?run={field value}
 *   'link' => 'group'  → detail_groups.php?group={field value}
 * Empty field values stay unlinked ("—"). Only that cell is a link; the
 * rest of the card is not, so a second linked cell can be added later.
 *
 * --- Turn off the quality border ---
 * (Removed from the default layouts — quality is shown via the quality tag
 * only. The optional quality_border key is no longer used here.)
 *
 * --- Add or reorder a row ---
 * Append or reorder entries under rows. Each row is an array of cells
 * left-to-right.
 *
 * --- Add a cell ---
 * Supported kinds:
 *
 *   id         Big identifier. Keys: field, optional prefix, optional class,
 *              optional link.
 *   quality    Colored quality pill. Keys: field.
 *   text       Plain text. Keys: field, optional class.
 *   time_range Start–end times. Keys: start, end, optional class.
 *   value_err  "value ± error". Keys: field (uses field + field_err).
 *
 * Prefix (optional, id cells only) — prepended to the field value:
 *
 *   ['kind' => 'id', 'field' => 'run_number', 'prefix' => 'R', 'class' => 'run-number']
 *   // displays as R20001
 *
 *   ['kind' => 'id', 'field' => 'group_number', 'prefix' => 'G', 'class' => 'run-number']
 *   // displays as G12
 *
 * Omit prefix to show the bare number (the default below).
 *
 * Example — third row with comment:
 *
 *   [
 *     ['kind' => 'text', 'field' => 'run_comment', 'class' => 'run-comment'],
 *   ],
 *
 * --- Fields and queries ---
 * Index pages use SELECT * from Run_info (runs) or Grouped_Analysis (groups),
 * so any real column on those tables is available to the layout. Prefer
 * columns that already live on the row (e.g. group_start / group_end) over
 * synthetic PHP-assembled names. Missing values still show "—".
 *
 * --- Run vs group ---
 *   run   layout → index Runs view + run cards on detail_groups.php
 *   group layout → index Groups view
 *
 * After editing, reload the index; no rebuild step.
 * =============================================================================
 */

return [
    'run' => [
        'rows' => [
            [
                ['kind' => 'id', 'field' => 'run_number', 'prefix' => 'Run ', 'class' => 'run-number', 'link' => 'run'],
                ['kind' => 'quality', 'field' => 'run_quality'],
            ],
            [
                ['kind' => 'text', 'field' => 'run_type', 'class' => 'run-type'],
                ['kind' => 'time_range', 'start' => 'run_start_datetime', 'end' => 'run_end_datetime', 'class' => 'run-time'],
            ],
        ],
    ],
    'group' => [
        'rows' => [
            [
                ['kind' => 'id', 'field' => 'group_number', 'prefix' => 'Group ', 'class' => 'run-number', 'link' => 'group'],
                ['kind' => 'quality', 'field' => 'group_quality'],
            ],
            [
                ['kind' => 'text', 'field' => 'group_type', 'class' => 'run-type'],
                ['kind' => 'time_range', 'start' => 'group_start', 'end' => 'group_end', 'class' => 'run-time'],
            ],
        ],
    ],
];
