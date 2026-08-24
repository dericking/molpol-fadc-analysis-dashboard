<?php
/**
 * tests/run.php — lightweight unit harness (no PHPUnit / no vendor).
 *
 *   php tests/run.php
 *
 * Covers pure helpers in schema.php + render_helpers.php. Exit 0 on PASS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/schema.php';
require_once __DIR__ . '/../includes/render_helpers.php';

$failures = 0;
$passed = 0;

function assert_eq($expected, $actual, string $label): void
{
    global $failures, $passed;
    if ($expected === $actual) {
        $passed++;
        echo "ok  {$label}\n";
        return;
    }
    $failures++;
    echo "FAIL {$label}\n";
    echo "  expected: " . var_export($expected, true) . "\n";
    echo "  actual:   " . var_export($actual, true) . "\n";
}

function assert_true(bool $cond, string $label): void
{
    assert_eq(true, $cond, $label);
}

echo "Unit harness\n\n";

// --- dates -----------------------------------------------------------------
$iso = parse_stored_calendar_date('2026-07-31 16:54:21');
assert_eq('2026-07-31', $iso['key'] ?? null, 'ISO stamp → key');
assert_eq('Friday, July 31, 2026', $iso['label'] ?? null, 'ISO stamp → label');

$linux = parse_stored_calendar_date('Fri Jul 31 16:54:21 EDT 2026');
assert_eq('2026-07-31', $linux['key'] ?? null, 'Linux date → key');
assert_eq('Friday, July 31, 2026', $linux['label'] ?? null, 'Linux date → label');

$pad = parse_stored_calendar_date('Fri Jul  4 16:54:21 EDT 2026');
assert_eq('2026-07-04', $pad['key'] ?? null, 'Linux date space-padded day → key');

assert_eq(null, parse_stored_calendar_date(''), 'empty stamp → null');
assert_eq(null, parse_stored_calendar_date(null), 'null stamp → null');

assert_eq('2026-01-15', parse_ymd_query_param('2026-01-15'), 'ymd valid');
assert_eq(null, parse_ymd_query_param('2026-13-01'), 'ymd invalid month');
assert_eq(null, parse_ymd_query_param('notadate'), 'ymd garbage');

assert_eq('16:54:21', format_time_only('2026-07-31 16:54:21'), 'format_time_only ISO');
assert_eq('16:54:21', format_time_only('Fri Jul 31 16:54:21 EDT 2026'), 'format_time_only Linux');
assert_eq('—', format_time_only(null), 'format_time_only null');

$sql = sql_expr_stamp_as_date('r.run_start');
assert_true(str_contains($sql, '`r`.`run_start`'), 'sql_expr qualifies alias.column');
assert_true(str_contains($sql, 'STR_TO_DATE'), 'sql_expr has Linux branch');
assert_eq('NULL', sql_expr_stamp_as_date('r; drop'), 'sql_expr rejects junk identifier');

// --- schema comment helpers ------------------------------------------------
assert_eq(
    'Beam Current Average',
    parse_comment_label('Beam Current Average [PV: hac_bcm_average]', 'epics_bcm_avg'),
    'comment label strips [PV:]'
);
assert_eq(
    'halld:laser:power',
    parse_comment_pv('Hall D laser power [PV: halld:laser:power'),
    'PV tolerant of missing closing bracket'
);
assert_eq('hac_bcm_average', parse_comment_pv('x [PV: hac_bcm_average]'), 'PV normal');
assert_eq(null, parse_comment_pv('no pv here'), 'PV absent → null');
assert_eq('Bcm Avg', humanize_column_name('epics_bcm_avg'), 'humanize strips epics_');

// --- quality_slug (P0-2) ---------------------------------------------------
assert_eq('pending', quality_slug(null), 'quality null → pending');
assert_eq('pending', quality_slug(''), 'quality empty → pending');
assert_eq('good', quality_slug('GOOD'), 'quality GOOD → good');
assert_eq('unknown', quality_slug('NOT_GOOD'), 'quality NOT_GOOD → unknown');
assert_eq('unknown', quality_slug('weird!'), 'quality weird! → unknown');

// --- soft_wrap_commas / fmt_value (display wrap for CSV-like strings) -------
assert_eq('1, 2, 3', soft_wrap_commas('1,2,3'), 'commas get spaces');
assert_eq('1, 2, 3', soft_wrap_commas('1, 2,3'), 'mixed spacing normalized');
assert_eq('1, 2, 3', soft_wrap_commas('1,  2,   3'), 'extra spaces collapsed');
assert_eq('1, 2, 3', fmt_value('1,2,3'), 'fmt_value wraps commas');
assert_eq('—', fmt_value(null), 'fmt_value null');
assert_eq('—', fmt_value(''), 'fmt_value empty');

// --- classifiers (order matters) -------------------------------------------
assert_eq(
    'Pedestal & Channel Thresholds',
    classify_daq_column('fadc_tet'),
    'fadc_tet → Pedestal & Channel Thresholds'
);
assert_eq(
    'Channel Masks & Operating Modes',
    classify_daq_column('fadc_tet_ignore_mask'),
    'fadc_tet_ignore_mask not swallowed by fadc_tet rule'
);
assert_eq('Other', classify_daq_column('totally_unknown_col_xyz'), 'unknown DAQ → Other');

$grouped = group_columns_by_section(
    [
        ['name' => 'fadc_tet', 'label' => 'TET'],
        ['name' => 'zzz_lone', 'label' => 'Lone'],
    ],
    'classify_daq_column'
);
assert_true(isset($grouped['Pedestal & Channel Thresholds']), 'group has Pedestal section');
assert_true(isset($grouped['Other']), 'group has Other for unmatched');

// --- formatting / paths ----------------------------------------------------
assert_eq('/plots/runs/', plots_base_slash('/plots/runs'), 'plots_base_slash adds slash');
assert_eq('/plots/runs/', plots_base_slash('/plots/runs/'), 'plots_base_slash idempotent');
assert_eq('', plots_base_slash(''), 'plots_base_slash empty');

assert_eq('42', csv_safe_cell('42'), 'csv numeric untouched');
assert_eq('-1.5', csv_safe_cell('-1.5'), 'csv negative number untouched');
assert_eq("'=cmd", csv_safe_cell('=cmd'), 'csv formula prefix quoted');
assert_eq('hello', csv_safe_cell('hello'), 'csv plain text');

echo "\n";
if ($failures === 0) {
    echo "PASS ({$passed} assertions)\n";
    exit(0);
}
echo "FAIL ({$failures} failed, {$passed} passed)\n";
exit(1);
