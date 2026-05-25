<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('dashboard');
$user = current_user();
$isAdmin = is_admin();

sync_operational_notifications($user);

// Status labels and order for CSV and display
$statusOrder = ['in_progress', 'submitted', 'live'];
$statusLabels = [
    'not_started' => 'Not Started',
    'in_progress' => 'In Progress',
    'submitted' => 'Submitted',
    'live' => 'Live',
    'needs_edit' => 'Needs Edit',
    'rejected' => 'Rejected',
];

$datePreset = strtolower(trim((string)($_GET['date_preset'] ?? '30d')));
$allowedDatePresets = ['7d', '30d', '90d', 'custom', 'all'];
if (!in_array($datePreset, $allowedDatePresets, true)) {
    $datePreset = '30d';
}

$fromDateInput = trim((string)($_GET['from_date'] ?? ''));
$toDateInput = trim((string)($_GET['to_date'] ?? ''));
$validatedFromInput = null;
$validatedToInput = null;

$validateDate = static function (string $value): ?string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
};

$today = new DateTimeImmutable('today');
$rangeFrom = null;
$rangeTo = null;
$activeDateRangeLabel = 'All Time';

if ($datePreset !== 'all') {
    if ($datePreset === 'custom') {
        $validatedFromInput = $validateDate($fromDateInput);
        $validatedToInput = $validateDate($toDateInput);
        if ($validatedFromInput !== null && $validatedToInput !== null && $validatedFromInput <= $validatedToInput) {
            $rangeFrom = $validatedFromInput;
            $rangeTo = $validatedToInput;
            $activeDateRangeLabel = 'Custom: ' . $rangeFrom . ' to ' . $rangeTo;
        } else {
            $datePreset = '30d';
        }
    }

    if ($datePreset === '7d') {
        $rangeFrom = $today->modify('-6 days')->format('Y-m-d');
        $rangeTo = $today->format('Y-m-d');
        $activeDateRangeLabel = 'Last 7 Days';
    } elseif ($datePreset === '30d') {
        $rangeFrom = $today->modify('-29 days')->format('Y-m-d');
        $rangeTo = $today->format('Y-m-d');
        $activeDateRangeLabel = 'Last 30 Days';
    } elseif ($datePreset === '90d') {
        $rangeFrom = $today->modify('-89 days')->format('Y-m-d');
        $rangeTo = $today->format('Y-m-d');
        $activeDateRangeLabel = 'Last 90 Days';
    }
}

$taskDateWhereLt = '';
$taskDateWherePlain = '';
$taskDateParams = [];
if ($rangeFrom !== null && $rangeTo !== null) {
    $taskDateWhereLt = ' AND DATE(lt.updated_at) BETWEEN ? AND ?';
    $taskDateWherePlain = ' WHERE DATE(updated_at) BETWEEN ? AND ?';
    $taskDateParams = [$rangeFrom, $rangeTo];
}

$dashboardFilterQuery = ['date_preset' => $datePreset];
if ($datePreset === 'custom') {
    $dashboardFilterQuery['from_date'] = $rangeFrom ?? '';
    $dashboardFilterQuery['to_date'] = $rangeTo ?? '';
}
$dashboardFilterQueryString = http_build_query($dashboardFilterQuery);
$exportCsvUrl = app_config()['base_url'] . '/dashboard.php?export=csv' . ($dashboardFilterQueryString !== '' ? '&' . $dashboardFilterQueryString : '');

$countWithDate = static function (string $sql, array $params): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)($stmt->fetch()['c'] ?? 0);
};

$fetchAllWithDate = static function (string $sql, array $params): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};

// Query metrics
$totalBusinesses = (int)db()->query('SELECT COUNT(*) AS c FROM businesses')->fetch()['c'];
$totalDirectories = (int)db()->query('SELECT COUNT(*) AS c FROM directories WHERE is_active = 1')->fetch()['c'];
$totalCitations = $countWithDate('SELECT COUNT(*) AS c FROM listing_tasks lt WHERE 1=1' . $taskDateWhereLt, $taskDateParams);
$liveCitations = $countWithDate("SELECT COUNT(*) AS c FROM listing_tasks lt WHERE lt.status = 'live'" . $taskDateWhereLt, $taskDateParams);
$correctNapCitations = $countWithDate("SELECT COUNT(*) AS c FROM listing_tasks lt WHERE lt.status = 'live' AND lt.nap_status = 'correct'" . $taskDateWhereLt, $taskDateParams);
$napErrorCitations = $countWithDate("SELECT COUNT(*) AS c FROM listing_tasks lt WHERE lt.status = 'live' AND lt.nap_status = 'nap_error'" . $taskDateWhereLt, $taskDateParams);
$totalClients = table_exists('clients') ? (int)db()->query('SELECT COUNT(*) AS c FROM clients WHERE is_active = 1')->fetch()['c'] : 0;

// Query status counts
$statusCounts = array_fill_keys($statusOrder, 0);
$statusRows = $fetchAllWithDate('SELECT status, COUNT(*) AS c FROM listing_tasks' . $taskDateWherePlain . ' GROUP BY status', $taskDateParams);
foreach ($statusRows as $row) {
    $key = (string)($row['status'] ?? '');
    if (array_key_exists($key, $statusCounts)) {
        $statusCounts[$key] = (int)$row['c'];
    }
}

