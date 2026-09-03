<?php
/**
 * includes/report_query.php
 *
 * Filter / query / column selection for report.php / report_advanced.php.
 * Requires bootstrap ($pdo, $config).
 *
 * Optional before include: $reportScript = 'report.php'|'report_advanced.php'
 * (basename of the page; defaults to report.php).
 *
 * Sets: $reportScript, $filterType, $filterExperiment, $filterDateFrom, $filterDateTo,
 * $runSearch, $groupSearch, $filterRun, $filterGroup, $find,
 * $defaultView, $view, $rowCap, $rows, $hasMore,
 * $types, $typeColumnMissing, $experiments, $experimentColumnMissing,
 * $dateColumnMissing, $availableColumns, $availableColumnSections,
 * $availableColumnRows, $reportMoreOpen, $reportColumnWarnings,
 * $selectedColumns, $selectedFields, $colsExplicit,
 * $format, $qs, $clearHref, $filtersActive, $resetColsHref, $csvHref,
 * $reportSiblingHref, $reportSiblingLabel, $siteTitle.
 */

if (!isset($reportScript) || !is_string($reportScript) || $reportScript === '') {
    $reportScript = 'report.php';
}
$reportScript = basename($reportScript);
if ($reportScript !== 'report_advanced.php') {
    $reportScript = 'report.php';
}

$filterType  = isset($_GET['type']) ? $_GET['type'] : '';
$filterExperiment = trim((string)(isset($_GET['experiment']) ? $_GET['experiment'] : ''));
$filterDateFrom = parse_ymd_query_param(isset($_GET['from']) ? $_GET['from'] : '');
$filterDateTo   = parse_ymd_query_param(isset($_GET['to']) ? $_GET['to'] : '');
if ($filterDateFrom !== null && $filterDateTo !== null && $filterDateFrom > $filterDateTo) {
    list($filterDateFrom, $filterDateTo) = array($filterDateTo, $filterDateFrom);
}
$runSearch   = trim((string)(isset($_GET['run']) ? $_GET['run'] : ''));
$groupSearch = trim((string)(isset($_GET['group']) ? $_GET['group'] : ''));
$filterRun   = ($runSearch !== '' && ctype_digit($runSearch)) ? (int)$runSearch : null;
$filterGroup = ($groupSearch !== '' && ctype_digit($groupSearch)) ? (int)$groupSearch : null;
$findRaw = (string)(isset($_GET['find']) ? $_GET['find'] : '');
$find = ($findRaw === 'run' || $findRaw === 'group') ? $findRaw : '';

$defaultView = (isset($config['default_view']) ? $config['default_view'] : 'runs') === 'groups' ? 'groups' : 'runs';
$view = (isset($_GET['view']) ? $_GET['view'] : $defaultView) === 'groups' ? 'groups' : 'runs';

if ($find === 'run') {
    $view = 'runs';
    $filterGroup = null;
} elseif ($find === 'group') {
    $view = 'groups';
    $filterRun = null;
} elseif ($filterRun !== null) {
    $view = 'runs';
} elseif ($filterGroup !== null) {
    $view = 'groups';
}

$rowCap = max(1, (int)(isset($config['report_row_cap']) ? $config['report_row_cap'] : 2000));

$typeTable  = $view === 'groups' ? 'Grouped_Analysis' : 'Run_info';
$typeColumn = $view === 'groups' ? 'group_type' : 'run_type';
$hasTypeColumn = table_has_column($pdo, $typeTable, $typeColumn);
$typeColumnMissing = !$hasTypeColumn;

$dateTable  = $typeTable;
$dateColumn = $view === 'groups' ? 'group_start' : 'run_start';
$dateColumnMissing = !table_has_column($pdo, $dateTable, $dateColumn);

$hasExperimentColumn = table_has_column($pdo, 'Run_info', 'run_experiment');
$experimentColumnMissing = !$hasExperimentColumn;
$hasRunGroupColumn = table_has_column($pdo, 'Run_info', 'run_group');

$availableColumns = report_available_columns($pdo, $view);
$availableColumnSections = report_available_column_sections($pdo, $view);
$availableColumnRows = report_available_column_rows($pdo, $view);
$reportColumnWarnings = report_layout_column_warnings($pdo, $view);

