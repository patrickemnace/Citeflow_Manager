<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('reports');

$totalBusinesses = (int)db()->query('SELECT COUNT(*) AS c FROM businesses')->fetch()['c'];
$totalDirectories = (int)db()->query('SELECT COUNT(*) AS c FROM directories WHERE is_active = 1')->fetch()['c'];
$totalCitations = (int)db()->query('SELECT COUNT(*) AS c FROM listing_tasks')->fetch()['c'];
$liveCitations = (int)db()->query("SELECT COUNT(*) AS c FROM listing_tasks WHERE status = 'live'")->fetch()['c'];
$pendingSubmissionCitations = (int)db()->query("SELECT COUNT(*) AS c FROM listing_tasks WHERE status = 'pending_submission'")->fetch()['c'];
$inProgressCitations = (int)db()->query("SELECT COUNT(*) AS c FROM listing_tasks WHERE status = 'in_progress'")->fetch()['c'];
$correctNapLiveCitations = (int)db()->query("SELECT COUNT(*) AS c FROM listing_tasks WHERE status = 'live' AND nap_status = 'correct'")->fetch()['c'];
$napErrorLiveCitations = (int)db()->query("SELECT COUNT(*) AS c FROM listing_tasks WHERE status = 'live' AND nap_status = 'nap_error'")->fetch()['c'];
$liveRate = $totalCitations > 0 ? round(($liveCitations / $totalCitations) * 100, 1) : 0.0;

