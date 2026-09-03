<?php
/**
 * seed_junk_data.php
 *
 * Populates the local Docker test database with fake runs across all
 * four tables, organized by calendar day. Run from the command line:
 *
 *   SITE_DB_PORT=3307 SITE_DB_USER=root SITE_DB_PASS=changeme \
 *     php seed_junk_data.php [min_runs]
 *
 * The optional argument is a run-count target (default 30). Whole days are
 * generated until the total number of inserted runs exceeds that target —
 * e.g. `60` keeps adding days until totalRuns > 60.
 *
 * Uses root (or another write-capable user) — the site's SELECT-only user
 * cannot INSERT by design (see docker/init/02_privileges.sql).
 *
 * Each day always gets 2 Polarization runs with opposite-sign raw
 * asymmetries. Independently and at random, a day may also include one
 * Systematic study, a Rate scan of 4–5 sequentially numbered runs, and/or
 * a Test run. Some Grouped_Analysis rows are typed MIXED.
 *
 * run_group is sequential across the whole seed: each Polarization run
 * gets its own group (opposite-sign pair = two groups — sign change
 * reflects a different iHWP/Wien combination), each Systematic study gets
 * its own unique group, and every Rate scan run on that day shares one group.
 *
 * run_type / run_quality / group_type / group_quality are lookup *codes*
 * (POLARIZATION, GOOD, MIXED, …), not display English. Analysis uses
 * generate_analysis_overrides() with Don’s column names (leftrate, asym_mol,
 * Azz, …). DAQ / EPICS get light domain overrides; remaining columns stay
 * schema-generic. After all runs, Grouped_Analysis gets one row per
 * run_group with error-weighted asym/pol averages and cycled iHWP/Wien
 * enums. Unknown columns fall through to generic fillers.
 */

require_once __DIR__ . '/../includes/schema.php';

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

$targetRuns = isset($argv[1]) ? max(1, (int)$argv[1]) : 30;

