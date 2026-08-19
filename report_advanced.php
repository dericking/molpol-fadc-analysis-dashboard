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
require_once __DIR__ . '/includes/report_query.php';

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
<script>
(function () {
  var dual = document.getElementById('report-col-dual');
  var form = dual && dual.closest('form');
  var available = document.getElementById('report-cols-available');
  var selected = document.getElementById('report-cols-selected');
  if (!dual || !form || !available || !selected) {
    return;
  }
  dual.hidden = false;

  function selectedOptions(sel) {
    return Array.prototype.slice.call(sel.selectedOptions || [], 0);
  }

  function findOptgroup(select, label) {
    var groups = select.getElementsByTagName('optgroup');
    for (var i = 0; i < groups.length; i++) {
      if (groups[i].label === label) {
        return groups[i];
      }
    }
    var g = document.createElement('optgroup');
    g.label = label || 'Other';
    select.appendChild(g);
    return g;
  }

  function pruneEmptyOptgroups(select) {
    var groups = Array.prototype.slice.call(select.getElementsByTagName('optgroup'), 0);
    groups.forEach(function (g) {
      if (!g.querySelector('option')) {
        g.parentNode.removeChild(g);
      }
    });
  }

  function catalogInsert(option) {
    var section = option.getAttribute('data-section') || 'Other';
    var group = findOptgroup(available, section);
    group.appendChild(option);
  }

  function moveToSelected() {
    selectedOptions(available).forEach(function (opt) {
      selected.appendChild(opt);
      opt.selected = false;
    });
    pruneEmptyOptgroups(available);
  }

  function moveToAvailable() {
    selectedOptions(selected).forEach(function (opt) {
      catalogInsert(opt);
      opt.selected = false;
    });
  }

  function moveSelected(delta) {
    var opts = selectedOptions(selected);
    if (!opts.length) {
      return;
    }
    if (delta < 0) {
      opts.forEach(function (opt) {
        var prev = opt.previousElementSibling;
        if (prev) {
          selected.insertBefore(opt, prev);
        }
      });
    } else {
      for (var i = opts.length - 1; i >= 0; i--) {
        var opt = opts[i];
        var next = opt.nextElementSibling;
        if (next) {
          selected.insertBefore(next, opt);
        }
      }
    }
  }

  function prepareSubmit() {
    Array.prototype.forEach.call(selected.options, function (opt) {
      opt.selected = true;
    });
    Array.prototype.forEach.call(available.options, function (opt) {
      opt.selected = false;
    });
    selected.setAttribute('name', 'cols[]');
    available.removeAttribute('name');
  }

  document.getElementById('report-cols-add').addEventListener('click', moveToSelected);
  document.getElementById('report-cols-remove').addEventListener('click', moveToAvailable);
  document.getElementById('report-cols-up').addEventListener('click', function () { moveSelected(-1); });
  document.getElementById('report-cols-down').addEventListener('click', function () { moveSelected(1); });

  available.addEventListener('dblclick', moveToSelected);
  selected.addEventListener('dblclick', moveToAvailable);

  form.addEventListener('submit', prepareSubmit);
})();
</script>
</body>
</html>
