<?php
/**
 * includes/schema.php
 *
 * Reads column names, types, and COLUMN_COMMENT metadata straight from
 * INFORMATION_SCHEMA at request time, instead of hardcoding a field list
 * in PHP. When your colleague adds a column to DAQ_config/EPICS_data/etc.
 * (with a COMMENT, as the current schema does), it appears on the site
 * automatically — no template edit required.
 *
 * This does NOT handle new tables — a new table still needs a page/query
 * wired up manually. It only removes the per-column maintenance burden.
 * table_exists() is used for optional lookup tables (type/quality labels).
 */

/**
 * Return column metadata for a table in the current database, in
 * declaration order.
 *
 * @return array<int, array{name:string, type:string, comment:string, label:string, pv:?string}>
 */
function get_table_columns(PDO $pdo, $table)
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_COMMENT
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
         ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute(array('table' => $table));

    $columns = array();
    foreach ($stmt->fetchAll() as $row) {
        $comment = isset($row['COLUMN_COMMENT']) ? $row['COLUMN_COMMENT'] : '';
        $columns[] = array(
            'name'        => $row['COLUMN_NAME'],
            'type'        => $row['DATA_TYPE'],   // e.g. 'float', 'enum', 'varchar'
            'column_type' => $row['COLUMN_TYPE'], // e.g. 'float(10,5)', "enum('Good','Bad',...)"
            'comment'     => $comment,
            'label'       => parse_comment_label($comment, $row['COLUMN_NAME']),
            'pv'          => parse_comment_pv($comment),
        );
    }
    return $columns;
}

/**
 * True if $table in the current database has a column named $column.
 * Used to skip SQL that names a column (e.g. index type DISTINCT) instead of 500ing.
 */
function table_has_column(PDO $pdo, $table, $column)
{
    foreach (get_table_columns($pdo, $table) as $col) {
        if ((isset($col['name']) ? $col['name'] : '') === $column) {
            return true;
        }
    }
    return false;
}

/**
 * True if $table exists in the current database.
 */
function table_exists(PDO $pdo, $table)
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $stmt->execute(array('table' => $table));
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Your COLUMN_COMMENTs follow the pattern "Description text [PV: NAME]".
 * Strip the trailing [PV: ...] bracket to get a clean display label.
 * Falls back to a humanized column name if there's no comment.
 */
function parse_comment_label($comment, $fallbackColumnName)
{
    if ($comment === '') {
        return humanize_column_name($fallbackColumnName);
    }
    $label = preg_replace('/\s*\[PV:.*?\]?\s*$/i', '', $comment);
    $label = trim($label);
    return $label !== '' ? $label : humanize_column_name($fallbackColumnName);
}

/**
 * Pull the EPICS PV name out of a comment, if present.
 * Tolerant of the one malformed comment in the current schema
 * (epics_las_pow_halld is missing its closing bracket).
 *
 * @return string|null
 */
function parse_comment_pv($comment)
{
    if (preg_match('/\[PV:\s*([^\]]*)\]?/i', $comment, $m)) {
        return trim($m[1], " \t\n\r\0\x0B]");
    }
    return null;
}

/**
 * epics_bcm_avg -> "Bcm Avg" (strip epics_ prefix, underscores to spaces, title case)
 * Only used when a column has no COMMENT to draw a label from.
 */
function humanize_column_name($name)
{
    $name = preg_replace('/^epics_/', '', $name);
    return ucwords(str_replace('_', ' ', $name));
}

/**
 * Fetch one row from $table by its primary key column, keyed by column name.
 * Returns null if no matching row (e.g. a run has no DAQ_config entry yet).
 *
 * @return array|null
 */
function fetch_row_by_key(PDO $pdo, $table, $keyColumn, $keyValue)
{
    // Table/column names are never user input here — they come from get_table_columns()
    // or fixed constants — so this interpolation is safe; the value itself is bound.
    $sql = "SELECT * FROM `{$table}` WHERE `{$keyColumn}` = :key LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array('key' => $keyValue));
    $row = $stmt->fetch();
    return $row ? $row : null;
}
