<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_client_login();
$client = current_client();
if ($client === null) {
    redirect('/client_portal.php');
}

$clientId = (int)$client['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'add_citation_comment') {
    $listingTaskId = (int)($_POST['listing_task_id'] ?? 0);
    $selectedBusinessIdFromPost = (int)($_POST['business_id'] ?? 0);
    $commentText = trim((string)($_POST['comment_text'] ?? ''));

    if ($listingTaskId <= 0 || $commentText === '') {
        set_flash('err', 'Please enter a comment before submitting.');
    } elseif (mb_strlen($commentText) > 2000) {
        set_flash('err', 'Comment is too long. Please keep it under 2000 characters.');
    } else {
        $allowedTaskStmt = db()->prepare('SELECT lt.id
                                         FROM listing_tasks lt
                                         INNER JOIN businesses b ON b.id = lt.business_id
                                         WHERE lt.id = ? AND b.client_id = ?
                                         LIMIT 1');
        $allowedTaskStmt->execute([$listingTaskId, $clientId]);
        $allowedTask = $allowedTaskStmt->fetch();

        if (!$allowedTask) {
            set_flash('err', 'Citation was not found for your account.');
        } else {
            db()->prepare('INSERT INTO client_citation_comments (listing_task_id, client_id, comment_text) VALUES (?, ?, ?)')
                ->execute([$listingTaskId, $clientId, $commentText]);
            set_flash('ok', 'Comment added successfully.');
        }
    }

    $redirectUrl = app_config()['base_url'] . '/client_dashboard.php';
    if ($selectedBusinessIdFromPost > 0) {
        $redirectUrl .= '?business_id=' . $selectedBusinessIdFromPost;
    }
    redirect($redirectUrl);
}

$businessCountStmt = db()->prepare('SELECT COUNT(*) AS c FROM businesses WHERE client_id = ?');
$businessCountStmt->execute([$clientId]);
$totalBusinesses = (int)($businessCountStmt->fetch()['c'] ?? 0);

$totalsStmt = db()->prepare("SELECT
    COUNT(*) AS total_citations,
    SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_citations,
    SUM(CASE WHEN lt.status = 'pending_submission' THEN 1 ELSE 0 END) AS pending_submission,
    SUM(CASE WHEN lt.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
    SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'nap_error' THEN 1 ELSE 0 END) AS nap_errors
FROM listing_tasks lt
INNER JOIN businesses b ON b.id = lt.business_id
WHERE b.client_id = ?");
$totalsStmt->execute([$clientId]);
$totals = $totalsStmt->fetch() ?: [];

$totalCitations = (int)($totals['total_citations'] ?? 0);
$liveCitations = (int)($totals['live_citations'] ?? 0);
$pendingSubmission = (int)($totals['pending_submission'] ?? 0);
$inProgress = (int)($totals['in_progress'] ?? 0);
$napErrors = (int)($totals['nap_errors'] ?? 0);
$liveRate = $totalCitations > 0 ? round(($liveCitations / $totalCitations) * 100, 1) : 0.0;
$portfolioNeedsAttention = $pendingSubmission + $inProgress + $napErrors;
$portfolioNeedsAttentionRate = $totalCitations > 0 ? round(($portfolioNeedsAttention / $totalCitations) * 100, 1) : 0.0;

$businessesStmt = db()->prepare('SELECT id, name, logo_path, city, state, status FROM businesses WHERE client_id = ? ORDER BY name ASC LIMIT 100');
$businessesStmt->execute([$clientId]);
$businesses = $businessesStmt->fetchAll();

$businessMetricsStmt = db()->prepare("SELECT b.id,
                                            COUNT(lt.id) AS total_citations,
                                            SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_citations
                                     FROM businesses b
                                     LEFT JOIN listing_tasks lt ON lt.business_id = b.id
                                     WHERE b.client_id = ?
                                     GROUP BY b.id");
$businessMetricsStmt->execute([$clientId]);
$businessMetricsById = [];
foreach ($businessMetricsStmt->fetchAll() as $metricRow) {
    $businessMetricsById[(int)$metricRow['id']] = [
        'total_citations' => (int)($metricRow['total_citations'] ?? 0),
        'live_citations' => (int)($metricRow['live_citations'] ?? 0),
    ];
}

$selectedBusinessId = (int)($_GET['business_id'] ?? 0);
$selectedBusiness = null;
foreach ($businesses as $businessItem) {
    if ((int)($businessItem['id'] ?? 0) === $selectedBusinessId) {
        $selectedBusiness = $businessItem;
        break;
    }
}
if ($selectedBusiness === null) {
    $selectedBusinessId = 0;
}

$selectedTotals = [
    'total_citations' => 0,
    'live_citations' => 0,
    'pending_submission' => 0,
    'in_progress' => 0,
    'nap_errors' => 0,
];
$selectedInsights = [
    'directories_covered' => 0,
    'updated_last_30_days' => 0,
    'needs_attention' => 0,
];
$selectedStatusCounts = [
    'not_started' => 0,
    'in_progress' => 0,
    'pending_submission' => 0,
    'live' => 0,
    'needs_edit' => 0,
    'rejected' => 0,
];
$selectedRecentCitations = [];
$selectedLiveRate = 0.0;
$selectedNeedsAttentionRate = 0.0;
$commentsByTaskId = [];

if ($selectedBusinessId > 0) {
    $selectedTotalsStmt = db()->prepare("SELECT
        COUNT(*) AS total_citations,
        SUM(CASE WHEN lt.status = 'live' THEN 1 ELSE 0 END) AS live_citations,
        SUM(CASE WHEN lt.status = 'pending_submission' THEN 1 ELSE 0 END) AS pending_submission,
        SUM(CASE WHEN lt.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN lt.status = 'live' AND lt.nap_status = 'nap_error' THEN 1 ELSE 0 END) AS nap_errors
    FROM listing_tasks lt
    WHERE lt.business_id = ?");
    $selectedTotalsStmt->execute([$selectedBusinessId]);
    $selectedTotals = $selectedTotalsStmt->fetch() ?: $selectedTotals;

    $selectedRecentStmt = db()->prepare("SELECT lt.id, lt.status, lt.nap_status, lt.submitted_url, lt.updated_at,
                                                d.name AS directory_name,
                                                d.logo_path AS directory_logo_path,
                                                latest_comment.latest_comment_text,
                                                latest_comment.latest_comment_at,
                                  latest_comment.latest_comment_status,
                                  latest_comment.latest_comment_resolved_at,
                                                COALESCE(comment_totals.comments_count, 0) AS comments_count
                                         FROM listing_tasks lt
                                         INNER JOIN directories d ON d.id = lt.directory_id
                                         LEFT JOIN (
                                            SELECT cc1.listing_task_id,
                                                   cc1.comment_text AS latest_comment_text,
                                  cc1.created_at AS latest_comment_at,
                                  cc1.resolution_status AS latest_comment_status,
                                  cc1.resolved_at AS latest_comment_resolved_at
                                            FROM client_citation_comments cc1
                                            INNER JOIN (
                                                SELECT listing_task_id, MAX(id) AS max_id
                                                FROM client_citation_comments
                                                WHERE client_id = ?
                                                GROUP BY listing_task_id
                                            ) latest_ids ON latest_ids.max_id = cc1.id
                                         ) latest_comment ON latest_comment.listing_task_id = lt.id
                                         LEFT JOIN (
                                            SELECT listing_task_id, COUNT(*) AS comments_count
                                            FROM client_citation_comments
                                            WHERE client_id = ?
                                            GROUP BY listing_task_id
                                         ) comment_totals ON comment_totals.listing_task_id = lt.id
                                         WHERE lt.business_id = ?
                                         ORDER BY lt.updated_at DESC
                                         LIMIT 25");
    $selectedRecentStmt->execute([$clientId, $clientId, $selectedBusinessId]);
    $selectedRecentCitations = $selectedRecentStmt->fetchAll();

    $selectedInsightsStmt = db()->prepare("SELECT
        COUNT(DISTINCT lt.directory_id) AS directories_covered,
        SUM(CASE WHEN lt.updated_at >= (NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS updated_last_30_days,
        SUM(CASE
            WHEN lt.status IN ('pending_submission', 'needs_edit', 'rejected')
                OR (lt.status = 'live' AND lt.nap_status = 'nap_error')
            THEN 1 ELSE 0 END) AS needs_attention
    FROM listing_tasks lt
    WHERE lt.business_id = ?");
    $selectedInsightsStmt->execute([$selectedBusinessId]);
    $selectedInsights = $selectedInsightsStmt->fetch() ?: $selectedInsights;

    $selectedStatusStmt = db()->prepare('SELECT status, COUNT(*) AS c FROM listing_tasks WHERE business_id = ? GROUP BY status');
    $selectedStatusStmt->execute([$selectedBusinessId]);
    foreach ($selectedStatusStmt->fetchAll() as $statusRow) {
        $statusKey = (string)($statusRow['status'] ?? '');
        if (array_key_exists($statusKey, $selectedStatusCounts)) {
            $selectedStatusCounts[$statusKey] = (int)($statusRow['c'] ?? 0);
        }
    }

    $selectedTotalCount = (int)($selectedTotals['total_citations'] ?? 0);
    $selectedLiveCount = (int)($selectedTotals['live_citations'] ?? 0);
    $selectedLiveRate = $selectedTotalCount > 0 ? round(($selectedLiveCount / $selectedTotalCount) * 100, 1) : 0.0;
    $selectedNeedsAttentionRate = $selectedTotalCount > 0
        ? round((((int)($selectedInsights['needs_attention'] ?? 0)) / $selectedTotalCount) * 100, 1)
        : 0.0;

    // Pre-load all comments for citations in this business
    $allCommentsStmt = db()->prepare(
        'SELECT ccc.listing_task_id, ccc.id, ccc.comment_text, ccc.created_at, ccc.resolution_status, ccc.resolved_at
         FROM client_citation_comments ccc
         INNER JOIN listing_tasks lt ON lt.id = ccc.listing_task_id
         WHERE lt.business_id = ? AND ccc.client_id = ?
         ORDER BY ccc.created_at ASC'
    );
    $allCommentsStmt->execute([$selectedBusinessId, $clientId]);
    $commentsByTaskId = [];
    foreach ($allCommentsStmt->fetchAll() as $commentRow) {
        $taskId = (int)$commentRow['listing_task_id'];
        $commentsByTaskId[$taskId][] = [
            'id'                => (int)$commentRow['id'],
            'comment_text'      => (string)$commentRow['comment_text'],
            'created_at'        => (string)$commentRow['created_at'],
            'resolution_status' => (string)($commentRow['resolution_status'] ?? 'pending'),
            'resolved_at'       => (string)($commentRow['resolved_at'] ?? ''),
        ];
    }
}

$statusBadge = static function (string $status): string {
    return match ($status) {
        'live' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
        'pending_submission' => 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-200',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-200',
        'not_started' => 'bg-slate-100 text-slate-700 dark:bg-slate-700/70 dark:text-slate-200',
        'needs_edit' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-700/70 dark:text-slate-200',
    };
};

$statusBarClass = static function (string $status): string {
    return match ($status) {
        'live' => 'bg-emerald-500',
        'pending_submission' => 'bg-orange-500',
        'in_progress' => 'bg-blue-500',
        'needs_edit' => 'bg-amber-500',
        'rejected' => 'bg-rose-500',
        default => 'bg-slate-400',
    };
};

render_header('Client Portal');
?>
<section class="mb-6 overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white shadow-sm">
    <div class="grid gap-6 p-6 lg:grid-cols-[1.4fr_0.6fr] lg:items-center">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-200">Client Portal</p>
            <h1 class="mt-2 text-3xl font-extrabold"><?php echo e((string)$client['name']); ?></h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-200">Track citation progress, portfolio health, and business-level activity in one clean read-only workspace.</p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-slate-100">Businesses: <?php echo e((string)$totalBusinesses); ?></span>
                <span class="inline-flex rounded-full border border-cyan-300/30 bg-cyan-400/15 px-3 py-1 text-xs font-semibold text-cyan-100">Portfolio Live Rate: <?php echo e((string)$liveRate); ?>%</span>
                <span class="inline-flex rounded-full border border-rose-300/30 bg-rose-400/15 px-3 py-1 text-xs font-semibold text-rose-100">Needs Attention: <?php echo e((string)$portfolioNeedsAttention); ?></span>
            </div>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-300">Portfolio Health</p>
            <div class="mt-2 flex items-end justify-between gap-2">
                <p class="text-3xl font-extrabold text-emerald-300"><?php echo e((string)$liveRate); ?>%</p>
                <p class="text-xs font-semibold text-slate-300">Live Citation Rate</p>
            </div>
            <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-white/15">
                <div class="h-full rounded-full bg-emerald-400" style="width: <?php echo e((string)$liveRate); ?>%;"></div>
            </div>
            <p class="mt-2 text-xs text-slate-300">Attention Load: <?php echo e((string)$portfolioNeedsAttentionRate); ?>% of portfolio citations.</p>
            <a class="mt-4 inline-flex w-full items-center justify-center rounded-lg border border-white/20 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-white/10" href="<?php echo e(app_config()['base_url']); ?>/client_logout.php">Logout</a>
        </div>
    </div>
</section>

<section class="mb-6 grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
    <article class="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-4 shadow-sm dark:border-cyan-500/30 dark:bg-cyan-500/10">
        <p class="text-xs font-bold uppercase tracking-wide text-cyan-700 dark:text-cyan-200">Portfolio Citations</p>
        <p class="mt-2 text-3xl font-extrabold text-cyan-900 dark:text-cyan-100"><?php echo e((string)$totalCitations); ?></p>
        <p class="mt-1 text-xs text-cyan-700 dark:text-cyan-200/80">All tracked listing tasks across businesses</p>
    </article>
    <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">Portfolio Live</p>
        <p class="mt-2 text-3xl font-extrabold text-emerald-900 dark:text-emerald-100"><?php echo e((string)$liveCitations); ?></p>
        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-200/80">Listings currently live and visible</p>
    </article>
    <article class="rounded-2xl border border-orange-200 bg-orange-50 px-4 py-4 shadow-sm dark:border-orange-500/30 dark:bg-orange-500/10">
        <p class="text-xs font-bold uppercase tracking-wide text-orange-700 dark:text-orange-200">Pending Submission</p>
        <p class="mt-2 text-3xl font-extrabold text-orange-900 dark:text-orange-100"><?php echo e((string)$pendingSubmission); ?></p>
        <p class="mt-1 text-xs text-orange-700 dark:text-orange-200/80">Awaiting directory submission</p>
    </article>
    <article class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 shadow-sm dark:border-blue-500/30 dark:bg-blue-500/10">
        <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-200">In Progress</p>
        <p class="mt-2 text-3xl font-extrabold text-blue-900 dark:text-blue-100"><?php echo e((string)$inProgress); ?></p>
        <p class="mt-1 text-xs text-blue-700 dark:text-blue-200/80">Actively being worked by the team</p>
    </article>
    <article class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 shadow-sm dark:border-rose-500/30 dark:bg-rose-500/10">
        <p class="text-xs font-bold uppercase tracking-wide text-rose-700 dark:text-rose-200">NAP Errors (Live)</p>
        <p class="mt-2 text-3xl font-extrabold text-rose-900 dark:text-rose-100"><?php echo e((string)$napErrors); ?></p>
        <p class="mt-1 text-xs text-rose-700 dark:text-rose-200/80">Live citations with NAP mismatch</p>
    </article>
</section>

<section class="grid gap-6 lg:grid-cols-12">
    <aside class="lg:col-span-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:sticky lg:top-24">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Business Navigator</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-200"><?php echo e((string)count($businesses)); ?> items</span>
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Select a business to load focused metrics and recent updates.</p>
            <div class="mt-3">
                <input id="business_search" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:ring-sky-500/30" type="search" placeholder="Search business name...">
            </div>
            <div id="business_list" class="mt-3 max-h-[560px] space-y-2 overflow-y-auto pr-1">
                <?php foreach ($businesses as $business): ?>
                    <?php
                        $isSelectedBusiness = (int)$selectedBusinessId === (int)$business['id'];
                        $itemMetrics = $businessMetricsById[(int)$business['id']] ?? ['total_citations' => 0, 'live_citations' => 0];
                        $itemTotal = (int)($itemMetrics['total_citations'] ?? 0);
                        $itemLive = (int)($itemMetrics['live_citations'] ?? 0);
                        $itemLiveRate = $itemTotal > 0 ? round(($itemLive / $itemTotal) * 100, 1) : 0.0;
                    ?>
                    <a
                        href="<?php echo e(app_config()['base_url']); ?>/client_dashboard.php?business_id=<?php echo e((string)$business['id']); ?>"
                        data-business-name="<?php echo e(strtolower((string)$business['name'])); ?>"
                        class="business-item block rounded-xl border px-4 py-3 transition hover:-translate-y-0.5 hover:shadow-sm <?php echo $isSelectedBusiness ? 'border-sky-300 bg-sky-50 ring-1 ring-sky-200 dark:border-sky-500/60 dark:bg-sky-500/10 dark:ring-sky-500/30' : 'border-slate-200 bg-slate-50 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800/70 dark:hover:border-slate-500'; ?>"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <?php if (trim((string)($business['logo_path'] ?? '')) !== ''): ?>
                                    <img class="h-10 w-10 shrink-0 rounded-lg border border-slate-200 object-cover dark:border-slate-700" src="<?php echo e(public_asset_url((string)$business['logo_path'])); ?>" alt="<?php echo e((string)$business['name']); ?> logo">
                                <?php else: ?>
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-xs font-bold uppercase text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                                        <?php echo e(substr((string)$business['name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?php echo e((string)$business['name']); ?></p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-300"><?php echo e((string)$business['city']); ?>, <?php echo e((string)$business['state']); ?></p>
                                </div>
                            </div>
                            <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200"><?php echo e(ucfirst((string)$business['status'])); ?></span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                            <span class="rounded-full bg-cyan-100 px-2 py-0.5 font-semibold text-cyan-800 dark:bg-cyan-500/15 dark:text-cyan-200">Total: <?php echo e((string)$itemTotal); ?></span>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Live: <?php echo e((string)$itemLive); ?></span>
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">Rate: <?php echo e((string)$itemLiveRate); ?>%</span>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$businesses): ?>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-300">No businesses linked to this client yet.</div>
                <?php endif; ?>
            </div>
        </article>
    </aside>

    <div class="lg:col-span-8 space-y-6">
        <?php if ($selectedBusinessId > 0 && $selectedBusiness !== null): ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-700">
                    <div class="flex items-start gap-3">
                        <?php if (trim((string)($selectedBusiness['logo_path'] ?? '')) !== ''): ?>
                            <img class="h-12 w-12 shrink-0 rounded-xl border border-slate-200 object-cover dark:border-slate-700" src="<?php echo e(public_asset_url((string)$selectedBusiness['logo_path'])); ?>" alt="<?php echo e((string)$selectedBusiness['name']); ?> logo">
                        <?php else: ?>
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-200 text-sm font-bold uppercase text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                                <?php echo e(substr((string)$selectedBusiness['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100"><?php echo e((string)$selectedBusiness['name']); ?></h2>
                            <p class="text-sm text-slate-500 dark:text-slate-300"><?php echo e((string)$selectedBusiness['city']); ?>, <?php echo e((string)$selectedBusiness['state']); ?> · Status: <?php echo e(ucfirst((string)$selectedBusiness['status'])); ?></p>
                            <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Business Performance View</p>
                        </div>
                    </div>
                    <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" href="<?php echo e(app_config()['base_url']); ?>/client_dashboard.php">Clear Selection</a>
                </div>

                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));">
                    <article class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 shadow-sm dark:border-cyan-500/30 dark:bg-cyan-500/10">
                        <p class="text-xs font-bold uppercase tracking-wide text-cyan-700 dark:text-cyan-200">Total Citations</p>
                        <p class="mt-1 text-2xl font-extrabold text-cyan-900 dark:text-cyan-100"><?php echo e((string)(int)($selectedTotals['total_citations'] ?? 0)); ?></p>
                    </article>
                    <article class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">Live Citations</p>
                        <p class="mt-1 text-2xl font-extrabold text-emerald-900 dark:text-emerald-100"><?php echo e((string)(int)($selectedTotals['live_citations'] ?? 0)); ?></p>
                    </article>
                    <article class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 shadow-sm dark:border-orange-500/30 dark:bg-orange-500/10">
                        <p class="text-xs font-bold uppercase tracking-wide text-orange-700 dark:text-orange-200">Pending Submission</p>
                        <p class="mt-1 text-2xl font-extrabold text-orange-900 dark:text-orange-100"><?php echo e((string)(int)($selectedTotals['pending_submission'] ?? 0)); ?></p>
                    </article>
                    <article class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 shadow-sm dark:border-blue-500/30 dark:bg-blue-500/10">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-200">In Progress</p>
                        <p class="mt-1 text-2xl font-extrabold text-blue-900 dark:text-blue-100"><?php echo e((string)(int)($selectedTotals['in_progress'] ?? 0)); ?></p>
                    </article>
                    <article class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm dark:border-rose-500/30 dark:bg-rose-500/10">
                        <p class="text-xs font-bold uppercase tracking-wide text-rose-700 dark:text-rose-200">NAP Errors (Live)</p>
                        <p class="mt-1 text-2xl font-extrabold text-rose-900 dark:text-rose-100"><?php echo e((string)(int)($selectedTotals['nap_errors'] ?? 0)); ?></p>
                    </article>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Live Rate</p>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo e((string)$selectedLiveRate); ?>%</p>
                        </div>
                        <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full bg-emerald-500" style="width: <?php echo e((string)$selectedLiveRate); ?>%;"></div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/80">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Needs Attention Rate</p>
                            <p class="text-sm font-bold <?php echo $selectedNeedsAttentionRate > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'; ?>"><?php echo e((string)$selectedNeedsAttentionRate); ?>%</p>
                        </div>
                        <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full <?php echo $selectedNeedsAttentionRate > 0 ? 'bg-rose-500' : 'bg-emerald-500'; ?>" style="width: <?php echo e((string)$selectedNeedsAttentionRate); ?>%;"></div>
                        </div>
                    </div>
                </div>
            </article>

            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Directories Covered</p>
                    <p class="mt-1 text-3xl font-extrabold text-slate-900 dark:text-slate-100"><?php echo e((string)(int)($selectedInsights['directories_covered'] ?? 0)); ?></p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Unique directories with active citation records.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Updated (30 Days)</p>
                    <p class="mt-1 text-3xl font-extrabold text-slate-900 dark:text-slate-100"><?php echo e((string)(int)($selectedInsights['updated_last_30_days'] ?? 0)); ?></p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Recent work activity within the last month.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Needs Attention</p>
                    <p class="mt-1 text-3xl font-extrabold <?php echo (int)($selectedInsights['needs_attention'] ?? 0) > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'; ?>"><?php echo e((string)(int)($selectedInsights['needs_attention'] ?? 0)); ?></p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Pending submission, edits, rejects, and live NAP errors.</p>
                </article>
            </section>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Status Distribution</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Share of citations by workflow status for this business.</p>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 dark:border-slate-700 dark:bg-slate-800/80">
                    <?php foreach ($selectedStatusCounts as $statusKey => $statusCount): ?>
                        <?php $statusRatio = (int)($selectedTotals['total_citations'] ?? 0) > 0 ? round(((int)$statusCount / (int)$selectedTotals['total_citations']) * 100, 1) : 0; ?>
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-700 dark:text-slate-200"><?php echo e(status_label((string)$statusKey)); ?></span>
                                <span class="text-slate-500 dark:text-slate-400"><?php echo e((string)$statusCount); ?> · <?php echo e((string)$statusRatio); ?>%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div class="h-full rounded-full <?php echo e($statusBarClass((string)$statusKey)); ?>" style="width: <?php echo e((string)$statusRatio); ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Recent Citation Updates</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Most recently updated citation records for this business.</p>
                <div class="cf-table-wrap mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="cf-table min-w-[820px] divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/90">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">Directory</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300 hidden sm:table-cell">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300 hidden md:table-cell">NAP</th>
                                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">View</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300 hidden lg:table-cell">Updated</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">Comments</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                            <?php foreach ($selectedRecentCitations as $citation): ?>
                                <?php $directoryLogoPath = trim((string)($citation['directory_logo_path'] ?? '')); ?>
                                <tr class="odd:bg-white even:bg-slate-50/60 dark:odd:bg-slate-900 dark:even:bg-slate-800/70">
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                        <div class="flex items-center gap-2.5 min-w-[200px]">
                                            <?php if ($directoryLogoPath !== ''): ?>
                                                <img class="h-8 w-8 shrink-0 rounded-md border border-slate-200 object-cover dark:border-slate-700" src="<?php echo e(public_asset_url($directoryLogoPath)); ?>" alt="<?php echo e((string)$citation['directory_name']); ?> logo">
                                            <?php else: ?>
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-100 text-[11px] font-bold uppercase text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                    <?php echo e(substr((string)$citation['directory_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="font-medium text-slate-800 dark:text-slate-100"><?php echo e((string)$citation['directory_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm hidden sm:table-cell">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($statusBadge((string)$citation['status'])); ?>"><?php echo e(status_label((string)$citation['status'])); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300 hidden md:table-cell">
                                        <?php
                                            $citationStatus = (string)($citation['status'] ?? '');
                                            $citationNapStatus = (string)($citation['nap_status'] ?? '');
                                            $normalizedNapStatus = strtolower(trim($citationNapStatus));
                                            $isCorrectNap = in_array($normalizedNapStatus, ['correct', 'correct_nap', 'correct nap'], true);
                                        ?>
                                        <?php if ($isCorrectNap && $citationStatus !== 'live'): ?>
                                            —
                                        <?php else: ?>
                                            <?php echo e($citationNapStatus); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($citationStatus === 'live' && trim((string)$citation['submitted_url']) !== ''): ?>
                                            <a
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200 dark:hover:bg-emerald-500/20 dark:hover:text-emerald-100"
                                                href="<?php echo e((string)$citation['submitted_url']); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label="View live citation"
                                                title="View live citation"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2.06 12C3.14 7.99 6.77 5 11 5c4.23 0 7.86 2.99 8.94 7-1.08 4.01-4.71 7-8.94 7-4.23 0-7.86-2.99-8.94-7z"/>
                                                    <circle cx="11" cy="12" r="3"/>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap hidden lg:table-cell dark:text-slate-400"><?php echo e((string)$citation['updated_at']); ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg border border-brand-300 bg-brand-50 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition open-comment-modal dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-200 dark:hover:bg-brand-500/20"
                                                data-citation-id="<?php echo e((string)(int)$citation['id']); ?>"
                                                data-directory-name="<?php echo e((string)$citation['directory_name']); ?>"
                                                title="View and add comments"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3.196 12.87c-.263.26-.36.64-.27 1.01.112.53.567.9 1.07.9.05 0 .1-.002.15-.01l.113-.013A4.972 4.972 0 0 0 10 15c4.418 0 8-1.79 8-4s-3.582-4-8-4-8 1.79-8 4c0 .364.074.718.208 1.05a2.001 2.001 0 0 1 .596 1.82Z" /></svg>
                                                <span class="inline-flex rounded-full bg-brand-200 px-1.5 py-0.5 text-[10px] font-bold text-brand-900 dark:bg-brand-400/20 dark:text-brand-100"><?php echo e((string)(int)($citation['comments_count'] ?? 0)); ?></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$selectedRecentCitations): ?>
                                <tr>
                                    <td class="px-4 py-4 text-sm text-slate-500 dark:text-slate-300" colspan="6">No citation updates yet for this business.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        <?php else: ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-7 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Business Detail Workspace</p>
                <h2 class="mt-2 text-xl font-extrabold text-slate-900 dark:text-slate-100">Select a business to view details</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-300">Use the Business Navigator on the left to review live rates, citation health insights, status distribution, and recent updates per business.</p>
            </article>
        <?php endif; ?>
    </div>
</section>

<script>
(() => {
    const searchInput = document.getElementById('business_search');
    if (!searchInput) {
        return;
    }

    const items = Array.from(document.querySelectorAll('.business-item'));
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        items.forEach((item) => {
            const name = item.getAttribute('data-business-name') || '';
            item.style.display = name.includes(query) ? '' : 'none';
        });
    });
})();

(() => {
    const trackingUrl = <?php echo json_encode(rtrim((string)app_config()['base_url'], '/') . '/client_tracking.php', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    if (!trackingUrl) {
        return;
    }

    let lastHeartbeatAt = 0;
    let lastInteractionAt = 0;

    const sendHeartbeat = (force = false) => {
        const now = Date.now();
        if (!force && now - lastHeartbeatAt < 10000) {
            return;
        }
        lastHeartbeatAt = now;

        const currentPath = window.location.pathname + window.location.search;
        const pageTitle = (document.title || 'Client Portal').replace(/\s+-\s+CiteFlow Manager$/, '');
        const payload = JSON.stringify({
            current_path: currentPath,
            page_title: pageTitle || 'Client Portal',
        });

        if (navigator.sendBeacon) {
            const blob = new Blob([payload], { type: 'application/json' });
            if (navigator.sendBeacon(trackingUrl, blob)) {
                return;
            }
        }

        fetch(trackingUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload,
        }).catch(() => {
            const pingUrl = `${trackingUrl}?ping=1&current_path=${encodeURIComponent(currentPath)}&page_title=${encodeURIComponent(pageTitle || 'Client Portal')}`;
            fetch(pingUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
            }).catch(() => {});
        });
    };

    sendHeartbeat(true);

    window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            sendHeartbeat(false);
        }
    }, 15000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            sendHeartbeat(true);
        }
    });

    ['click', 'keydown', 'scroll', 'pointerdown'].forEach((eventName) => {
        window.addEventListener(eventName, () => {
            const now = Date.now();
            if (now - lastInteractionAt < 8000) {
                return;
            }
            lastInteractionAt = now;
            sendHeartbeat(true);
        }, { passive: true });
    });

    window.addEventListener('beforeunload', () => {
        sendHeartbeat(true);
    });
})();

// Comment Modal Handler
(() => {
    const baseUrl = <?php echo json_encode(rtrim((string)app_config()['base_url'], '/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const selectedBusinessId = <?php echo json_encode($selectedBusinessId, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const allComments = <?php echo json_encode($commentsByTaskId ?? [], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    const modal = document.createElement('div');
    modal.id = 'clientCommentModal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 opacity-0 pointer-events-none transition-opacity duration-200';
    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-y-auto flex flex-col dark:bg-slate-900 dark:ring-1 dark:ring-slate-700">
            <div class="sticky top-0 bg-white border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-3 dark:bg-slate-900 dark:border-slate-700">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100"><span id="modalDirName"></span> - Comments & Updates</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Manage comments for this citation</p>
                </div>
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" id="closeCommentModal" aria-label="Close modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-5">
                <div id="existingComments" class="mb-6 space-y-3"></div>
                <form id="addCommentForm" method="post" action="${baseUrl}/client_dashboard.php" class="border-t border-slate-200 pt-5 dark:border-slate-700">
                    <input type="hidden" name="action" value="add_citation_comment">
                    <input type="hidden" name="listing_task_id" id="modalListingTaskId" value="">
                    <input type="hidden" name="business_id" value="${selectedBusinessId}">
                    <div class="mb-3">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Add Your Comment</label>
                        <textarea
                            name="comment_text"
                            maxlength="2000"
                            rows="3"
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-400"
                            placeholder="Share updates or questions about this citation..."
                        ></textarea>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">Maximum 2000 characters</p>
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition">Post Comment</button>
                </form>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const closeBtn = modal.querySelector('#closeCommentModal');
    const triggers = document.querySelectorAll('.open-comment-modal');
    const dirNameSpan = modal.querySelector('#modalDirName');
    const listingTaskIdInput = modal.querySelector('#modalListingTaskId');
    const existingCommentsDiv = modal.querySelector('#existingComments');

    const openModal = (citationId, directoryName) => {
        dirNameSpan.textContent = directoryName;
        listingTaskIdInput.value = String(citationId);

        const comments = allComments[citationId] || [];

        if (comments.length === 0) {
            existingCommentsDiv.innerHTML = '<p class="text-sm text-slate-500 dark:text-slate-300 py-2">No comments yet. Be the first to add one!</p>';
        } else {
            existingCommentsDiv.innerHTML = comments.map(c => {
                const isUpdated = (c.resolution_status || 'pending').toLowerCase() === 'updated';
                const bgClass = isUpdated
                    ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-900/25'
                    : 'border-amber-200 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/25';
                const textClass = isUpdated ? 'text-emerald-900 dark:text-emerald-100' : 'text-amber-900 dark:text-amber-100';
                const headingClass = isUpdated ? 'text-emerald-900 dark:text-emerald-200' : 'text-amber-900 dark:text-amber-200';
                const statusHtml = isUpdated
                    ? `<span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.75 7.812a1 1 0 0 1-1.42.006L3.29 10.329a1 1 0 1 1 1.42-1.408l3.54 3.57 7.04-7.095a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>
                        Updated
                    </span>`
                    : `<span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Pending</span>`;
                const escapedText = c.comment_text
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                return `
                    <div class="rounded-lg border ${bgClass} p-3">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <p class="text-xs font-semibold ${headingClass}">${c.created_at}</p>
                            ${statusHtml}
                        </div>
                        <p class="text-sm ${textClass} whitespace-pre-wrap break-words">${escapedText}</p>
                    </div>
                `;
            }).join('');
        }

        modal.classList.remove('opacity-0', 'pointer-events-none');
    };

    const closeModal = () => {
        modal.classList.add('opacity-0', 'pointer-events-none');
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const citationId = parseInt(trigger.getAttribute('data-citation-id'), 10);
            const dirName = trigger.getAttribute('data-directory-name') || 'Citation';
            openModal(citationId, dirName);
        });
    });



    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('opacity-0')) {
            closeModal();
        }
    });
})();
</script>
<?php render_footer(); ?>