// Column selection: cols[]=… or comma-separated cols=
$colsExplicit = array_key_exists('cols', $_GET);
$requestedFields = null;
if ($colsExplicit) {
    $rawCols = $_GET['cols'];
    if (is_array($rawCols)) {
        $requestedFields = array_values(array_filter(array_map('strval', $rawCols), function ($v) { return $v !== ''; }));
    } else {
        $requestedFields = array_values(array_filter(array_map('trim', explode(',', (string)$rawCols)), function ($v) { return $v !== ''; }));
    }
}
$selectedColumns = report_selected_columns($pdo, $view, $requestedFields);
$selectedFields = array_map(function ($c) { return (string)$c['field']; }, $selectedColumns);

$reportMoreOpen = false;
foreach (array_slice($availableColumnRows, 1) as $moreRow) {
    foreach ($moreRow as $section) {
        if (!is_array($section)) {
            continue;
        }
        foreach ($section['columns'] as $col) {
            if (in_array((string)$col['field'], $selectedFields, true)) {
                $reportMoreOpen = true;
                break 3;
            }
        }
    }
}

$where  = [];
$params = [];

if ($view === 'runs') {
    if ($filterType !== '' && $hasTypeColumn) {
        $where[]        = 'r.run_type = :type';
        $params['type'] = $filterType;
    }
    if ($filterExperiment !== '' && $hasExperimentColumn) {
        $where[]              = 'r.run_experiment = :experiment';
        $params['experiment'] = $filterExperiment;
    }
    if (($filterDateFrom !== null || $filterDateTo !== null) && !$dateColumnMissing) {
        $dateExpr = sql_expr_stamp_as_date('r.run_start');
        if ($filterDateFrom !== null) {
            $where[] = "{$dateExpr} >= :date_from";
            $params['date_from'] = $filterDateFrom;
        }
        if ($filterDateTo !== null) {
            $where[] = "{$dateExpr} <= :date_to";
            $params['date_to'] = $filterDateTo;
        }
    }
    if ($filterRun !== null) {
        $where[]       = 'r.run_number = :run';
        $params['run'] = $filterRun;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $analysisSelect = '';
    if (table_exists($pdo, 'Analysis')) {
        $aCols = [];
        foreach (get_table_columns($pdo, 'Analysis') as $col) {
            $name = $col['name'];
            if ($name === 'run_number' || $name === 'last_updated') {
                continue;
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }
            $aCols[] = 'a.`' . $name . '`';
        }
        if ($aCols !== []) {
            $analysisSelect = ', ' . implode(', ', $aCols);
        }
    }

    $extra = report_extra_join_sql($pdo, $selectedColumns);

    $sql = "SELECT r.*{$analysisSelect}{$extra['select']}
            FROM Run_info r
            LEFT JOIN Analysis a ON a.run_number = r.run_number
            {$extra['join']}
            {$whereSql}
            ORDER BY r.run_number DESC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
} else {
    if ($filterType !== '' && $hasTypeColumn) {
        $where[]        = 'group_type = :type';
        $params['type'] = $filterType;
    }
    if ($filterExperiment !== '' && $hasExperimentColumn && $hasRunGroupColumn) {
        $where[] = 'group_number IN (
            SELECT run_group FROM Run_info
            WHERE run_experiment = :experiment AND run_group IS NOT NULL
        )';
        $params['experiment'] = $filterExperiment;
    }
    if (($filterDateFrom !== null || $filterDateTo !== null) && !$dateColumnMissing) {
        $dateExpr = sql_expr_stamp_as_date('group_start');
        if ($filterDateFrom !== null) {
            $where[] = "{$dateExpr} >= :date_from";
            $params['date_from'] = $filterDateFrom;
        }
        if ($filterDateTo !== null) {
            $where[] = "{$dateExpr} <= :date_to";
            $params['date_to'] = $filterDateTo;
        }
    }
    if ($filterGroup !== null) {
        $where[]         = 'group_number = :group';
        $params['group'] = $filterGroup;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
        "SELECT * FROM Grouped_Analysis {$whereSql}
         ORDER BY group_number DESC
         LIMIT :limit"
    );
}

