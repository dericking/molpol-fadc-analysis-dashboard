<?php
/**
 * includes/index_query.php
 *
 * Filter / query / date-bucket prep for index.php. Requires bootstrap
 * ($pdo, $config).
 *
 * Sets: $filterType, $filterExperiment, $filterDateFrom, $filterDateTo,
 * $runSearch, $groupSearch, $filterRun, $filterGroup,
 * $find, $defaultView, $defaultLayout, $defaultPanel, $view, $layout,
 * $panel, $rowCap, $rows, $hasMore, $types (list of {code,label}), $typeColumnMissing,
 * $typeTable, $typeColumn, $experiments, $experimentColumnMissing,
 * $dateColumnMissing, $dateTable, $dateColumn,
 * $qs, $dateBuckets, $clearHref, $filtersActive, $siteTitle.
 */

$filterType  = $_GET['type'] ?? '';
$filterExperiment = trim((string)($_GET['experiment'] ?? ''));
$filterDateFrom = parse_ymd_query_param($_GET['from'] ?? '');
$filterDateTo   = parse_ymd_query_param($_GET['to'] ?? '');
if ($filterDateFrom !== null && $filterDateTo !== null && $filterDateFrom > $filterDateTo) {
    [$filterDateFrom, $filterDateTo] = [$filterDateTo, $filterDateFrom];
}
$runSearch   = trim((string)($_GET['run'] ?? ''));
$groupSearch = trim((string)($_GET['group'] ?? ''));
$filterRun   = ($runSearch !== '' && ctype_digit($runSearch)) ? (int)$runSearch : null;
$filterGroup = ($groupSearch !== '' && ctype_digit($groupSearch)) ? (int)$groupSearch : null;
$findRaw = (string)($_GET['find'] ?? '');
$find = ($findRaw === 'run' || $findRaw === 'group') ? $findRaw : '';

$defaultView   = ($config['default_view'] ?? 'runs') === 'groups' ? 'groups' : 'runs';
$defaultLayout = ($config['default_layout'] ?? 'table') === 'cards' ? 'cards' : 'table';
$defaultPanel  = ($config['default_panel'] ?? 'top') === 'side' ? 'side' : 'top';

$view   = ($_GET['view'] ?? $defaultView) === 'groups' ? 'groups' : 'runs';
$layout = ($_GET['layout'] ?? $defaultLayout) === 'table' ? 'table' : 'cards';
$panel  = ($_GET['panel'] ?? $defaultPanel) === 'side' ? 'side' : 'top';

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

$rowCap = max(1, (int)($config['row_cap'] ?? 300));

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

$where  = [];
$params = [];

if ($view === 'runs') {
    if ($filterType !== '' && $hasTypeColumn) {
        $where[]        = 'run_type = :type';
        $params['type'] = $filterType;
    }
    if ($filterExperiment !== '' && $hasExperimentColumn) {
        $where[]              = 'run_experiment = :experiment';
        $params['experiment'] = $filterExperiment;
    }
    if (($filterDateFrom !== null || $filterDateTo !== null) && !$dateColumnMissing) {
        $dateExpr = sql_expr_stamp_as_date('run_start');
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
        $where[]       = 'run_number = :run';
        $params['run'] = $filterRun;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
        "SELECT * FROM Run_info {$whereSql}
         ORDER BY run_number DESC
         LIMIT :limit"
    );
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

$qs = function (array $overrides) use ($defaultView, $defaultLayout, $defaultPanel): string {
    $merged = array_merge([
        'view'        => $_GET['view'] ?? $defaultView,
        'layout'      => $_GET['layout'] ?? $defaultLayout,
        'panel'       => $_GET['panel'] ?? $defaultPanel,
        'type'        => $_GET['type'] ?? '',
        'experiment'  => $_GET['experiment'] ?? '',
        'from'        => $_GET['from'] ?? '',
        'to'          => $_GET['to'] ?? '',
        'run'         => $_GET['run'] ?? '',
        'group'       => $_GET['group'] ?? '',
    ], $overrides);
    if (($merged['view'] ?? $defaultView) === $defaultView) {
        unset($merged['view']);
    }
    if (($merged['layout'] ?? $defaultLayout) === $defaultLayout) {
        unset($merged['layout']);
    }
    if (($merged['panel'] ?? $defaultPanel) === $defaultPanel) {
        unset($merged['panel']);
    }
    $query = http_build_query(array_filter($merged, fn($v) => $v !== '' && $v !== null));
    return $query === '' ? '' : ('?' . $query);
};

$dateBuckets = [];
foreach ($rows as $row) {
    $rawTs = $view === 'groups' ? ($row['group_start'] ?? null) : ($row['run_start'] ?? null);
    $parsed = parse_stored_calendar_date($rawTs !== null ? (string)$rawTs : null);
    if ($parsed !== null) {
        $dateKey = $parsed['key'];
        $label   = $parsed['label'];
    } else {
        $dateKey = '0000-00-00';
        $label   = 'Unknown date';
    }
    if (!isset($dateBuckets[$dateKey])) {
        $dateBuckets[$dateKey] = ['label' => $label, 'items' => []];
    }
    $dateBuckets[$dateKey]['items'][] = $row;
}
krsort($dateBuckets);

$clearParams = [];
if ($view !== $defaultView) {
    $clearParams['view'] = $view;
}
if ($layout !== $defaultLayout) {
    $clearParams['layout'] = $layout;
}
if ($panel !== $defaultPanel) {
    $clearParams['panel'] = $panel;
}
$clearHref = 'index.php' . ($clearParams ? '?' . http_build_query($clearParams) : '');
$filtersActive = (
    $filterType !== ''
    || $filterExperiment !== ''
    || $filterDateFrom !== null
    || $filterDateTo !== null
    || $filterRun !== null
    || $filterGroup !== null
);
$siteTitle = (string)($config['site_title'] ?? 'Møller Run Log');
