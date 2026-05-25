<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('businesses');

$pendingBulkDeleteRows = [];
$pendingSingleDeleteId = 0;

if (($_GET['export'] ?? '') === 'csv') {
    $exportRows = db()->query("SELECT b.name, b.phone, b.website, b.gbp_link, b.email, b.address_line1, b.city, b.state, b.postal_code, b.country,
                                      b.status, COALESCE(c.name, '') AS client_name, COALESCE(NULLIF(b.categories, ''), b.category) AS categories
                               FROM businesses b
                               LEFT JOIN clients c ON c.id = b.client_id
                               ORDER BY b.updated_at DESC")->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="businesses-export.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['name', 'phone', 'website', 'gbp_link', 'email', 'address_line1', 'city', 'state', 'postal_code', 'country', 'status', 'client_name', 'categories']);
    foreach ($exportRows as $exportRow) {
        fputcsv($out, array_values($exportRow));
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('bulk_action') === 'import_csv') {
    if (isset($_FILES['import_file']) && is_array($_FILES['import_file']) && (int)($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmp = (string)($_FILES['import_file']['tmp_name'] ?? '');
        $handle = fopen($tmp, 'rb');
        if ($handle !== false) {
            $header = fgetcsv($handle) ?: [];
            $headerMap = array_flip(array_map(static fn ($v): string => strtolower(trim((string)$v)), $header));
            $createdCount = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $name = trim((string)($row[$headerMap['name'] ?? -1] ?? ''));
                $phone = trim((string)($row[$headerMap['phone'] ?? -1] ?? ''));
                $address = trim((string)($row[$headerMap['address_line1'] ?? -1] ?? ''));
                $city = trim((string)($row[$headerMap['city'] ?? -1] ?? ''));
                $state = trim((string)($row[$headerMap['state'] ?? -1] ?? ''));
                $postal = trim((string)($row[$headerMap['postal_code'] ?? -1] ?? ''));
                if ($name === '' || $phone === '' || $address === '' || $city === '' || $state === '' || $postal === '') {
                    continue;
                }

                $clientName = trim((string)($row[$headerMap['client_name'] ?? -1] ?? ''));
                $clientId = null;
                if ($clientName !== '' && table_exists('clients')) {
                    $clientStmt = db()->prepare('SELECT id FROM clients WHERE name = ? LIMIT 1');
                    $clientStmt->execute([$clientName]);
                    $clientId = (int)($clientStmt->fetch()['id'] ?? 0) ?: null;
                }

                db()->prepare('INSERT INTO businesses (client_id, name, phone, website, gbp_link, email, address_line1, city, state, postal_code, country, status, category, categories, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $clientId,
                        $name,
                        $phone,
                        trim((string)($row[$headerMap['website'] ?? -1] ?? '')),
                        trim((string)($row[$headerMap['gbp_link'] ?? -1] ?? '')),
                        trim((string)($row[$headerMap['email'] ?? -1] ?? '')),
                        $address,
                        $city,
                        $state,
                        $postal,
                        trim((string)($row[$headerMap['country'] ?? -1] ?? 'USA')),
                        trim((string)($row[$headerMap['status'] ?? -1] ?? 'active')),
                        trim((string)($row[$headerMap['categories'] ?? -1] ?? '')),
                        trim((string)($row[$headerMap['categories'] ?? -1] ?? '')),
                        (int)(current_user()['id'] ?? 0)
                    ]);
                $createdCount++;
            }
            fclose($handle);
            set_flash('ok', 'Imported ' . $createdCount . ' businesses from CSV.');
        }
    }
    redirect('/businesses.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (post('bulk_action') === 'request_delete_selected' || isset($_POST['request_delete_selected'])) && is_admin()) {
    $selectedIds = array_map('intval', $_POST['selected_ids'] ?? []);
    $selectedIds = array_values(array_filter($selectedIds, static fn (int $id): bool => $id > 0));

    if (!$selectedIds) {
        set_flash('warning', 'Select at least one business to delete.');
        redirect('/businesses.php');
    }

    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $lookupStmt = db()->prepare('SELECT id, name FROM businesses WHERE id IN (' . $placeholders . ')');
    $lookupStmt->execute($selectedIds);
    $pendingBulkDeleteRows = $lookupStmt->fetchAll();

    if (!$pendingBulkDeleteRows) {
        set_flash('warning', 'No matching businesses were found for deletion.');
        redirect('/businesses.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('bulk_action') === 'delete_selected' && is_admin()) {
    $selectedIds = array_map('intval', $_POST['selected_ids'] ?? []);
    $selectedIds = array_values(array_filter($selectedIds, static fn (int $id): bool => $id > 0));

    if ($selectedIds) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $lookupStmt = db()->prepare('SELECT id, name FROM businesses WHERE id IN (' . $placeholders . ')');
        $lookupStmt->execute($selectedIds);
        $foundRows = $lookupStmt->fetchAll();

        if ($foundRows) {
            db()->prepare('DELETE FROM businesses WHERE id IN (' . $placeholders . ')')->execute($selectedIds);
            foreach ($foundRows as $businessRow) {
                $deletedId = (int)($businessRow['id'] ?? 0);
                if ($deletedId <= 0) {
                    continue;
                }
                log_activity('business', $deletedId, 'delete', [
                    'name' => (string)($businessRow['name'] ?? ''),
                    'bulk' => true,
                ]);
            }
            set_flash('ok', 'Deleted ' . count($foundRows) . ' business(es).');
        }
    }

    if (!$selectedIds) {
        set_flash('warning', 'Select at least one business to delete.');
    }
    redirect('/businesses.php');
}

if ((isset($_GET['confirm_delete']) || isset($_GET['delete'])) && is_admin()) {
    $pendingSingleDeleteId = (int)($_GET['confirm_delete'] ?? $_GET['delete']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('bulk_action') === 'delete_single' && is_admin()) {
    $id = (int)post('delete_id');
    $business = db()->prepare('SELECT name FROM businesses WHERE id = ?');
    $business->execute([$id]);
    $businessRow = $business->fetch();
    $stmt = db()->prepare('DELETE FROM businesses WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        log_activity('business', $id, 'delete', [
            'name' => (string)($businessRow['name'] ?? ''),
        ]);
    }
    set_flash('ok', 'Business deleted.');
    redirect('/businesses.php');
}

$rows = db()->query("SELECT b.id, b.name, b.logo_path, b.city, b.state, b.phone, b.status, b.client_id,
                            c.name AS client_name,
                            COALESCE(NULLIF(categories, ''), category) AS category_display,
                            b.updated_at, b.website, b.email, b.address_line1, b.postal_code, b.country, b.description, b.hours_json, b.contact_name, b.social_media
                     FROM businesses b
                     LEFT JOIN clients c ON c.id = b.client_id
                     ORDER BY b.updated_at DESC")->fetchAll();

$clientOptions = active_clients();

$statusCounts = ['all' => count($rows)];
foreach ($rows as $row) {
    $s = strtolower(trim((string)($row['status'] ?? 'active')));
    $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
}
$statusOrder  = ['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive'];
$statusColors = [
    'active'   => 'bg-emerald-100 text-emerald-700',
    'inactive' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    'pending'  => 'bg-amber-100 text-amber-700',
];

render_header('Businesses');
?>
<section class="rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <?php if ($pendingSingleDeleteId > 0): ?>
        <?php
            $pendingSingleStmt = db()->prepare('SELECT id, name FROM businesses WHERE id = ? LIMIT 1');
            $pendingSingleStmt->execute([$pendingSingleDeleteId]);
            $pendingSingleRow = $pendingSingleStmt->fetch();
        ?>
        <?php if ($pendingSingleRow): ?>
            <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/55 dark:bg-slate-950/75"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900 shadow-2xl dark:border-amber-700 dark:bg-slate-900 dark:text-amber-200">
                    <p class="text-sm font-bold">Delete confirmation required</p>
                    <p class="mt-1 text-sm">You are about to permanently delete business: <span class="font-semibold"><?php echo e((string)$pendingSingleRow['name']); ?></span>.</p>
                    <p class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">Warning: This action cannot be undone.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="post">
                            <input type="hidden" name="bulk_action" value="delete_single">
                            <input type="hidden" name="delete_id" value="<?php echo e((string)$pendingSingleRow['id']); ?>">
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yes, delete business</button>
                        </form>
                        <a href="<?php echo e(app_config()['base_url']); ?>/businesses.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-700 px-5 py-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Businesses</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manage your business profiles and open Location Manager for citations.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="rounded-lg border border-slate-300 bg-white/80 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100 dark:hover:bg-slate-700" href="<?php echo e(app_config()['base_url']); ?>/businesses.php?export=csv">Export CSV</a>
            <a class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" href="<?php echo e(app_config()['base_url']); ?>/business_form.php">Add Business</a>
        </div>
    </div>

    <div class="grid gap-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-5 py-3 lg:grid-cols-[1fr,auto]">
        <form method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="bulk_action" value="import_csv">
            <input class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" type="file" name="import_file" accept=".csv,text/csv" required>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" type="submit">Import CSV</button>
        </form>
        <div></div>
    </div>

    <!-- Filter + Search bar -->
    <div class="cf-mobile-stack flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-3">
        <div class="flex flex-wrap gap-1.5" id="biz_status_tabs">
            <button onclick="filterBusinesses('all')" data-tab="all"
                class="biz-tab rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm transition active-tab">
                All <span class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600"><?php echo count($rows); ?></span>
            </button>
            <?php foreach ($statusOrder as $sv => $sl): ?>
            <button onclick="filterBusinesses('<?php echo e($sv); ?>')" data-tab="<?php echo e($sv); ?>"
                class="biz-tab rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm transition">
                <?php echo e($sl); ?> <span class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600"><?php echo $statusCounts[$sv] ?? 0; ?></span>
            </button>
            <?php endforeach; ?>
            <?php foreach ($statusCounts as $sv => $cnt): ?>
                <?php if ($sv !== 'all' && !isset($statusOrder[$sv])): ?>
                <button onclick="filterBusinesses('<?php echo e($sv); ?>')" data-tab="<?php echo e($sv); ?>"
                    class="biz-tab rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm transition">
                    <?php echo e(ucfirst($sv)); ?> <span class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600"><?php echo $cnt; ?></span>
                </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <input id="biz_search" type="search" placeholder="Search by name or location…"
            class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 sm:w-56 md:w-64"
            oninput="filterBusinesses()">
        <select id="biz_client_filter" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm sm:w-auto" onchange="filterBusinesses()">
            <option value="">All Clients</option>
            <?php foreach ($clientOptions as $client): ?>
                <option value="<?php echo e(strtolower((string)$client['name'])); ?>"><?php echo e((string)$client['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="cf-table-wrap overflow-x-auto">
    <table class="cf-table min-w-[980px] divide-y divide-slate-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="biz-col-logo w-12 px-2 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Logo</th>
                <th class="biz-col-name w-[16%] px-2 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Name</th>
                <th class="hidden w-[12%] px-3 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 lg:table-cell">Client</th>
                <th class="w-[14%] px-3 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Location</th>
                <th class="w-[10%] px-3 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Completeness</th>
                <th class="w-[10%] px-3 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th>
                <th class="hidden w-[12%] px-3 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 xl:table-cell">Updated</th>
                <th class="w-[18%] px-3 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Actions</th>
            </tr>
        </thead>
        <tbody id="biz_tbody" class="divide-y divide-slate-100 bg-white">
            <?php foreach ($rows as $row): ?>
                <?php
                    $st      = strtolower(trim((string)($row['status'] ?? 'active')));
                    $stBadge = $statusColors[$st] ?? 'bg-sky-100 text-sky-700';
                    $stLabel = ucfirst($st);
                    $completeness = business_completeness($row);
                    $score = (int)$completeness['score'];
                ?>
                <tr class="hover:bg-slate-50"
                    data-status="<?php echo e($st); ?>"
                    data-client="<?php echo e(strtolower((string)($row['client_name'] ?? ''))); ?>"
                    data-name="<?php echo e(strtolower($row['name'] . ' ' . $row['city'] . ' ' . $row['state'])); ?>">
                    <td class="biz-col-logo px-2 py-3 align-middle text-sm text-slate-600">
                        <?php if (trim((string)($row['logo_path'] ?? '')) !== ''): ?>
                            <img class="biz-logo-mark biz-logo-img h-10 w-10 rounded-lg border border-slate-200 object-cover" src="<?php echo e(public_asset_url((string)$row['logo_path'])); ?>" alt="Business logo">
                        <?php else: ?>
                            <?php $businessInitial = strtoupper(substr(trim((string)$row['name']), 0, 1)) ?: '?'; ?>
                            <span class="biz-logo-mark inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-sm font-bold text-slate-600"><?php echo e($businessInitial); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="biz-col-name px-2 py-3 align-middle text-sm font-semibold text-slate-900 break-words"><?php echo e($row['name']); ?></td>
                    <td class="hidden px-3 py-3 align-middle text-sm text-slate-600 break-words lg:table-cell"><?php echo e((string)($row['client_name'] ?? '—')); ?></td>
                    <td class="px-3 py-3 align-middle text-sm text-slate-600 break-words"><?php echo e($row['city'] . ', ' . $row['state']); ?></td>
                    <td class="px-3 py-3 align-middle text-sm">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?php echo e(completeness_badge_class($score)); ?>"><?php echo e((string)$score); ?>%</span>
                    </td>
                    <td class="px-3 py-3 align-middle">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?php echo $stBadge; ?>"><?php echo e($stLabel); ?></span>
                    </td>
                    <td class="hidden px-3 py-3 align-middle text-sm text-slate-500 xl:table-cell break-words"><?php echo e((string)$row['updated_at']); ?></td>
                    <td class="px-3 py-3 align-middle text-right whitespace-nowrap">
                        <div class="flex flex-nowrap items-center justify-end gap-1.5">
                            <a class="whitespace-nowrap rounded-lg bg-brand-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-brand-700" href="<?php echo e(app_config()['base_url']); ?>/location_manager.php?business_id=<?php echo e((string)$row['id']); ?>" title="Open location manager" aria-label="Open location manager">Location Manager</a>
                            <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100" href="<?php echo e(app_config()['base_url']); ?>/business_form.php?id=<?php echo e((string)$row['id']); ?>" title="Edit business" aria-label="Edit business">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-8.7 8.7a2 2 0 0 1-.878.513l-2.73.78a.75.75 0 0 1-.927-.927l.78-2.73a2 2 0 0 1 .513-.878l8.7-8.7Z" /></svg>
                            </a>
                            <?php if (is_admin()): ?>
                                <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50" href="<?php echo e(app_config()['base_url']); ?>/businesses.php?confirm_delete=<?php echo e((string)$row['id']); ?>" title="Delete business" aria-label="Delete business">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 2.5a1 1 0 0 0-1 1V4h-2.5a.75.75 0 0 0 0 1.5h.538l.84 10.08A2.5 2.5 0 0 0 9.12 18h1.76a2.5 2.5 0 0 0 2.492-2.42l.84-10.08h.538a.75.75 0 0 0 0-1.5h-2.5v-.5a1 1 0 0 0-1-1h-2.5Zm2 1.5h-1V4h1v0Zm-1.69 3.25a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 1.5 0v-6Zm3.38 0a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 1.5 0v-6Z" clip-rule="evenodd" /></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr id="biz_no_results" class="hidden">
                <td class="px-3 py-6 text-sm text-slate-500" colspan="11">No businesses match your filter.</td>
            </tr>
            <?php if (!$rows): ?>
                <tr><td class="px-5 py-6 text-sm text-slate-500" colspan="11">No businesses yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<style>
.biz-tab.active-tab { background-color: #0284c7; color: #fff; border-color: #0284c7; }
.biz-tab.active-tab span { background-color: rgba(255,255,255,0.25); color: #fff; }
@media (max-width: 640px) {
    .cf-table { table-layout: auto !important; }
    .biz-col-logo { width: 3.1rem !important; min-width: 3.1rem !important; max-width: 3.1rem !important; }
    .biz-col-name { width: auto !important; min-width: 10rem !important; }
    .biz-col-logo { padding-left: 0.35rem !important; padding-right: 0.4rem !important; }
    .biz-col-name { padding-left: 0.35rem !important; padding-right: 0.5rem !important; }
    .biz-logo-mark { width: 2.25rem !important; height: 2.25rem !important; }
    .biz-logo-img { object-fit: contain !important; background: #fff; padding: 0.1rem; }
}
</style>
<script>
let _bizStatus = 'all';
function filterBusinesses(status) {
    if (status !== undefined) {
        _bizStatus = status;
        document.querySelectorAll('.biz-tab').forEach(btn => {
            btn.classList.toggle('active-tab', btn.dataset.tab === status);
        });
    }
    const q    = (document.getElementById('biz_search').value || '').toLowerCase().trim();
    const client = (document.getElementById('biz_client_filter').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#biz_tbody tr[data-status]');
    let visible = 0;
    rows.forEach(tr => {
        const okStatus = _bizStatus === 'all' || tr.dataset.status === _bizStatus;
        const okSearch = !q || (tr.dataset.name || '').includes(q);
        const okClient = !client || (tr.dataset.client || '') === client;
        const show = okStatus && okSearch && okClient;
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const nr = document.getElementById('biz_no_results');
    if (nr) nr.classList.toggle('hidden', visible > 0 || rows.length === 0);
}
filterBusinesses('all');
</script>
<?php render_footer(); ?>
