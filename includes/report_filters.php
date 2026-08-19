<?php
/**
 * includes/report_filters.php
 *
 * Shared List / Find / Filters / Dates block for report.php and
 * report_advanced.php. Expects report_query.php variables, including
 * $reportScript and $qs. Does not open/close the <form>.
 */
?>
    <?php if ($view !== $defaultView): ?>
      <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
    <?php endif; ?>

    <div class="top-nav-col top-nav-display">
      <div class="top-nav-heading-row">
        <h2 class="panel-heading">List</h2>
        <div class="top-nav-actions">
          <?php if ($filtersActive): ?>
            <a class="top-nav-clear" href="<?= htmlspecialchars($clearHref) ?>">clear filters</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="top-nav-row">
        <div class="view-toggle" role="group" aria-label="Report subject">
          <a class="<?= $view === 'runs' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($reportScript . $qs(['view' => 'runs', 'run' => '', 'group' => ''])) ?>">Runs</a>
          <a class="<?= $view === 'groups' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($reportScript . $qs(['view' => 'groups', 'run' => '', 'group' => ''])) ?>">Groups</a>
        </div>
      </div>
      <div class="top-nav-row top-nav-row-spacer" aria-hidden="true"></div>
    </div>

    <div class="top-nav-col top-nav-find">
      <div class="top-nav-heading-row">
        <h2 class="panel-heading">Find</h2>
      </div>
      <div class="top-nav-row">
        <div class="search-row">
          <input class="id-search" type="text" name="run" inputmode="numeric" pattern="[0-9]*" size="7" value="<?= htmlspecialchars($runSearch) ?>" placeholder="run #" aria-label="Run number">
          <button type="submit" name="find" value="run">Find</button>
        </div>
      </div>
      <div class="top-nav-row">
        <div class="search-row">
          <input class="id-search" type="text" name="group" inputmode="numeric" pattern="[0-9]*" size="7" value="<?= htmlspecialchars($groupSearch) ?>" placeholder="group #" aria-label="Group number">
          <button type="submit" name="find" value="group">Find</button>
        </div>
      </div>
    </div>

    <div class="top-nav-col top-nav-filters">
      <div class="top-nav-heading-row">
        <h2 class="panel-heading">Filters</h2>
      </div>
      <div class="top-nav-row top-nav-type-exp">
        <select name="type" aria-label="Type" onchange="this.form.submit()" <?= $typeColumnMissing ? 'disabled' : '' ?>>
          <option value="">Run Types</option>
          <?php foreach ($types as $t): ?>
            <?php
              $typeCode  = is_array($t) ? (string)($t['code'] ?? '') : (string)$t;
              $typeLabel = is_array($t) ? (string)($t['label'] ?? $typeCode) : (string)$t;
            ?>
            <option value="<?= htmlspecialchars($typeCode) ?>" <?= $typeCode === $filterType ? 'selected' : '' ?>>
              <?= htmlspecialchars($typeLabel !== '' ? $typeLabel : $typeCode) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="experiment" aria-label="Experiment" onchange="this.form.submit()" <?= $experimentColumnMissing ? 'disabled' : '' ?>>
          <option value="">Experiments</option>
          <?php foreach ($experiments as $exp): ?>
            <?php $exp = (string)$exp; ?>
            <option value="<?= htmlspecialchars($exp) ?>" <?= $exp === $filterExperiment ? 'selected' : '' ?>>
              <?= htmlspecialchars($exp) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="top-nav-row top-nav-row-spacer" aria-hidden="true"></div>
    </div>

    <div class="top-nav-col top-nav-dates">
      <div class="top-nav-heading-row">
        <h2 class="panel-heading">Dates From–To</h2>
      </div>
      <div class="top-nav-row">
        <input type="date" name="from" aria-label="From" value="<?= htmlspecialchars($filterDateFrom ?? '') ?>" onchange="this.form.submit()" <?= $dateColumnMissing ? 'disabled' : '' ?>>
      </div>
      <div class="top-nav-row">
        <input type="date" name="to" aria-label="To" value="<?= htmlspecialchars($filterDateTo ?? '') ?>" onchange="this.form.submit()" <?= $dateColumnMissing ? 'disabled' : '' ?>>
      </div>
    </div>
