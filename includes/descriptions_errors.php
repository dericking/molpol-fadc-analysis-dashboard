<?php
/**
 * includes/descriptions_errors.php
 *
 * Catalog of on-page status / warning help text for caretakers and users.
 * Short "summary" lines appear inline; help_errors.php shows title, summary,
 * Description (body), and Fix (fix) — opened in a new tab via the ⓘ link
 * (no JavaScript).
 *
 * body and fix are trusted HTML (edited only in this file — not request input).
 * Use <p>, <ul>/<li>, <code>, etc. summary and title stay plain text.
 *
 * Keys are stable IDs used by render_status_message() / render_layout_errors().
 */

return [

    'empty_table_row' => [
        'summary' => 'No data recorded for this run.',
        'title'   => 'No table row for this run',
        'body'    => <<<'HTML'
<p>The detail page loaded the parent run, but there is no matching row in this
table (Analysis, DAQ_config, or EPICS_data) for that run_number.</p>
<p>This is normal when prompt or secondary processing has not written a row yet.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Confirm the run exists in Run_info.</li>
  <li>Confirm the analysis / DAQ / EPICS producer wrote a row for this run_number.</li>
  <li>If you expected data, inspect the database table for that run_number.</li>
</ul>
HTML
,
    ],

    'empty_group_analysis' => [
        'summary' => 'Group analysis has not been completed for this group yet.',
        'title'   => 'No Grouped_Analysis row',
        'body'    => <<<'HTML'
<p>Member runs may already reference this group_number in Run_info, but there is
not yet a Grouped_Analysis row (the landing spot for group_analysis.C results).</p>
<p>This is an expected state before final group analysis finishes.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Check Run_info.run_group for member runs.</li>
  <li>Confirm group_analysis.C (or your producer) has inserted Grouped_Analysis.</li>
</ul>
HTML
,
    ],

    'empty_group_runs' => [
        'summary' => 'No runs are assigned to this group yet.',
        'title'   => 'No member runs for this group',
        'body'    => <<<'HTML'
<p>A Grouped_Analysis row exists for this group_number, but no Run_info rows
currently have run_group set to that number.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Check Run_info.run_group values for the intended members.</li>
  <li>See whether runs were reassigned to another group after analysis was written.</li>
</ul>
HTML
,
    ],

    'group_missing_run_group_column' => [
        'summary' => 'WARNING: Run_info.run_group is missing; member runs cannot be listed.',
        'title'   => 'Group membership column missing',
        'body'    => <<<'HTML'
<p>The group page looks up member runs with
<code>SELECT * FROM Run_info WHERE run_group = &hellip;</code>.
That column was not found in <code>INFORMATION_SCHEMA</code>, so the query
was skipped and this page did not 500.</p>
<p>From the index this should not happen: group links exist because
<code>run_group</code> (or a layout field) is on the run row. Direct URLs or
a renamed/dropped FK can still reach this state.</p>
<p>Grouped_Analysis for this group_number may still render above. The
member-run list is empty because membership cannot be queried, not because
no runs are assigned.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Confirm <code>Run_info.run_group</code> still exists.</li>
  <li>If it was renamed, update the <code>WHERE</code> in
      <code>detail_groups.php</code> (and the Group cell in
      <code>includes/layouts/layout_tables.php</code> / Run Info if needed).</li>
</ul>
HTML
,
    ],

    'index_no_match' => [
        'summary' => 'No runs or groups match this filter.',
        'title'   => 'Nothing matched the index filters',
        'body'    => <<<'HTML'
<p>The Runs/Groups list query returned no rows for the current type, experiment,
and/or date filters, and/or run # / group # search.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Clear filters and browse again.</li>
  <li>Confirm the run or group number exists in the database.</li>
  <li>Remember the type filter matches the stored <em>code</em>
      (<code>POLARIZATION</code>, not the dropdown label
      &ldquo;Polarization&rdquo;). The dropdown shows lookup
      <code>display_label</code> and submits the code.</li>
  <li>Experiment is an exact match on <code>Run_info.run_experiment</code>
      (groups: any member run with that experiment).</li>
  <li>Dates are an inclusive calendar-day range on
      <code>run_start_datetime</code> / <code>group_start</code>.</li>
</ul>
HTML
,
    ],

    'report_no_match' => [
        'summary' => 'No rows match this report filter.',
        'title'   => 'Nothing matched the report filters',
        'body'    => <<<'HTML'
<p>The report query returned no rows for the current view and filters
(type, experiment, dates, and/or run # / group #).</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Clear filters or widen the date range.</li>
  <li>Confirm the run or group exists in the database.</li>
  <li>Type filter uses lookup <em>codes</em>; experiment is an exact match.</li>
</ul>
HTML
,
    ],

    'report_truncated' => [
        'summary' => 'WARNING: Report results were truncated at the row cap.',
        'title'   => 'Report row cap reached',
        'body'    => <<<'HTML'
<p>More rows matched the filters than <code>report_row_cap</code> in
<code>includes/config.php</code> allows. The table and CSV include only the
first cap rows (newest run/group numbers first).</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Narrow type, experiment, or date filters.</li>
  <li>Or raise <code>report_row_cap</code> in <code>includes/config.php</code> if appropriate.</li>
</ul>
HTML
,
    ],

    'report_unknown_column' => [
        'summary' => 'WARNING: layout_report references an unknown column; it was skipped.',
        'title'   => 'Report layout column missing from schema',
        'body'    => <<<'HTML'
<p>A field named in <code>layout_report</code> (<code>includes/layouts/layout_report.php</code> —
a section column or a <code>defaults</code> entry) does not exist on the live table for its
declared <code>source</code> (<code>run</code> → Run_info,
<code>analysis</code> → Analysis, <code>epics</code> → EPICS_data,
<code>daq</code> → DAQ_config, <code>group</code> → Grouped_Analysis), or
a default names a field that is not in the live catalog.</p>
<p>The report page keeps working: bad entries are omitted from the picker,
table, and CSV. This is a caretaker signal, not a user error.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Edit <code>layout_report</code> and correct field names and sources.</li>
  <li>Confirm the column exists in MariaDB (<code>INFORMATION_SCHEMA</code> / Don’s schema).</li>
  <li>Reload <code>report.php</code> or <code>report_advanced.php</code> (use <strong>reset columns</strong> if a sticky <code>cols=</code> URL is in play).</li>
</ul>
HTML
,
    ],

    'index_missing_type_column' => [
        'summary' => 'WARNING: Type column is missing; type filter is disabled.',
        'title'   => 'Index type column missing',
        'body'    => <<<'HTML'
<p>The run/group list expected a type column on this table
(<code>Run_info.run_type</code> or <code>Grouped_Analysis.group_type</code>)
and did not find it in <code>INFORMATION_SCHEMA</code>.</p>
<p>The list still loads. The type dropdown is empty and a type filter in the
URL is ignored, so the page does not 500.</p>
<p>Those columns are part of the data model and should always exist. This
warning is a safety net if they are renamed or dropped.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Confirm <code>run_type</code> / <code>group_type</code> still exist on
      <code>Run_info</code> / <code>Grouped_Analysis</code>.</li>
  <li>If they were renamed, update <code>includes/index_query.php</code> and
      <code>includes/layouts/layout_lookups.php</code> (and the card/table layouts)
      to the new names.</li>
  <li>Type labels come from <code>run_type_lookup</code> when that table
      exists; otherwise the dropdown lists distinct codes from the row
      column. Filter SQL still uses the code on the row.</li>
</ul>
HTML
,
    ],

    'index_missing_experiment_column' => [
        'summary' => 'WARNING: Experiment column is missing; experiment filter is disabled.',
        'title'   => 'Index experiment column missing',
        'body'    => <<<'HTML'
<p>The run/group list expected <code>Run_info.run_experiment</code> and did
not find it in <code>INFORMATION_SCHEMA</code>.</p>
<p>The list still loads. The experiment dropdown is empty and an experiment
filter in the URL is ignored, so the page does not 500.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Confirm <code>run_experiment</code> still exists on
      <code>Run_info</code>.</li>
  <li>If it was renamed, update <code>includes/index_query.php</code> (and
      any card/table layouts that show it) to the new name.</li>
  <li>If experiments move to a lookup table, keep a text column on the row
      (or JOIN) so the index still has a string to list and filter.</li>
</ul>
HTML
,
    ],

    'index_missing_date_column' => [
        'summary' => 'WARNING: Start-time column is missing; rows are grouped under Unknown date.',
        'title'   => 'Index date column missing',
        'body'    => <<<'HTML'
<p>The run/group list groups rows by calendar day using
<code>Run_info.run_start_datetime</code> or <code>Grouped_Analysis.group_start</code>.
That column was not found in <code>INFORMATION_SCHEMA</code>.</p>
<p>The list still loads. Every row is placed under a single
<strong>Unknown date</strong> heading so the page does not 500. Cards and
tables may still show times if their layout fields point at other columns.
A from/to date filter in the URL is ignored until the column is back.</p>
<p>Rows can also land under Unknown date when the start stamp is empty or
not ISO / Linux <code>date</code> text. The ⓘ on the heading is only shown
when the column itself is missing, so those two cases stay distinct.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Confirm <code>run_start_datetime</code> / <code>group_start</code> still exist
      on <code>Run_info</code> / <code>Grouped_Analysis</code>.</li>
  <li>If they were renamed, update the bucket key in
      <code>includes/index_query.php</code> (and the time cells in
      <code>includes/layouts/layout_cards.php</code> / <code>includes/layouts/layout_tables.php</code> if
      needed).</li>
</ul>
HTML
,
    ],

    'plot_bad_base' => [
        'summary' => 'WARNING: Plot directory could not be located.',
        'title'   => 'Plot base path problem',
        'body'    => <<<'HTML'
<p>The site could not use a usable plots root directory. This is a configuration
problem (not merely “this run has no plots folder”).</p>
<p>Typical causes include an empty filesystem base with an absolute web URL, a
missing DOCUMENT_ROOT when using a site-relative web base, a missing or
unreadable plots root, or an empty web base when images need links.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Edit <code>includes/config.php</code> plot path keys (web + filesystem bases).</li>
  <li>Ensure the web server can read the plots root on disk.</li>
  <li>For absolute web URLs, set an explicit <code>*_plots_fs_base</code>.</li>
</ul>
HTML
,
    ],

    'plot_missing_id' => [
        'summary' => 'Plot directory for these plots has not been created.',
        'title'   => 'No per-id plot folder',
        'body'    => <<<'HTML'
<p>The plots root is configured and readable, but there is no subdirectory for
this run_number or group_number yet.</p>
<p>That is often normal: not every analysis produces plots, and folders may be
created only when images are written.</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Confirm whether the plot producer created <code>{plots_base}/{id}/</code>.</li>
  <li>Check that <code>includes/config.php</code> bases point at the intended tree.</li>
</ul>
HTML
,
    ],

    'plot_empty' => [
        'summary' => 'No plots available.',
        'title'   => 'Plot folder is empty',
        'body'    => <<<'HTML'
<p>The per-id plot directory exists and is readable, but it contains no recognized
image files (png, jpg, jpeg, gif, webp, svg).</p>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Confirm files were written into the correct <code>{plots_base}/{id}/</code> folder.</li>
  <li>Use a supported image extension on those filenames.</li>
</ul>
HTML
,
    ],

    'layout_band_invalid' => [
        'summary' => 'WARNING: A layouts band in includes/layouts/layout_sections.php is not an array of rows.',
        'title'   => 'Invalid layouts band shape',
        'body'    => <<<'HTML'
<p>Each entry under <code>layouts</code> (for example <code>main</code> or <code>other</code>) must be an array of rows.
Each row is itself an array of classifier section titles (use <code>null</code> for a spacer).</p>
<p><strong>Correct:</strong></p>
<pre>'main' => [
    ['Section A', 'Section B'],
],</pre>
<p><strong>Incorrect:</strong></p>
<pre>'main' => 'Section A',</pre>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Edit <code>includes/layouts/layout_sections.php</code> and correct the band shape.</li>
  <li>Reload the detail page.</li>
  <li>Sections that could not be placed may appear under Unallocated Sections until fixed.</li>
</ul>
HTML
,
    ],

    'layout_flat_list' => [
        'summary' => 'WARNING: A layouts band has a flat list of section titles.',
        'title'   => 'Flat layouts list (needs rows-of-rows)',
        'body'    => <<<'HTML'
<p>A layouts band listed section titles directly instead of wrapping them in a
row array. The page continues, but that band’s misplaced entries are skipped
and those sections may show under Unallocated Sections.</p>
<p><strong>Correct:</strong></p>
<pre>'main' => [
    ['Section A', 'Section B'],
],</pre>
<p><strong>Incorrect:</strong></p>
<pre>'main' => [
    'Section A',
    'Section B',
],</pre>
HTML
,
        'fix'     => <<<'HTML'
<ul>
  <li>Edit <code>includes/layouts/layout_sections.php</code> for the band named in the on-page warning.</li>
  <li>Wrap section titles in a row array, then reload the detail page.</li>
</ul>
HTML
,
    ],

];