// Handle CSV export
if (($_GET['export'] ?? '') === 'csv' && $isAdmin) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="citeflow-dashboard-' . date('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Type', 'Label', 'Value']);
    fputcsv($out, ['Filter', 'Date Range', $activeDateRangeLabel]);
    fputcsv($out, ['', '', '']);
    
    // KPIs
    fputcsv($out, ['KPI', 'Total Businesses', $totalBusinesses]);
    fputcsv($out, ['KPI', 'Active Directories', $totalDirectories]);
    fputcsv($out, ['KPI', 'Total Citations', $totalCitations]);
    fputcsv($out, ['KPI', 'Live Citations', $liveCitations]);
    fputcsv($out, ['KPI', 'Correct NAP (Live)', $correctNapCitations]);
    fputcsv($out, ['KPI', 'NAP Error (Live)', $napErrorCitations]);
    
    // Status distribution
    foreach ($statusCounts as $statusKey => $count) {
        $label = $statusLabels[$statusKey] ?? $statusKey;
        fputcsv($out, ['Status', $label, $count]);
    }
    
    fclose($out);
    exit;
}

$businessDetails = db()->query("SELECT b.name AS business_name,
                       b.logo_path,
                                      COALESCE(c.name, '-') AS client_name,
                                      CONCAT_WS(', ', b.city, b.state) AS location
                               FROM businesses b
                               LEFT JOIN clients c ON c.id = b.client_id
                               ORDER BY b.name ASC
                               LIMIT 150")->fetchAll();

$activeDirectoryDetails = db()->query("SELECT name,
                           logo_path,
                                             directory_type,
                                             country,
                                             priority_level
                                      FROM directories
                                      WHERE is_active = 1
                                      ORDER BY name ASC
                                      LIMIT 150")->fetchAll();

$citationByBusiness = $fetchAllWithDate("SELECT b.id AS business_id,
                        b.name AS business_name,
                        b.logo_path,
                        COUNT(*) AS total_citations,
                        SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_citations,
                        SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'correct' THEN 1 ELSE 0 END) AS correct_nap_citations,
                        SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'nap_error' THEN 1 ELSE 0 END) AS nap_error_citations
                    FROM listing_tasks lt
                    INNER JOIN businesses b ON b.id = lt.business_id
                    WHERE 1=1" . $taskDateWhereLt . "
                    GROUP BY b.id, b.name, b.logo_path
                    ORDER BY total_citations DESC, b.name ASC
                    LIMIT 200", $taskDateParams);

$liveByBusiness = $fetchAllWithDate("SELECT b.id AS business_id,
                      b.name AS business_name,
                      b.logo_path,
                      SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_citations,
                      SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'correct' THEN 1 ELSE 0 END) AS correct_nap_citations,
                      SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'nap_error' THEN 1 ELSE 0 END) AS nap_error_citations
                  FROM listing_tasks lt
                  INNER JOIN businesses b ON b.id = lt.business_id
                  WHERE 1=1" . $taskDateWhereLt . "
                  GROUP BY b.id, b.name, b.logo_path
                  HAVING live_citations > 0
                  ORDER BY live_citations DESC, b.name ASC
                  LIMIT 200", $taskDateParams);

$correctNapByBusiness = $fetchAllWithDate("SELECT b.id AS business_id,
                         b.name AS business_name,
                         b.logo_path,
                         SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_citations,
                         SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'correct' THEN 1 ELSE 0 END) AS correct_nap_citations
                     FROM listing_tasks lt
                     INNER JOIN businesses b ON b.id = lt.business_id
                     WHERE 1=1" . $taskDateWhereLt . "
                     GROUP BY b.id, b.name, b.logo_path
                     HAVING correct_nap_citations > 0
                     ORDER BY correct_nap_citations DESC, b.name ASC
                     LIMIT 200", $taskDateParams);

$napErrorByBusiness = $fetchAllWithDate("SELECT b.id AS business_id,
                          b.name AS business_name,
                          b.logo_path,
                          SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_citations,
                          SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'nap_error' THEN 1 ELSE 0 END) AS nap_error_citations
                      FROM listing_tasks lt
                      INNER JOIN businesses b ON b.id = lt.business_id
                      WHERE 1=1" . $taskDateWhereLt . "
                      GROUP BY b.id, b.name, b.logo_path
                      HAVING nap_error_citations > 0
                      ORDER BY nap_error_citations DESC, b.name ASC
                      LIMIT 200", $taskDateParams);

