<?php
/**
 * includes/index_results.php
 *
 * Date-bucketed run/group list body for index.php (shared by top/side panel).
 * Expects variables from index_query.php.
 */
?>
<?php if (!empty($typeColumnMissing)): ?>
  <?php
    render_status_message(
        'index_missing_type_column',
        'WARNING: ' . $typeTable . '.' . $typeColumn . ' is missing; type filter is disabled.'
    );
  ?>
<?php endif; ?>

<?php if (!empty($experimentColumnMissing)): ?>
  <?php
    render_status_message(
        'index_missing_experiment_column',
        'WARNING: Run_info.run_experiment is missing; experiment filter is disabled.'
    );
  ?>
<?php endif; ?>

<?php if (!$rows): ?>
  <?php
    $noMatchSummary = 'No ' . ($view === 'groups' ? 'groups' : 'runs') . ' match this filter.';
    render_status_message('index_no_match', $noMatchSummary);
  ?>
<?php endif; ?>

<?php $firstDateHeading = true; ?>
<?php foreach ($dateBuckets as $dateKey => $bucket): ?>
  <div class="date-heading<?= $firstDateHeading ? ' date-heading-first' : '' ?>">
    <div class="date-heading-title">
      <h2><?= htmlspecialchars($bucket['label']) ?></h2>
      <?php if (!empty($dateColumnMissing) && $dateKey === '0000-00-00'): ?>
        <?php render_status_info_link('index_missing_date_column'); ?>
      <?php endif; ?>
    </div>
    <span class="count"><?= count($bucket['items']) ?> <?= $view === 'groups'
        ? 'group' . (count($bucket['items']) === 1 ? '' : 's')
        : 'run' . (count($bucket['items']) === 1 ? '' : 's') ?></span>
  </div>
  <?php $firstDateHeading = false; ?>
  <?php if ($layout === 'table'): ?>
    <?php if ($view === 'groups'): ?>
      <?php render_group_table($bucket['items']); ?>
    <?php else: ?>
      <?php render_run_table($bucket['items']); ?>
    <?php endif; ?>
  <?php else: ?>
    <div class="card-grid">
      <?php foreach ($bucket['items'] as $item): ?>
        <?php if ($view === 'groups'): ?>
          <?php render_group_card($item); ?>
        <?php else: ?>
          <?php render_run_card($item); ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endforeach; ?>

<?php if ($hasMore): ?>
  <p class="cap-note">Showing the most recent <?= $rowCap ?> matching <?= $view === 'groups' ? 'groups' : 'runs' ?>. Narrow with a filter to see older ones.</p>
<?php endif; ?>
