<?php
/**
 * includes/layouts/layout_run_summary.php
 *
 * Run Info / Group Info modals for Run_info and Grouped_Analysis rows.
 *
 * =============================================================================
 * CARETAKER INSTRUCTIONS
 * =============================================================================
 *
 * File to edit: includes/layouts/layout_run_summary.php only.
 * Rendering / links: render_run_summary() in includes/render_helpers.php.
 *
 * --- Structure ---
 *   'run' / 'group' => [
 *     'rows'   => [ row, row, ... ],   // each row = cells left → right
 *     'footer' => [ cell, ... ],       // optional full-width trailers
 *   ]
 *
 * --- Cell kinds ---
 *   text        Plain value. Keys: field, label
 *   quality     Quality as colored text (not the list pill). Keys: field, label
 *   id          Identifier; optional link => 'run'|'group'. Keys: field, label, link?
 *   time_range  Full start → end stamps. Keys: start, end, label
 *               (empty sides use page-supplied empty labels)
 *   comment     Multiline note (usually in footer). Keys: field, label
 *               Omitted from output when the field is empty.
 *
 * --- Example: add requested beam current on its own row (run) ---
 *
 *   [
 *     ['kind' => 'text', 'field' => 'requested_current', 'label' => 'Requested current'],
 *   ],
 *
 * After editing, reload detail_runs.php or detail_groups.php; no rebuild step.
 * =============================================================================
 */

return [
    'run' => [
        'rows' => [
            [
                ['kind' => 'text', 'field' => 'run_type', 'label' => 'Type'],
                ['kind' => 'quality', 'field' => 'run_quality', 'label' => 'Quality'],
                ['kind' => 'id', 'field' => 'run_group', 'label' => 'Group', 'link' => 'group'],
            ],
            [
                ['kind' => 'text', 'field' => 'run_experiment', 'label' => 'Experiment'],
                ['kind' => 'time_range', 'start' => 'run_start', 'end' => 'run_end', 'label' => 'Run time'],
            ],
        ],
        'footer' => [
            ['kind' => 'comment', 'field' => 'comment', 'label' => 'Comment'],
        ],
    ],
    'group' => [
        'rows' => [
            [
                ['kind' => 'text', 'field' => 'group_type', 'label' => 'Type'],
                ['kind' => 'quality', 'field' => 'group_quality', 'label' => 'Quality'],
            ],
            [
                ['kind' => 'time_range', 'start' => 'group_start', 'end' => 'group_end', 'label' => 'Group time'],
            ],
        ],
        'footer' => [
            ['kind' => 'comment', 'field' => 'group_comment', 'label' => 'Comment'],
        ],
    ],
];