$pendingByBusiness = $fetchAllWithDate("SELECT b.id AS business_id,
                          b.name AS business_name,
                          b.logo_path,
                          COUNT(*) AS pending_citations,
                          SUM(CASE WHEN lt.status = 'not_started' THEN 1 ELSE 0 END) AS not_started_count,
                          SUM(CASE WHEN lt.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                          SUM(CASE WHEN lt.status = 'needs_edit' THEN 1 ELSE 0 END) AS needs_edit_count
                      FROM listing_tasks lt
                      INNER JOIN businesses b ON b.id = lt.business_id
                      WHERE lt.status IN ('not_started', 'in_progress', 'needs_edit')" . $taskDateWhereLt . "
                      GROUP BY b.id, b.name, b.logo_path
                      ORDER BY pending_citations DESC, b.name ASC
                      LIMIT 200", $taskDateParams);

$citationDrilldownRows = $fetchAllWithDate("SELECT lt.business_id,
                                             b.name AS business_name,
                                             d.name AS directory_name,
                                             d.logo_path AS directory_logo_path,
                                             lt.status,
                                             lt.nap_status,
                                             COALESCE(NULLIF(lt.submitted_url, ''), '-') AS citation_url,
                                             DATE_FORMAT(lt.updated_at, '%Y-%m-%d %H:%i') AS updated_at
                                      FROM listing_tasks lt
                                      INNER JOIN businesses b ON b.id = lt.business_id
                                      INNER JOIN directories d ON d.id = lt.directory_id
                                      WHERE 1=1" . $taskDateWhereLt . "
                                      ORDER BY lt.updated_at DESC
                                      LIMIT 4000", $taskDateParams);

$clientDetails = table_exists('clients')
    ? db()->query('SELECT c.name,
                          COUNT(b.id) AS business_count,
                          SUM(CASE WHEN b.status = "active" THEN 1 ELSE 0 END) AS active_businesses
                   FROM clients c
                   LEFT JOIN businesses b ON b.client_id = c.id
                   WHERE c.is_active = 1
                   GROUP BY c.id
                   ORDER BY c.name ASC
                   LIMIT 150')->fetchAll()
    : [];

$metricDetails = [
    'businesses' => [
        'title' => 'Businesses',
        'columns' => ['Business', 'Client', 'Location'],
        'rows' => array_map(static function (array $r): array {
            $name = (string)$r['business_name'];
            $logoPath = trim((string)($r['logo_path'] ?? ''));
            return [
                [
                    'text' => $name,
                    'avatar' => $logoPath !== '' ? public_asset_url($logoPath) : '',
                    'avatar_alt' => $name . ' logo',
                ],
                (string)$r['client_name'],
                (string)$r['location'],
            ];
        }, $businessDetails),
    ],
    'directories' => [
        'title' => 'Active Directories',
        'columns' => ['Directory', 'Type', 'Country', 'Priority'],
        'rows' => array_map(static function (array $r): array {
            $name = (string)$r['name'];
            $logoPath = trim((string)($r['logo_path'] ?? ''));
            return [
                [
                    'text' => $name,
                    'avatar' => $logoPath !== '' ? public_asset_url($logoPath) : '',
                    'avatar_alt' => $name . ' logo',
                ],
                (string)$r['directory_type'],
                (string)$r['country'],
                (string)$r['priority_level'],
            ];
        }, $activeDirectoryDetails),
    ],
    'citations' => [
        'title' => 'Total Citations',
        'columns' => ['Business', 'Total Citations', 'Live', 'Correct NAP (Live)', 'NAP Error (Live)'],
        'rows' => array_map(static function (array $r): array {
            $businessId = (int)$r['business_id'];
            $businessName = (string)$r['business_name'];
            $logoPath = trim((string)($r['logo_path'] ?? ''));
            return [
                [
                    'text' => $businessName,
                    'avatar' => $logoPath !== '' ? public_asset_url($logoPath) : '',
                    'avatar_alt' => $businessName . ' logo',
                ],
                ['text' => (string)$r['total_citations'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'total']],
                ['text' => (string)$r['live_citations'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'live']],
                ['text' => (string)$r['correct_nap_citations'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'correct_nap']],
                ['text' => (string)$r['nap_error_citations'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'nap_error']],
            ];
        }, $citationByBusiness),
    ],
    'live' => [
        'title' => 'Live Citations',
        'columns' => ['Business', 'Live Citations', 'Correct NAP', 'NAP Error', 'Correct Rate'],
        'rows' => array_map(static function (array $r): array {
            $businessId = (int)$r['business_id'];
            $businessName = (string)$r['business_name'];
            $logoPath = trim((string)($r['logo_path'] ?? ''));
            $live = (int)$r['live_citations'];
            $correct = (int)$r['correct_nap_citations'];
            $rate = $live > 0 ? round(($correct / $live) * 100, 1) : 0;
            return [
                [
                    'text' => $businessName,
                    'avatar' => $logoPath !== '' ? public_asset_url($logoPath) : '',
                    'avatar_alt' => $businessName . ' logo',
                ],
                ['text' => (string)$live, 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'live']],
                ['text' => (string)$correct, 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'correct_nap']],
                ['text' => (string)$r['nap_error_citations'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'nap_error']],
                $rate . '%',
            ];
        }, $liveByBusiness),
    ],
    'correct_nap' => [
        'title' => 'Correct NAP (Live)',
        'columns' => ['Business', 'Correct NAP (Live)', 'Total Live', 'Correct Rate'],
        'rows' => array_map(static function (array $r): array {
            $businessId = (int)$r['business_id'];
            $businessName = (string)$r['business_name'];
            $logoPath = trim((string)($r['logo_path'] ?? ''));
            $live = (int)$r['live_citations'];
            $correct = (int)$r['correct_nap_citations'];
            $rate = $live > 0 ? round(($correct / $live) * 100, 1) : 0;
            return [
                [
                    'text' => $businessName,
                    'avatar' => $logoPath !== '' ? public_asset_url($logoPath) : '',
                    'avatar_alt' => $businessName . ' logo',
                ],
                ['text' => (string)$correct, 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'correct_nap']],
                ['text' => (string)$live, 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'live']],
                $rate . '%',
            ];
        }, $correctNapByBusiness),
    ],
    'nap_error' => [
        'title' => 'NAP Error (Live)',
        'columns' => ['Business', 'NAP Error (Live)', 'Total Live', 'Error Rate'],
        'rows' => array_map(static function (array $r): array {
            $businessId = (int)$r['business_id'];
            $businessName = (string)$r['business_name'];
            $logoPath = trim((string)($r['logo_path'] ?? ''));
            $live = (int)$r['live_citations'];
            $errors = (int)$r['nap_error_citations'];
            $rate = $live > 0 ? round(($errors / $live) * 100, 1) : 0;
            return [
                [
                    'text' => $businessName,
                    'avatar' => $logoPath !== '' ? public_asset_url($logoPath) : '',
                    'avatar_alt' => $businessName . ' logo',
                ],
                ['text' => (string)$errors, 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'nap_error']],
                ['text' => (string)$live, 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'live']],
                $rate . '%',
            ];
        }, $napErrorByBusiness),
    ],
    'pending' => [
        'title' => 'Pending Citations',
        'columns' => ['Business', 'Pending Citations', 'Not Started', 'In Progress', 'Needs Edit'],
        'rows' => array_map(static function (array $r): array {
            $businessId = (int)$r['business_id'];
            $businessName = (string)$r['business_name'];
            $logoPath = trim((string)($r['logo_path'] ?? ''));
            return [
                [
                    'text' => $businessName,
                    'avatar' => $logoPath !== '' ? public_asset_url($logoPath) : '',
                    'avatar_alt' => $businessName . ' logo',
                ],
                ['text' => (string)$r['pending_citations'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'pending']],
                ['text' => (string)$r['not_started_count'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'not_started']],
                ['text' => (string)$r['in_progress_count'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'in_progress']],
                ['text' => (string)$r['needs_edit_count'], 'drilldown' => ['business_id' => $businessId, 'business_name' => $businessName, 'scope' => 'needs_edit']],
            ];
        }, $pendingByBusiness),
    ],
    'clients' => [
        'title' => 'Clients',
        'columns' => ['Client', 'Businesses', 'Active Businesses'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['name'], (string)$r['business_count'], (string)$r['active_businesses']], $clientDetails),
    ],
];

