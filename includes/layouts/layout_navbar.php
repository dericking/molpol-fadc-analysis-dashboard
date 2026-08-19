<?php
/**
 * includes/layouts/layout_navbar.php
 *
 * Optional master top navigation bar for every page (site-wide links).
 *
 * =============================================================================
 * CARETAKER INSTRUCTIONS
 * =============================================================================
 *
 * File to edit for *links*: includes/layouts/layout_navbar.php only.
 *
 * When `links` is empty, the bar is not rendered at all.
 * Fill `links` with href + label pairs to show a horizontal bar at the top
 * of each page (handy for linking into the broader polarimeter site).
 *
 * --- links ---
 * Ordered list of:
 *   ['href' => 'https://… or relative.php', 'label' => 'Display name']
 * Entries missing href or label are skipped.
 *
 * This file is the intentional place for hrefs (unlike other layout files).
 *
 * --- Colors ---
 * Do not put colors here. Edit the CSS variables in assets/style.css (:root):
 *
 *   --site-nav-bg          Bar background
 *   --site-nav-text        Link / label text
 *   --site-nav-hover       Link hover background
 *   --site-nav-hover-text  Link hover text
 *   --site-nav-border      Left / bottom / right border (no top)
 *
 * Named CSS colors and #hex are fine there.
 * See: https://www.w3schools.com/cssref/css_colors.php
 *
 * The bar uses the same max width as page content (centered).
 *
 * After editing, reload any page; no rebuild step.
 * =============================================================================
 */

return [
    // Empty = no navbar. Example:
    //
    // 'links' => [
    //     ['href' => 'https://hallaweb.jlab.org/equipment/moller/index.html', 'label' => 'Moller Polarimeter Home'],
    //     ['href' => 'index.php', 'label' => 'Run Log'],
    // ],
    'links' => [
        ['href' => 'https://hallaweb.jlab.org/equipment/moller/index.html', 'label' => 'Moller Polarimeter Home'],
    ],
];