$dbHost = getenv('SITE_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('SITE_DB_PORT') ?: '3307'; // defaults to the Docker test port
$dbName = getenv('SITE_DB_NAME') ?: 'app_db';
$dbUser = getenv('SITE_DB_USER') ?: 'root';
$dbPass = getenv('SITE_DB_PASS') ?: 'changeme';

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Columns that should never get a generated value: primary key (handled
// explicitly per run) and any auto-managed timestamp.
$SKIP_COLUMNS = array('run_number', 'last_updated');

/**
 * PHP 5.4-safe random_int (test seed only).
 */
function seed_random_int($min, $max)
{
    return mt_rand((int)$min, (int)$max);
}

function seed_pick($choices)
{
    return $choices[seed_random_int(0, count($choices) - 1)];
}

/**
 * PHP 5.4-safe random_bytes (test seed only).
 */
function seed_random_bytes($length)
{
    $length = (int)$length;
    if ($length <= 0) {
        return '';
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return openssl_random_pseudo_bytes($length);
    }
    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr(mt_rand(0, 255));
    }
    return $bytes;
}

function rand_float($lo, $hi){
    return $lo + (mt_rand(0, 1000000) / 1000000) * ($hi - $lo);
}

/** Positive absolute error = |value| * uniform(pctLo, pctHi) / 100. */
function pct_of($value, $pctLo, $pctHi){
    return abs($value) * rand_float($pctLo, $pctHi) / 100.0;
}

/** Positive value near $nominal with ±$pctPercent relative scatter. */
function jitter($nominal, $pctPercent){
    $frac = $pctPercent / 100.0;
    return $nominal * (1.0 + rand_float(-$frac, $frac));
}

/** Linux `date` style string, matching production Run_info VARCHAR stamps. */
function format_linux_date($ts){
    return date('D M j H:i:s T Y', $ts);
}

function random_value_for_column($col)
{
    $type   = $col['type'];
    $ctype  = $col['column_type'];
    $name   = $col['name'];

    // FK codes — never invent a random VARCHAR that would fail the lookup.
    if (in_array($name, ['run_type', 'group_type'], true)) {
        return 'OTHER';
    }
    if (in_array($name, ['run_quality', 'group_quality'], true)) {
        return 'PENDING';
    }

    // ENUM: parse the option list out of COLUMN_TYPE, e.g. "enum('IN','OUT')"
    if ($type === 'enum' && preg_match_all("/'([^']*)'/", $ctype, $m)) {
        $options = $m[1];
        return $options[array_rand($options)];
    }

    // Physically bounded quantities — domain physics, not just FLOAT(M,D).
    // Analysis / DAQ / EPICS coherent fields use the override helpers below.
    $boundedRanges = [
        'target_pol'         => [0.05, 0.12],
        'target_foil_avgt'   => [280.0, 320.0],
        'requested_current'  => [0.5, 5.0],
        'beam_sigma_x'       => [0.05, 0.35],
        'beam_sigma_y'       => [0.05, 0.35],
    ];
    $lookupKey = strtolower($name);
    if (array_key_exists($lookupKey, $boundedRanges)) {
        list($lo, $hi) = $boundedRanges[$lookupKey];
        $decimals = 4;
        if (preg_match('/\((\d+),(\d+)\)/', $ctype, $m)) {
            $decimals = (int)$m[2];
        }
        $value = rand_float($lo, $hi);
        return round($value, min($decimals, 10));
    }

    // Trigger prescales: -1 disabled, 0 none, small positive keep-every-Nth.
    if (preg_match('/^trig_ps\d+$/i', $name)) {
        $choices = [-1, 0, 0, 1, 2, 3];
        return $choices[array_rand($choices)];
    }

    // Linux-'date'-style free-text start/end timestamps (VARCHAR, not DATETIME)
    if (in_array($name, array('run_start_datetime', 'run_end_datetime', 'run_start', 'run_end'), true)) {
        return format_linux_date(seed_random_int(strtotime('2026-01-01'), strtotime('2026-12-31')));
    }
    if (in_array($name, array('run_start_unix', 'run_end_unix'), true)) {
        return seed_random_int(strtotime('2026-01-01'), strtotime('2026-12-31'));
    }

    if ($type === 'datetime' || $type === 'timestamp') {
        return date('Y-m-d H:i:s', seed_random_int(strtotime('2026-01-01'), strtotime('2026-12-31')));
    }

    if (in_array($type, ['int', 'smallint', 'mediumint', 'bigint', 'tinyint'], true)) {
        // TINYINT(1) is conventionally boolean-ish in this schema
        if ($type === 'tinyint' && (strpos($ctype, '(1)') !== false)) {
            return seed_random_int(0, 1);
        }
        // Pedestal / TET maxped are 0–1023; nsat is 1–4 when set.
        if (preg_match('/maxped$/i', $name)) {
            return seed_random_int(40, 120);
        }
        if (preg_match('/nsat$/i', $name)) {
            return seed_random_int(1, 4);
        }
        if (preg_match('/npeak$/i', $name)) {
            return seed_random_int(1, 4);
        }
        $unsigned = (strpos($ctype, 'unsigned') !== false);
        if ($type === 'tinyint') {
            $max = 120;
        } elseif ($type === 'smallint') {
            $max = 5000;
        } else {
            $max = 100000;
        }
        return $unsigned ? seed_random_int(0, $max) : seed_random_int(-$max, $max);
    }

    if (in_array($type, ['float', 'double', 'decimal'], true)) {
        // Respect the column's FULL declared precision (M,D), not just
        // decimal places — e.g. FLOAT(7,6) allows only ~1 digit before
        // the decimal point (max magnitude just under 10).
        $decimals = 4;
        $maxMagnitude = 1000.0; // sane default for DOUBLE / unspecified precision
        if (preg_match('/\((\d+),(\d+)\)/', $ctype, $m)) {
            $precision = (int)$m[1];
            $decimals  = (int)$m[2];
            $intDigits = max(1, $precision - $decimals);
            $maxMagnitude = pow(10, $intDigits) * 0.9;
        }
        $value = (mt_rand(-1000000, 1000000) / 1000000) * $maxMagnitude;
        return round($value, min($decimals, 10));
    }

    if (in_array($type, ['varchar', 'char', 'text', 'mediumtext'], true)) {
        if (strcasecmp($name, 'epics_ihwp') === 0) {
            return seed_random_int(0, 1) ? 'IN' : 'OUT';
        }
        if (preg_match('/^epics_hel_pattern$/i', $name)) {
            return seed_pick(array('pair', 'quartet', 'octet'));
        }
        if (preg_match('/^epics_n_pass$/i', $name)) {
            return (string) seed_random_int(1, 5);
        }
        if (preg_match('/^epics_las_mode_/i', $name)) {
            return seed_pick(array('CW', 'Pulsed', 'OFF'));
        }
        if (preg_match('/^fadc_(crate|slot)$/i', $name)) {
            return $name === 'fadc_crate' || substr(strtolower($name), -5) === 'crate'
                ? 'vme-crate.example'
                : (string) seed_random_int(3, 8);
        }
        // Pedestal / TET lists: comma-separated channel values.
        if (preg_match('/(allch_ped|^fadc_ped$)/i', $name) || stripos($col['comment'], 'Pedestal') !== false) {
            $vals = [];
            for ($i = 0; $i < 16; $i++) {
                $vals[] = (string) round(rand_float(40, 90), 1);
            }
            return implode(',', $vals);
        }
        if (preg_match('/(allch_tet|^fadc_tet$)/i', $name) || stripos($col['comment'], 'threshold') !== false) {
            $vals = [];
            for ($i = 0; $i < 16; $i++) {
                $vals[] = (string) seed_random_int(20, 80);
            }
            return implode(',', $vals);
        }
        if (stripos($col['comment'], 'mask') !== false || preg_match('/_mask$/i', $name)) {
            return '0x' . strtoupper(dechex(seed_random_int(0, 0xFFFF)));
        }
        if (preg_match('/mode$/i', $name)) {
            return (string) seed_random_int(0, 3);
        }
        return substr(bin2hex(seed_random_bytes(6)), 0, 12);
    }

    return null; // unhandled type — leave NULL rather than guess
}

/**
 * Coherent, physics-plausible values for one Analysis row (Don column names).
 * $asymSign: +1 / -1 forces raw-asymmetry sign; null picks randomly.
 * $rateScale: multiplies the nominal rate band (used to step a Rate scan).
 */
function generate_analysis_overrides($asymSign = null, $rateScale = 1.0){
    $leftRate  = rand_float(55000, 60000) * $rateScale;
    $rightRate = rand_float(55000, 60000) * $rateScale;
    $coinRate  = rand_float(42000, 48000) * $rateScale;
    $accRate   = $coinRate * rand_float(0.0075, 0.015);

    // ~1 s of ticks at the stated clock rate
    $clk100khz = (int) round(jitter(100000, 0.25));
    $clk20mhz  = (int) round(jitter(20000000, 0.25));

    $bcm         = seed_random_int(200, 300);
    $asymQ       = rand_float(1e-5, 8e-5); // 10–80 ppm
    $qpedCalc    = round(rand_float(6.0, 9.0), 4);

    $sign        = isset($asymSign) ? $asymSign : (seed_random_int(0, 1) ? 1 : -1);
    $asymMol     = rand_float(0.052, 0.056) * $sign;
    $asymMolErr  = pct_of($asymMol, 0.15, 0.25);
    $azz         = 0.776536;
    $polTarg     = 0.08015;
    $polBeam     = $asymMol / $azz / $polTarg;

    return [
        'leftrate'      => $leftRate,
        'leftrate_err'  => pct_of($leftRate, 0.085, 0.1),
        'rightrate'     => $rightRate,
        'rightrate_err' => pct_of($rightRate, 0.085, 0.1),
        'coinrate'      => $coinRate,
        'coinrate_err'  => pct_of($coinRate, 0.085, 0.1),
        'accrate'       => $accRate,
        'accrate_err'   => pct_of($accRate, 0.085, 0.1),
        'counts_to_Hz'  => 100.0,

        'bcm'           => $bcm,
        'clock100kHz'   => $clk100khz,
        'clock20MHz'    => $clk20mhz,
        'deadtime_tau_1'=> rand_float(32e-9, 33e-9),
        'deadtime_tau_2'=> rand_float(55e-9, 56e-9),
        'accid_tau'     => rand_float(25e-9, 28e-9),

        'asym_mol'      => $asymMol,
        'asym_mol_err'  => $asymMolErr,
        'Azz'           => $azz,
        'poltarg'       => $polTarg,
        'pol_beam'      => $polBeam,
        'pol_beam_err'  => $asymMolErr,

        'asym_q'        => $asymQ,
        'asym_q_err'    => pct_of($asymQ, 1.5, 2.5),
        'qpedcalc'      => $qpedCalc,
        'qpedused'      => round($qpedCalc, 1),
    ];
}

/** Rough but plausible DAQ_config values for FADC / Møller trigger fields. */
function generate_daq_overrides(){
    $wOffset = seed_random_int(80, 140);
    $wWidth  = seed_random_int(40, 80);
    $nsb     = seed_random_int(2, 8);
    $nsa     = seed_random_int(8, 24);
    $npeak   = seed_random_int(1, 3);
    $maxped  = seed_random_int(50, 100);
    $nsat    = seed_random_int(1, 3);
    $pedVals = [];
    $tetVals = [];
    for ($i = 0; $i < 16; $i++) {
        $pedVals[] = (string) round(rand_float(45, 85), 1);
        $tetVals[] = (string) seed_random_int(25, 70);
    }
    $samePed = round(rand_float(50, 70), 1);
    $sameTet = (string) seed_random_int(30, 60);

    return [
        'fadc_crate'    => 'vme-crate.example',
        'fadc_slot'     => (string) seed_random_int(3, 8),
        'fadc_adc_mask' => '0xFFFF',
        'fadc_trg_mask' => '0x00FF',
        'fadc_tet_ignore_mask' => '0x0000',
        'fadc_allch_mode' => '1',

        'fadc_allch_w_offset' => $wOffset,
        'fadc_allch_w_width'  => $wWidth,
        'fadc_allch_nsb'      => $nsb,
        'fadc_allch_nsa'      => $nsa,
        'fadc_w_offset'       => $wOffset,
        'fadc_w_width'        => $wWidth,
        'fadc_nsb'            => $nsb,
        'fadc_nsa'            => $nsa,

        'fadc_allch_npeak'  => $npeak,
        'fadc_allch_maxped' => $maxped,
        'fadc_allch_nsat'   => $nsat,
        'fadc_npeak'        => $npeak,
        'fadc_maxped'       => $maxped,
        'fadc_nsat'         => $nsat,

        'fadc_dac'  => seed_random_int(800, 1200),
        'fadc_gain' => round(rand_float(0.05, 0.25), 5),
        'fadc_accumulator_scaler_mode_mask' => '0x0000',

        'fadc_l_offset'   => round(rand_float(10, 40), 3),
        'fadc_r_offset'   => round(rand_float(10, 40), 3),
        'fadc_disc_width' => round(rand_float(1, 4), 2),
        'fadc_disc_mode'  => seed_random_int(0, 1),
        'fadc_l_sum_thr'  => round(rand_float(50, 200), 2),
        'fadc_r_sum_thr'  => round(rand_float(50, 200), 2),
        'fadc_trg_sel'    => seed_random_int(0, 2),
        'fadc_trg_width'  => seed_random_int(1, 8),

        'fadc_allch_ped' => implode(',', $pedVals),
        'fadc_ped'       => (string) $samePed,
        'fadc_allch_tet' => implode(',', $tetVals),
        'fadc_tet'       => $sameTet,
    ];
}

/** Rough EPICS snapshot: beam ~Hall A Møller energies, sensible currents/temps. */
function generate_epics_overrides(){
    $eBeam = rand_float(1050, 1150);
    $bcm   = rand_float(0.8, 3.5);
    $ihwp  = seed_random_int(0, 1) ? 'IN' : 'OUT';

    return [
        'epics_E_beam'   => round($eBeam, 3),
        'epics_E_inj'    => round(rand_float(55, 65), 3),
        'epics_E_Slinac' => round(rand_float(500, 600), 2),
        'epics_E_Nlinac' => round(rand_float(500, 600), 2),
        'epics_n_pass'   => (string) seed_random_int(1, 5),

        'epics_bcm_avg'     => round($bcm, 5),
        'epics_unser'       => round($bcm * rand_float(0.95, 1.05), 5),
        'epics_bcm_us'      => round($bcm * rand_float(0.9, 1.1), 5),
        'epics_bcm_ds'      => round($bcm * rand_float(0.9, 1.1), 5),
        'epics_inj_bcm_tot' => round(rand_float(20, 80), 4),
        'epics_inj_bcm_halla' => round($bcm * rand_float(0.8, 1.2), 5),

        'epics_bpm01_X'  => round(rand_float(-1.5, 1.5), 5),
        'epics_bpm01_Y'  => round(rand_float(-1.5, 1.5), 5),
        'epics_bpm04_X'  => round(rand_float(-1.5, 1.5), 5),
        'epics_bpm04_Y'  => round(rand_float(-1.5, 1.5), 5),
        'epics_bpm04a_X' => round(rand_float(-1.5, 1.5), 5),
        'epics_bpm04a_Y' => round(rand_float(-1.5, 1.5), 5),
        'epics_bpm02a_X' => round(rand_float(-1.5, 1.5), 5),
        'epics_bpm02a_Y' => round(rand_float(-1.5, 1.5), 5),

        'epics_q1_cur'  => round(rand_float(50, 200), 2),
        'epics_q2_cur'  => round(rand_float(50, 200), 2),
        'epics_q3_cur'  => round(rand_float(50, 200), 2),
        'epics_q4_cur'  => round(rand_float(50, 200), 2),
        'epics_dip_cur' => round(rand_float(100, 400), 2),

        'epics_tgt_foil'      => seed_random_int(1, 3),
        'epics_tgt_angle_deg' => round(rand_float(-5, 5), 3),
        'epics_tgt_lin_pos_mm'=> round(rand_float(0, 50), 3),
        'epics_tgt_ladder_temp1' => round(rand_float(20, 35), 2),
        'epics_tgt_ladder_temp2' => round(rand_float(20, 35), 2),
        'epics_tgt_ladder_temp3' => round(rand_float(20, 35), 2),

        'epics_ihwp'        => $ihwp,
        'epics_rhwp'        => round(rand_float(0, 90), 2),
        'epics_vwien_angle' => round(rand_float(-90, 90), 2),
        'epics_hwien_angle' => round(rand_float(-90, 90), 2),

        'epics_hel_pattern' => seed_pick(array('pair', 'quartet', 'octet')),
        'epics_hel_freq'    => round(rand_float(29.5, 30.5), 6),
        'epics_t_settle'    => round(rand_float(60, 100), 2),
        'epics_t_stable'    => round(rand_float(400, 600), 2),

        'epics_mol_mag_cur_meas'   => round(rand_float(20, 80), 3),
        'epics_mol_mag_field_meas' => round(rand_float(1.0, 3.5), 4),
        'epics_mol_cooler_temp'    => round(rand_float(3.5, 5.5), 3),

        'epics_det_hv_ch1' => round(rand_float(1400, 1800), 1),
        'epics_det_hv_ch2' => round(rand_float(1400, 1800), 1),
        'epics_det_hv_ch3' => round(rand_float(1400, 1800), 1),
        'epics_det_hv_ch4' => round(rand_float(1400, 1800), 1),
        'epics_det_hv_ch5' => round(rand_float(1400, 1800), 1),
        'epics_det_hv_ch6' => round(rand_float(1400, 1800), 1),
        'epics_det_hv_ch7' => round(rand_float(1400, 1800), 1),
        'epics_det_hv_ch8' => round(rand_float(1400, 1800), 1),
    ];
}

/**
 * Build the ordered run schedule for one calendar day.
 * Each entry: run_type, run_group, asym_sign (+1/-1/null), rate_scale, comment.
 * $nextGroup is advanced once per Polarization run, each Systematic study,
 * and each Rate scan series.
 */
function build_day_schedule($dateLabel, &$nextGroup){
    $schedule = [];

    // Always: two Polarization runs with opposite-sign raw asymmetries.
    // Each gets its own run_group — a sign flip implies a different
    // iHWP / Wien combination, so they are not one analysis group.
    $firstPolSign = seed_random_int(0, 1) ? 1 : -1;
    $polGroupA = $nextGroup++;
    $schedule[] = [
        'run_type'   => 'POLARIZATION',
        'run_group'  => $polGroupA,
        'asym_sign'  => $firstPolSign,
        'rate_scale' => 1.0,
        'comment'    => "Polarization run ({$dateLabel}), group {$polGroupA}.",
    ];
    $polGroupB = $nextGroup++;
    $schedule[] = [
        'run_type'   => 'POLARIZATION',
        'run_group'  => $polGroupB,
        'asym_sign'  => -$firstPolSign,
        'rate_scale' => 1.0,
        'comment'    => "Polarization run, opposite sign ({$dateLabel}), group {$polGroupB}.",
    ];

    // Optional: one Systematic study — its own unique run_group.
    if (seed_random_int(0, 1) === 1) {
        $sysGroup = $nextGroup++;
        $sysSign = seed_random_int(0, 1) ? 1 : -1;
        $schedule[] = [
            'run_type'   => 'SYSTEMATIC_STUDY',
            'run_group'  => $sysGroup,
            'asym_sign'  => $sysSign,
            'rate_scale' => 1.0,
            'comment'    => "Systematic study ({$dateLabel}), group {$sysGroup}.",
        ];
    }

    // Optional: Rate scan of 4–5 sequential runs — all share one run_group.
    // Scales step the detection rates so the scan is visually distinct.
    if (seed_random_int(0, 1) === 1) {
        $scanGroup = $nextGroup++;
        $nScan = seed_random_int(4, 5);
        for ($s = 0; $s < $nScan; $s++) {
            $scale = 0.55 + ($s / max(1, $nScan - 1)) * 0.55; // ~0.55 → 1.10
            $schedule[] = [
                'run_type'   => 'RATE_SCAN',
                'run_group'  => $scanGroup,
                'asym_sign'  => null, // random sign per scan point
                'rate_scale' => $scale,
                'comment'    => 'Rate scan point ' . ($s + 1) . "/{$nScan} ({$dateLabel}), group {$scanGroup}.",
            ];
        }
    }

    // Optional: a Test run in its own group (exercises the TEST lookup code).
    if (seed_random_int(0, 3) === 0) {
        $testGroup = $nextGroup++;
        $schedule[] = [
            'run_type'   => 'TEST',
            'run_group'  => $testGroup,
            'asym_sign'  => null,
            'rate_scale' => 1.0,
            'comment'    => "Test run ({$dateLabel}), group {$testGroup}.",
        ];
    }

    return $schedule;
}

/**
 * Error-weighted average and its standard error.
 *   x̄ = Σ(x_i / σ_i²) / Σ(1/σ_i²)
 *   σ_x̄ = 1 / sqrt(Σ(1/σ_i²))
 *
 * @param list<array{0:float,1:float}> $pairs  [[value, error], ...]
 * @return array{0:float,1:float}|null
 */
function error_weighted_average($pairs){
    $wSum = 0.0;
    $wxSum = 0.0;
    foreach ($pairs as $pair) {
        $val = $pair[0];
        $err = $pair[1];
        $err = abs((float) $err);
        if ($err <= 0.0) {
            continue;
        }
        $w = 1.0 / ($err * $err);
        $wSum += $w;
        $wxSum += ((float) $val) * $w;
    }
    if ($wSum <= 0.0) {
        return null;
    }
    return [$wxSum / $wSum, 1.0 / sqrt($wSum)];
}

/** Generic insert: build column list + values from schema metadata. */
function insert_generic_row($pdo, $table, $columns, $runNumber, $overrides = []){
    $cols = ['run_number'];
    $vals = [$runNumber];

    foreach ($columns as $col) {
        if (in_array($col['name'], $GLOBALS['SKIP_COLUMNS'], true)) {
            continue;
        }
        $cols[] = $col['name'];
        $vals[] = array_key_exists($col['name'], $overrides)
            ? $overrides[$col['name']]
            : random_value_for_column($col);
    }

    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $colList      = implode(', ', array_map(function ($c) { return "`{$c}`"; }, $cols));
    $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders})");
    $stmt->execute($vals);
}

