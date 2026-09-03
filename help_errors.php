<?php
/**
 * help_errors.php — caretaker/user help for on-page status keys.
 * Opened in a new tab from the ⓘ link (no JavaScript modal).
 *
 * Query parameter: key (optional)
 *   - omitted / empty → catalog index: left nav + every topic on one page
 *   - valid key       → single-topic page (same Description / Fix shell)
 *   - unknown key     → 404 with the same shell
 *
 * body / fix from descriptions_errors.php are trusted HTML (catalog only).
 */
require_once __DIR__ . '/includes/bootstrap.php';
debug_step('help_errors.php: after bootstrap');

$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
$catalog = get_error_descriptions();
debug_step('help_errors.php: catalog loaded keys=' . count($catalog));
$isIndex = ($key === '');

if ($isIndex) {
    $pageTitle = 'Status message help';
} elseif (isset($catalog[$key])) {
    $entry     = $catalog[$key];
    $pageTitle = (string)(isset($entry['title']) ? $entry['title'] : $key);
    $title     = $pageTitle;
    $summary   = (string)(isset($entry['summary']) ? $entry['summary'] : '');
    $body      = trim((string)(isset($entry['body']) ? $entry['body'] : ''));
    $fix       = trim((string)(isset($entry['fix']) ? $entry['fix'] : ''));
} else {
    http_response_code(404);
    $entry     = null;
    $pageTitle = 'Help not found';
    $title     = 'Help topic not found';
    $summary   = 'Unknown help key.';
    $body      = '<p>That <code>key</code> is not in the status-help catalog.</p>'
        . '<p>Open <a href="help_errors.php">all status topics</a>, or use the circled “i” '
        . 'next to an on-page status message.</p>';
    $fix       = '';
}

/**
 * Render one catalog topic (Description + optional Fix).
 *
 * @param array{title?:string,summary?:string,body?:string,fix?:string} $entry
 */
function render_help_topic($topicKey, $entry, $asSection = false){
    $title   = (string)(isset($entry['title']) ? $entry['title'] : $topicKey);
    $summary = (string)(isset($entry['summary']) ? $entry['summary'] : '');
    $body    = trim((string)(isset($entry['body']) ? $entry['body'] : ''));
    $fix     = trim((string)(isset($entry['fix']) ? $entry['fix'] : ''));

    if ($asSection) {
        echo '<article class="help-topic" id="' . htmlspecialchars($topicKey) . '">';
        echo '<h2 class="help-topic-title">' . htmlspecialchars($title) . '</h2>';
        echo '<p class="help-topic-meta"><code>' . htmlspecialchars($topicKey) . '</code>';
        if ($summary !== '') {
            echo ' — ' . htmlspecialchars($summary);
        }
        echo '</p>';
    }

    render_section_header('Description');
    echo '<div class="help-prose">' . $body . '</div>';

    if ($fix !== '') {
        render_section_header('Fix');
        echo '<div class="help-prose">' . $fix . '</div>';
    }

    if ($asSection) {
        echo '</article>';
    }
}
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
<div class="wrap-wide<?= $isIndex ? '' : ' wrap-help' ?>">

<?php if ($isIndex): ?>

<h1><?= htmlspecialchars($pageTitle) ?></h1>
<p class="subtitle">
  <a href="index.php">« Back to Main Page</a>
  &middot;
  <a href="help_howto.php">How-to</a>
</p>
<p class="subtitle">Catalog of on-page status messages (query parameter <code>key</code> selects one topic).</p>

<div class="help-layout">
  <nav class="help-nav" aria-label="Status help topics">
    <p class="panel-heading">Topics</p>
    <ul>
      <?php foreach ($catalog as $navKey => $navEntry): ?>
        <li>
          <a href="#<?= htmlspecialchars((string)$navKey) ?>">
            <?= htmlspecialchars((string)(isset($navEntry['title']) ? $navEntry['title'] : $navKey)) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <div class="help-main">
    <?php foreach ($catalog as $topicKey => $topicEntry): ?>
      <?php render_help_topic((string)$topicKey, $topicEntry, true); ?>
    <?php endforeach; ?>
  </div>
</div>

<?php else: ?>

<h1><?= htmlspecialchars($title) ?></h1>
<p class="subtitle">
  <a href="index.php">« Back to Main Page</a>
  &middot;
  <a href="help_howto.php">How-to</a>
  &middot;
  <a href="help_errors.php">All status topics</a>
</p>
<?php if ($summary !== ''): ?>
  <p class="subtitle"><?= htmlspecialchars($summary) ?></p>
<?php endif; ?>
<?php if ($entry !== null): ?>
  <p class="help-topic-meta"><code><?= htmlspecialchars($key) ?></code></p>
<?php endif; ?>

<?php
if ($entry !== null) {
    render_help_topic($key, $entry, false);
} else {
    render_section_header('Description');
    echo '<div class="help-prose">' . $body . '</div>';
}
?>

<?php endif; ?>

</div>
</body>
</html>
