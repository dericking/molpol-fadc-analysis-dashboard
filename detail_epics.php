<?php
require_once __DIR__ . '/includes/bootstrap.php';
debug_step('detail_epics.php: after bootstrap');

$runNumber = require_positive_int_query('run');
$runInfo   = require_row($pdo, 'Run_info', 'run_number', $runNumber, 'Run');

// --- Page copy (edit these) ---
$pageTitle        = "Run {$runNumber} — EPICS Data";
$pageHeading      = $pageTitle;
$otherHeading       = 'Other EPICS Information';
$unallocatedHeading = 'Unallocated Sections';
$emptyMessage       = 'No data recorded for this run.';
$lastUpdatedLabel   = 'Table Row Last Updated:';
// --- end page copy ---

$pack = load_section_view($pdo, 'epics', $runNumber);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php render_site_navbar(); ?>
<div class="wrap-wide">

<h1><?= htmlspecialchars($pageHeading) ?></h1>
<p class="subtitle">
  <a href="index.php">« Back to Main Page</a>
  &middot;
  <a href="detail_runs.php?run=<?= (int)$runNumber ?>">Run detail</a>
</p>

<?php if ($pack['row'] === null): ?>
  <?php render_status_message('empty_table_row', $emptyMessage); ?>
<?php else: ?>
  <?php render_layout_errors($pack); ?>
  <?php render_featured_rows($pack); ?>
  <?php render_section_cards($pack, 'main'); ?>

  <?php render_section_header($otherHeading, $lastUpdatedLabel, $pack['last_updated']); ?>
  <?php render_section_cards($pack, 'other'); ?>

  <?php // Required: surfaces classifier sections not placed in featured/layouts. ?>
  <?php render_unallocated_sections($pack, $unallocatedHeading); ?>
<?php endif; ?>

</div>
</body>
</html>
