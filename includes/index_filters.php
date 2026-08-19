<?php
/**
 * includes/index_filters.php
 *
 * Filter form controls for index.php. Set before require:
 *   $filtersVariant — 'top' (horizontal bar) or 'side' (stacked panel)
 */
$filtersVariant = ($filtersVariant ?? 'top') === 'side' ? 'side' : 'top';
$formClass = $filtersVariant === 'side' ? 'filters filters-panel' : 'filters filters-top';
?>
<form class="<?= htmlspecialchars($formClass) ?>" method="get" action="index.php">
  <?php if ($view !== $defaultView): ?>
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
  <?php endif; ?>
  <?php if ($layout !== $defaultLayout): ?>
    <input type="hidden" name="layout" value="<?= htmlspecialchars($layout) ?>">
  <?php endif; ?>
  <?php if ($panel !== $defaultPanel): ?>
    <input type="hidden" name="panel" value="<?= htmlspecialchars($panel) ?>">
  <?php endif; ?>

  <?php if ($filtersVariant === 'side'): ?>
    <div class="panel-block">
      <h2 class="panel-heading">Display Options</h2>
      <div class="view-toggle" role="group" aria-label="List view">
        <a class="<?= $view === 'runs' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['view' => 'runs', 'run' => '', 'group' => ''])) ?>">Runs</a>
        <a class="<?= $view === 'groups' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['view' => 'groups', 'run' => '', 'group' => ''])) ?>">Groups</a>
      </div>
      <div class="view-toggle" role="group" aria-label="Display layout">
        <a class="<?= $layout === 'table' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['layout' => 'table'])) ?>">Table</a>
        <a class="<?= $layout === 'cards' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['layout' => 'cards'])) ?>">Cards</a>
      </div>
    </div>

    <div class="panel-block">
      <h2 class="panel-heading">Find</h2>
      <div class="search-row">
        <input class="id-search" type="text" name="run" inputmode="numeric" pattern="[0-9]*" size="7" value="<?= htmlspecialchars($runSearch) ?>" placeholder="run #" aria-label="Run number">
        <button type="submit" name="find" value="run">Find</button>
      </div>
      <div class="search-row">
        <input class="id-search" type="text" name="group" inputmode="numeric" pattern="[0-9]*" size="7" value="<?= htmlspecialchars($groupSearch) ?>" placeholder="group #" aria-label="Group number">
        <button type="submit" name="find" value="group">Find</button>
      </div>
    </div>

    <div class="panel-block">
      <h2 class="panel-heading">Filters</h2>
      <select name="type" onchange="this.form.submit()" aria-label="Type">
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
      <select name="experiment" onchange="this.form.submit()" aria-label="Experiment" <?= $experimentColumnMissing ? 'disabled' : '' ?>>
        <option value="">Experiments</option>
        <?php foreach ($experiments as $exp): ?>
          <?php $exp = (string)$exp; ?>
          <option value="<?= htmlspecialchars($exp) ?>" <?= $exp === $filterExperiment ? 'selected' : '' ?>>
            <?= htmlspecialchars($exp) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="panel-block">
      <h2 class="panel-heading">Dates From–To</h2>
      <input type="date" name="from" aria-label="From" value="<?= htmlspecialchars($filterDateFrom ?? '') ?>" onchange="this.form.submit()" <?= $dateColumnMissing ? 'disabled' : '' ?>>
      <input type="date" name="to" aria-label="To" value="<?= htmlspecialchars($filterDateTo ?? '') ?>" onchange="this.form.submit()" <?= $dateColumnMissing ? 'disabled' : '' ?>>
    </div>

    <?php if ($filtersActive): ?>
      <div class="panel-block panel-block-clear">
        <a class="panel-clear-btn" href="<?= htmlspecialchars($clearHref) ?>">Clear Filters</a>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <input type="checkbox" id="top-nav-collapse" class="top-nav-collapse-cb">
    <div class="top-nav-col top-nav-display">
      <div class="top-nav-heading-row">
        <h2 class="panel-heading">Display Options</h2>
        <div class="top-nav-actions">
          <?php if ($filtersActive): ?>
            <a class="top-nav-clear" href="<?= htmlspecialchars($clearHref) ?>">clear</a>
          <?php endif; ?>
          <label class="top-nav-collapse-label" for="top-nav-collapse">
            <span class="top-nav-collapse-hide">Collapse</span>
            <span class="top-nav-collapse-show">Expand</span>
          </label>
        </div>
      </div>
      <div class="top-nav-row">
        <div class="view-toggle" role="group" aria-label="List view">
          <a class="<?= $view === 'runs' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['view' => 'runs', 'run' => '', 'group' => ''])) ?>">Runs</a>
          <a class="<?= $view === 'groups' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['view' => 'groups', 'run' => '', 'group' => ''])) ?>">Groups</a>
        </div>
      </div>
      <div class="top-nav-row">
        <div class="view-toggle" role="group" aria-label="Display layout">
          <a class="<?= $layout === 'table' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['layout' => 'table'])) ?>">Table</a>
          <a class="<?= $layout === 'cards' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($qs(['layout' => 'cards'])) ?>">Cards</a>
        </div>
      </div>
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
        <select name="type" aria-label="Type" onchange="this.form.submit()">
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
  <?php endif; ?>
</form>