/** Insert one Grouped_Analysis row from an explicit value map (PK is group_number). */
function insert_grouped_analysis_row($pdo, $columns, $values){
    $cols = [];
    $vals = [];
    foreach ($columns as $col) {
        if (in_array($col['name'], $GLOBALS['SKIP_COLUMNS'], true)) {
            continue;
        }
        if (!array_key_exists($col['name'], $values)) {
            continue;
        }
        $cols[] = $col['name'];
        $vals[] = $values[$col['name']];
    }
    if (!$cols) {
        return;
    }
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $colList      = implode(', ', array_map(function ($c) { return "`{$c}`"; }, $cols));
    $stmt = $pdo->prepare("INSERT INTO `Grouped_Analysis` ({$colList}) VALUES ({$placeholders})");
    $stmt->execute($vals);
}

$daqColumns      = get_table_columns($pdo, 'DAQ_config');
$epicsColumns    = get_table_columns($pdo, 'EPICS_data');
$analysisColumns = get_table_columns($pdo, 'Analysis');
$runInfoColumns  = get_table_columns($pdo, 'Run_info');
$groupedColumns  = get_table_columns($pdo, 'Grouped_Analysis');

$startRun = 20000;
$runNumber = $startRun;
$nextGroup = 1; // sequential run_group ids across the whole seed
$totalRuns = 0;
$dayCount = 0;
// Cycle all run_quality lookup codes so index/group cards exercise every tag style.
$qualityCycle = ['GOOD', 'BAD', 'SUSPECT', 'PENDING', 'JUNK'];
$experimentCycle = ['Møller', 'Commissioning', 'Test beam'];
// Accumulators for Grouped_Analysis: group_id => type + value/error pairs
$groupAccum = [];

