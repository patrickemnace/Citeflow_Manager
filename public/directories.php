<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_permission('directories');

$pendingDirectoryDeleteRows = [];
$pendingSingleDeleteId = 0;

if ((isset($_GET['confirm_delete']) || isset($_GET['delete']))) {
    $pendingSingleDeleteId = (int)($_GET['confirm_delete'] ?? $_GET['delete']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('bulk_action') === 'delete_single') {
    $id = (int)post('delete_id');
    $dir = db()->prepare('SELECT name FROM directories WHERE id = ?');
    $dir->execute([$id]);
    $dirRow = $dir->fetch();
    $deleteStmt = db()->prepare('DELETE FROM directories WHERE id = ?');
    $deleteStmt->execute([$id]);
    if ($deleteStmt->rowCount() > 0) {
        log_activity('directory', $id, 'delete', [
            'name' => (string)($dirRow['name'] ?? ''),
        ]);
    }
    set_flash('ok', 'Directory removed.');
    redirect('/directories.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (post('bulk_action') === 'request_delete_selected' || isset($_POST['request_delete_selected']))) {
    $selectedIds = array_map('intval', $_POST['selected_ids'] ?? []);
    $selectedIds = array_values(array_filter($selectedIds, static fn (int $id): bool => $id > 0));

    if (!$selectedIds) {
        set_flash('warning', 'Select at least one directory to delete.');
        redirect('/directories.php');
    }

    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $lookupStmt = db()->prepare('SELECT id, name FROM directories WHERE id IN (' . $placeholders . ')');
    $lookupStmt->execute($selectedIds);
    $pendingDirectoryDeleteRows = $lookupStmt->fetchAll();

    if (!$pendingDirectoryDeleteRows) {
        set_flash('warning', 'No matching directories were found for deletion.');
        redirect('/directories.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('bulk_action') === 'delete_selected') {
    $selectedIds = array_map('intval', $_POST['selected_ids'] ?? []);
    $selectedIds = array_values(array_filter($selectedIds, static fn (int $id): bool => $id > 0));

    if ($selectedIds) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $lookupStmt = db()->prepare('SELECT id, name FROM directories WHERE id IN (' . $placeholders . ')');
        $lookupStmt->execute($selectedIds);
        $foundRows = $lookupStmt->fetchAll();

        if ($foundRows) {
            db()->prepare('DELETE FROM directories WHERE id IN (' . $placeholders . ')')->execute($selectedIds);
            foreach ($foundRows as $dirRow) {
                $deletedId = (int)($dirRow['id'] ?? 0);
                if ($deletedId <= 0) {
                    continue;
                }
                log_activity('directory', $deletedId, 'delete', [
                    'name' => (string)($dirRow['name'] ?? ''),
                    'bulk' => true,
                ]);
            }
            set_flash('ok', 'Deleted ' . count($foundRows) . ' director' . (count($foundRows) === 1 ? 'y.' : 'ies.'));
        }
    }

    if (!$selectedIds) {
        set_flash('warning', 'Select at least one directory to delete.');
    }

    redirect('/directories.php');
}

$search = trim((string)($_GET['q'] ?? ''));
$filterType = trim((string)($_GET['type'] ?? ''));
$filterRegion = trim((string)($_GET['region'] ?? ''));
$filterPriority = trim((string)($_GET['priority'] ?? ''));
$filterPricing = trim((string)($_GET['pricing'] ?? ''));
$filterLogin = trim((string)($_GET['login'] ?? ''));
$filterActive = trim((string)($_GET['active'] ?? ''));

$typeOptions = ['General', 'Search', 'Map', 'Review', 'SMB', 'Social', 'Industry Specific', 'Data Aggregator', 'Local Chamber'];
$regionOptions = ['Global', 'USA', 'Canada', 'UK', 'Australia', 'Europe', 'Asia Pacific', 'Middle East', 'Latin America', 'Africa'];
$priorityOptions = ['low', 'medium', 'high'];
$priorityLabels = ['low' => 'Easy', 'medium' => 'Moderate', 'high' => 'Hard'];
$pricingOptions = ['free', 'paid'];

if (!in_array($filterType, $typeOptions, true)) {
    $filterType = '';
}
if (!in_array($filterRegion, $regionOptions, true)) {
    $filterRegion = '';
}
if (!in_array($filterPriority, $priorityOptions, true)) {
    $filterPriority = '';
}
if (!in_array($filterPricing, $pricingOptions, true)) {
    $filterPricing = '';
}
if ($filterLogin !== '1' && $filterLogin !== '0') {
    $filterLogin = '';
}
if ($filterActive !== '1' && $filterActive !== '0') {
    $filterActive = '';
}

$sql = 'SELECT * FROM directories WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR website LIKE ? OR notes LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}
if ($filterType !== '') {
    $sql .= ' AND directory_type = ?';
    $params[] = $filterType;
}
if ($filterRegion !== '') {
    $sql .= ' AND country = ?';
    $params[] = $filterRegion;
}
if ($filterPriority !== '') {
    $sql .= ' AND priority_level = ?';
    $params[] = $filterPriority;
}
if ($filterPricing !== '') {
    $sql .= ' AND pricing_model = ?';
    $params[] = $filterPricing;
}
if ($filterLogin !== '') {
    $sql .= ' AND requires_login = ?';
    $params[] = (int)$filterLogin;
}
if ($filterActive !== '') {
    $sql .= ' AND is_active = ?';
    $params[] = (int)$filterActive;
}

$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

render_header('Directories');
?>
<section class="rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <?php if ($pendingSingleDeleteId > 0): ?>
        <?php
            $pendingSingleStmt = db()->prepare('SELECT id, name FROM directories WHERE id = ? LIMIT 1');
            $pendingSingleStmt->execute([$pendingSingleDeleteId]);
            $pendingSingleRow = $pendingSingleStmt->fetch();
        ?>
        <?php if ($pendingSingleRow): ?>
            <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/55 dark:bg-slate-950/75"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900 shadow-2xl dark:border-amber-700 dark:bg-slate-900 dark:text-amber-200">
                    <p class="text-sm font-bold">Delete confirmation required</p>
                    <p class="mt-1 text-sm">You are about to permanently delete directory: <span class="font-semibold"><?php echo e((string)$pendingSingleRow['name']); ?></span>.</p>
                    <p class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">Warning: This action cannot be undone.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="post">
                            <input type="hidden" name="bulk_action" value="delete_single">
                            <input type="hidden" name="delete_id" value="<?php echo e((string)$pendingSingleRow['id']); ?>">
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yes, delete directory</button>
                        </form>
                        <a href="<?php echo e(app_config()['base_url']); ?>/directories.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-700/80">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Directories</h1>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Manage available citation directories for submission workflows.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" href="<?php echo e(app_config()['base_url']); ?>/directory_form.php">Add Directory</a>
        </div>
    </div>

    <form method="get" class="border-b border-slate-200 bg-white/70 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/40">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
            <input name="q" value="<?php echo e($search); ?>" placeholder="Search name, website, notes" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />

            <select name="type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <option value="">All types</option>
                <?php foreach ($typeOptions as $option): ?>
                    <option value="<?php echo e($option); ?>" <?php echo $filterType === $option ? 'selected' : ''; ?>><?php echo e($option); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="region" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <option value="">All regions</option>
                <?php foreach ($regionOptions as $option): ?>
                    <option value="<?php echo e($option); ?>" <?php echo $filterRegion === $option ? 'selected' : ''; ?>><?php echo e($option); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="priority" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <option value="">All difficulty levels</option>
                <?php foreach ($priorityOptions as $option): ?>
                    <option value="<?php echo e($option); ?>" <?php echo $filterPriority === $option ? 'selected' : ''; ?>><?php echo e((string)($priorityLabels[$option] ?? ucfirst($option))); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="pricing" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Pricing: Any</option>
                <option value="free" <?php echo $filterPricing === 'free' ? 'selected' : ''; ?>>Free</option>
                <option value="paid" <?php echo $filterPricing === 'paid' ? 'selected' : ''; ?>>Paid</option>
            </select>

            <select name="login" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Login: Any</option>
                <option value="1" <?php echo $filterLogin === '1' ? 'selected' : ''; ?>>Login Required</option>
                <option value="0" <?php echo $filterLogin === '0' ? 'selected' : ''; ?>>No Login</option>
            </select>

            <select name="active" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                <option value="">Status: Any</option>
                <option value="1" <?php echo $filterActive === '1' ? 'selected' : ''; ?>>Active</option>
                <option value="0" <?php echo $filterActive === '0' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Apply Filters</button>
            <a href="<?php echo e(app_config()['base_url']); ?>/directories.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Reset</a>
            <span class="text-sm text-slate-500 dark:text-slate-400"><?php echo e((string)count($rows)); ?> result(s)</span>
        </div>
    </form>
    <div class="cf-table-wrap overflow-x-auto">
    <table class="cf-table divide-y divide-slate-200">
        <thead class="bg-slate-50 dark:bg-slate-800">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Logo</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Name</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Website</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Type</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Region</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Difficulty</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Pricing</th>
                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Login Required</th>
                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
            <?php foreach ($rows as $row): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                    <td class="px-5 py-4 text-sm text-slate-600">
                        <?php if (trim((string)($row['logo_path'] ?? '')) !== ''): ?>
                            <img class="h-10 w-10 rounded-lg border border-slate-200 object-cover" src="<?php echo e(public_asset_url((string)$row['logo_path'])); ?>" alt="Directory logo">
                        <?php else: ?>
                            <?php $directoryInitial = strtoupper(substr(trim((string)$row['name']), 0, 1)) ?: '?'; ?>
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-sm font-bold text-slate-600"><?php echo e($directoryInitial); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-sm font-semibold text-slate-900"><?php echo e($row['name']); ?></td>
                    <td class="px-5 py-4 text-sm"><a class="break-all text-brand-700 hover:text-brand-800" href="<?php echo e($row['website']); ?>" target="_blank"><?php echo e($row['website']); ?></a></td>
                    <td class="px-5 py-4 text-sm text-slate-600"><?php echo e((string)($row['directory_type'] ?? 'General')); ?></td>
                    <td class="px-5 py-4 text-sm text-slate-600"><?php echo e($row['country']); ?></td>
                    <?php $difficultyKey = strtolower(trim((string)($row['priority_level'] ?? 'medium'))); ?>
                    <td class="px-5 py-4 text-sm text-slate-600"><?php echo e((string)($priorityLabels[$difficultyKey] ?? status_label($difficultyKey))); ?></td>
                    <?php $pricingKey = strtolower(trim((string)($row['pricing_model'] ?? 'free'))); ?>
                    <td class="px-5 py-4 text-sm text-slate-600"><?php echo e($pricingKey === 'paid' ? 'Paid' : 'Free'); ?></td>
                    <td class="px-5 py-4 text-sm text-slate-600"><?php echo ((int)$row['requires_login'] === 1) ? 'Yes' : 'No'; ?></td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <?php if (trim((string)($row['notes'] ?? '')) !== ''): ?>
                            <div class="relative" data-notes-tip>
                                <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-2.5 py-2 text-slate-500 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Notes">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-6 4h4M4 6a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H6l-4 4V6z" /></svg>
                                </button>
                                <div class="notes-popup pointer-events-none fixed z-[9999] hidden w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-slate-400">Notes</p>
                                    <p class="whitespace-pre-wrap text-sm text-slate-700"><?php echo e((string)$row['notes']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" href="<?php echo e(app_config()['base_url']); ?>/directory_form.php?id=<?php echo e((string)$row['id']); ?>" title="Edit directory" aria-label="Edit directory">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-8.7 8.7a2 2 0 0 1-.878.513l-2.73.78a.75.75 0 0 1-.927-.927l.78-2.73a2 2 0 0 1 .513-.878l8.7-8.7Z" /></svg>
                            </a>
                            <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50" href="<?php echo e(app_config()['base_url']); ?>/directories.php?confirm_delete=<?php echo e((string)$row['id']); ?>" title="Delete directory" aria-label="Delete directory">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 2.5a1 1 0 0 0-1 1V4h-2.5a.75.75 0 0 0 0 1.5h.538l.84 10.08A2.5 2.5 0 0 0 9.12 18h1.76a2.5 2.5 0 0 0 2.492-2.42l.84-10.08h.538a.75.75 0 0 0 0-1.5h-2.5v-.5a1 1 0 0 0-1-1h-2.5Zm2 1.5h-1V4h1v0Zm-1.69 3.25a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 1.5 0v-6Zm3.38 0a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 1.5 0v-6Z" clip-rule="evenodd" /></svg>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400" colspan="9">No directories yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php render_footer(); ?>
<script>
const noteWrappers = document.querySelectorAll('[data-notes-tip]');

const hideAllNotes = () => {
    noteWrappers.forEach((wrapper) => {
        const popup = wrapper.querySelector('.notes-popup');
        if (!popup) return;
        popup.classList.add('hidden');
    });
};

const positionNotesPopup = (wrapper, popup) => {
    const trigger = wrapper.querySelector('button') || wrapper;
    const triggerRect = trigger.getBoundingClientRect();

    let left = triggerRect.right - popup.offsetWidth;
    if (left < 8) {
        left = 8;
    }
    if (left + popup.offsetWidth > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - popup.offsetWidth - 8);
    }

    let top = triggerRect.top - popup.offsetHeight - 8;
    if (top < 8) {
        top = triggerRect.bottom + 8;
    }

    popup.style.left = `${left}px`;
    popup.style.top = `${top}px`;
};

noteWrappers.forEach((wrapper) => {
    const popup = wrapper.querySelector('.notes-popup');
    if (!popup) return;

    wrapper.addEventListener('mouseenter', () => {
        popup.classList.remove('hidden');
        positionNotesPopup(wrapper, popup);
    });

    wrapper.addEventListener('mouseleave', () => {
        popup.classList.add('hidden');
    });
});

window.addEventListener('scroll', hideAllNotes, true);
window.addEventListener('resize', hideAllNotes);

</script>
