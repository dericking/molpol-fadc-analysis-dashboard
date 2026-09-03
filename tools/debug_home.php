<?php
/**
 * php54-debug landing page. Lists the probes for this branch.
 */
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>php54-debug probes</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="wrap-wide">
  <h1>php54-debug probes</h1>
  <p>This branch prints a red breadcrumb box on every page, and shows the real
     fatal/exception on the server-error page.</p>
  <ul>
    <li><a href="list_runs.php">list_runs.php</a> — live run_number values + detail links</li>
    <li><a href="php54_lint.php">php54_lint.php</a> — parse-check all site PHP files</li>
    <li><a href="db_probe.php">db_probe.php</a> — PDO + SELECT 1</li>
    <li><a href="../index.php">index.php</a> — main page (watch the debug box)</li>
    <li><a href="../help_errors.php">help_errors.php</a> — loads descriptions_errors.php</li>
  </ul>
  <p>PHP <?php echo htmlspecialchars(PHP_VERSION); ?></p>
</div>
</body>
</html>