// Distinct calendar days in 2026. Pre-pick enough for the worst case
// (Polarization-only days = 2 runs each), then generate whole days in
// chronological order until totalRuns exceeds the target.
$yearStart = strtotime('2026-01-15');
$yearEnd   = strtotime('2026-11-15');
$maxDayOffset = (int) (($yearEnd - $yearStart) / 86400);
$daysNeeded = (int) ceil($targetRuns / 2) + 1;
$dayOffsets = [];
while (count($dayOffsets) < $daysNeeded && count($dayOffsets) <= $maxDayOffset) {
    $offset = seed_random_int(0, $maxDayOffset);
    $dayOffsets[$offset] = true;
}
$dayOffsets = array_keys($dayOffsets);
sort($dayOffsets);

$pdo->beginTransaction();
try {
    foreach ($dayOffsets as $offset) {
        if ($totalRuns > $targetRuns) {
            break;
        }

        $dayStart = $yearStart + ($offset * 86400);
        $dateLabel = date('Y-m-d', $dayStart);
        $schedule = build_day_schedule($dateLabel, $nextGroup);
        $hour = 9; // start mid-morning; each run ~25 minutes later
        $minute = 0;
        $dayCount++;

        foreach ($schedule as $slot) {
            $startTs = $dayStart + ($hour * 3600) + ($minute * 60);
            $durationSec = seed_random_int(8 * 60, 18 * 60); // 8–18 min run
            $endTs   = $startTs + $durationSec;
            $analysisOverrides = generate_analysis_overrides($slot['asym_sign'], $slot['rate_scale']);

            insert_generic_row($pdo, 'Run_info', $runInfoColumns, $runNumber, [
                'run_group'      => $slot['run_group'],
                'run_experiment' => $experimentCycle[$dayCount % count($experimentCycle)],
                'run_start_datetime' => format_linux_date($startTs),
                'run_end_datetime'   => format_linux_date($endTs),
                'run_start_unix'     => $startTs,
                'run_end_unix'       => $endTs,
                'run_length'     => $durationSec,
                'run_type'       => $slot['run_type'],
                'run_quality'    => $qualityCycle[$totalRuns % count($qualityCycle)],
                'comment'        => $slot['comment'],
                'target_pol'     => round(rand_float(0.07, 0.09), 6),
            ]);
            insert_generic_row($pdo, 'DAQ_config', $daqColumns, $runNumber, generate_daq_overrides());
            insert_generic_row($pdo, 'EPICS_data', $epicsColumns, $runNumber, generate_epics_overrides());
            insert_generic_row($pdo, 'Analysis', $analysisColumns, $runNumber, $analysisOverrides);

            $gid = $slot['run_group'];
            if (!isset($groupAccum[$gid])) {
                $groupAccum[$gid] = [
                    'type'      => $slot['run_type'],
                    'asym'      => [],
                    'pol'       => [],
                    'start_ts'  => $startTs,
                    'end_ts'    => $endTs,
                ];
            }
            if ($startTs < $groupAccum[$gid]['start_ts']) {
                $groupAccum[$gid]['start_ts'] = $startTs;
            }
            if ($endTs > $groupAccum[$gid]['end_ts']) {
                $groupAccum[$gid]['end_ts'] = $endTs;
            }
            $groupAccum[$gid]['asym'][] = [
                $analysisOverrides['asym_mol'],
                $analysisOverrides['asym_mol_err'],
            ];
            $groupAccum[$gid]['pol'][] = [
                $analysisOverrides['pol_beam'],
                $analysisOverrides['pol_beam_err'],
            ];

            $runNumber++;
            $totalRuns++;
            $minute += 25;
            if ($minute >= 60) {
                $hour += (int)($minute / 60);
                $minute %= 60;
            }
        }
    }

    // One Grouped_Analysis row per run_group. iHWP / Wien cycle evenly.
    $ihwpCycle = ['IN', 'OUT', 'MIXED'];
    $wienCycle = ['FLIP-LEFT', 'FLIP-RIGHT', 'MIXED'];
    $groupIndex = 0;
    foreach ($groupAccum as $gid => $data) {
        $asymAvg = error_weighted_average($data['asym']);
        $polAvg  = error_weighted_average($data['pol']);
        $groupType = ($groupIndex % 6 === 5) ? 'MIXED' : $data['type'];
        insert_grouped_analysis_row($pdo, $groupedColumns, [
            'group_number'  => $gid,
            'group_type'    => $groupType,
            'group_quality' => $qualityCycle[$groupIndex % count($qualityCycle)],
            'group_comment' => "Grouped analysis for {$groupType} group {$gid}.",
            'group_start'   => date('Y-m-d H:i:s', $data['start_ts']),
            'group_end'     => date('Y-m-d H:i:s', $data['end_ts']),
            'asym_mol'      => isset($asymAvg[0]) ? $asymAvg[0] : null,
            'asym_mol_err'  => isset($asymAvg[1]) ? $asymAvg[1] : null,
            'pol_beam'      => isset($polAvg[0]) ? $polAvg[0] : null,
            'pol_beam_err'  => isset($polAvg[1]) ? $polAvg[1] : null,
            'epics_ihwp'    => $ihwpCycle[$groupIndex % 3],
            'epics_wien'    => $wienCycle[$groupIndex % 3],
        ]);
        $groupIndex++;
    }

    $pdo->commit();
    $lastRun = $runNumber - 1;
    echo "Inserted {$totalRuns} fake runs across {$dayCount} day(s) "
        . "and {$groupIndex} grouped-analysis row(s) "
        . "(target >{$targetRuns}; run_number {$startRun}-{$lastRun}).\n";
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seeding failed: ' . $e->getMessage() . "\n");
    exit(1);
}
