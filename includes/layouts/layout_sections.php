<?php
/**
 * includes/layouts/layout_sections.php
 *
 * Column classifiers + detail-page card-row layouts for Analysis, EPICS_data,
 * DAQ_config, and Grouped_Analysis.
 *
 * =============================================================================
 * CARETAKER INSTRUCTIONS
 * =============================================================================
 *
 * Edit this file to change which columns land in which section, and how those
 * sections are arranged on the detail pages. Do not put URLs or DB credentials
 * here.
 *
 * Each table key (analysis / epics / daq / grouped_analysis) has:
 *
 *   exclude       Column names omitted from cards (PK, last_updated, comments…).
 *   classifier    Ordered rules; first match wins. Unmatched → section "Other".
 *     match prefix  — key is the part before the first "_" (strstr).
 *     match regex   — pattern is a PHP preg pattern.
 *   featured      Ordered list of section titles for full-width featured rows.
 *                 Prefer an array even for a single section (clearer when
 *                 adding a second later). Use null for none:
 *
 *                   'featured' => [
 *                       'Asymmetries & Polarization',
 *                   ],
 *
 *                   'featured' => [
 *                       'Asymmetries & Polarization',
 *                       'EPICS States',
 *                   ],
 *
 *                   'featured' => null,
 *
 *                 A bare string is still accepted. Do not also list those
 *                 sections in layouts bands.
 *   layouts       Named card-layout bands. Convention: 'main' (always), and
 *                 'other' when the page inserts a mid-header or second block.
 *                 Each band is rows of section titles (left → right). Use null
 *                 as a spacer. Pages call render_section_cards($pack, 'main').
 *                 Do not use the band name 'unallocated' — the engine reserves
 *                 it for classifier sections not listed in featured/layouts
 *                 (shown via render_unallocated_sections(), ≤4 cards per row).
 *   ignore_sections  Optional list of classifier section titles to omit from
 *                 the unallocated band only. If the same title is also listed
 *                 in featured or a layouts band, it still renders there —
 *                 ignore_sections does not hide placed sections.
 *   duplicate_into  Optional: copy named columns into another section as well.
 *
 * featured / layouts strings MUST match a classifier "section" value exactly.
 * Anything not listed (and not in ignore_sections) still renders under
 * "Unallocated Sections" on the page (rows of at most 4 cards), so new
 * columns never silently vanish.
 *
 * After editing, reload the detail page; no rebuild step.
 * =============================================================================
 */

