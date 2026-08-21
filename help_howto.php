<?php
/**
 * help_howto.php — caretaker how-to (schema changes + layouts).
 * Left nav + one long page (same shell as help_errors.php catalog index).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = ((string)($config['site_title'] ?? 'Møller Run Log')) . ' — How-to';
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
<div class="wrap-wide">

  <h1>How-to</h1>
  <p class="subtitle">
    <a href="index.php">Browse</a>
    &middot; How-to
    &middot; <a href="help_errors.php">Status help</a>
  </p>
  <p class="subtitle">
    What the site picks up from MariaDB automatically, what you must name in
    <code>includes/layouts/</code>, and how each layout file shapes the page.
  </p>

  <div class="help-layout">
    <nav class="help-nav" aria-label="How-to sections">
      <p class="panel-heading">Sections</p>
      <ul>
        <li><a href="#agnostic">Agnostic vs required</a>
          <ul>
            <li><a href="#non-agnostic">Non-agnostic</a></li>
            <li><a href="#agnostic-ok">Agnostic</a></li>
          </ul>
        </li>
        <li><a href="#new-page">New page</a></li>
        <li><a href="#after-schema">After updating schema</a>
          <ul>
            <li><a href="#schema-to-site">Schema → website</a></li>
            <li><a href="#schema-rename">Rename</a></li>
            <li><a href="#schema-add">Addition</a></li>
            <li><a href="#schema-delete">Deletion</a></li>
            <li><a href="#schema-lookups">Type / quality codes</a></li>
            <li><a href="#local-docker">Local Docker / test</a></li>
            <li><a href="#verify">Quick verification</a></li>
          </ul>
        </li>
        <li><a href="#layouts">Managing layouts</a>
          <ul>
            <li><a href="#cell-kinds">Cell kinds, <code>id</code>, <code>link</code></a></li>
            <li><a href="#value-err">value ± error</a></li>
            <li><a href="#layout-sections">sections</a></li>
            <li><a href="#layout-tables">tables</a></li>
            <li><a href="#layout-cards">cards</a></li>
            <li><a href="#layout-lookups">lookups</a></li>
            <li><a href="#layout-run-summary">run / group summary</a></li>
            <li><a href="#layout-report">report</a></li>
            <li><a href="#layout-navbar">site navbar</a></li>
          </ul>
        </li>
        <li><a href="#where-to-edit">Where to edit</a></li>
      </ul>
    </nav>

    <div class="help-main help-prose">

      <article class="help-topic" id="agnostic">
        <h2 class="help-topic-title">Agnostic vs required</h2>
        <p>
          Principle: pages should <strong>degrade gracefully</strong>, not 500, when
          columns appear or disappear. Prefer layout edits under
          <code>includes/layouts/</code> over changing engine PHP.
        </p>
        <p>
          The live MariaDB schema is owned <strong>outside</strong> this site. The site
          reads column lists from <code>INFORMATION_SCHEMA</code> on every request.
          Many physics / DAQ / EPICS fields appear without a PHP edit. Filters,
          joins, and “which fields show on the index / report” still name columns
          explicitly — those are the exceptions.
        </p>

        <h3 id="non-agnostic">Non-agnostic (must stay in sync with SQL)</h3>
        <p>
          If you rename or drop one of these, layouts alone are not enough —
          query / page PHP expects the old name and will warn, join wrong, or
          break.
        </p>
        <p><strong>Identity and joins</strong></p>
        <table class="howto-mini-table">
          <thead>
            <tr><th>Item</th><th>Why it matters</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Tables <code>Run_info</code>, <code>DAQ_config</code>, <code>EPICS_data</code>, <code>Analysis</code>, <code>Grouped_Analysis</code></td>
              <td>Page / join targets</td>
            </tr>
            <tr>
              <td>PKs <code>run_number</code>, <code>group_number</code></td>
              <td>Detail URLs, FKs, joins</td>
            </tr>
            <tr>
              <td><code>Run_info.run_group</code></td>
              <td>Group membership, member list, group experiment filter</td>
            </tr>
          </tbody>
        </table>
        <p><strong>Index / report filters</strong> (soft WARNING if missing; control disabled):</p>
        <table class="howto-mini-table">
          <thead>
            <tr><th>Column</th><th>Role</th></tr>
          </thead>
          <tbody>
            <tr><td><code>run_type</code> / <code>group_type</code></td><td>Type filter (labels from <code>run_type_lookup</code> when present)</td></tr>
            <tr><td><code>run_experiment</code></td><td>Experiment filter</td></tr>
            <tr><td><code>run_start</code> / <code>group_start</code></td><td>Date buckets and from/to filter</td></tr>
          </tbody>
        </table>
        <p><strong>Explicit layout catalogs</strong> (not auto-discovered):</p>
        <table class="howto-mini-table">
          <thead>
            <tr><th>File</th><th>Exception</th></tr>
          </thead>
          <tbody>
            <tr><td><code>layout_navbar.php</code></td><td>Optional master top bar: list of <code>href</code> + <code>label</code>; empty <code>links</code> → bar hidden. Colors via <code>--site-nav-*</code> in <code>assets/style.css</code>.</td></tr>
            <tr><td><code>layout_report.php</code></td><td>Report/CSV columns are an explicit catalog + <code>defaults</code>. New physics fields do <strong>not</strong> appear until listed (with correct <code>source</code>).</td></tr>
            <tr><td><code>layout_cards</code> / <code>layout_tables</code> / <code>layout_run_summary</code></td><td>Index and modal cells name fields explicitly.</td></tr>
            <tr><td><code>layout_lookups.php</code></td><td>Which row columns use which lookup; quality → CSS slug.</td></tr>
            <tr><td><code>layout_sections.php</code></td><td>Grouping only; unmatched → Other/Unallocated, not auto-sectioned by physics meaning.</td></tr>
          </tbody>
        </table>
        <p>
          <strong>Hard-wired feature pages:</strong> any page that selects a
          <em>fixed</em> column for plots or math (e.g. a shift-check that reads
          a specific asymmetry field) must be updated when that column is
          renamed. Document the field name in that page’s query include.
        </p>
        <p>
          <strong>Naming conventions:</strong> detail grouping often relies on
          stable prefixes or documented regex families. Ad-hoc renames that break
          the convention force hand-maintained rules in <code>layout_sections.php</code>.
          Prefer agreeing naming with the schema owner when possible.
        </p>

        <h3 id="agnostic-ok">Agnostic — what happens automatically</h3>
        <table class="howto-mini-table">
          <thead>
            <tr><th>Change</th><th>Automatic behavior</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Add</strong> a column with a useful <code>COLUMN_COMMENT</code></td>
              <td>Appears on the matching detail page; comment is the label (EPICS: <code>[PV: …]</code> when present).</td>
            </tr>
            <tr>
              <td><strong>Add</strong> with no classifier match</td>
              <td>Still appears under <strong>Other</strong> / <strong>Unallocated Sections</strong> — does not vanish.</td>
            </tr>
            <tr>
              <td><strong>Drop</strong> a column only referenced in layouts</td>
              <td>Report: soft WARNING / skipped. Elsewhere: blank “—” / missing fields. Page should keep loading.</td>
            </tr>
            <tr>
              <td><strong>Change only</strong> <code>COLUMN_COMMENT</code></td>
              <td>New label/PV on next page load. No site file change required.</td>
            </tr>
            <tr>
              <td><code>foo</code> + <code>foo_err</code></td>
              <td>Detail cards render as one row: <code>value ± error</code> (see <a href="#value-err">below</a>).</td>
            </tr>
          </tbody>
        </table>
        <p>
          <strong>Comments are load-bearing.</strong> Do not strip
          <code>COLUMN_COMMENT</code>s; the UI has no second label dictionary for
          arbitrary columns. Caretaker presentation edits belong under
          <code>includes/layouts/</code> — no URLs or DB passwords there.
        </p>
      </article>

      <article class="help-topic" id="new-page">
        <h2 class="help-topic-title">Creating a new page</h2>
        <p>
          Introspection does <strong>not</strong> invent pages. For a new detail
          table you must:
        </p>
        <ol>
          <li>Add a page (or extend an existing one) and register it in <code>section_view_table_map()</code> in <code>includes/helpers_classify.php</code> (loaded via <code>render_helpers.php</code>; table, PK, classifier function).</li>
          <li>Add a matching key in <code>includes/layouts/layout_sections.php</code> (<code>exclude</code>, <code>classifier</code>, <code>featured</code>, <code>layouts</code>).</li>
          <li>Copy <code>detail_daq.php</code> (thin template), point it at that layout key, and wire nav links from run/group detail.</li>
          <li>Only then do caretaker layout polish (classifiers, featured, card bands).</li>
        </ol>
        <p>
          Learn the rest from existing pages (<code>detail_daq.php</code>,
          <code>detail_epics.php</code>, <code>detail_runs.php</code>) and the
          <a href="#layout-sections">sections</a> examples below.
        </p>
      </article>

      <article class="help-topic" id="after-schema">
        <h2 class="help-topic-title">After updating schema</h2>

        <h3 id="schema-to-site">How the schema turns into the website</h3>
        <p>Two paths:</p>
<pre>Detail cards (DAQ / EPICS / Analysis / groups)
  COLUMN + COLUMN_COMMENT in MariaDB
    → INFORMATION_SCHEMA at request time
    → get_table_columns()
    → layout_sections classifier (which card / section title)
    → page render (label from comment)

Index cards/tables, Run/Group Info, Report/CSV
  Only fields you list in layout_cards / layout_tables /
  layout_run_summary / layout_report
    → still need a real column on the row (SELECT * or JOIN)
    → missing field shows "—" or is skipped with a soft WARNING (report)
</pre>
        <p>
          <strong>Comments are load-bearing.</strong> The UI does not keep a second
          dictionary of labels for arbitrary columns. If you strip
          <code>COLUMN_COMMENT</code>, the site falls back to the raw column name.
        </p>

        <h3 id="schema-rename">Schema name change — step by step</h3>
        <p>Example: Don renames <code>run_comment</code> → <code>comment</code>.</p>
        <ol>
          <li>Search the repo for the <strong>old</strong> name, especially under <code>includes/layouts/</code>.</li>
          <li>Update every layout hit: classifier patterns, report catalog, cards, tables, run_summary, lookups.</li>
          <li>If the name is <a href="#non-agnostic">non-agnostic</a>, also change query PHP (<code>index_query.php</code>, <code>report_query.php</code>, …).</li>
          <li>Reload the affected pages. On report, use <strong>reset columns</strong> if an old sticky <code>cols=</code> URL is still in the address bar.</li>
        </ol>

        <h3 id="schema-add">Schema addition — step by step</h3>
        <p>Example: new Analysis column <code>pol_beam_drift</code> with a comment. Tables this covers: <code>DAQ_config</code>, <code>EPICS_data</code>, <code>Analysis</code>, <code>Grouped_Analysis</code> (and similar tables driven by <code>layout_sections</code>).</p>
        <ol>
          <li>Ensure the column has a clear <code>COLUMN_COMMENT</code> (and EPICS PV in the comment if applicable).</li>
          <li>Open the detail page — the field should already appear (often under Other / Unallocated).</li>
          <li>Optional: add a classifier line in <code>layout_sections</code> so it lands in the right card.
            Prefer <strong>one pattern per line</strong> (exact <code>^name$</code> or a tight prefix/regex).
            More specific patterns before broader ones; first match wins.
            Section titles in <code>featured</code> / <code>layouts</code> must <strong>exactly</strong> match the classifier <code>section</code> string.</li>
          <li>Optional: add it to <code>layout_report</code> (<code>section_rows</code> / <code>defaults</code>) if it belongs in the report/CSV picker. Unknown catalog entries → soft WARNING and skipped.</li>
          <li>Optional: add to cards / tables / run_summary if it belongs on the index or Run/Group Info.</li>
          <li>Reload — no rebuild step.</li>
        </ol>

        <h3 id="schema-delete">Schema deletion — step by step</h3>
        <ol>
          <li>Remove the field from every layout that names it (same search as rename): classifiers, report, cards, tables, summary, lookups <code>columns</code> map.</li>
          <li>Non-agnostic fields: remove or rewrite filter/join usage in query PHP.</li>
          <li>For renames: keep old and new only if the live DB still has both; otherwise remove the stale layout entry so warnings stay quiet.</li>
          <li>Report: stale catalog entries produce a soft WARNING and are skipped — clean them when you can.</li>
          <li>Reload and spot-check detail + index + report.</li>
        </ol>

        <h3 id="schema-lookups">New or changed type / quality codes</h3>
        <p>Display labels live in <code>run_type_lookup</code> / <code>run_quality_lookup</code>, not in PHP.</p>
        <ol>
          <li>Insert/update rows in the lookup tables (<code>code</code>, <code>display_label</code>).</li>
          <li>If a <strong>new quality code</strong> needs a distinct CSS color, add a slug in <code>layout_lookups.php</code> (<code>quality_slugs</code>) and a matching rule in <code>assets/style.css</code> if required.</li>
          <li>If a <strong>row column</strong> starts using a different lookup table, update the <code>columns</code> map in <code>layout_lookups.php</code>.</li>
        </ol>
        <p>See also <a href="#layout-lookups">Managing layouts → lookups</a>.</p>

        <h3 id="local-docker">Local Docker / test schema only</h3>
        <p>When experimenting locally (not Don’s production script):</p>
        <ul>
          <li>Edit <code>docker/init/01_schema.sql</code> (and seed if needed). Recreate the container volume when the DDL changes.</li>
          <li>Do <strong>not</strong> treat a local schema tweak as applying to the schema owner’s source script without a separate proposed-SQL handoff.</li>
          <li>Comment-only display tweaks can be a standalone <code>ALTER TABLE … MODIFY COLUMN</code> against the test DB without a full reseed.</li>
        </ul>

        <h3 id="verify">Quick verification after a schema update</h3>
        <ol>
          <li>Hit the relevant detail page for a known id — confirm new fields show and nothing important sits only in Unallocated unless intended.</li>
          <li>Index: type / experiment / date filters still behave (or show the expected soft WARNING).</li>
          <li>Report simple + advanced: picker/catalog warnings if any; CSV header matches selected columns.</li>
          <li>Grep layouts for the <strong>old</strong> column name after a rename.</li>
        </ol>
      </article>

      <article class="help-topic" id="layouts">
        <h2 class="help-topic-title">Managing layouts</h2>
        <p>
          All caretaker presentation files live in <code>includes/layouts/</code>.
          Each file’s header comment is the authoritative field list. Below:
          shared cell ideas first, then each file with examples and previews.
        </p>

        <h3 id="cell-kinds">Shared cell kinds — especially <code>id</code> and <code>link</code></h3>
        <p>
          Cards, tables, and (with small differences) run/group summary use the
          same vocabulary. You never write a URL in a layout file.
        </p>
        <table class="howto-mini-table">
          <thead>
            <tr><th>kind</th><th>What it does</th><th>Typical keys</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><code>id</code></td>
              <td>Shows the field as an identifier (often larger / monospace). Used for run #, group #, etc.</td>
              <td><code>field</code>, optional <code>prefix</code>, <code>class</code>, <code>link</code></td>
            </tr>
            <tr>
              <td><code>link</code> (option)</td>
              <td>
                Not a kind — an optional key on a cell.
                <code>'link' =&gt; 'run'</code> turns the cell into a link to
                <code>detail_runs.php?run={value}</code>.
                <code>'link' =&gt; 'group'</code> → <code>detail_groups.php?group={value}</code>.
                Empty values stay plain text (“—”), not a dead link.
              </td>
              <td><code>run</code> or <code>group</code> only</td>
            </tr>
            <tr>
              <td><code>quality</code></td>
              <td>Colored quality pill (or colored text in the summary modal). Code → label via lookups; color via <code>quality_slugs</code>.</td>
              <td><code>field</code></td>
            </tr>
            <tr>
              <td><code>text</code></td>
              <td>Plain value (type codes become English if that column is in lookups).</td>
              <td><code>field</code>, optional <code>class</code> / <code>label</code></td>
            </tr>
            <tr>
              <td><code>time_range</code></td>
              <td>Start → end timestamps.</td>
              <td><code>start</code>, <code>end</code></td>
            </tr>
            <tr>
              <td><code>value_err</code></td>
              <td>Index card/table helper: shows <code>field</code> ± <code>field_err</code> in one cell.</td>
              <td><code>field</code> (expects <code>field_err</code> sibling column)</td>
            </tr>
          </tbody>
        </table>
        <p><strong>Example — linked run number:</strong></p>
<pre>['kind' =&gt; 'id', 'field' =&gt; 'run_number', 'link' =&gt; 'run', 'class' =&gt; 'run-number']
</pre>
        <p>
          If the row’s <code>run_number</code> is <code>20031</code>, the cell shows
          <strong>20031</strong> as a link to <code>detail_runs.php?run=20031</code>.
          Without <code>link</code>, it is still styled as an id but is not clickable.
          Linking <code>run_group</code> with <code>'link' =&gt; 'group'</code> opens the group page for that group id.
        </p>

        <h3 id="value-err">How <code>value</code> and <code>value_err</code> work together</h3>
        <p>
          On <strong>detail</strong> cards (sections), pairing is automatic when both
          columns exist in the same section’s column list: the base field and
          its <code>_err</code> sibling collapse to one row.
        </p>
        <p><strong>Example schema</strong> (illustrative):</p>
<pre>CREATE TABLE Analysis (
  run_number   INT UNSIGNED PRIMARY KEY,
  asym_mol     DOUBLE NULL COMMENT 'Moller asymmetry',
  asym_mol_err DOUBLE NULL COMMENT 'Moller asymmetry uncertainty',
  leftover_err DOUBLE NULL COMMENT 'Orphan error with no base column'
);
</pre>
        <p><strong>What the card shows:</strong></p>
        <div class="howto-preview" aria-hidden="true">
          <div class="section-group card">
            <h3>Asymmetries (example)</h3>
            <dl class="fields">
              <dt>Moller asymmetry</dt><dd>0.0123 ± 0.0004</dd>
              <dt>Orphan error with no base column</dt><dd>0.9</dd>
            </dl>
          </div>
          <p class="howto-preview-caption">
            <code>asym_mol</code> + <code>asym_mol_err</code> → one line.
            Label comes from the <strong>base</strong> column’s comment.
            An orphan <code>*_err</code> with no matching base still renders alone.
            A differently named uncertainty (e.g. <code>asym_mol_unc</code>) is <em>not</em> paired — two separate rows.
          </p>
        </div>
        <p>
          On the <strong>index</strong>, use cell kind <code>value_err</code> with
          <code>'field' =&gt; 'asym_mol'</code> to get the same ± display in one table/card cell.
        </p>

        <h3 id="layout-sections">sections — <code>layout_sections.php</code></h3>
        <p>
          Controls detail pages for Analysis, EPICS, DAQ, and Grouped_Analysis:
          which column lands in which card, and how cards are arranged.
        </p>
        <ul>
          <li><code>exclude</code> — columns omitted from cards (PK, <code>last_updated</code>, comments shown elsewhere).</li>
          <li><code>classifier</code> — ordered rules; <strong>first match wins</strong>. Prefer one pattern per line. Unmatched → section title <code>Other</code>.</li>
          <li><code>featured</code> — full-width featured rows. Titles must match classifier <code>section</code> strings exactly. Do not also list those titles in <code>layouts</code>.</li>
          <li><code>layouts</code> — named bands (<code>main</code>, optional <code>other</code>). Each band is rows of section titles left→right; <code>null</code> = empty spacer slot.</li>
          <li><code>ignore_sections</code> — hide a leftover from Unallocated only (does not hide it if also featured/laid out).</li>
        </ul>
        <p><strong>Example</strong> (dummy names — not live schema):</p>
<pre>'demo' =&gt; [
    'exclude' =&gt; ['run_number', 'last_updated'],
    'classifier' =&gt; [
        ['match' =&gt; 'regex', 'pattern' =&gt; '/^widget_temp$/',  'section' =&gt; 'Widget Sensors'],
        ['match' =&gt; 'regex', 'pattern' =&gt; '/^widget_press$/', 'section' =&gt; 'Widget Sensors'],
        ['match' =&gt; 'regex', 'pattern' =&gt; '/^gadget_/',       'section' =&gt; 'Gadget Bank'],
    ],
    'featured' =&gt; [
        'Widget Sensors',
    ],
    'layouts' =&gt; [
        'main' =&gt; [
            ['Gadget Bank', null, null, null],
        ],
    ],
],
</pre>
        <div class="howto-preview" aria-hidden="true">
          <div class="section-group card featured-row">
            <h3>Widget Sensors</h3>
            <dl class="fields">
              <dt>widget_temp</dt><dd>21.4</dd>
              <dt>widget_press</dt><dd>1.02</dd>
            </dl>
          </div>
          <div class="card-row cols-4">
            <div class="section-group card">
              <h3>Gadget Bank</h3>
              <dl class="fields">
                <dt>gadget_a</dt><dd>on</dd>
                <dt>gadget_b</dt><dd>off</dd>
              </dl>
            </div>
            <div class="card-spacer" aria-hidden="true"></div>
            <div class="card-spacer" aria-hidden="true"></div>
            <div class="card-spacer" aria-hidden="true"></div>
          </div>
          <p class="howto-preview-caption">Featured = full width. <code>main</code> row = up to four cards; <code>null</code> keeps empty slots. Unlisted classifier sections still appear under Unallocated Sections.</p>
        </div>

        <h3 id="layout-tables">tables — <code>layout_tables.php</code></h3>
        <p>
          Drives the index (and group member list) when
          <code>?layout=table</code>. Keys: <code>run</code> and <code>group</code>, each with a
          flat <code>columns</code> array. <strong>Array order = left-to-right column order.</strong>
        </p>
        <p><strong>Before</strong> — Quality is first:</p>
<pre>'run' =&gt; [
  'columns' =&gt; [
    ['header' =&gt; 'Quality',    'kind' =&gt; 'quality', 'field' =&gt; 'run_quality'],
    ['header' =&gt; 'Run Number', 'kind' =&gt; 'id',      'field' =&gt; 'run_number', 'link' =&gt; 'run'],
    ['header' =&gt; 'Group',      'kind' =&gt; 'id',      'field' =&gt; 'run_group',  'link' =&gt; 'group'],
  ],
],
</pre>
        <div class="howto-preview" aria-hidden="true">
          <div class="list-table-wrap">
            <table class="list-table">
              <thead>
                <tr><th>Quality</th><th>Run Number</th><th>Group</th></tr>
              </thead>
              <tbody>
                <tr class="list-table-row">
                  <td><span class="quality-tag quality-tag-good">Good</span></td>
                  <td><a class="list-table-link" href="#">20031</a></td>
                  <td><a class="list-table-link" href="#">12</a></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="howto-preview-caption">First entry in <code>columns</code> → first table column.</p>
        </div>
        <p><strong>After moving Run Number above Quality</strong> in the PHP array:</p>
<pre>'columns' =&gt; [
  ['header' =&gt; 'Run Number', 'kind' =&gt; 'id',      'field' =&gt; 'run_number', 'link' =&gt; 'run'],
  ['header' =&gt; 'Quality',    'kind' =&gt; 'quality', 'field' =&gt; 'run_quality'],
  ['header' =&gt; 'Group',      'kind' =&gt; 'id',      'field' =&gt; 'run_group',  'link' =&gt; 'group'],
],
</pre>
        <div class="howto-preview" aria-hidden="true">
          <div class="list-table-wrap">
            <table class="list-table">
              <thead>
                <tr><th>Run Number</th><th>Quality</th><th>Group</th></tr>
              </thead>
              <tbody>
                <tr class="list-table-row">
                  <td><a class="list-table-link" href="#">20031</a></td>
                  <td><span class="quality-tag quality-tag-good">Good</span></td>
                  <td><a class="list-table-link" href="#">12</a></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="howto-preview-caption">Same data; only the order of entries in <code>columns</code> changed. Reload the index — no rebuild.</p>
        </div>

        <h3 id="layout-cards">cards — <code>layout_cards.php</code></h3>
        <p>
          Same cell kinds as tables, but arranged as <code>rows</code> of cells
          (top→bottom rows, left→right within a row) for the card view on the
          index / group member list.
        </p>
<pre>'run' =&gt; [
  'rows' =&gt; [
    [
      ['kind' =&gt; 'id', 'field' =&gt; 'run_number', 'link' =&gt; 'run', 'class' =&gt; 'run-number'],
      ['kind' =&gt; 'quality', 'field' =&gt; 'run_quality'],
    ],
    [
      ['kind' =&gt; 'time_range', 'start' =&gt; 'run_start', 'end' =&gt; 'run_end'],
    ],
  ],
],
</pre>
        <div class="howto-preview howto-card-preview" aria-hidden="true">
          <div class="run-card">
            <div class="run-card-row">
              <span class="run-number"><a href="#">20031</a></span>
              <span class="quality-tag quality-tag-good">Good</span>
            </div>
            <div class="run-card-row">
              <span class="run-time">2026-08-01 10:00 → 11:00</span>
            </div>
          </div>
          <p class="howto-preview-caption">First layout row → top of card; <code>link =&gt; run</code> makes 20031 open the run detail page.</p>
        </div>

        <h3 id="layout-lookups">lookups — <code>layout_lookups.php</code></h3>
        <p>
          The database stores short <strong>codes</strong> on the row
          (e.g. <code>run_quality = 'GOOD'</code>, <code>run_type = 'POLARIZATION'</code>).
          Humans want English (“Good”, “Polarization”). That English lives in
          lookup tables, not in PHP.
        </p>
        <p><strong>Three maps in one file:</strong></p>
        <ol>
          <li><code>tables</code> — for each lookup table, which columns hold the code and the label (usually <code>code</code> / <code>display_label</code>).</li>
          <li><code>columns</code> — which <em>row</em> fields should be resolved through which lookup table. Example: both <code>run_type</code> and <code>group_type</code> can share <code>run_type_lookup</code>.</li>
          <li><code>quality_slugs</code> — maps a quality <em>code</em> to a CSS suffix (<code>GOOD</code> → <code>good</code> → class <code>quality-tag-good</code>). This is color, not the English label.</li>
        </ol>
<pre>// DB (sketch)
run_quality_lookup:  code=GOOD, display_label=Good
Run_info row:        run_quality='GOOD'

// layout_lookups.php
'columns' =&gt; [
  'run_quality' =&gt; 'run_quality_lookup',
],
'quality_slugs' =&gt; [
  'GOOD' =&gt; 'good',
],
</pre>
        <div class="howto-preview" aria-hidden="true">
          <p>Without lookup: cell shows <code>GOOD</code>.</p>
          <p>With lookup + slug: cell shows <span class="quality-tag quality-tag-good">Good</span> (label from DB, color from CSS).</p>
          <p class="howto-preview-caption">
            New type/quality <strong>codes</strong>: insert rows in the lookup table.
            New quality color: add a <code>quality_slugs</code> entry and a
            <code>.quality-tag-…</code> rule in <code>assets/style.css</code> if needed.
            Unknown codes fall back to a lowercase slug when possible, else pending styling.
          </p>
        </div>

        <h3 id="layout-run-summary">run / group summary — <code>layout_run_summary.php</code></h3>
        <p>
          One file, two keys: <code>run</code> (Run Info modal on run detail) and
          <code>group</code> (Group Info modal on group detail). Each has
          <code>rows</code> (grid of labeled cells) and optional <code>footer</code>
          (usually a comment; omitted when empty).
        </p>
        <p><strong>Run</strong> layout sketch:</p>
<pre>'run' =&gt; [
  'rows' =&gt; [
    [
      ['kind' =&gt; 'text', 'field' =&gt; 'run_type', 'label' =&gt; 'Type'],
      ['kind' =&gt; 'quality', 'field' =&gt; 'run_quality', 'label' =&gt; 'Quality'],
      ['kind' =&gt; 'id', 'field' =&gt; 'run_group', 'label' =&gt; 'Group', 'link' =&gt; 'group'],
    ],
    [
      ['kind' =&gt; 'time_range', 'start' =&gt; 'run_start', 'end' =&gt; 'run_end', 'label' =&gt; 'Run time'],
    ],
  ],
  'footer' =&gt; [
    ['kind' =&gt; 'comment', 'field' =&gt; 'comment', 'label' =&gt; 'Comment'],
  ],
],
</pre>
        <div class="howto-preview" aria-hidden="true">
          <div class="run-summary">
            <div class="run-summary-row cols-3">
              <div><div class="run-summary-label">Type</div><div class="run-summary-value">Polarization</div></div>
              <div><div class="run-summary-label">Quality</div><div class="run-summary-value"><span class="quality-tag quality-tag-good">Good</span></div></div>
              <div><div class="run-summary-label">Group</div><div class="run-summary-value"><a href="#">12</a></div></div>
            </div>
            <div class="run-summary-row cols-1">
              <div><div class="run-summary-label">Run time</div><div class="run-summary-value run-summary-time">2026-08-01 10:00:00 → 2026-08-01 11:00:00</div></div>
            </div>
            <div class="run-summary-comment">
              <div class="run-summary-comment-label">Comment</div>
              <div class="run-summary-comment-body">Example run comment.</div>
            </div>
          </div>
          <p class="howto-preview-caption">Run Info modal preview. Group link uses <code>link =&gt; group</code>.</p>
        </div>
        <p><strong>Group</strong> layout sketch:</p>
<pre>'group' =&gt; [
  'rows' =&gt; [
    [
      ['kind' =&gt; 'text', 'field' =&gt; 'group_type', 'label' =&gt; 'Type'],
      ['kind' =&gt; 'quality', 'field' =&gt; 'group_quality', 'label' =&gt; 'Quality'],
    ],
    [
      ['kind' =&gt; 'time_range', 'start' =&gt; 'group_start', 'end' =&gt; 'group_end', 'label' =&gt; 'Group time'],
    ],
  ],
  'footer' =&gt; [
    ['kind' =&gt; 'comment', 'field' =&gt; 'group_comment', 'label' =&gt; 'Comment'],
  ],
],
</pre>
        <div class="howto-preview" aria-hidden="true">
          <div class="run-summary">
            <div class="run-summary-row cols-2">
              <div><div class="run-summary-label">Type</div><div class="run-summary-value">Polarization</div></div>
              <div><div class="run-summary-label">Quality</div><div class="run-summary-value"><span class="quality-tag quality-tag-good">Good</span></div></div>
            </div>
            <div class="run-summary-row cols-1">
              <div><div class="run-summary-label">Group time</div><div class="run-summary-value run-summary-time">2026-08-01 09:00:00 → 2026-08-01 18:00:00</div></div>
            </div>
            <div class="run-summary-comment">
              <div class="run-summary-comment-label">Comment</div>
              <div class="run-summary-comment-body">Example group comment.</div>
            </div>
          </div>
          <p class="howto-preview-caption">Group Info modal preview — same file, <code>'group'</code> key. Open from group detail via the Group Info control.</p>
        </div>

        <h3 id="layout-report">report — <code>layout_report.php</code></h3>
        <p>
          Powers <code>report.php</code> (simple checkbox “Available Data”) and
          <code>report_advanced.php</code> (Available | Selected dual list).
          Unlike detail cards, the report does <strong>not</strong> auto-list every
          schema column. You maintain an explicit catalog.
        </p>
        <p><strong>Two top-level keys:</strong> <code>run</code> and <code>group</code>.</p>
        <p>Each has:</p>
        <ul>
          <li>
            <code>section_rows</code> — rows of picker groups. Each group has
            <code>title</code> (column-group heading on the simple form; Advanced
            Available optgroup label) and <code>columns</code> (entries the user
            can select). <code>null</code> in a row = empty slot (spacing only).
            First row is always visible on the simple report; later rows start
            under “More available data”.
          </li>
          <li>
            <code>defaults</code> — field names checked when the URL has no
            <code>cols=</code>. This is a <em>set</em> (membership). Default
            <em>table / CSV order</em> follows catalog order among those fields
            (section_rows order). Advanced users can reorder; order is posted
            as <code>cols[]</code>.
          </li>
        </ul>
        <p><strong>Each column entry:</strong></p>
        <ul>
          <li><code>field</code> — DB column name</li>
          <li><code>header</code> — checkbox label and table / CSV header text</li>
          <li><code>source</code> — which table to read:
            <code>run</code> → Run_info,
            <code>analysis</code> → Analysis (LEFT JOIN),
            <code>epics</code> → EPICS_data,
            <code>daq</code> → DAQ_config,
            <code>group</code> → Grouped_Analysis</li>
          <li>optional <code>kind</code> / <code>link</code> — same ideas as index (<code>id</code> + <code>link</code> for clickable run/group numbers in the HTML table)</li>
        </ul>
        <p><strong>Layout → simple report “Available Data”</strong></p>
<pre>'run' =&gt; [
  'defaults' =&gt; ['run_number', 'pol_beam'],
  'section_rows' =&gt; [
    [
      [
        'title' =&gt; 'Run identity',
        'columns' =&gt; [
          ['field' =&gt; 'run_number', 'header' =&gt; 'Run Number', 'source' =&gt; 'run', 'kind' =&gt; 'id', 'link' =&gt; 'run'],
          ['field' =&gt; 'run_group',  'header' =&gt; 'Group',      'source' =&gt; 'run', 'kind' =&gt; 'id', 'link' =&gt; 'group'],
        ],
      ],
      [
        'title' =&gt; 'Polarization',
        'columns' =&gt; [
          ['field' =&gt; 'pol_beam',     'header' =&gt; 'Beam pol',     'source' =&gt; 'analysis'],
          ['field' =&gt; 'pol_beam_err', 'header' =&gt; 'Beam pol err', 'source' =&gt; 'analysis'],
        ],
      ],
      null,
      null,
    ],
  ],
],
</pre>
        <div class="howto-preview" aria-hidden="true">
          <p class="panel-heading" style="margin:0 0 0.35rem">Available Data</p>
          <div class="report-column-row">
            <div class="report-column-section">
              <h3 class="report-column-section-title">Run identity</h3>
              <div class="report-column-section-options">
                <label class="report-column-option"><input type="checkbox" checked disabled> Run Number</label>
                <label class="report-column-option"><input type="checkbox" disabled> Group</label>
              </div>
            </div>
            <div class="report-column-section">
              <h3 class="report-column-section-title">Polarization</h3>
              <div class="report-column-section-options">
                <label class="report-column-option"><input type="checkbox" checked disabled> Beam pol</label>
                <label class="report-column-option"><input type="checkbox" disabled> Beam pol err</label>
              </div>
            </div>
            <div class="report-column-section report-column-section-spacer" aria-hidden="true"></div>
            <div class="report-column-section report-column-section-spacer" aria-hidden="true"></div>
          </div>
          <p class="howto-preview-caption">
            Each <code>section_rows</code> slot → one titled checkbox column.
            <code>header</code> is the label next to the box; <code>field</code> is the
            posted <code>cols[]</code> value. Entries in <code>defaults</code> start checked
            (<code>run_number</code>, <code>pol_beam</code> here). <code>null</code> slots leave empty
            columns in that row. A second <code>section_rows</code> row would appear
            under “More available data” on the simple form.
          </p>
          <div class="list-table-wrap">
            <table class="list-table">
              <thead>
                <tr><th>Run Number</th><th>Beam pol</th></tr>
              </thead>
              <tbody>
                <tr class="list-table-row">
                  <td><a class="list-table-link" href="#">20031</a></td>
                  <td>0.87</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="howto-preview-caption">
            With only the defaults checked, the HTML table and CSV show those
            columns in catalog order. Apply columns posts the checked set as
            <code>cols[]</code> (DOM order among checked boxes on the simple form).
          </p>
        </div>
        <p><strong>Advanced report</strong> (<code>report_advanced.php</code>) uses the same catalog:</p>
        <ul>
          <li>Available (left) — catalog fields not yet selected, grouped by <code>title</code></li>
          <li>Selected (right) — current columns; ↑↓ change table/CSV order</li>
          <li>Apply columns → posts <code>cols[]</code> in Selected order</li>
        </ul>
        <p>
          Wrong <code>field</code> or <code>source</code> → soft WARNING
          <code>layout_report … skipped</code>; the page keeps working.
          Only columns that exist on the live schema are offered.
          After editing <code>layout_report.php</code>, reload; use
          <strong>reset columns</strong> if an old sticky <code>cols=</code> URL is stuck.
        </p>

        <h3 id="layout-navbar">site navbar — <code>layout_navbar.php</code></h3>
        <p>
          Optional horizontal bar at the top of every page for linking into the
          broader polarimeter site (or other run-log pages). When
          <code>links</code> is empty, nothing is rendered.
        </p>
<pre>return [
  'links' =&gt; [
    ['href' =&gt; 'https://example.org/polarimeter/', 'label' =&gt; 'Polarimeter Home'],
    ['href' =&gt; 'index.php', 'label' =&gt; 'Run Log'],
  ],
];
</pre>
        <p>
          Colors are <strong>not</strong> in this file. Edit the
          <code>--site-nav-*</code> variables in <code>assets/style.css</code>
          (<code>:root</code>):
          <code>--site-nav-bg</code>, <code>--site-nav-text</code>,
          <code>--site-nav-hover</code>, <code>--site-nav-hover-text</code>,
          <code>--site-nav-border</code> (left / bottom / right).
          Named colors: see
          <a href="https://www.w3schools.com/cssref/css_colors.php" target="_blank" rel="noopener">CSS color names (W3Schools)</a>.
          The bar is centered at the same max width as page content.
          Unlike other layout files, this one is meant to hold real <code>href</code>s.
        </p>
      </article>

      <article class="help-topic" id="where-to-edit">
        <h2 class="help-topic-title">Where to edit (reminder)</h2>
        <table class="howto-mini-table">
          <thead>
            <tr><th>Goal</th><th>Where</th></tr>
          </thead>
          <tbody>
            <tr><td>Master top navbar (site-wide links)</td><td><code>includes/layouts/layout_navbar.php</code></td></tr>
            <tr><td>Section cards / classifiers</td><td><code>includes/layouts/layout_sections.php</code></td></tr>
            <tr><td>Report / CSV columns</td><td><code>includes/layouts/layout_report.php</code></td></tr>
            <tr><td>Index cards / tables / Run &amp; Group Info</td><td><code>includes/layouts/layout_*.php</code></td></tr>
            <tr><td>Lookups / quality CSS slugs</td><td><code>includes/layouts/layout_lookups.php</code></td></tr>
            <tr><td>Site title, caps, plot paths</td><td><code>includes/config.php</code></td></tr>
            <tr><td>Connection</td><td><code>SITE_DB_*</code> at deploy time (not the repo)</td></tr>
            <tr><td>Engine SQL / new pages</td><td><code>includes/*.php</code> + page PHP — only for <a href="#non-agnostic">exceptions</a> above</td></tr>
          </tbody>
        </table>
      </article>

    </div>
  </div>

</div>
</body>
</html>