$businessDetails = db()->query("SELECT b.name AS business_name,
                                       COALESCE(c.name, '-') AS client_name,
                                       CONCAT_WS(', ', b.city, b.state) AS location
                                FROM businesses b
                                LEFT JOIN clients c ON c.id = b.client_id
                                ORDER BY b.name ASC
                                LIMIT 150")->fetchAll();

$activeDirectoryDetails = db()->query("SELECT name,
                                              directory_type,
                                              country,
                                              priority_level
                                       FROM directories
                                       WHERE is_active = 1
                                       ORDER BY name ASC
                                       LIMIT 150")->fetchAll();

$allCitationDetails = db()->query("SELECT b.name AS business_name,
                                          d.name AS directory_name,
                                          lt.status,
                                          lt.nap_status
                                   FROM listing_tasks lt
                                   INNER JOIN businesses b ON b.id = lt.business_id
                                   INNER JOIN directories d ON d.id = lt.directory_id
                                   ORDER BY lt.updated_at DESC
                                   LIMIT 200")->fetchAll();

$liveCitationDetails = db()->query("SELECT b.name AS business_name,
                                           d.name AS directory_name,
                                           COALESCE(NULLIF(lt.submitted_url, ''), '-') AS citation_url,
                                           lt.nap_status
                                    FROM listing_tasks lt
                                    INNER JOIN businesses b ON b.id = lt.business_id
                                    INNER JOIN directories d ON d.id = lt.directory_id
                                    WHERE lt.status = 'live'
                                    ORDER BY lt.updated_at DESC
                                    LIMIT 200")->fetchAll();

$pendingSubmissionDetails = db()->query("SELECT b.name AS business_name,
                                                d.name AS directory_name,
                                                COALESCE(NULLIF(lt.submitted_url, ''), '-') AS submission_url,
                                                lt.nap_status
                                         FROM listing_tasks lt
                                         INNER JOIN businesses b ON b.id = lt.business_id
                                         INNER JOIN directories d ON d.id = lt.directory_id
                                         WHERE lt.status = 'pending_submission'
                                         ORDER BY lt.updated_at DESC
                                         LIMIT 200")->fetchAll();

$inProgressDetails = db()->query("SELECT b.name AS business_name,
                                         d.name AS directory_name,
                                         lt.priority_level,
                                         lt.nap_status
                                  FROM listing_tasks lt
                                  INNER JOIN businesses b ON b.id = lt.business_id
                                  INNER JOIN directories d ON d.id = lt.directory_id
                                  WHERE lt.status = 'in_progress'
                                  ORDER BY lt.updated_at DESC
                                  LIMIT 200")->fetchAll();

$correctNapLiveDetails = db()->query("SELECT b.name AS business_name,
                                             d.name AS directory_name,
                                             COALESCE(NULLIF(lt.submitted_url, ''), '-') AS citation_url
                                      FROM listing_tasks lt
                                      INNER JOIN businesses b ON b.id = lt.business_id
                                      INNER JOIN directories d ON d.id = lt.directory_id
                                      WHERE lt.status = 'live' AND lt.nap_status = 'correct'
                                      ORDER BY lt.updated_at DESC
                                      LIMIT 200")->fetchAll();

$napErrorLiveDetails = db()->query("SELECT b.name AS business_name,
                                           d.name AS directory_name,
                                           COALESCE(NULLIF(lt.submitted_url, ''), '-') AS citation_url
                                    FROM listing_tasks lt
                                    INNER JOIN businesses b ON b.id = lt.business_id
                                    INNER JOIN directories d ON d.id = lt.directory_id
                                    WHERE lt.status = 'live' AND lt.nap_status = 'nap_error'
                                    ORDER BY lt.updated_at DESC
                                    LIMIT 200")->fetchAll();

$metricDetails = [
    'businesses' => [
        'title' => 'Businesses',
        'columns' => ['Business', 'Client', 'Location'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['business_name'], (string)$r['client_name'], (string)$r['location']], $businessDetails),
    ],
    'active_directories' => [
        'title' => 'Active Directories',
        'columns' => ['Directory', 'Type', 'Country', 'Priority'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['name'], (string)$r['directory_type'], (string)$r['country'], (string)$r['priority_level']], $activeDirectoryDetails),
    ],
    'total_citations' => [
        'title' => 'Total Citations',
        'columns' => ['Business', 'Directory', 'Status', 'NAP'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['business_name'], (string)$r['directory_name'], status_label((string)$r['status']), (string)$r['nap_status']], $allCitationDetails),
    ],
    'live_citations' => [
        'title' => 'Live Citations',
        'columns' => ['Business', 'Directory', 'Citation URL', 'NAP'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['business_name'], (string)$r['directory_name'], (string)$r['citation_url'], (string)$r['nap_status']], $liveCitationDetails),
    ],
    'pending_submission' => [
        'title' => 'Pending Submission',
        'columns' => ['Business', 'Directory', 'Submission URL', 'NAP'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['business_name'], (string)$r['directory_name'], (string)$r['submission_url'], (string)$r['nap_status']], $pendingSubmissionDetails),
    ],
    'in_progress' => [
        'title' => 'In Progress',
        'columns' => ['Business', 'Directory', 'Priority', 'NAP'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['business_name'], (string)$r['directory_name'], (string)$r['priority_level'], (string)$r['nap_status']], $inProgressDetails),
    ],
    'correct_nap_live' => [
        'title' => 'Correct NAP (Live)',
        'columns' => ['Business', 'Directory', 'Citation URL'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['business_name'], (string)$r['directory_name'], (string)$r['citation_url']], $correctNapLiveDetails),
    ],
    'nap_error_live' => [
        'title' => 'NAP Error (Live)',
        'columns' => ['Business', 'Directory', 'Citation URL'],
        'rows' => array_map(static fn(array $r): array => [(string)$r['business_name'], (string)$r['directory_name'], (string)$r['citation_url']], $napErrorLiveDetails),
    ],
    'live_rate' => [
        'title' => 'Live Rate',
        'columns' => ['Metric', 'Value'],
        'rows' => [
            ['Total Citations', (string)$totalCitations],
            ['Live Citations', (string)$liveCitations],
            ['Live Rate', (string)$liveRate . '%'],
        ],
    ],
];

$reportMetrics = [
    ['key' => 'businesses', 'label' => 'Businesses', 'value' => (string)$totalBusinesses, 'tone' => 'border-blue-200 bg-blue-50 text-blue-800'],
    ['key' => 'active_directories', 'label' => 'Active Directories', 'value' => (string)$totalDirectories, 'tone' => 'border-violet-200 bg-violet-50 text-violet-800'],
    ['key' => 'total_citations', 'label' => 'Total Citations', 'value' => (string)$totalCitations, 'tone' => 'border-cyan-200 bg-cyan-50 text-cyan-800'],
    ['key' => 'live_citations', 'label' => 'Live Citations', 'value' => (string)$liveCitations, 'tone' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
    ['key' => 'pending_submission', 'label' => 'Pending Submission', 'value' => (string)$pendingSubmissionCitations, 'tone' => 'border-amber-200 bg-amber-50 text-amber-800'],
    ['key' => 'in_progress', 'label' => 'In Progress', 'value' => (string)$inProgressCitations, 'tone' => 'border-indigo-200 bg-indigo-50 text-indigo-800'],
    ['key' => 'correct_nap_live', 'label' => 'Correct NAP (Live)', 'value' => (string)$correctNapLiveCitations, 'tone' => 'border-teal-200 bg-teal-50 text-teal-800'],
    ['key' => 'nap_error_live', 'label' => 'NAP Error (Live)', 'value' => (string)$napErrorLiveCitations, 'tone' => 'border-rose-200 bg-rose-50 text-rose-800'],
    ['key' => 'live_rate', 'label' => 'Live Rate', 'value' => (string)$liveRate . '%', 'tone' => 'border-slate-300 bg-slate-50 text-slate-800'],
];

$clientSummary = table_exists('clients')
    ? db()->query('SELECT c.name, COUNT(b.id) AS business_count,
                          AVG(CASE WHEN b.website <> "" AND b.email <> "" AND b.description <> "" THEN 100 ELSE 55 END) AS readiness_score
                   FROM clients c
                   LEFT JOIN businesses b ON b.client_id = c.id
                   GROUP BY c.id
                   ORDER BY business_count DESC, c.name ASC')->fetchAll()
    : [];

$taskSummary = db()->query("SELECT status, COUNT(*) AS c FROM listing_tasks GROUP BY status ORDER BY c DESC")->fetchAll();
$directoryPerformance = db()->query("SELECT d.name,
                                            COUNT(lt.id) AS total_tasks,
                                            SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_count,
                          SUM(CASE WHEN lt.status = 'pending_submission' THEN 1 ELSE 0 END) AS pending_count,
                          SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'nap_error' THEN 1 ELSE 0 END) AS nap_error_count
                                     FROM directories d
                                     LEFT JOIN listing_tasks lt ON lt.directory_id = d.id
                                     GROUP BY d.id
                      ORDER BY live_count DESC, pending_count DESC, total_tasks DESC
                                     LIMIT 15")->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="citeflow-report.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Type', 'Name', 'Value 1', 'Value 2', 'Value 3', 'Value 4']);
    foreach ($reportMetrics as $metric) {
        fputcsv($out, ['KPI', (string)$metric['label'], (string)$metric['value'], '', '', '']);
    }
    foreach ($taskSummary as $row) {
        fputcsv($out, ['Task Status', (string)$row['status'], (string)$row['c'], '', '', '']);
    }
    foreach ($directoryPerformance as $row) {
        fputcsv($out, ['Directory', (string)$row['name'], (string)$row['total_tasks'], (string)$row['live_count'], (string)$row['pending_count'], (string)$row['nap_error_count']]);
    }
    fclose($out);
    exit;
}

render_header('Reports');
?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Reports</h1>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Cross-client reporting, workflow visibility, and directory performance snapshots.</p>
        </div>
        <a class="rounded-lg border border-slate-300 bg-white/80 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100 dark:hover:bg-slate-700" href="<?php echo e(app_config()['base_url']); ?>/reports.php?export=csv">Export CSV</a>
    </div>
</section>

<section class="mb-6 grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <?php foreach ($reportMetrics as $metric): ?>
        <button
            type="button"
            class="metric-box rounded-xl border px-4 py-3 text-left shadow-sm transition hover:scale-[1.01] hover:shadow <?php echo e((string)$metric['tone']); ?>"
            data-metric-key="<?php echo e((string)$metric['key']); ?>"
            data-metric-label="<?php echo e((string)$metric['label']); ?>"
        >
            <p class="text-[10px] font-bold uppercase tracking-widest opacity-80"><?php echo e((string)$metric['label']); ?></p>
            <p class="mt-1 text-2xl font-extrabold leading-none"><?php echo e((string)$metric['value']); ?></p>
            <p class="mt-2 text-[11px] font-semibold opacity-75">Click to view details</p>
        </button>
    <?php endforeach; ?>
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

<section class="grid gap-6 lg:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Task Status Summary</h2>
        <div class="mt-4 space-y-3">
            <?php foreach ($taskSummary as $row): ?>
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/70">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo e(status_label((string)$row['status'])); ?></span>
                    <span class="text-sm font-bold text-slate-900 dark:text-slate-100"><?php echo e((string)$row['c']); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$taskSummary): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-300">No citation status data yet.</div>
            <?php endif; ?>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Client Coverage</h2>
        <div class="mt-4 space-y-3">
            <?php foreach ($clientSummary as $row): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/70">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?php echo e((string)$row['name']); ?></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400"><?php echo e((string)$row['business_count']); ?> businesses</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Approx. readiness: <?php echo e((string)(int)round((float)$row['readiness_score'])); ?>%</p>
                </div>
            <?php endforeach; ?>
            <?php if (!$clientSummary): ?>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-300">No client data yet.</div>
            <?php endif; ?>
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2 dark:border-slate-700 dark:bg-slate-900">
        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Directory Performance</h2>
        <div class="cf-table-wrap mt-4 overflow-x-auto">
            <table class="cf-table min-w-[760px] divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Directory</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tasks</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Live</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pending Submission</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">NAP Errors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                    <?php foreach ($directoryPerformance as $row): ?>
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900 dark:text-slate-100"><?php echo e((string)$row['name']); ?></td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e((string)$row['total_tasks']); ?></td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e((string)$row['live_count']); ?></td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e((string)$row['pending_count']); ?></td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e((string)$row['nap_error_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$directoryPerformance): ?>
                        <tr>
                            <td class="px-4 py-4 text-sm text-slate-500 dark:text-slate-400" colspan="5">No directory performance data yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<script>
const metricDetails = <?php echo json_encode($metricDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const metricModal = document.getElementById('metricDetailModal');
const metricTitle = document.getElementById('metricDetailTitle');
const metricHead = document.getElementById('metricDetailHead');
const metricBody = document.getElementById('metricDetailBody');
const metricEmpty = document.getElementById('metricDetailEmpty');
const metricTableWrap = document.getElementById('metricDetailTableWrap');
const closeMetricBtn = document.getElementById('closeMetricDetailModal');

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
                const value = String(cell ?? '-');
                const header = String(detail.columns[index] ?? '').toLowerCase();
                const isUrlColumn = header.includes('url');
                const isHttpUrl = /^https?:\/\//i.test(value);

                if (isUrlColumn && isHttpUrl) {
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

closeMetricBtn.addEventListener('click', closeMetricModal);
metricModal.addEventListener('click', (event) => {
    if (event.target === metricModal) {
        closeMetricModal();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !metricModal.classList.contains('hidden')) {
        closeMetricModal();
    }
});
</script>
<?php render_footer(); ?>