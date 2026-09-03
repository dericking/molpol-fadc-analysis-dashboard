<?php
/**
 * tools/list_runs.php  — php54-debug
 *
 * Lists run_number values from Run_info so you can open live detail pages.
 * Uses the same bootstrap / PDO path as the site.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (function_exists('debug_step')) {
    debug_step('list_runs.php: after bootstrap');
}

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

header('Content-Type: text/html; charset=utf-8');

$cap = 200;
$hasRunInfo = table_exists($pdo, 'Run_info');
$hasGroupCol = $hasRunInfo && table_has_column($pdo, 'Run_info', 'run_group');
$startCol = $hasRunInfo
    ? first_present_column($pdo, 'Run_info', array('run_start_datetime', 'run_start'))
    : null;
$hasStartCol = ($startCol !== null);
$hasTypeCol  = $hasRunInfo && table_has_column($pdo, 'Run_info', 'run_type');

$rows = array();
$total = 0;
$error = null;

if (!$hasRunInfo) {
    $error = 'Run_info table was not found in this database.';
} else {
    try {
        $total = (int)$pdo->query('SELECT COUNT(*) FROM Run_info')->fetchColumn();
        $cols = array('`run_number`');
        if ($hasGroupCol) {
            $cols[] = '`run_group`';
        }
        if ($hasStartCol) {
            $cols[] = '`' . $startCol . '`';
        }
        if ($hasTypeCol) {
            $cols[] = '`run_type`';
        }
        $sql = 'SELECT ' . implode(', ', $cols)
            . ' FROM Run_info ORDER BY run_number DESC LIMIT ' . (int)$cap;
        $rows = $pdo->query($sql)->fetchAll();
        if (function_exists('debug_step')) {
            debug_step('list_runs.php: fetched ' . count($rows) . ' of ' . $total);
        }
    } catch (Exception $e) {
        $error = 'Query failed: ' . $e->getMessage();
    }
}

$siteTitle = isset($config['site_title']) ? $config['site_title'] : 'Møller Run Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($siteTitle); ?> — live run numbers</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="wrap-wide">
  <h1>Live run numbers <small>(php54-debug)</small></h1>
  <p class="subtitle">
    <a href="debug_home.php">Debug probes</a>
    · <a href="../index.php">index.php</a>
    · <a href="../help_howto.php">How-to</a>
  </p>
<?php if ($error !== null): ?>
  <p class="empty-state"><?php echo htmlspecialchars($error); ?></p>
<?php elseif ($rows === array()): ?>
  <p class="empty-state">Run_info is empty.</p>
<?php else: ?>
  <p><?php echo (int)$total; ?> run<?php echo $total === 1 ? '' : 's'; ?> in
     <code>Run_info</code><?php
        if ($total > $cap) {
            echo ' — showing the ' . (int)$cap . ' highest run_number values';
        }
     ?>.</p>
  <div class="list-table-wrap">
    <table class="list-table">
      <thead>
        <tr>
          <th>Run Number</th>
          <?php if ($hasGroupCol): ?><th>Group</th><?php endif; ?>
          <?php if ($hasStartCol): ?><th>Start</th><?php endif; ?>
          <?php if ($hasTypeCol): ?><th>Type</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <?php $n = (int)$row['run_number']; ?>
        <tr class="list-table-row">
          <td>
            <a class="list-table-link" href="../detail_runs.php?run=<?php echo $n; ?>">
              <span class="run-number"><?php echo $n; ?></span>
            </a>
            · <a href="../detail_daq.php?run=<?php echo $n; ?>">DAQ</a>
            · <a href="../detail_epics.php?run=<?php echo $n; ?>">EPICS</a>
          </td>
          <?php if ($hasGroupCol): ?>
            <td><?php
              $g = isset($row['run_group']) ? $row['run_group'] : null;
              if ($g !== null && $g !== '') {
                  $gi = (int)$g;
                  echo '<a class="list-table-link" href="../detail_groups.php?group='
                      . $gi . '"><span class="run-group">' . $gi . '</span></a>';
              } else {
                  echo '—';
              }
            ?></td>
          <?php endif; ?>
          <?php if ($hasStartCol): ?>
            <td><?php echo htmlspecialchars(isset($row[$startCol]) ? (string)$row[$startCol] : '—'); ?></td>
          <?php endif; ?>
          <?php if ($hasTypeCol): ?>
            <td><?php echo htmlspecialchars(isset($row['run_type']) ? (string)$row['run_type'] : '—'); ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>
</body>
</html>
