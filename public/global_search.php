<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();

$user = current_user();
$isAdmin = is_admin();
$query = trim((string)($_GET['q'] ?? ''));
$like = '%' . $query . '%';

$businessRows = [];
$directoryRows = [];
$citationRows = [];
$clientRows = [];

if ($query !== '' && mb_strlen($query) >= 2) {
    if (has_permission('businesses')) {
        $businessStmt = db()->prepare("SELECT b.id, b.name, b.city, b.state, b.status, b.logo_path,
                                              COALESCE(c.name, '-') AS client_name
                                       FROM businesses b
                                       LEFT JOIN clients c ON c.id = b.client_id
                                       WHERE b.name LIKE ?
                                          OR b.city LIKE ?
                                          OR b.state LIKE ?
                                          OR b.phone LIKE ?
                                          OR b.website LIKE ?
                                          OR b.email LIKE ?
                                       ORDER BY b.updated_at DESC
                                       LIMIT 20");
        $businessStmt->execute([$like, $like, $like, $like, $like, $like]);
        $businessRows = $businessStmt->fetchAll();
    }

    if (has_permission('directories')) {
        $directoryStmt = db()->prepare("SELECT id, name, directory_type, country, priority_level, logo_path
                                        FROM directories
                                        WHERE name LIKE ?
                                           OR directory_type LIKE ?
                                           OR country LIKE ?
                                        ORDER BY is_active DESC, name ASC
                                        LIMIT 20");
        $directoryStmt->execute([$like, $like, $like]);
        $directoryRows = $directoryStmt->fetchAll();
    }

    if (has_permission('location_manager')) {
        $citationSql = "SELECT lt.id, lt.business_id, lt.status, lt.nap_status, lt.updated_at,
                               b.name AS business_name,
                               d.name AS directory_name,
                               COALESCE(NULLIF(lt.submitted_url, ''), '-') AS citation_url
                        FROM listing_tasks lt
                        INNER JOIN businesses b ON b.id = lt.business_id
                        INNER JOIN directories d ON d.id = lt.directory_id
                        WHERE (
                            b.name LIKE ?
                            OR d.name LIKE ?
                            OR lt.status LIKE ?
                            OR lt.nap_status LIKE ?
                            OR lt.submitted_url LIKE ?
                        )";
        $citationParams = [$like, $like, $like, $like, $like];

        if (!$isAdmin) {
            $citationSql .= ' AND lt.assigned_to = ?';
            $citationParams[] = (int)($user['id'] ?? 0);
        }

        $citationSql .= ' ORDER BY lt.updated_at DESC LIMIT 25';

        $citationStmt = db()->prepare($citationSql);
        $citationStmt->execute($citationParams);
        $citationRows = $citationStmt->fetchAll();
    }

    if ($isAdmin && table_exists('clients')) {
        $clientStmt = db()->prepare("SELECT id, name, brand_name, primary_email, website
                                     FROM clients
                                     WHERE is_active = 1
                                       AND (
                                           name LIKE ?
                                           OR brand_name LIKE ?
                                           OR primary_email LIKE ?
                                           OR website LIKE ?
                                       )
                                     ORDER BY name ASC
                                     LIMIT 20");
        $clientStmt->execute([$like, $like, $like, $like]);
        $clientRows = $clientStmt->fetchAll();
    }
}

$hasResults = !empty($businessRows) || !empty($directoryRows) || !empty($citationRows) || !empty($clientRows);

render_header('Global Search');
?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Global Search</h1>
    <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Search across businesses, citations, directories, and clients from one place.</p>
    <form method="get" class="mt-4 flex flex-wrap gap-2">
        <input name="q" type="search" value="<?php echo e($query); ?>" placeholder="Type at least 2 characters..." class="w-full flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm sm:min-w-[24rem]">
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Search</button>
        <a href="<?php echo e(app_config()['base_url']); ?>/global_search.php" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset</a>
    </form>
</section>

<?php if ($query === ''): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">Start by entering a keyword above.</section>
<?php elseif (mb_strlen($query) < 2): ?>
    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800 shadow-sm">Please enter at least 2 characters.</section>
<?php elseif (!$hasResults): ?>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">No results found for <strong><?php echo e($query); ?></strong>.</section>
<?php else: ?>
    <div class="grid gap-6 lg:grid-cols-2">
        <?php if ($businessRows): ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Businesses</h2>
                <div class="mt-3 space-y-2">
                    <?php foreach ($businessRows as $row): ?>
                        <a href="<?php echo e(app_config()['base_url']); ?>/location_manager.php?business_id=<?php echo e((string)$row['id']); ?>" class="block rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 hover:border-brand-300 hover:bg-brand-50/40">
                            <p class="text-sm font-semibold text-slate-900"><?php echo e((string)$row['name']); ?></p>
                            <p class="mt-0.5 text-xs text-slate-500"><?php echo e((string)$row['client_name']); ?> · <?php echo e(trim((string)$row['city'] . ', ' . (string)$row['state'], ' ,')); ?> · <?php echo e(status_label((string)$row['status'])); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endif; ?>

        <?php if ($citationRows): ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Citations</h2>
                <div class="mt-3 space-y-2">
                    <?php foreach ($citationRows as $row): ?>
                        <a href="<?php echo e(app_config()['base_url']); ?>/location_manager.php?business_id=<?php echo e((string)$row['business_id']); ?>&open_citations=1" class="block rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 hover:border-brand-300 hover:bg-brand-50/40">
                            <p class="text-sm font-semibold text-slate-900"><?php echo e((string)$row['business_name']); ?> · <?php echo e((string)$row['directory_name']); ?></p>
                            <p class="mt-0.5 text-xs text-slate-500">Status: <?php echo e(status_label((string)$row['status'])); ?> · NAP: <?php echo e(status_label((string)$row['nap_status'])); ?> · Updated: <?php echo e((string)$row['updated_at']); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endif; ?>

        <?php if ($directoryRows): ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Directories</h2>
                <div class="mt-3 space-y-2">
                    <?php foreach ($directoryRows as $row): ?>
                        <a href="<?php echo e(app_config()['base_url']); ?>/directories.php" class="block rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 hover:border-brand-300 hover:bg-brand-50/40">
                            <p class="text-sm font-semibold text-slate-900"><?php echo e((string)$row['name']); ?></p>
                            <p class="mt-0.5 text-xs text-slate-500"><?php echo e((string)$row['directory_type']); ?> · <?php echo e((string)$row['country']); ?> · <?php echo e(status_label((string)$row['priority_level'])); ?> Priority</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endif; ?>

        <?php if ($clientRows): ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Clients</h2>
                <div class="mt-3 space-y-2">
                    <?php foreach ($clientRows as $row): ?>
                        <a href="<?php echo e(app_config()['base_url']); ?>/clients.php" class="block rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 hover:border-brand-300 hover:bg-brand-50/40">
                            <p class="text-sm font-semibold text-slate-900"><?php echo e((string)$row['name']); ?></p>
                            <p class="mt-0.5 text-xs text-slate-500"><?php echo e((string)($row['brand_name'] ?: '-')); ?> · <?php echo e((string)($row['primary_email'] ?: '-')); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php render_footer(); ?>