return [
    // --- Analysis (detail_runs.php) — Don's column names (regex; no site prefix) ---
    'analysis' => [
        'exclude' => ['run_number', 'last_updated'],
        'classifier' => [
            // Old site-prefix convention (rate_ / clk_ / dt_ / chg_ / pol_):
            // ['match' => 'prefix', 'key' => 'rate', 'section' => 'Event Rates & Scalers'],
            // ['match' => 'prefix', 'key' => 'clk',  'section' => 'Clock Scalers'],
            // ['match' => 'prefix', 'key' => 'dt',   'section' => 'Deadtime & Accidentals'],
            // ['match' => 'prefix', 'key' => 'chg',  'section' => 'Charge Monitor & Asymmetry'],
            // ['match' => 'prefix', 'key' => 'pol',  'section' => 'Asymmetries & Polarization'],

            ['match' => 'regex', 'pattern' => '/^(left|right|coin|acc)rate(_err)?$/', 'section' => 'Event Rates & Scalers'],
            ['match' => 'regex', 'pattern' => '/^counts_to_Hz$/',                   'section' => 'Event Rates & Scalers'],

            ['match' => 'regex', 'pattern' => '/^(clock100kHz|clock20MHz)$/',        'section' => 'Diagnostics & Timing'],
            ['match' => 'regex', 'pattern' => '/^deadtime_tau_[12]$/',               'section' => 'Diagnostics & Timing'],
            ['match' => 'regex', 'pattern' => '/^accid_tau$/',                       'section' => 'Diagnostics & Timing'],

            ['match' => 'regex', 'pattern' => '/^asym_mol(_err)?$/',                 'section' => 'Asymmetries & Polarization Parameters'],
            ['match' => 'regex', 'pattern' => '/^(Azz|poltarg)$/',                   'section' => 'Asymmetries & Polarization Parameters'],
            ['match' => 'regex', 'pattern' => '/^pol_beam(_err)?$/',                 'section' => 'Asymmetries & Polarization Parameters'],

            ['match' => 'regex', 'pattern' => '/^bcm$/',                             'section' => 'Charge Asymmetry & Pedestals'],
            ['match' => 'regex', 'pattern' => '/^asym_q(_err)?$/',                   'section' => 'Charge Asymmetry & Pedestals'],
            ['match' => 'regex', 'pattern' => '/^qped(used|calc)$/',                 'section' => 'Charge Asymmetry & Pedestals'],
        ],
        // Featured rows (array of section titles; one entry = one featured row).
        'featured' => [
            'Asymmetries & Polarization Parameters',
        ],
        'layouts' => [
            'main' => [
                ['Event Rates & Scalers', 'Diagnostics & Timing', 'Charge Asymmetry & Pedestals', null],
            ],
        ],
    ],

    // --- Grouped_Analysis (detail_groups.php) — prefix convention ---
    'grouped_analysis' => [
        'exclude' => ['group_number', 'last_updated', 'group_comment'],
        'classifier' => [
            ['match' => 'prefix', 'key' => 'group', 'section' => 'Group Information'],
            ['match' => 'prefix', 'key' => 'asym',  'section' => 'Asymmetries & Polarization'],
            ['match' => 'prefix', 'key' => 'pol',   'section' => 'Asymmetries & Polarization'],
            ['match' => 'prefix', 'key' => 'epics', 'section' => 'EPICS States'],
        ],
        // Featured rows (array of section titles; one entry = one featured row).
        'featured' => [
            'Asymmetries & Polarization',
        ],
        'layouts' => [
            'main' => [
                ['Group Information', 'EPICS States'],
            ],
        ],
    ],

    // --- EPICS_data (detail_epics.php) — Don’s table comment groups ---
    'epics' => [
        'exclude' => ['run_number', 'last_updated'],
        'classifier' => [
            ['match' => 'regex', 'pattern' => '/^epics_E_/',       'section' => 'Accelerator & Beam Energy'],
            ['match' => 'regex', 'pattern' => '/^epics_n_pass$/',  'section' => 'Accelerator & Beam Energy'],
            ['match' => 'regex', 'pattern' => '/^epics_bcm_/',     'section' => 'Beam Current Monitors (BCMs) & Unsers'],
            ['match' => 'regex', 'pattern' => '/^epics_unser$/',   'section' => 'Beam Current Monitors (BCMs) & Unsers'],
            ['match' => 'regex', 'pattern' => '/^epics_inj_bcm/',  'section' => 'Beam Current Monitors (BCMs) & Unsers'],
            ['match' => 'regex', 'pattern' => '/^epics_bpm/',      'section' => 'Beam Position Monitors (BPMs)'],
            ['match' => 'regex', 'pattern' => '/^epics_q\d/',      'section' => 'Beamline Magnets'],
            ['match' => 'regex', 'pattern' => '/^epics_dip_/',     'section' => 'Beamline Magnets'],
            ['match' => 'regex', 'pattern' => '/^epics_mcz/',      'section' => 'Beamline Magnets'],
            ['match' => 'regex', 'pattern' => '/^epics_tgt_/',     'section' => 'Target System'],
            ['match' => 'regex', 'pattern' => '/^epics_target$/',  'section' => 'Target System'],
            ['match' => 'regex', 'pattern' => '/^epics_las_/',     'section' => 'Injector Laser & Slits'],
            ['match' => 'regex', 'pattern' => '/^epics_slit_/',    'section' => 'Injector Laser & Slits'],
            ['match' => 'regex', 'pattern' => '/^epics_pockels_/', 'section' => 'Injector Laser & Slits'],
            ['match' => 'regex', 'pattern' => '/^epics_ihwp$/',    'section' => 'Wien Filters & Waveplates'],
            ['match' => 'regex', 'pattern' => '/^epics_rhwp$/',    'section' => 'Wien Filters & Waveplates'],
            ['match' => 'regex', 'pattern' => '/^epics_vwien_/',   'section' => 'Wien Filters & Waveplates'],
            ['match' => 'regex', 'pattern' => '/^epics_sol_/',     'section' => 'Wien Filters & Waveplates'],
            ['match' => 'regex', 'pattern' => '/^epics_hwien_/',   'section' => 'Wien Filters & Waveplates'],
            ['match' => 'regex', 'pattern' => '/^epics_hel_/',     'section' => 'Helicity Board Settings'],
            ['match' => 'regex', 'pattern' => '/^epics_t_settle$/','section' => 'Helicity Board Settings'],
            ['match' => 'regex', 'pattern' => '/^epics_t_stable$/','section' => 'Helicity Board Settings'],
            ['match' => 'regex', 'pattern' => '/^epics_mol_/',     'section' => 'Superconducting Solenoid Magnet'],
            ['match' => 'regex', 'pattern' => '/^epics_cryo_/',    'section' => 'Cryomech Compressor System'],
            ['match' => 'regex', 'pattern' => '/^epics_det_hv/',   'section' => 'Detector High Voltage Readbacks'],
        ],
        'featured' => [
            'Accelerator & Beam Energy',
        ],
        'layouts' => [
            'main' => [
                ['Wien Filters & Waveplates', 'Beam Current Monitors (BCMs) & Unsers', 'Beam Position Monitors (BPMs)', 'Beamline Magnets'],
            ],
            'other' => [
                ['Target System', 'Injector Laser & Slits', 'Helicity Board Settings', 'Detector High Voltage Readbacks'],
                ['Superconducting Solenoid Magnet', 'Cryomech Compressor System', null, null],
            ],
        ],
    ],

    // --- DAQ_config (detail_daq.php) — Don’s table comment groups (suggested until confirmed) ---
    'daq' => [
        'exclude' => ['run_number', 'last_updated'],
        'classifier' => [
            // Crate & Slot Identification
            ['match' => 'regex', 'pattern' => '/^fadc_crate$/',                      'section' => 'Crate & Slot Identification'],
            ['match' => 'regex', 'pattern' => '/^fadc_slot$/',                       'section' => 'Crate & Slot Identification'],

            // Channel Masks & Operating Modes
            ['match' => 'regex', 'pattern' => '/^fadc_adc_mask$/',                   'section' => 'Channel Masks & Operating Modes'],
            ['match' => 'regex', 'pattern' => '/^fadc_trg_mask$/',                   'section' => 'Channel Masks & Operating Modes'],
            ['match' => 'regex', 'pattern' => '/^fadc_tet_ignore_mask$/',            'section' => 'Channel Masks & Operating Modes'],
            ['match' => 'regex', 'pattern' => '/^fadc_allch_mode$/',                 'section' => 'Channel Masks & Operating Modes'],

            // Windowing & Timing Definitions
            ['match' => 'regex', 'pattern' => '/^fadc_allch_w_offset$/',             'section' => 'Windowing & Timing'],
            ['match' => 'regex', 'pattern' => '/^fadc_allch_w_width$/',              'section' => 'Windowing & Timing'],
            ['match' => 'regex', 'pattern' => '/^fadc_allch_nsb$/',                  'section' => 'Windowing & Timing'],
            ['match' => 'regex', 'pattern' => '/^fadc_allch_nsa$/',                  'section' => 'Windowing & Timing'],
            ['match' => 'regex', 'pattern' => '/^fadc_w_offset$/',                   'section' => 'Windowing & Timing'],
            ['match' => 'regex', 'pattern' => '/^fadc_w_width$/',                    'section' => 'Windowing & Timing'],
            ['match' => 'regex', 'pattern' => '/^fadc_nsb$/',                        'section' => 'Windowing & Timing'],
            ['match' => 'regex', 'pattern' => '/^fadc_nsa$/',                        'section' => 'Windowing & Timing'],

            // Peak Processing & Pedestal Limits
            ['match' => 'regex', 'pattern' => '/^fadc_allch_npeak$/',                'section' => 'Peak Processing & Pedestal Limits'],
            ['match' => 'regex', 'pattern' => '/^fadc_allch_maxped$/',               'section' => 'Peak Processing & Pedestal Limits'],
            ['match' => 'regex', 'pattern' => '/^fadc_allch_nsat$/',                 'section' => 'Peak Processing & Pedestal Limits'],
            ['match' => 'regex', 'pattern' => '/^fadc_npeak$/',                      'section' => 'Peak Processing & Pedestal Limits'],
            ['match' => 'regex', 'pattern' => '/^fadc_maxped$/',                     'section' => 'Peak Processing & Pedestal Limits'],
            ['match' => 'regex', 'pattern' => '/^fadc_nsat$/',                       'section' => 'Peak Processing & Pedestal Limits'],

            // DAC, Gain & Accumulator Configuration
            ['match' => 'regex', 'pattern' => '/^fadc_dac$/',                        'section' => 'DAC, Gain & Accumulator'],
            ['match' => 'regex', 'pattern' => '/^fadc_gain$/',                       'section' => 'DAC, Gain & Accumulator'],
            ['match' => 'regex', 'pattern' => '/^fadc_accumulator_scaler_mode_mask$/','section' => 'DAC, Gain & Accumulator'],

            // Møller Discriminator & Trigger Logic Settings
            ['match' => 'regex', 'pattern' => '/^fadc_l_offset$/',                   'section' => 'Møller Discriminator & Trigger Logic'],
            ['match' => 'regex', 'pattern' => '/^fadc_r_offset$/',                   'section' => 'Møller Discriminator & Trigger Logic'],
            ['match' => 'regex', 'pattern' => '/^fadc_disc_width$/',                 'section' => 'Møller Discriminator & Trigger Logic'],
            ['match' => 'regex', 'pattern' => '/^fadc_disc_mode$/',                  'section' => 'Møller Discriminator & Trigger Logic'],
            ['match' => 'regex', 'pattern' => '/^fadc_l_sum_thr$/',                  'section' => 'Møller Discriminator & Trigger Logic'],
            ['match' => 'regex', 'pattern' => '/^fadc_r_sum_thr$/',                  'section' => 'Møller Discriminator & Trigger Logic'],
            ['match' => 'regex', 'pattern' => '/^fadc_trg_sel$/',                    'section' => 'Møller Discriminator & Trigger Logic'],
            ['match' => 'regex', 'pattern' => '/^fadc_trg_width$/',                  'section' => 'Møller Discriminator & Trigger Logic'],

            // Pedestal & Channel Threshold
            ['match' => 'regex', 'pattern' => '/^fadc_allch_ped$/',                  'section' => 'Pedestal & Channel Thresholds'],
            ['match' => 'regex', 'pattern' => '/^fadc_ped$/',                        'section' => 'Pedestal & Channel Thresholds'],
            ['match' => 'regex', 'pattern' => '/^fadc_allch_tet$/',                  'section' => 'Pedestal & Channel Thresholds'],
            ['match' => 'regex', 'pattern' => '/^fadc_tet$/',                        'section' => 'Pedestal & Channel Thresholds'],
        ],
        'featured' => [
            'Crate & Slot Identification',
        ],
        'layouts' => [
            'main' => [
                [
                    'Channel Masks & Operating Modes',
                    'Windowing & Timing',
                    'Peak Processing & Pedestal Limits',
                    'DAC, Gain & Accumulator',
                ],
            ],
            'other' => [
                [
                    'Møller Discriminator & Trigger Logic',
                    'Pedestal & Channel Thresholds',
                    null,
                    null,
                ],
            ],
        ],
    ],
];
