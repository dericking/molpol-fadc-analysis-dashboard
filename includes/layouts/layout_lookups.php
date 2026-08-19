<?php
/**
 * includes/layouts/layout_lookups.php
 *
 * Maps row columns to shared lookup tables (type / quality codes).
 *
 * =============================================================================
 * CARETAKER INSTRUCTIONS
 * =============================================================================
 *
 * File to edit: includes/layouts/layout_lookups.php only.
 *
 * The database stores a short `code` on Run_info / Grouped_Analysis.
 * Display English lives in the lookup as `display_label`. This file does
 * not list those labels — the site reads them at request time.
 *
 * --- columns ---
 * Row field name => lookup table. Same table can be shared (run_type and
 * group_type both use run_type_lookup). Note that run and group types are 
 * now using the same lookup table.
 *
 * --- tables ---
 * Lookup table => code / label column names (defaults: code, display_label).
 *
 * --- quality_slugs ---
 * Lookup *code* => CSS suffix (quality-tag-{slug}). Not display labels.
 * Unknown codes fall back to strtolower(code) if it is [a-z]+, else pending.
 *
 * After editing, reload the page; no rebuild step.
 * =============================================================================
 */

return [
    'tables' => [
        'run_type_lookup' => [
            'code'  => 'code',
            'label' => 'display_label',
        ],
        'run_quality_lookup' => [
            'code'  => 'code',
            'label' => 'display_label',
        ],
    ],
    'columns' => [
        'run_type'      => 'run_type_lookup',
        'group_type'    => 'run_type_lookup',
        'run_quality'   => 'run_quality_lookup',
        'group_quality' => 'run_quality_lookup',
    ],
    'quality_slugs' => [
        'GOOD'    => 'good',
        'BAD'     => 'bad',
        'SUSPECT' => 'suspect',
        'PENDING' => 'pending',
        'JUNK'    => 'junk',
        // Pre-lookup ENUM values (display English stored on the row).
        'Good'    => 'good',
        'Bad'     => 'bad',
        'Suspect' => 'suspect',
        'Pending' => 'pending',
        'Junk'    => 'junk',
    ],
];
