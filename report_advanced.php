<?php
/**
 * report_advanced.php — filtered report with Available | Selected column picker.
 *
 * Simple form (checkbox picker): report.php
 * Query prep: includes/report_query.php
 * Column catalog: includes/layouts/layout_report.php
 */
$reportScript = 'report_advanced.php';
require_once __DIR__ . '/includes/bootstrap.php';
debug_step('report_advanced.php: after bootstrap');
require_once __DIR__ . '/includes/report_query.php';
debug_step('report_advanced.php: after report_query');

if ($format === 'csv') {
    emit_report_csv($view, $selectedColumns, $rows);
}

$pageTitle = $siteTitle . ' — Advanced Report';
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
    <h1><?= htmlspecialchars($siteTitle) ?> — Advanced Report</h1>
    <p class="subtitle">
      <a href="index.php">Browse</a>
      &middot; <a href="<?= htmlspecialchars($reportSiblingHref) ?>"><?= htmlspecialchars($reportSiblingLabel) ?></a>
      &middot; Advanced Report
    </p>
  </header>

  <form class="filters filters-top report-filters" method="get" action="<?= htmlspecialchars($reportScript) ?>">
    <?php require __DIR__ . '/includes/report_filters.php'; ?>

    <div class="report-columns-block">
      <div class="top-nav-heading-row">
        <h2 class="panel-heading">Columns</h2>
        <div class="top-nav-actions">
          <?php if ($colsExplicit): ?>
            <a class="top-nav-clear" href="<?= htmlspecialchars($resetColsHref) ?>">reset columns</a>
          <?php endif; ?>
        </div>
      </div>
      <noscript>
        <p class="empty-state status-message">Column picking and ordering need JavaScript enabled. Use the <a href="<?= htmlspecialchars($reportSiblingHref) ?>">Simple Report Form</a> for checkbox column picking.</p>
      </noscript>
      <div class="report-col-dual" id="report-col-dual" hidden>
        <?php
          $selectedSet = array_fill_keys($selectedFields, true);
          $byField = [];
          foreach ($availableColumns as $col) {
              $byField[(string)$col['field']] = $col;
          }
        ?>
        <div class="report-col-pane">
          <h3 class="report-column-section-title">Available</h3>
          <select id="report-cols-available" class="report-col-select" multiple size="14" aria-label="Available columns">
            <?php foreach ($availableColumnSections as $section): ?>
              <?php
                $availInSection = [];
                foreach ($section['columns'] as $col) {
                    $field = (string)$col['field'];
                    if (!isset($selectedSet[$field])) {
                        $availInSection[] = $col;
                    }
                }
                if ($availInSection === []) {
                    continue;
                }
              ?>
              <optgroup label="<?= htmlspecialchars((string)$section['title']) ?>">
                <?php foreach ($availInSection as $col): ?>
                  <option value="<?= htmlspecialchars((string)$col['field']) ?>" data-section="<?= htmlspecialchars((string)$section['title']) ?>">
                    <?= htmlspecialchars((string)$col['header']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="report-col-mid" role="group" aria-label="Move columns between lists">
          <button type="button" class="report-col-btn" id="report-cols-add" title="Add to selected">&rarr;</button>
          <button type="button" class="report-col-btn" id="report-cols-remove" title="Remove from selected">&larr;</button>
        </div>
        <div class="report-col-pane">
          <h3 class="report-column-section-title">Selected (table / CSV order)</h3>
          <select id="report-cols-selected" class="report-col-select" multiple size="14" aria-label="Selected columns">
            <?php foreach ($selectedFields as $field): ?>
              <?php if (!isset($byField[$field])) { continue; } ?>
              <?php
                $col = $byField[$field];
                $sectionTitle = '';
                foreach ($availableColumnSections as $section) {
                    foreach ($section['columns'] as $sc) {
                        if ((string)$sc['field'] === $field) {
                            $sectionTitle = (string)$section['title'];
                            break 2;
                        }
                    }
                }
              ?>
              <option value="<?= htmlspecialchars($field) ?>" data-section="<?= htmlspecialchars($sectionTitle) ?>">
                <?= htmlspecialchars((string)$col['header']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="report-col-mid" role="group" aria-label="Reorder selected columns">
          <button type="button" class="report-col-btn" id="report-cols-up" title="Move up">&uarr;</button>
          <button type="button" class="report-col-btn" id="report-cols-down" title="Move down">&darr;</button>
        </div>
      </div>
      <div class="report-columns-footer">
        <button type="submit" class="report-apply-cols">Apply columns</button>
      </div>
    </div>
  </form>

  <?php require __DIR__ . '/includes/report_results.php'; ?>

</div>
<script src="assets/site.js" defer></script>
</body>
</html>
