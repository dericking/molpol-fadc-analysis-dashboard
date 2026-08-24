<?php
/**
 * includes/layouts/layout_report.php
 *
 * Column catalog for report.php / report_advanced.php (HTML table + CSV).
 *
 * =============================================================================
 * CARETAKER INSTRUCTIONS
 * =============================================================================
 *
 * File to edit: includes/layouts/layout_report.php only.
 *
 * Each of 'run' / 'group' has:
 *
 *   section_rows  Preferred. Rows of picker groups (null = empty slot).
 *                 Flattened into Advanced Available optgroups (title = section).
 *                 Each slot is a section:
 *                   'title'   => Available optgroup label
 *                   'columns' => fields offered in that group
 *                 Column keys: field, header,
 *                 source ('run'|'analysis'|'epics'|'daq'|'group'),
 *                 optional kind (id|quality|text), optional link (run|group).
 *   sections      Fallback: flat list of section arrays (same shape as a row).
 *   defaults      Field names used by the Simple Report Form when the URL omits
 *                 cols=. Membership only — default table order follows
 *                 section_rows / sections. Advanced users reorder via Selected
 *                 (posted as cols[] in list order).
 *
 * Only fields that exist on the live schema are offered at runtime.
 * Unknown names (wrong field or source) are skipped and surface as a soft
 * WARNING on the report pages — the page does not 500.
 * After editing, reload report.php or report_advanced.php (reset columns if a
 * sticky cols= URL is still in the address bar); no rebuild step.
 * =============================================================================
 */

