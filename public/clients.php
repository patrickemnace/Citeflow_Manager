<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin();

$pendingDeleteId = 0;

if (isset($_GET['confirm_delete']) || isset($_GET['delete'])) {
    $pendingDeleteId = (int)($_GET['confirm_delete'] ?? $_GET['delete']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_client') {
    $id = (int)post('delete_id');
    $stmt = db()->prepare('DELETE FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        log_activity('client', $id, 'delete');
        set_flash('ok', 'Client removed.');
    } else {
        set_flash('warning', 'Client was not found or already deleted.');
    }
    redirect('/clients.php');
}

$rows = db()->query('SELECT c.*, COUNT(b.id) AS business_count
                     FROM clients c
                     LEFT JOIN businesses b ON b.client_id = c.id
                     GROUP BY c.id
                     ORDER BY c.updated_at DESC, c.name ASC')->fetchAll();

render_header('Clients');
?>
<section class="rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <?php if ($pendingDeleteId > 0): ?>
        <?php
            $pendingStmt = db()->prepare('SELECT id, name FROM clients WHERE id = ? LIMIT 1');
            $pendingStmt->execute([$pendingDeleteId]);
            $pendingClient = $pendingStmt->fetch();
        ?>
        <?php if ($pendingClient): ?>
            <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/55 dark:bg-slate-950/75"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900 shadow-2xl dark:border-amber-700 dark:bg-slate-900 dark:text-amber-200">
                    <p class="text-sm font-bold">Delete confirmation required</p>
                    <p class="mt-1 text-sm">You are about to permanently delete client: <span class="font-semibold"><?php echo e((string)$pendingClient['name']); ?></span>.</p>
                    <p class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">Warning: This action cannot be undone.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="post">
                            <input type="hidden" name="action" value="delete_client">
                            <input type="hidden" name="delete_id" value="<?php echo e((string)$pendingClient['id']); ?>">
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yes, delete client</button>
                        </form>
                        <a href="<?php echo e(app_config()['base_url']); ?>/clients.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-700 px-5 py-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Clients</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Group businesses by account or brand for cleaner reporting and operations.</p>
        </div>
        <a class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" href="<?php echo e(app_config()['base_url']); ?>/client_form.php">Add Client</a>
    </div>

    <div class="cf-table-wrap overflow-x-auto">
        <table class="cf-table min-w-[980px] divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Client</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Brand</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Website</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Primary Contact</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Account</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Portal</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Businesses</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                <?php foreach ($rows as $row): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="px-5 py-4 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e((string)$row['name']); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400"><?php echo e((string)($row['brand_name'] ?: '—')); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">
                            <?php if (trim((string)$row['website']) !== ''): ?>
                                <a class="text-brand-700 dark:text-brand-400 hover:underline" href="<?php echo e((string)$row['website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)$row['website']); ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">
                            <div><?php echo e((string)($row['primary_email'] ?: '—')); ?></div>
                            <div class="text-xs text-slate-400 dark:text-slate-500"><?php echo e((string)($row['primary_phone'] ?: '')); ?></div>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">
                            <?php $accountActive = (int)($row['is_active'] ?? 0) === 1; ?>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold <?php echo $accountActive ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200'; ?>">
                                <?php echo $accountActive ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600">
                            <?php $portalEnabled = trim((string)($row['portal_password_hash'] ?? '')) !== ''; ?>
                            <div>
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full <?php echo $portalEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?>" title="<?php echo $portalEnabled ? 'Password set' : 'No password'; ?>" aria-label="<?php echo $portalEnabled ? 'Password set' : 'No password'; ?>">
                                    <?php if ($portalEnabled): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.313a1 1 0 0 1-1.42-.008l-3.25-3.313a1 1 0 1 1 1.428-1.402l2.54 2.588 6.547-6.598a1 1 0 0 1 1.399.006Z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-slate-400">
                                <?php echo trim((string)($row['portal_last_login_at'] ?? '')) !== '' ? e((string)$row['portal_last_login_at']) : 'Never logged in'; ?>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo e((string)$row['business_count']); ?></td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100" href="<?php echo e(app_config()['base_url']); ?>/client_form.php?id=<?php echo e((string)$row['id']); ?>" title="Edit client" aria-label="Edit client">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-8.7 8.7a2 2 0 0 1-.878.513l-2.73.78a.75.75 0 0 1-.927-.927l.78-2.73a2 2 0 0 1 .513-.878l8.7-8.7Z" /></svg>
                                </a>
                                <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50" href="<?php echo e(app_config()['base_url']); ?>/clients.php?confirm_delete=<?php echo e((string)$row['id']); ?>" title="Delete client" aria-label="Delete client">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 2.5a1 1 0 0 0-1 1V4h-2.5a.75.75 0 0 0 0 1.5h.538l.84 10.08A2.5 2.5 0 0 0 9.12 18h1.76a2.5 2.5 0 0 0 2.492-2.42l.84-10.08h.538a.75.75 0 0 0 0-1.5h-2.5v-.5a1 1 0 0 0-1-1h-2.5Zm2 1.5h-1V4h1v0Zm-1.69 3.25a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 1.5 0v-6Zm3.38 0a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 1.5 0v-6Z" clip-rule="evenodd" /></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td class="px-5 py-6 text-sm text-slate-500" colspan="8">No clients yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>