foreach ($params as $key => $val) {
    $stmt->bindValue(":{$key}", $val);
}
$stmt->bindValue(':limit', $rowCap + 1, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();
$hasMore = count($rows) > $rowCap;
if ($hasMore) {
    $rows = array_slice($rows, 0, $rowCap);
}

$types = [];
if ($hasTypeColumn) {
    $lookupTable = lookup_table_for_column($typeColumn);
    $lookupMap = ($lookupTable !== null) ? load_lookup_map($pdo, $lookupTable) : [];
    if ($lookupMap !== []) {
        foreach ($lookupMap as $code => $label) {
            $types[] = ['code' => $code, 'label' => $label];
        }
    } else {
        $rawTypes = $view === 'groups'
            ? $pdo->query('SELECT DISTINCT group_type FROM Grouped_Analysis WHERE group_type IS NOT NULL ORDER BY group_type')
                   ->fetchAll(PDO::FETCH_COLUMN)
            : $pdo->query('SELECT DISTINCT run_type FROM Run_info ORDER BY run_type')
                   ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rawTypes as $code) {
            $code = (string)$code;
            $types[] = ['code' => $code, 'label' => $code];
        }
    }
}

$experiments = [];
if ($hasExperimentColumn) {
    $experiments = $pdo->query(
        "SELECT DISTINCT run_experiment FROM Run_info
         WHERE run_experiment IS NOT NULL AND run_experiment <> ''
         ORDER BY run_experiment"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($filterExperiment !== '' && !in_array($filterExperiment, $experiments, true)) {
        $experiments[] = $filterExperiment;
        sort($experiments, SORT_STRING);
    }
}

$formatRaw = strtolower((string)(isset($_GET['format']) ? $_GET['format'] : ''));
$format = ($formatRaw === 'csv') ? 'csv' : 'html';

$qs = function ($overrides) use ($defaultView, $selectedFields, $colsExplicit) {
    $base = [
        'view'       => isset($_GET['view']) ? $_GET['view'] : $defaultView,
        'type'       => isset($_GET['type']) ? $_GET['type'] : '',
        'experiment' => isset($_GET['experiment']) ? $_GET['experiment'] : '',
        'from'       => isset($_GET['from']) ? $_GET['from'] : '',
        'to'         => isset($_GET['to']) ? $_GET['to'] : '',
        'run'        => isset($_GET['run']) ? $_GET['run'] : '',
        'group'      => isset($_GET['group']) ? $_GET['group'] : '',
    ];
    if ($colsExplicit) {
        $base['cols'] = $selectedFields;
    }
    $merged = array_merge($base, $overrides);
    if ((isset($merged['view']) ? $merged['view'] : $defaultView) === $defaultView) {
        unset($merged['view']);
    }
    if (isset($merged['format']) && $merged['format'] === 'html') {
        unset($merged['format']);
    }
    // Drop empty scalars; keep cols array even if we need http_build_query
    $filtered = [];
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $filtered[$k] = $v;
    }
    $query = http_build_query($filtered);
    return $query === '' ? '' : ('?' . $query);
};

$clearParams = [];
if ($view !== $defaultView) {
    $clearParams['view'] = $view;
}
if ($colsExplicit) {
    $clearParams['cols'] = $selectedFields;
}
$clearHref = $reportScript . ($clearParams ? '?' . http_build_query($clearParams) : '');

$resetColsParams = [
    'view'       => $view !== $defaultView ? $view : null,
    'type'       => $filterType !== '' ? $filterType : null,
    'experiment' => $filterExperiment !== '' ? $filterExperiment : null,
    'from'       => $filterDateFrom,
    'to'         => $filterDateTo,
    'run'        => $filterRun !== null ? (string)$filterRun : null,
    'group'      => $filterGroup !== null ? (string)$filterGroup : null,
];
$resetColsHref = $reportScript . (($q = http_build_query(array_filter($resetColsParams, function ($v) { return $v !== null && $v !== ''; }))) === '' ? '' : ('?' . $q));

$csvHref = $reportScript . $qs(['format' => 'csv']);

$reportSiblingScript = $reportScript === 'report_advanced.php' ? 'report.php' : 'report_advanced.php';
$reportSiblingLabel = $reportScript === 'report_advanced.php' ? 'Simple Report Form' : 'Advanced Report Form';
$reportSiblingHref = $reportSiblingScript . $qs([]);

$filtersActive = (
    $filterType !== ''
    || $filterExperiment !== ''
    || $filterDateFrom !== null
    || $filterDateTo !== null
    || $filterRun !== null
    || $filterGroup !== null
);
$siteTitle = (string)(isset($config['site_title']) ? $config['site_title'] : 'Møller Run Log');
