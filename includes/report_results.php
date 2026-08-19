<?php
/**
 * includes/report_results.php
 *
 * Shared warnings / toolbar / table body for report.php and
 * report_advanced.php. Expects report_query.php variables.
 */
?>
  <?php render_status_warning_box($reportColumnWarnings); ?>

  <div class="report-toolbar">
    <p class="report-count">
      <?= (int)count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?>
      <?php if ($view === 'groups'): ?>
        (groups)
      <?php else: ?>
        (runs)
      <?php endif; ?>
      &middot; cap <?= (int)$rowCap ?>
    </p>
    <p class="report-download">
      <a class="report-csv-link" href="<?= htmlspecialchars($csvHref) ?>">Download CSV</a>
    </p>
  </div>

  <?php if ($hasMore): ?>
    <?php render_status_message('report_truncated'); ?>
  <?php endif; ?>

  <?php if ($rows === []): ?>
    <?php render_status_message('report_no_match'); ?>
  <?php else: ?>
    <?php render_report_table($selectedColumns, $rows); ?>
  <?php endif; ?>
