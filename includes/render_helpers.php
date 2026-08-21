<?php
/**
 * includes/render_helpers.php
 *
 * Public entry for shared helpers. Pages and bootstrap require this file only;
 * implementation lives in helpers_*.php (load order respects dependencies).
 */

require_once __DIR__ . '/helpers_datetime.php';
require_once __DIR__ . '/helpers_classify.php';
require_once __DIR__ . '/helpers_render.php';
require_once __DIR__ . '/helpers_plots.php';
require_once __DIR__ . '/helpers_report.php';
