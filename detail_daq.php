<?php
/**
 * detail_daq.php — canonical thin detail-page template.
 *
 * To add another detail page:
 *   1. Add a key in includes/layouts/layout_sections.php (exclude, classifier, featured, layouts).
 *   2. Copy this file; change knobs, layout key, and nav links.
 *   3. Place bands with render_section_header() + render_section_cards($pack, 'name').
 *   4. Call render_layout_errors($pack) so malformed layouts bands show a WARNING
 *      instead of a PHP TypeError (flat lists must be rows-of-rows).
 *   5. Always call render_unallocated_sections($pack, $unallocatedHeading) after
 *      the layout bands (required check: classifier sections not in featured/
 *      layouts still show; omit only via ignore_sections in layout_sections.php).
 *   6. Keep unique header / plots / lists as plain HTML on the page.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$runNumber = require_positive_int_query('run');
$runInfo   = require_row($pdo, 'Run_info', 'run_number', $runNumber, 'Run');

// --- Page copy (edit these) ---
$pageTitle        = "Run {$runNumber} — DAQ Configuration";
$pageHeading      = $pageTitle;
$otherHeading       = 'Other DAQ Information';
$unallocatedHeading = 'Unallocated Sections';
$emptyMessage       = 'No data recorded for this run.';
$lastUpdatedLabel   = 'Table Row Last Updated:';
// --- end page copy ---

$pack = load_section_view($pdo, 'daq', $runNumber);
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
