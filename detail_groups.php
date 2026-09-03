<?php
/**
 * detail_groups.php — one run_group: Grouped_Analysis + member runs from Run_info
 * (+ plots). Opens if either side exists; 404 only when both are missing.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$groupId = require_positive_int_query('group');

$hasRunGroupColumn = table_has_column($pdo, 'Run_info', 'run_group');
$runGroupColumnMissing = !$hasRunGroupColumn;

if ($hasRunGroupColumn) {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM Run_info
         WHERE run_group = :group
         ORDER BY run_number ASC"
    );
    $stmt->execute(['group' => $groupId]);
    $runs = $stmt->fetchAll();
} else {
    $runs = [];
}

$pack = load_section_view($pdo, 'grouped_analysis', $groupId);

if (!$runs && $pack['row'] === null) {
    http_response_code(404);
    ?>
    <!DOCTYPE html><html><head><meta charset="utf-8"><title>Group not found</title>
    <link rel="stylesheet" href="assets/style.css"></head>
    <body><div class="wrap-wide">
      <p>Group <?= (int)$groupId ?> was not found.
         <a href="index.php">« Back to Main Page</a></p>
    </div></body></html>
    <?php
    exit;
}

$groupLabel = format_group_label($groupId);
$runCount = count($runs);
$firstRun = $runCount > 0 ? $runs[0] : null;
$lastRun  = $runCount > 0 ? $runs[$runCount - 1] : null;

// --- Page copy (edit these) ---
$pageTitle           = "Group {$groupLabel} — Møller Group Detail";
$pageHeading         = "Møller Analysis Group {$groupLabel}";
$analysisHeading     = 'Group Analysis';
$unallocatedHeading  = 'Unallocated Sections';
$runsHeading         = 'Runs in Group';
$plotsHeading        = 'Group Analysis Plots';
$emptyMessage        = 'Group analysis has not been completed for this group yet.';
$emptyRunsMessage    = 'No runs are assigned to this group yet.';
$lastUpdatedLabel    = 'Table Row Last Updated:';
$groupInfoModalTitle = 'Group Info';
$noStartTimeLabel    = 'no start time';
$noEndTimeLabel      = 'ongoing / not recorded';
// --- end page copy ---

$defaultLayout = (isset($config['default_layout']) ? $config['default_layout'] : 'table') === 'cards' ? 'cards' : 'table';
$listLayout = (isset($_GET['layout']) ? $_GET['layout'] : $defaultLayout) === 'table' ? 'table' : 'cards';

$layoutQs = function ($nextLayout) use ($groupId, $defaultLayout {
    $params = ['group' => (int)$groupId];
    if ($nextLayout !== $defaultLayout) {
        $params['layout'] = $nextLayout;
    }
    return 'detail_groups.php?' . http_build_query($params) . '#runs-in-group';
};
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

<h1><?= htmlspecialchars($pageHeading) ?></h1>
<p class="subtitle">
  <a href="index.php">« Back to Main Page</a>
  &middot;
  <a href="#group-info">Group Info</a>
</p>
<p class="meta">
  <?= (int)$runCount ?> run<?= $runCount === 1 ? '' : 's' ?>
  <?php if ($firstRun !== null && $lastRun !== null): ?>
    (<?= (int)$firstRun['run_number'] ?>–<?= (int)$lastRun['run_number'] ?>)
  <?php endif; ?>
</p>

<?php render_section_header($analysisHeading, $lastUpdatedLabel, $pack['last_updated']); ?>

<?php if ($pack['row'] === null): ?>
  <?php render_status_message('empty_group_analysis', $emptyMessage); ?>
<?php else: ?>
  <?php render_layout_errors($pack); ?>
  <?php render_featured_rows($pack); ?>
  <?php render_section_cards($pack, 'main'); ?>
  <?php // Required: surfaces classifier sections not placed in featured/layouts. ?>
  <?php render_unallocated_sections($pack, $unallocatedHeading); ?>
<?php endif; ?>

<div class="section-header" id="runs-in-group">
  <h2><?= htmlspecialchars($runsHeading) ?></h2>
  <?php if ($runCount > 0): ?>
    <div class="meta-right">
      <div class="view-toggle" role="group" aria-label="Run list layout">
        <a class="<?= $listLayout === 'table' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($layoutQs('table')) ?>">Table</a>
        <a class="<?= $listLayout === 'cards' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($layoutQs('cards')) ?>">Cards</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if ($runGroupColumnMissing): ?>
  <?php render_status_message('group_missing_run_group_column'); ?>
<?php elseif ($runCount === 0): ?>
  <?php render_status_message('empty_group_runs', $emptyRunsMessage); ?>
<?php elseif ($listLayout === 'table'): ?>
  <?php render_run_table($runs); ?>
<?php else: ?>
  <div class="card-grid">
    <?php foreach ($runs as $run): ?>
      <?php render_run_card($run); ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php render_section_header($plotsHeading); ?>

<?php render_analysis_plots($config, 'group', (int)$groupId); ?>

</div>

<!-- CSS :target modal — open via #group-info, close via href="#" (no JS) -->
<div id="group-info" class="modal" role="dialog" aria-modal="true" aria-labelledby="group-info-title">
  <a href="#" class="modal-backdrop" tabindex="-1" aria-label="Close Group Info"></a>
  <div class="modal-panel card">
    <div class="modal-header">
      <h2 id="group-info-title"><?= htmlspecialchars($groupInfoModalTitle) ?></h2>
      <a class="modal-close" href="#" aria-label="Close">Close</a>
    </div>
    <?php
      render_run_summary(isset($pack['row']) ? $pack['row'] : [], 'group', [
          'empty_start' => $noStartTimeLabel,
          'empty_end'   => $noEndTimeLabel,
      ]);
    ?>
  </div>
</div>

</body>
</html>
