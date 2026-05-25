<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin();

$activeWindowSeconds = 90;
$activeUsers = fetch_live_user_tracking_rows($activeWindowSeconds, 12);
$activeClients = fetch_live_client_tracking_rows($activeWindowSeconds, 12);
$activeCount = count($activeUsers);
$trackingBase = rtrim((string)app_config()['base_url'], '/');
$trackingFeedUrl = $trackingBase . '/user_tracking.php?view=active&limit=12';

render_header('Live User Tracking');
?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Live User Tracking</h1>
            <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Monitor who is online, where they are working, and watch the feed refresh automatically.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Active Users</p>
                <p class="mt-1 text-2xl font-extrabold text-emerald-800 dark:text-emerald-200"><?php echo e((string)$activeCount); ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-600 dark:text-slate-400">Refresh</p>
                <p class="mt-1 text-2xl font-extrabold text-slate-800 dark:text-slate-200">10s</p>
            </div>
        </div>
    </div>
    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-300">
        Presence updates are sent automatically in the background and on user interaction, so activity changes without a manual page refresh.
    </div>
</section>

<section id="live_user_tracking_panel" data-feed-url="<?php echo e($trackingFeedUrl); ?>" data-refresh-ms="10000" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="mb-4 flex items-center justify-between gap-2">
        <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Active Sessions</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Users active in the last 90 seconds and their current page.</p>
        </div>
        <span id="live_user_tracking_stamp" class="text-xs font-semibold text-slate-500 dark:text-slate-400">Updated <?php echo e(date('g:i:s A')); ?></span>
    </div>
    <div id="live_user_tracking_list" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($activeUsers as $trackedUser): ?>
            <article class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/30">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo e((string)$trackedUser['full_name']); ?></p>
                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300"><?php echo e('Online ' . format_duration_seconds((int)($trackedUser['seconds_online'] ?? 0))); ?></span>
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e((string)($trackedUser['designation'] ?: ucfirst((string)$trackedUser['role']))); ?> · <?php echo e((string)$trackedUser['page_title']); ?></p>
                <p class="mt-1 text-xs font-medium text-slate-700 break-all dark:text-slate-300"><?php echo e((string)$trackedUser['current_path']); ?></p>
                <p class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400"><?php echo e('Last seen ' . format_relative_seconds((int)($trackedUser['seconds_since_last_seen'] ?? 0))); ?></p>
            </article>
        <?php endforeach; ?>
    </div>
    <div id="live_user_tracking_empty" class="<?php echo $activeUsers ? 'hidden ' : ''; ?>rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">No users are active right now.</div>

    <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-700">
        <div class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Active Client Sessions</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Clients active in the last 90 seconds in the portal.</p>
            </div>
            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300" id="live_client_tracking_count"><?php echo e((string)count($activeClients)); ?> online</span>
        </div>

        <div id="live_client_tracking_list" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($activeClients as $trackedClient): ?>
                <article class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-900 dark:bg-sky-950/30">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo e((string)$trackedClient['client_name']); ?></p>
                        <span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-900/50 dark:text-sky-300"><?php echo e('Online ' . format_duration_seconds((int)($trackedClient['seconds_online'] ?? 0))); ?></span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e((string)$trackedClient['primary_email']); ?> · <?php echo e((string)$trackedClient['page_title']); ?></p>
                    <p class="mt-1 text-xs font-medium text-slate-700 break-all dark:text-slate-300"><?php echo e((string)$trackedClient['current_path']); ?></p>
                    <p class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400"><?php echo e('Last seen ' . format_relative_seconds((int)($trackedClient['seconds_since_last_seen'] ?? 0))); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div id="live_client_tracking_empty" class="<?php echo $activeClients ? 'hidden ' : ''; ?>rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">No clients are active right now.</div>
    </div>
</section>
<?php render_footer(); ?>