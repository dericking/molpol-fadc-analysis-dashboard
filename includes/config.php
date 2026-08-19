<?php
/**
 * includes/config.php
 *
 * Site-wide defaults and paths. Edit this file for caretaker knobs —
 * not index.php or the detail pages.
 *
 * No database credentials here (those live in includes/dbconnect-template.php, or the deployed copy it is cloned to).
 * No card/table column layouts here (those live in includes/layouts/).
 */

return [
    // Index browse limit (not a full export).
    'row_cap' => 300,

    // Report page / CSV row limit (higher than browse).
    'report_row_cap' => 2000,

    // Default index toggles when the URL omits ?view= / ?layout= / ?panel=
    'default_view'   => 'runs',   // runs | groups
    'default_layout' => 'table',  // table | cards
    'default_panel'  => 'side',   // top | side  (filter bar placement)

    'site_title' => 'Møller Polarimetry Run Log',

    // Web-accessible bases for plot images (trailing slash optional).
    // Images live under: {web_base}/{run_number}/ or {web_base}/{group_number}/
    // Examples: '/molpol/plots/runs/', 'https://hallawww.jlab.org/…/plots/groups/'
    'run_plots_web_base'   => '/plots/runs/',
    'group_plots_web_base' => '/plots/groups/',

    // Filesystem directories that map to the web bases (used to scan for images).
    // Leave empty to try DOCUMENT_ROOT + relative web_base (site-relative paths only).
    // Absolute URLs as web_base require an explicit fs_base here.
    'run_plots_fs_base'   => '',
    'group_plots_fs_base' => '',
];