// Calculate additional metrics
$pendingCitations = $countWithDate("SELECT COUNT(*) AS c FROM listing_tasks lt WHERE lt.status IN ('not_started', 'in_progress', 'needs_edit')" . $taskDateWhereLt, $taskDateParams);
$successRate = $totalCitations > 0 ? round(($liveCitations / $totalCitations) * 100, 1) : 0;
$avgCitationsPerBusiness = $totalBusinesses > 0 ? round($totalCitations / $totalBusinesses, 1) : 0;
$napCorrectRate = $liveCitations > 0 ? round(($correctNapCitations / $liveCitations) * 100, 1) : 0;

$metricItems = [
    ['key' => 'businesses', 'label' => 'Businesses', 'value' => $totalBusinesses, 'icon' => '🏢', 'text' => 'text-blue-700 dark:text-blue-300', 'label_text' => 'text-blue-600 dark:text-blue-200', 'bg' => 'bg-blue-50 dark:bg-blue-950/40', 'border' => 'border-blue-200 dark:border-blue-900/60', 'bar' => 'bg-blue-500 dark:bg-blue-400', 'subtext' => 'Total managed accounts'],
    ['key' => 'directories', 'label' => 'Active Directories', 'value' => $totalDirectories, 'icon' => '📂', 'text' => 'text-violet-700 dark:text-violet-300', 'label_text' => 'text-violet-600 dark:text-violet-200', 'bg' => 'bg-violet-50 dark:bg-violet-950/40', 'border' => 'border-violet-200 dark:border-violet-900/60', 'bar' => 'bg-violet-500 dark:bg-violet-400', 'subtext' => 'Available platforms'],
    ['key' => 'citations', 'label' => 'Total Citations', 'value' => $totalCitations, 'icon' => '📋', 'text' => 'text-cyan-700 dark:text-cyan-300', 'label_text' => 'text-cyan-600 dark:text-cyan-200', 'bg' => 'bg-cyan-50 dark:bg-cyan-950/40', 'border' => 'border-cyan-200 dark:border-cyan-900/60', 'bar' => 'bg-cyan-500 dark:bg-cyan-400', 'subtext' => $avgCitationsPerBusiness . ' per business'],
    ['key' => 'live', 'label' => 'Live Citations', 'value' => $liveCitations, 'icon' => '✅', 'text' => 'text-emerald-700 dark:text-emerald-300', 'label_text' => 'text-emerald-600 dark:text-emerald-200', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/40', 'border' => 'border-emerald-200 dark:border-emerald-900/60', 'bar' => 'bg-emerald-500 dark:bg-emerald-400', 'subtext' => $successRate . '% success rate'],
    ['key' => 'correct_nap', 'label' => 'Correct NAP (Live)', 'value' => $correctNapCitations, 'icon' => '✔️', 'text' => 'text-teal-700 dark:text-teal-300', 'label_text' => 'text-teal-600 dark:text-teal-200', 'bg' => 'bg-teal-50 dark:bg-teal-950/40', 'border' => 'border-teal-200 dark:border-teal-900/60', 'bar' => 'bg-teal-500 dark:bg-teal-400', 'subtext' => $napCorrectRate . '% accuracy'],
    ['key' => 'nap_error', 'label' => 'NAP Error (Live)', 'value' => $napErrorCitations, 'icon' => '⚠️', 'text' => 'text-rose-700 dark:text-rose-300', 'label_text' => 'text-rose-600 dark:text-rose-200', 'bg' => 'bg-rose-50 dark:bg-rose-950/40', 'border' => 'border-rose-200 dark:border-rose-900/60', 'bar' => 'bg-rose-500 dark:bg-rose-400', 'subtext' => 'Requires attention'],
    ['key' => 'pending', 'label' => 'Pending Citations', 'value' => $pendingCitations, 'icon' => '⏳', 'text' => 'text-amber-700 dark:text-amber-300', 'label_text' => 'text-amber-600 dark:text-amber-200', 'bg' => 'bg-amber-50 dark:bg-amber-950/40', 'border' => 'border-amber-200 dark:border-amber-900/60', 'bar' => 'bg-amber-500 dark:bg-amber-400', 'subtext' => 'In progress'],
];
if ($isAdmin) {
    $metricItems[] = ['key' => 'clients', 'label' => 'Clients', 'value' => $totalClients, 'icon' => '👥', 'text' => 'text-slate-700 dark:text-slate-300', 'label_text' => 'text-slate-600 dark:text-slate-200', 'bg' => 'bg-slate-50 dark:bg-slate-950/40', 'border' => 'border-slate-200 dark:border-slate-900/60', 'bar' => 'bg-slate-500 dark:bg-slate-400', 'subtext' => 'Active accounts'];
}
$metricTotal = 0;
foreach ($metricItems as $metric) {
    $metricTotal += (int)$metric['value'];
}

$statusColors = [
    'not_started' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-200',
    'submitted' => 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-200',
    'live' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200',
    'needs_edit' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-200',
    'rejected' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-200',
];

$statusBarColors = [
    'not_started' => 'bg-slate-500 dark:bg-slate-400',
    'in_progress' => 'bg-blue-500 dark:bg-blue-400',
    'submitted' => 'bg-violet-500 dark:bg-violet-400',
    'live' => 'bg-emerald-500 dark:bg-emerald-400',
    'needs_edit' => 'bg-amber-500 dark:bg-amber-400',
    'rejected' => 'bg-rose-500 dark:bg-rose-400',
];

$recentActivity = [];
if (table_exists('activity_logs')) {
    $recentActivityStmt = db()->query("SELECT al.entity_type, al.entity_id, al.action, al.created_at, u.full_name AS actor_name
                                       FROM activity_logs al
                                       LEFT JOIN users u ON u.id = al.actor_id
                                       ORDER BY al.id DESC
                                       LIMIT 12");
    $recentActivity = $recentActivityStmt->fetchAll();
}

$businessRows = db()->query('SELECT id, name, logo_path, phone, website, email, address_line1, city, state, postal_code, country, category, categories, description, hours_json, contact_name, social_media FROM businesses ORDER BY updated_at DESC LIMIT 12')->fetchAll();
$completenessLeaderboard = [];
foreach ($businessRows as $businessRow) {
    $details = business_completeness($businessRow);
    $completenessLeaderboard[] = [
        'id' => (int)$businessRow['id'],
        'name' => (string)$businessRow['name'],
        'score' => (int)$details['score'],
        'missing' => $details['missing'],
    ];
}
usort($completenessLeaderboard, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
$completenessLeaderboard = array_slice($completenessLeaderboard, 0, 6);

$clientSnapshot = table_exists('clients') ? db()->query('SELECT c.id, c.name, COUNT(b.id) AS business_count,
                                                                SUM(CASE WHEN b.status = "active" THEN 1 ELSE 0 END) AS active_businesses
                                                         FROM clients c
                                                         LEFT JOIN businesses b ON b.client_id = c.id
                                                         GROUP BY c.id
                                                         ORDER BY business_count DESC, c.name ASC
                                                         LIMIT 6')->fetchAll() : [];

render_header('Dashboard');
?>
<div class="mb-8 rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-6 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white"><?php echo $isAdmin ? 'Admin' : 'Employee'; ?> Dashboard</h1>
            <p class="mt-2 text-base font-medium text-slate-700 dark:text-slate-200"><?php echo $isAdmin ? 'Full system access enabled. Manage users, directories, and operational data.' : 'Your account has employee-level access. System administration and protected settings are hidden.'; ?></p>
            <p class="mt-1 text-sm font-semibold text-brand-700 dark:text-brand-300">Analytics Range: <?php echo e($activeDateRangeLabel); ?></p>
        </div>
        <?php if ($isAdmin): ?>
            <div class="flex flex-wrap justify-end gap-2">
                <a class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" href="<?php echo e(app_config()['base_url']); ?>/users.php">Manage Users</a>
                <a class="rounded-lg border border-slate-300 bg-white/80 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100 dark:hover:bg-slate-700" href="<?php echo e(app_config()['base_url']); ?>/directories.php">Manage Directories</a>
            </div>
        <?php endif; ?>
    </div>
    <form method="get" class="mt-5 grid gap-2 rounded-xl border border-slate-200 bg-white/70 p-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end dark:border-slate-700 dark:bg-slate-900/40">
        <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Date Range</label>
            <select name="date_preset" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <option value="7d" <?php echo $datePreset === '7d' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30d" <?php echo $datePreset === '30d' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90d" <?php echo $datePreset === '90d' ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="custom" <?php echo $datePreset === 'custom' ? 'selected' : ''; ?>>Custom</option>
                <option value="all" <?php echo $datePreset === 'all' ? 'selected' : ''; ?>>All Time</option>
            </select>
        </div>
        <div id="fromDateWrap">
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">From</label>
            <input type="date" name="from_date" value="<?php echo e($rangeFrom ?? ''); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
        </div>
        <div id="toDateWrap">
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">To</label>
            <input type="date" name="to_date" value="<?php echo e($rangeTo ?? ''); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Apply</button>
            <a href="<?php echo e(app_config()['base_url']); ?>/dashboard.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Reset</a>
        </div>
    </form>
</div>

<section class="mb-8">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Key Performance Metrics</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Click any metric to view detailed breakdown</p>
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <?php foreach ($metricItems as $metric): ?>
            <button
                type="button"
                class="metric-box group relative rounded-xl border <?php echo e($metric['border']); ?> <?php echo e($metric['bg']); ?> px-5 py-6 text-left shadow-sm transition-all duration-200 hover:border-opacity-100 hover:shadow-lg hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900"
                data-metric-key="<?php echo e((string)$metric['key']); ?>"
                data-metric-label="<?php echo e((string)$metric['label']); ?>"
            >
                <div class="absolute right-4 top-4 text-3xl opacity-20 group-hover:opacity-40 transition-opacity"><?php echo e((string)$metric['icon']); ?></div>
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest <?php echo e($metric['label_text']); ?>"><?php echo e((string)$metric['label']); ?></p>
                        <p class="mt-3 text-4xl font-extrabold leading-none <?php echo e($metric['text']); ?>"><?php echo e((string)$metric['value']); ?></p>
                        <p class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-400"><?php echo e((string)($metric['subtext'] ?? '')); ?></p>
                    </div>
                    <div class="flex items-center justify-center h-12 w-12 rounded-lg bg-white/50 dark:bg-slate-800/50 group-hover:bg-white dark:group-hover:bg-slate-700 transition">
                        <span class="text-xl"><?php echo e((string)$metric['icon']); ?></span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t <?php echo e($metric['border']); ?> flex items-center text-xs font-semibold text-slate-600 dark:text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-300 transition">
                    <span>View Details</span>
                    <span class="ml-1 group-hover:translate-x-0.5 transition">→</span>
                </div>
            </button>
        <?php endforeach; ?>
    </div>
</section>

<div id="metricDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="w-full max-w-5xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <h3 id="metricDetailTitle" class="text-base font-bold text-slate-900 dark:text-slate-100">Metric Details</h3>
            <button id="closeMetricDetailModal" type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Close</button>
        </div>
        <div class="max-h-[70vh] overflow-auto p-5">
            <div id="metricDetailEmpty" class="hidden rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">No data available for this metric.</div>
            <div id="metricDetailTableWrap" class="cf-table-wrap hidden overflow-x-auto">
                <table class="cf-table min-w-[860px] divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr id="metricDetailHead"></tr>
                    </thead>
                    <tbody id="metricDetailBody" class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="citationDrilldownModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/60 px-4" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="w-full max-w-5xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <h3 id="citationDrilldownTitle" class="text-base font-bold text-slate-900 dark:text-slate-100">Citation Drilldown</h3>
            <button id="closeCitationDrilldownModal" type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Close</button>
        </div>
        <div class="max-h-[70vh] overflow-auto p-5">
            <div id="citationDrilldownEmpty" class="hidden rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">No citation records found for this selection.</div>
            <div id="citationDrilldownTableWrap" class="cf-table-wrap hidden overflow-x-auto">
                <table class="cf-table min-w-[900px] divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Directory</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">NAP</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Citation URL</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Updated</th>
                        </tr>
                    </thead>
                    <tbody id="citationDrilldownBody" class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<section class="mb-8 rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:to-slate-800/50">
    <div class="mb-8 flex items-center justify-between gap-2">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Metrics Overview</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">System-wide distribution across all key performance indicators.</p>
        </div>
        <div class="flex gap-2">
            <span class="inline-flex rounded-lg bg-brand-50 px-4 py-2 text-sm font-bold text-brand-700 dark:bg-brand-950/40 dark:text-brand-200">Total: <span class="ml-2"><?php echo e((string)$metricTotal); ?></span></span>
        </div>
    </div>
    <?php if ($metricTotal > 0): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900/50">
            <div class="mb-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">Distribution</p>
                <div class="h-4 w-full overflow-hidden rounded-lg bg-slate-200 dark:bg-slate-700">
                    <div class="flex h-full w-full">
                        <?php foreach ($metricItems as $metric): ?>
                            <?php
                                $value = (int)$metric['value'];
                                $ratio = round(($value / $metricTotal) * 100, 2);
                            ?>
                            <div class="h-full <?php echo e($metric['bar']); ?>" style="width: <?php echo e((string)$ratio); ?>%;" title="<?php echo e((string)$metric['label']); ?>: <?php echo e((string)$ratio); ?>%"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($metricItems as $metric): ?>
                    <?php
                        $value = (int)$metric['value'];
                        $ratio = round(($value / $metricTotal) * 100, 1);
                    ?>
                    <button
                        type="button"
                        class="metric-summary-btn flex items-center justify-between rounded-lg border <?php echo e($metric['border']); ?> bg-slate-50/50 px-4 py-3 transition hover:border-opacity-100 hover:bg-white dark:bg-slate-800/30 dark:hover:bg-slate-800"
                        data-metric-key="<?php echo e((string)$metric['key']); ?>"
                    >
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg"><?php echo e((string)$metric['icon']); ?></span>
                            <div class="text-left">
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300"><?php echo e((string)$metric['label']); ?></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e((string)$ratio); ?>%</p>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo e((string)$value); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">No metric data available yet. Start by adding businesses and directories.</div>
    <?php endif; ?>
</section>

<section class="grid gap-6 <?php echo $isAdmin ? 'lg:grid-cols-2' : 'lg:grid-cols-1'; ?>">
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-6 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Citation Status Pipeline</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Current workload distribution by status.</p>
            </div>
            <a class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" href="<?php echo e(app_config()['base_url']); ?>/businesses.php">Open Businesses</a>
        </div>
        <div class="space-y-3.5">
            <?php foreach ($statusOrder as $statusKey): ?>
                <?php
                    $count = (int)($statusCounts[$statusKey] ?? 0);
                    $ratio = $totalCitations > 0 ? round(($count / $totalCitations) * 100, 1) : 0;
                ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e($statusColors[$statusKey] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'); ?>"><?php echo e($statusLabels[$statusKey] ?? $statusKey); ?></span>
                        <span class="text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo e((string)$count); ?></span>
                    </div>
                    <div class="mb-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div class="h-full rounded-full <?php echo e($statusBarColors[$statusKey] ?? 'bg-brand-600 dark:bg-brand-500'); ?>" style="width: <?php echo e((string)$ratio); ?>%;"></div>
                    </div>
                    <p class="text-xs font-medium text-slate-600 dark:text-slate-400"><?php echo e((string)$ratio); ?>% of total citations</p>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <?php if ($isAdmin): ?>
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-6 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Recent Activity</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Latest changes across all system entities.</p>
                </div>
                <a class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" href="<?php echo e(app_config()['base_url']); ?>/activity_logs.php">View All</a>
            </div>
            <div class="space-y-2">
                <?php foreach ($recentActivity as $item): ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/70">
                        <p class="text-sm font-semibold text-slate-800"><?php echo e((string)($item['actor_name'] ?: 'System')); ?> · <?php echo e((string)$item['action']); ?></p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><?php echo e((string)$item['entity_type']); ?> #<?php echo e((string)$item['entity_id']); ?> · <?php echo e((string)$item['created_at']); ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recentActivity): ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-300">No activity logs yet.</div>
                <?php endif; ?>
            </div>
        </article>

    <?php endif; ?>
</section>

<section class="mt-8 grid gap-6 lg:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-6 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Completeness Watchlist</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Lowest readiness scores requiring attention.</p>
            </div>
            <a class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" href="<?php echo e(app_config()['base_url']); ?>/businesses.php">Open Businesses</a>
        </div>
        <div class="space-y-3">
            <?php foreach ($completenessLeaderboard as $item): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/70">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900"><?php echo e((string)$item['name']); ?></p>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e(completeness_badge_class((int)$item['score'])); ?>"><?php echo e((string)$item['score']); ?>%</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e(implode(', ', array_slice($item['missing'], 0, 3))); ?></p>
                </div>
            <?php endforeach; ?>
            <?php if (!$completenessLeaderboard): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-300">No businesses available yet.</div>
            <?php endif; ?>
        </div>
    </article>
</section>

<?php if ($isAdmin): ?>
<section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="mb-6 flex items-center justify-between gap-2">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Client Snapshot</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Business volume distribution by account.</p>
        </div>
            <a class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700" href="<?php echo e($exportCsvUrl); ?>">Export CSV</a>
    </div>
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($clientSnapshot as $client): ?>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-sm font-semibold text-slate-900"><?php echo e((string)$client['name']); ?></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e((string)$client['business_count']); ?> businesses · <?php echo e((string)$client['active_businesses']); ?> active</p>
            </div>
        <?php endforeach; ?>
        <?php if (!$clientSnapshot): ?>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-300">No clients configured yet.</div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
<script>
const metricDetails = <?php echo json_encode($metricDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const citationDrilldownRows = <?php echo json_encode($citationDrilldownRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const metricModal = document.getElementById('metricDetailModal');
const metricTitle = document.getElementById('metricDetailTitle');
const metricHead = document.getElementById('metricDetailHead');
const metricBody = document.getElementById('metricDetailBody');
const metricEmpty = document.getElementById('metricDetailEmpty');
const metricTableWrap = document.getElementById('metricDetailTableWrap');
const closeMetricBtn = document.getElementById('closeMetricDetailModal');
const citationModal = document.getElementById('citationDrilldownModal');
const citationTitle = document.getElementById('citationDrilldownTitle');
const citationBody = document.getElementById('citationDrilldownBody');
const citationEmpty = document.getElementById('citationDrilldownEmpty');
const citationTableWrap = document.getElementById('citationDrilldownTableWrap');
const closeCitationBtn = document.getElementById('closeCitationDrilldownModal');

const statusLabel = (status) => {
    const value = String(status || '');
    if (value === '') {
        return '-';
    }

    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

const scopeLabels = {
    total: 'Total Citations',
    live: 'Live Citations',
    correct_nap: 'Correct NAP (Live)',
    nap_error: 'NAP Error (Live)',
    pending: 'Pending Citations',
    not_started: 'Not Started',
    in_progress: 'In Progress',
    needs_edit: 'Needs Edit',
};

const makeDirInitial = (name) => {
    const el = document.createElement('span');
    el.className = 'inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded border border-slate-200 bg-slate-100 text-xs font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300';
    el.textContent = String(name || '?').charAt(0).toUpperCase();
    return el;
};

const closeCitationModal = () => {
    citationModal.classList.add('hidden');
    citationModal.classList.remove('flex');
    citationModal.setAttribute('aria-hidden', 'true');
};

const renderCitationDrilldown = (drilldown) => {
    const businessId = Number(drilldown.business_id || 0);
    const businessName = String(drilldown.business_name || 'Business');
    const scope = String(drilldown.scope || 'total');

    const matchesScope = (row) => {
        if (scope === 'live') return row.status === 'live';
        if (scope === 'correct_nap') return row.status === 'live' && row.nap_status === 'correct';
        if (scope === 'nap_error') return row.status === 'live' && row.nap_status === 'nap_error';
        if (scope === 'pending') return ['not_started', 'in_progress', 'needs_edit'].includes(row.status);
        if (scope === 'not_started') return row.status === 'not_started';
        if (scope === 'in_progress') return row.status === 'in_progress';
        if (scope === 'needs_edit') return row.status === 'needs_edit';
        return true;
    };

    const rows = citationDrilldownRows.filter((row) => Number(row.business_id) === businessId && matchesScope(row));
    citationTitle.textContent = `${businessName} - ${scopeLabels[scope] || 'Citations'} (${rows.length})`;
    citationBody.innerHTML = '';

    if (!rows.length) {
        citationTableWrap.classList.add('hidden');
        citationEmpty.classList.remove('hidden');
    } else {
        rows.forEach((row) => {
            const tr = document.createElement('tr');

            // Directory cell with logo
            const dirTd = document.createElement('td');
            dirTd.className = 'px-4 py-3 text-sm text-slate-700 dark:text-slate-300';
            const dirWrap = document.createElement('div');
            dirWrap.className = 'flex items-center gap-2';
            const dirLogoPath = String(row.directory_logo_path || '');
            const dirName = String(row.directory_name || '-');
            if (dirLogoPath) {
                const img = document.createElement('img');
                img.src = dirLogoPath;
                img.alt = dirName + ' logo';
                img.className = 'h-6 w-6 flex-shrink-0 rounded border border-slate-200 bg-white object-cover dark:border-slate-700 dark:bg-slate-900';
                img.onerror = function () { this.replaceWith(makeDirInitial(dirName)); };
                dirWrap.appendChild(img);
            } else {
                dirWrap.appendChild(makeDirInitial(dirName));
            }
            const dirTextSpan = document.createElement('span');
            dirTextSpan.className = 'font-semibold';
            dirTextSpan.textContent = dirName;
            dirWrap.appendChild(dirTextSpan);
            dirTd.appendChild(dirWrap);
            tr.appendChild(dirTd);

            // Remaining cells: status, nap, url, updated
            const restCells = [
                statusLabel(String(row.status || '-')),
                statusLabel(String(row.nap_status || '-')),
                String(row.citation_url || '-'),
                String(row.updated_at || '-'),
            ];
            restCells.forEach((cellValue, index) => {
                const td = document.createElement('td');
                td.className = 'px-4 py-3 text-sm text-slate-700 dark:text-slate-300';
                if (index === 2 && /^https?:\/\//i.test(cellValue)) {
                    const link = document.createElement('a');
                    link.href = cellValue;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.className = 'font-semibold text-blue-600 hover:text-blue-800 hover:underline';
                    link.textContent = cellValue;
                    td.appendChild(link);
                } else {
                    td.textContent = cellValue;
                }
                tr.appendChild(td);
            });
            citationBody.appendChild(tr);
        });
        citationEmpty.classList.add('hidden');
        citationTableWrap.classList.remove('hidden');
    }

    citationModal.classList.remove('hidden');
    citationModal.classList.add('flex');
    citationModal.setAttribute('aria-hidden', 'false');
};

const renderMetricDetail = (metricKey) => {
    const detail = metricDetails[metricKey] ?? null;
    if (!detail) {
        return;
    }

    metricTitle.textContent = `${detail.title} Details`;
    metricHead.innerHTML = '';
    metricBody.innerHTML = '';

    detail.columns.forEach((column) => {
        const th = document.createElement('th');
        th.className = 'px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400';
        th.textContent = column;
        metricHead.appendChild(th);
    });

    if (!detail.rows.length) {
        metricTableWrap.classList.add('hidden');
        metricEmpty.classList.remove('hidden');
    } else {
        detail.rows.forEach((row) => {
            const tr = document.createElement('tr');
            row.forEach((cell, index) => {
                const td = document.createElement('td');
                td.className = 'px-4 py-3 text-sm text-slate-700 dark:text-slate-300';
                const isCellObject = cell !== null && typeof cell === 'object' && !Array.isArray(cell);
                const value = isCellObject ? String(cell.text ?? '-') : String(cell ?? '-');
                const header = String(detail.columns[index] ?? '').toLowerCase();
                const isUrlColumn = header.includes('url');
                const isHttpUrl = /^https?:\/\//i.test(value);
                const drilldown = isCellObject && cell.drilldown && typeof cell.drilldown === 'object' ? cell.drilldown : null;
                const avatar = isCellObject ? String(cell.avatar ?? '') : '';
                const avatarAlt = isCellObject ? String(cell.avatar_alt ?? value) : value;
                const hasAvatarField = isCellObject && Object.prototype.hasOwnProperty.call(cell, 'avatar');

                if (drilldown !== null) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'font-semibold text-blue-600 hover:text-blue-800 hover:underline';
                    btn.textContent = value;
                    btn.addEventListener('click', () => renderCitationDrilldown(drilldown));
                    td.appendChild(btn);
                } else if (hasAvatarField) {
                    const wrap = document.createElement('div');
                    wrap.className = 'flex items-center gap-2';

                    if (avatar !== '') {
                        const img = document.createElement('img');
                        img.src = avatar;
                        img.alt = avatarAlt;
                        img.className = 'h-7 w-7 rounded-md border border-slate-200 bg-white object-cover dark:border-slate-700 dark:bg-slate-900';
                        wrap.appendChild(img);
                    } else {
                        const initial = value.trim() !== '' ? value.trim().charAt(0).toUpperCase() : '?';
                        const fallback = document.createElement('span');
                        fallback.className = 'inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-slate-100 text-xs font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300';
                        fallback.textContent = initial;
                        fallback.setAttribute('aria-label', 'No logo available');
                        wrap.appendChild(fallback);
                    }

                    const text = document.createElement('span');
                    text.className = 'font-semibold text-slate-800 dark:text-slate-100';
                    text.textContent = value;

                    wrap.appendChild(text);
                    td.appendChild(wrap);
                } else if (isUrlColumn && isHttpUrl) {
                    const link = document.createElement('a');
                    link.href = value;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.className = 'font-semibold text-blue-600 hover:text-blue-800 hover:underline';
                    link.textContent = value;
                    td.appendChild(link);
                } else {
                    td.textContent = value;
                }
                tr.appendChild(td);
            });
            metricBody.appendChild(tr);
        });
        metricEmpty.classList.add('hidden');
        metricTableWrap.classList.remove('hidden');
    }
};

const openMetricModal = (metricKey) => {
    renderMetricDetail(metricKey);
    metricModal.classList.remove('hidden');
    metricModal.classList.add('flex');
    metricModal.setAttribute('aria-hidden', 'false');
};

const closeMetricModal = () => {
    closeCitationModal();
    metricModal.classList.add('hidden');
    metricModal.classList.remove('flex');
    metricModal.setAttribute('aria-hidden', 'true');
};

document.querySelectorAll('.metric-box').forEach((box) => {
    box.addEventListener('click', () => {
        const metricKey = box.getAttribute('data-metric-key') || '';
        openMetricModal(metricKey);
    });
});

document.querySelectorAll('.metric-summary-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        const metricKey = btn.getAttribute('data-metric-key') || '';
        openMetricModal(metricKey);
    });
});

closeMetricBtn.addEventListener('click', closeMetricModal);
metricModal.addEventListener('click', (event) => {
    if (event.target === metricModal) {
        closeMetricModal();
    }
});

closeCitationBtn.addEventListener('click', closeCitationModal);
citationModal.addEventListener('click', (event) => {
    if (event.target === citationModal) {
        closeCitationModal();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !citationModal.classList.contains('hidden')) {
        closeCitationModal();
    } else if (event.key === 'Escape' && !metricModal.classList.contains('hidden')) {
        closeMetricModal();
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const datePresetSelect = document.querySelector('select[name="date_preset"]');
    const fromDateWrap = document.getElementById('fromDateWrap');
    const toDateWrap = document.getElementById('toDateWrap');
    function updateDateFields() {
        const show = datePresetSelect.value === 'custom';
        fromDateWrap.style.display = show ? '' : 'none';
        toDateWrap.style.display = show ? '' : 'none';
    }
    if (datePresetSelect && fromDateWrap && toDateWrap) {
        datePresetSelect.addEventListener('change', updateDateFields);
        updateDateFields();
    }
});
</script>
<?php render_footer(); ?>