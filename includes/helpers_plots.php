<?php
/**
 * includes/helpers_plots.php
 *
 * Plot directory resolution and analysis plot rendering.
 */

/**
 * Normalize a path/URL base to end with a single trailing slash (or empty).
 */
function plots_base_slash($base)
{
    $base = trim($base);
    if ($base === '') {
        return '';
    }
    return rtrim($base, '/') . '/';
}

/**
 * Resolve the on-disk plots location: {base}/{id}/.
 * If fs_base is empty and web_base is site-relative, try DOCUMENT_ROOT + web_base/{id}/.
 *
 * @return array{status: 'ok'|'missing_id'|'bad_base', dir: ?string}
 *   ok         — id directory exists and is readable (dir set)
 *   missing_id — plots base is OK; this id's subfolder was never created
 *   bad_base   — base unusable / missing / unreadable, or id dir unreadable
 */
function resolve_plots_directory($fsBase, $webBase, $id)
{
    $subdir = (string)$id;
    $fsBase = trim($fsBase);
    $baseDir = null;
    $dir = null;

    if ($fsBase !== '') {
        $baseDir = rtrim($fsBase, "/\\");
        $dir = $baseDir . DIRECTORY_SEPARATOR . $subdir;
    } else {
        $webBase = trim($webBase);
        if ($webBase === '' || preg_match('#^https?://#i', $webBase)) {
            return array('status' => 'bad_base', 'dir' => null);
        }
        $docRoot = rtrim((string)(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ''), "/\\");
        if ($docRoot === '') {
            return array('status' => 'bad_base', 'dir' => null);
        }
        $path = parse_url($webBase, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $webBase;
        }
        $rel = trim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $baseDir = $docRoot . DIRECTORY_SEPARATOR . $rel;
        $dir = $baseDir . DIRECTORY_SEPARATOR . $subdir;
    }

    if ($baseDir === null || $dir === null || !is_dir($baseDir) || !is_readable($baseDir)) {
        return array('status' => 'bad_base', 'dir' => null);
    }
    if (!is_dir($dir)) {
        return array('status' => 'missing_id', 'dir' => null);
    }
    if (!is_readable($dir)) {
        return array('status' => 'bad_base', 'dir' => null);
    }
    return array('status' => 'ok', 'dir' => $dir);
}

/**
 * List image files in a plots directory, alphanumeric (natural) order.
 * @return list<string> basenames only
 */
function list_plot_image_files($dir)
{
    // Raster formats only. SVG is deliberately absent: it can carry script,
    // and the gallery links each plot directly, so clicking one would open it
    // as a same-origin document. Apache types these files from the extension,
    // so a renamed SVG is declared image/* and never executes. ROOT can emit
    // SVG (SaveAs("plot.svg")) — do not add it back without also changing how
    // the plot is linked.
    $allowed = array('png', 'jpg', 'jpeg', 'gif', 'webp');
    $files = array();
    $entries = @scandir($dir);
    if ($entries === false) {
        return array();
    }
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $files[] = $name;
    }
    natcasesort($files);
    return array_values($files);
}

/**
 * Render analysis plots for a run or group.
 * $kind: 'run' | 'group' — selects config keys *_plots_web_base / *_plots_fs_base.
 *
 * - bad_base (config / parent root) → hard WARNING
 * - missing id subfolder → softer “not been created”
 * - id dir OK but no images → soft “No plots available.”
 * - Otherwise images in alphanumeric order under web_base/{id}/
 */
function render_analysis_plots($config, $kind, $id)
{
    $webKey = $kind === 'group' ? 'group_plots_web_base' : 'run_plots_web_base';
    $fsKey  = $kind === 'group' ? 'group_plots_fs_base' : 'run_plots_fs_base';
    $webBase = plots_base_slash((string)(isset($config[$webKey]) ? $config[$webKey] : ''));
    $fsBase  = (string)(isset($config[$fsKey]) ? $config[$fsKey] : '');

    $resolved = resolve_plots_directory($fsBase, $webBase, $id);
    if ($resolved['status'] === 'bad_base') {
        render_status_message('plot_bad_base');
        return;
    }
    if ($resolved['status'] === 'missing_id') {
        render_status_message('plot_missing_id');
        return;
    }

    $dir = isset($resolved['dir']) ? $resolved['dir'] : null;
    if ($dir === null) {
        render_status_message('plot_bad_base');
        return;
    }

    $files = list_plot_image_files($dir);
    if (!$files) {
        render_status_message('plot_empty');
        return;
    }

    if ($webBase === '') {
        render_status_message('plot_bad_base');
        return;
    }

    echo '<div class="plot-gallery">';
    foreach ($files as $name) {
        $src = $webBase . $id . '/' . rawurlencode($name);
        $alt = pathinfo($name, PATHINFO_FILENAME);
        echo '<figure class="plot-item">';
        echo '<a href="' . htmlspecialchars($src) . '" target="_blank" rel="noopener">';
        echo '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '" loading="lazy">';
        echo '</a>';
        echo '<figcaption>' . htmlspecialchars($name) . '</figcaption>';
        echo '</figure>';
    }
    echo '</div>';
}
