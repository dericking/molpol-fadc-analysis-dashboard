<?php
/**
 * report.php — filtered run/group report with checkbox column picker + CSV.
 *
 * Column pick/reorder (dual list): report_advanced.php
 * Query prep: includes/report_query.php
 * Column catalog: includes/layouts/layout_report.php
 */
$reportScript = 'report.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/report_query.php';

if ($format === 'csv') {
    emit_report_csv($view, $selectedColumns, $rows);
}

$pageTitle = $siteTitle . ' — Report';
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

  <header>
    <h1><?= htmlspecialchars($siteTitle) ?> — Report</h1>
    <p class="subtitle">
      <a href="index.php">Browse</a>
      &middot; Report
      &middot; <a href="<?= htmlspecialchars($reportSiblingHref) ?>"><?= htmlspecialchars($reportSiblingLabel) ?></a>
    </p>
  </header>

  <form class="filters filters-top report-filters" method="get" action="<?= htmlspecialchars($reportScript) ?>">
    <?php require __DIR__ . '/includes/report_filters.php'; ?>

    <div class="report-columns-block">
      <div class="top-nav-heading-row">
        <h2 class="panel-heading">Available Data</h2>
        <div class="top-nav-actions">
          <?php if ($colsExplicit): ?>
            <a class="top-nav-clear" href="<?= htmlspecialchars($resetColsHref) ?>">reset columns</a>
          <?php endif; ?>
        </div>
      </div>
      <?php
        $primaryRow = $availableColumnRows[0] ?? [];
        $extraRows = array_slice($availableColumnRows, 1);
      ?>
      <?php if ($primaryRow !== []): ?>
        <?php render_report_column_picker_row($primaryRow, $selectedFields); ?>
      <?php endif; ?>
      <?php if ($extraRows !== []): ?>
        <input type="checkbox" id="report-more-cols" class="report-more-cb" <?= $reportMoreOpen ? 'checked' : '' ?>>
        <label class="report-more-label" for="report-more-cols">
          <span class="report-more-open">More available data</span>
          <span class="report-more-close">Hide extra data</span>
        </label>
        <div class="report-column-more">
          <?php foreach ($extraRows as $extraRow): ?>
            <?php render_report_column_picker_row($extraRow, $selectedFields); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="report-columns-footer">
        <button type="submit" class="report-apply-cols">Apply columns</button>
      </div>
    </div>
  </form>

  <?php require __DIR__ . '/includes/report_results.php'; ?>

</div>
</body>
</html>
