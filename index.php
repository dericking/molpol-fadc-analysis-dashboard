<?php
/**
 * index.php — run/group browse list.
 *
 * Panel placement: ?panel=top|side (default from config default_panel).
 * Data prep: includes/index_query.php
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/index_query.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($siteTitle) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="index-app">
<?php render_site_navbar(); ?>
<div class="wrap-wide index-shell">

  <header class="index-chrome">
    <h1><?= htmlspecialchars($siteTitle) ?></h1>
    <p class="subtitle">
      <?php if ($panel === 'top'): ?>
        Top Panel &middot; <a href="index.php<?= htmlspecialchars($qs(['panel' => 'side'])) ?>">Side Panel</a>
      <?php else: ?>
        <a href="index.php<?= htmlspecialchars($qs(['panel' => 'top'])) ?>">Top Panel</a> &middot; Side Panel
      <?php endif; ?>
      &middot; <a href="report.php">Report</a>
      &middot; <a href="help_howto.php">How-to</a>
    </p>

    <?php if ($panel === 'top'): ?>
      <?php
        $filtersVariant = 'top';
        require __DIR__ . '/includes/index_filters.php';
      ?>
    <?php endif; ?>
  </header>

  <div class="index-body">
    <?php if ($panel === 'side'): ?>
      <div class="index-layout">
        <aside class="index-panel" aria-label="Filters and display options">
          <?php
            $filtersVariant = 'side';
            require __DIR__ . '/includes/index_filters.php';
          ?>
        </aside>
        <main class="index-main">
          <?php require __DIR__ . '/includes/index_results.php'; ?>
        </main>
      </div>
    <?php else: ?>
      <main class="index-scroll">
        <?php require __DIR__ . '/includes/index_results.php'; ?>
      </main>
    <?php endif; ?>
  </div>

</div>
<script src="assets/site.js" defer></script>
</body>
</html>
