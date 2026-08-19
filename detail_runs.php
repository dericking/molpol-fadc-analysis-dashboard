<?php
require_once __DIR__ . '/includes/bootstrap.php';

$runNumber = require_positive_int_query('run');
$runInfo   = require_row($pdo, 'Run_info', 'run_number', $runNumber, 'Run');

// --- Page copy (edit these) ---
$pageTitle          = "Run {$runNumber} — Møller Run Detail";
$runInfoModalTitle  = 'Run Info';
$analysisHeading    = 'Analysis';
$unallocatedHeading = 'Unallocated Sections';
$plotsHeading       = 'Plots';
$emptyMessage       = 'No data recorded for this run.';
$lastUpdatedLabel   = 'Table Row Last Updated:';
$noStartTimeLabel   = 'no start time';
$noEndTimeLabel     = 'ongoing / not recorded';
// --- end page copy ---

$pack = load_section_view($pdo, 'analysis', $runNumber);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php render_site_navbar(); ?>
<div class="wrap-wide">

<h1><?= htmlspecialchars($pageTitle) ?></h1>
<p class="subtitle">
  <a href="index.php">« Back to Main Page</a>
  &middot;
  <a href="#run-info">Run Info</a>
  &middot;
  <a href="detail_epics.php?run=<?= (int)$runNumber ?>">EPICS Data</a>
  &middot;
  <a href="detail_daq.php?run=<?= (int)$runNumber ?>">DAQ Configuration</a>
</p>

<?php render_section_header($analysisHeading, $lastUpdatedLabel, $pack['last_updated']); ?>

<?php if ($pack['row'] === null): ?>
  <?php render_status_message('empty_table_row', $emptyMessage); ?>
<?php else: ?>
  <?php render_layout_errors($pack); ?>
  <?php render_featured_rows($pack); ?>
  <?php render_section_cards($pack, 'main'); ?>
  <?php // Required: surfaces classifier sections not placed in featured/layouts. ?>
  <?php render_unallocated_sections($pack, $unallocatedHeading); ?>
<?php endif; ?>

<?php render_section_header($plotsHeading); ?>

<?php render_analysis_plots($config, 'run', (int)$runNumber); ?>

</div>

<!-- CSS :target modal — open via #run-info, close via href="#" (no JS) -->
<div id="run-info" class="modal" role="dialog" aria-modal="true" aria-labelledby="run-info-title">
  <a href="#" class="modal-backdrop" tabindex="-1" aria-label="Close Run Info"></a>
  <div class="modal-panel card">
    <div class="modal-header">
      <h2 id="run-info-title"><?= htmlspecialchars($runInfoModalTitle) ?></h2>
      <a class="modal-close" href="#" aria-label="Close">Close</a>
    </div>
    <?php
      render_run_summary($runInfo, 'run', [
          'empty_start' => $noStartTimeLabel,
          'empty_end'   => $noEndTimeLabel,
      ]);
    ?>
  </div>
</div>

</body>
</html>