return [
    'run' => [
        'defaults' => [
            'run_number',
            'run_group',
            'run_start',
            'run_end',
            'leftrate',
            'leftrate_err',
            'rightrate',
            'rightrate_err',
            'coinrate',
            'coinrate_err',
            //'accrate',
            //'accrate_err',
            'asym_mol',
            'asym_mol_err',
            'asym_q',
            'asym_q_err',
            'Azz',
            'poltarg',
            'pol_beam',
            'pol_beam_err',
        ],
        'section_rows' => [
            [
                [
                    'title' => 'Run identity',
                    'columns' => [
                        ['field' => 'run_number',     'header' => 'Run Number', 'source' => 'run', 'kind' => 'id', 'link' => 'run'],
                        ['field' => 'run_group',      'header' => 'Group',      'source' => 'run', 'kind' => 'id', 'link' => 'group'],
                        ['field' => 'run_type',       'header' => 'Run Type',   'source' => 'run'],
                    ],
                ],
                [
                    'title' => 'Detector rates',
                    'columns' => [
                        ['field' => 'leftrate',      'header' => 'Left rate',      'source' => 'analysis'],
                        ['field' => 'leftrate_err',  'header' => 'Left rate err',  'source' => 'analysis'],
                        ['field' => 'rightrate',     'header' => 'Right rate',     'source' => 'analysis'],
                        ['field' => 'rightrate_err', 'header' => 'Right rate err', 'source' => 'analysis'],
                        ['field' => 'coinrate',      'header' => 'Coin rate',      'source' => 'analysis'],
                        ['field' => 'coinrate_err',  'header' => 'Coin rate err',  'source' => 'analysis'],
                        ['field' => 'accrate',       'header' => 'Acc rate',       'source' => 'analysis'],
                        ['field' => 'accrate_err',   'header' => 'Acc rate err',   'source' => 'analysis'],
                        ['field' => 'counts_to_Hz',  'header' => 'Counts to Hz',   'source' => 'analysis'],
                    ],
                ],
                [
                    'title' => 'Asymmetry & polarization',
                    'columns' => [
                        ['field' => 'asym_mol',      'header' => 'Asym mol',     'source' => 'analysis'],
                        ['field' => 'asym_mol_err',  'header' => 'Asym mol err', 'source' => 'analysis'],
                        ['field' => 'asym_q',        'header' => 'Asym q',       'source' => 'analysis'],
                        ['field' => 'asym_q_err',    'header' => 'Asym q err',   'source' => 'analysis'],
                        ['field' => 'Azz',           'header' => 'Azz',          'source' => 'analysis'],
                        ['field' => 'poltarg',       'header' => 'Target pol',   'source' => 'analysis'],
                        ['field' => 'pol_beam',      'header' => 'Beam pol',     'source' => 'analysis'],
                        ['field' => 'pol_beam_err',  'header' => 'Beam pol err', 'source' => 'analysis'],
                    ],
                ],
                [
                    'title' => 'Timing & charge',
                    'columns' => [
                        ['field' => 'bcm',            'header' => 'BCM',           'source' => 'analysis'],
                        ['field' => 'clock100kHz',    'header' => 'Clock 100kHz',  'source' => 'analysis'],
                        ['field' => 'clock20MHz',     'header' => 'Clock 20MHz',   'source' => 'analysis'],
                        ['field' => 'deadtime_tau_1', 'header' => 'Deadtime tau1', 'source' => 'analysis'],
                        ['field' => 'deadtime_tau_2', 'header' => 'Deadtime tau2', 'source' => 'analysis'],
                        ['field' => 'accid_tau',      'header' => 'Accid tau',     'source' => 'analysis'],
                        ['field' => 'qpedused',       'header' => 'Qped used',     'source' => 'analysis'],
                        ['field' => 'qpedcalc',       'header' => 'Qped calc',     'source' => 'analysis'],
                    ],
                ],
                [
                    'title' => 'Other Information',
                    'columns' => [
                        ['field' => 'run_start',      'header' => 'Start',      'source' => 'run'],
                        ['field' => 'run_end',        'header' => 'End',        'source' => 'run'],
                        ['field' => 'run_quality',    'header' => 'Run Quality','source' => 'run', 'kind' => 'quality'],
                        ['field' => 'comment',        'header' => 'Comment',    'source' => 'run'],
                        ['field' => 'run_experiment', 'header' => 'Experiment', 'source' => 'run'],
                    ],
                ],
            ],
            [
                [
                    'title' => 'EPICS Laser & Wien',
                    'columns' => [
                        ['field' => 'epics_ihwp',         'header' => 'iHWP',           'source' => 'epics'],
                        ['field' => 'epics_rhwp',         'header' => 'rHWP',           'source' => 'epics'],
                        ['field' => 'epics_vwien_angle',  'header' => 'V Wien angle',   'source' => 'epics'],
                        ['field' => 'epics_hwien_angle',  'header' => 'H Wien angle',   'source' => 'epics'],
                        ['field' => 'epics_sol_phi_fg',   'header' => 'Sol angle',      'source' => 'epics'],
                    ],
                ],
                [
                    'title' => 'EPICS Helicity',
                    'columns' => [
                        ['field' => 'epics_hel_pattern', 'header' => 'Pattern',  'source' => 'epics'],
                        ['field' => 'epics_hel_freq',    'header' => 'Freq',     'source' => 'epics'],
                        ['field' => 'epics_t_settle',    'header' => 'T settle', 'source' => 'epics'],
                        ['field' => 'epics_t_stable',    'header' => 'T stable', 'source' => 'epics'],
                    ],
                ],
                [
                    'title' => 'EPICS Beam',
                    'columns' => [
                        ['field' => 'epics_E_beam',  'header' => 'Beam energy', 'source' => 'epics'],
                        ['field' => 'epics_n_pass',  'header' => 'Passes',      'source' => 'epics'],
                        ['field' => 'epics_bcm_avg', 'header' => 'BCM avg',     'source' => 'epics'],
                    ],
                ],
                null,
                null,
            ],
        ],
    ],

    'group' => [
        'defaults' => [
            'group_number',
            'group_type',
            'group_quality',
            'group_start',
            'group_end',
            'asym_mol',
            'asym_mol_err',
            'pol_beam',
            'pol_beam_err',
            'epics_ihwp',
            'epics_wien',
        ],
        'sections' => [
            [
                'title' => 'Group identity',
                'columns' => [
                    ['field' => 'group_number',  'header' => 'Group',      'source' => 'group', 'kind' => 'id', 'link' => 'group'],
                    ['field' => 'group_type',    'header' => 'Group Type', 'source' => 'group'],
                    ['field' => 'group_comment', 'header' => 'Comment',    'source' => 'group'],
                ],
            ],
            [
                'title' => 'Asymmetry & polarization',
                'columns' => [
                    ['field' => 'asym_mol',     'header' => 'Asym mol',     'source' => 'group'],
                    ['field' => 'asym_mol_err', 'header' => 'Asym mol err', 'source' => 'group'],
                    ['field' => 'pol_beam',     'header' => 'Beam pol',     'source' => 'group'],
                    ['field' => 'pol_beam_err', 'header' => 'Beam pol err', 'source' => 'group'],
                ],
            ],
            [
                'title' => 'EPICS states',
                'columns' => [
                    ['field' => 'epics_ihwp', 'header' => 'iHWP', 'source' => 'group'],
                    ['field' => 'epics_wien', 'header' => 'Wien', 'source' => 'group'],
                ],
            ],
            [
                'title' => 'Other Information',
                'columns' => [
                    ['field' => 'group_start',   'header' => 'Start',      'source' => 'group'],
                    ['field' => 'group_end',     'header' => 'End',        'source' => 'group'],
                    ['field' => 'group_quality', 'header' => 'Group Quality',    'source' => 'group'],
                    ['field' => 'group_comment', 'header' => 'Comment',    'source' => 'group'],
                ],
            ],
        ],
    ],
];
