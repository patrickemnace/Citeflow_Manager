<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();

$user = current_user();
sync_operational_notifications($user);
$returnTo = trim((string)($_GET['from'] ?? ''));
$nextUrl = trim((string)($_GET['next'] ?? ''));

if ($returnTo !== '') {
    $parsedPath = (string)parse_url($returnTo, PHP_URL_PATH);
    $returnTo = $parsedPath !== '' ? $parsedPath : $returnTo;
}

if ($returnTo !== '') {
    $basePath = rtrim((string)app_config()['base_url'], '/');
    if ($basePath !== '' && str_starts_with($returnTo, $basePath . '/')) {
        $returnTo = substr($returnTo, strlen($basePath));
    } elseif ($basePath !== '' && $returnTo === $basePath) {
        $returnTo = '/dashboard.php';
    }
}

if ($returnTo === '' || str_starts_with($returnTo, 'http://') || str_starts_with($returnTo, 'https://') || !str_starts_with($returnTo, '/')) {
    $returnTo = '/dashboard.php';
}

if ($nextUrl !== '') {
    if (str_starts_with($nextUrl, '/')) {
        // keep local app-relative paths
    } elseif (str_starts_with($nextUrl, 'http://') || str_starts_with($nextUrl, 'https://')) {
        $appHost = (string)parse_url((string)app_config()['base_url'], PHP_URL_HOST);
        $nextHost = (string)parse_url($nextUrl, PHP_URL_HOST);
        if ($appHost === '' || $nextHost === '' || strcasecmp($appHost, $nextHost) !== 0) {
            $nextUrl = '';
        }
    } else {
        $nextUrl = '';
    }
}

if (isset($_GET['read'])) {
    mark_notification_read((int)$_GET['read'], $user);
    $redirectTarget = $nextUrl !== '' ? $nextUrl : (is_admin() ? '/notifications.php' : $returnTo);
    redirect($redirectTarget);
}

if (isset($_GET['read_all'])) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0')->execute([(int)$user['id']]);
    set_flash('ok', 'Notifications marked as read.');
    redirect(is_admin() ? '/notifications.php' : $returnTo);
}

require_admin();

// Date filtering
$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate = trim((string)($_GET['to_date'] ?? ''));
$readStatus = trim((string)($_GET['read_status'] ?? 'all'));

$whereClause = '';
$params = [];

if ($fromDate !== '') {
    $whereClause .= ' AND DATE(created_at) >= ?';
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $whereClause .= ' AND DATE(created_at) <= ?';
    $params[] = $toDate;
}

if ($readStatus === 'unread') {
    $whereClause .= ' AND is_read = 0';
} elseif ($readStatus === 'read') {
    $whereClause .= ' AND is_read = 1';
}

function notification_visual_meta(string $title, string $message): array
{
    $haystack = strtolower(trim($title . ' ' . $message));

    if (str_contains($haystack, 'business') || str_contains($haystack, 'client') || str_contains($haystack, 'location')) {
        return [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 9h.01"/><path d="M9 12h.01"/><path d="M9 15h.01"/><path d="M15 9h.01"/><path d="M15 12h.01"/><path d="M15 15h.01"/></svg>',
            'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        ];
    }

    if (str_contains($haystack, 'citation') || str_contains($haystack, 'directory') || str_contains($haystack, 'listing') || str_contains($haystack, 'submission')) {
        return [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        ];
    }

    if (str_contains($haystack, 'delete') || str_contains($haystack, 'removed') || str_contains($haystack, 'failed') || str_contains($haystack, 'error')) {
        return [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>',
            'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        ];
    }

    return [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>',
        'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
    ];
}

$sql = 'SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL)' . $whereClause . ' ORDER BY created_at DESC LIMIT 500';
array_unshift($params, (int)$user['id']);

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

render_header('Notifications');
?>
<section class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-slate-50/50 to-indigo-50/30 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950/20">
    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-700/80">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Notifications</h1>
                <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">Stay updated with system and workflow activity</p>
            </div>
            <a class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600 transition-colors" href="<?php echo e(app_config()['base_url']); ?>/notifications.php?read_all=1">Mark all read</a>
        </div>
    </div>

    <!-- Filter Section -->
    <form method="get" class="border-b border-slate-200/50 bg-gradient-to-r from-slate-50/80 to-indigo-50/40 px-5 py-4 dark:border-slate-700/50 dark:from-slate-800/30 dark:to-indigo-950/20">
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-2">From Date</label>
                <input type="date" name="from_date" value="<?php echo e($fromDate); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm transition-colors focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-indigo-500">
            </div>
            <div class="sm:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-2">To Date</label>
                <input type="date" name="to_date" value="<?php echo e($toDate); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm transition-colors focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-indigo-500">
            </div>
            <div class="sm:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-2">Status</label>
                <select name="read_status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm transition-colors focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-indigo-500">
                    <option value="all" <?php echo $readStatus === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="unread" <?php echo $readStatus === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                    <option value="read" <?php echo $readStatus === 'read' ? 'selected' : ''; ?>>Read Only</option>
                </select>
            </div>
            <div class="flex flex-col justify-end gap-2 sm:col-span-1">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600 transition-colors">Filter</button>
                <a href="<?php echo e(app_config()['base_url']); ?>/notifications.php" class="text-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors">Reset</a>
            </div>
        </div>
    </form>

    <div class="divide-y divide-slate-100 dark:divide-slate-700">
        <?php foreach ($rows as $row): ?>
            <?php 
                $isUnread = (int)$row['is_read'] === 0;
                $title = (string)($row['title'] ?? 'Notification');
                $message = (string)($row['message'] ?? '');
                $visual = notification_visual_meta($title, $message);
                $businessLogo = notification_business_logo_path($row);
                $actionUrl = trim((string)($row['action_url'] ?? ''));
                $readUrl = app_config()['base_url'] . '/notifications.php?read=' . (int)$row['id'] . '&from=' . rawurlencode((string)($_SERVER['REQUEST_URI'] ?? '/notifications.php'));
                if ($actionUrl !== '') {
                    $readUrl .= '&next=' . rawurlencode($actionUrl);
                }
                $targetUrl = $isUnread ? $readUrl : ($actionUrl !== '' ? $actionUrl : $readUrl);
            ?>
            <a href="<?php echo e((string)$targetUrl); ?>" class="flex flex-wrap items-start justify-between gap-4 px-5 py-4 transition-all duration-200 hover:shadow-inner <?php echo $isUnread ? 'bg-indigo-50/60 hover:bg-indigo-100/60 dark:bg-indigo-950/30 dark:hover:bg-indigo-900/40' : 'bg-white hover:bg-slate-50 dark:bg-slate-900/50 dark:hover:bg-slate-800/50'; ?>">
                <div class="max-w-3xl flex-1">
                    <div class="flex items-start gap-3">
                        <?php if ($businessLogo !== ''): ?>
                            <img src="<?php echo e($businessLogo); ?>" alt="Business logo" class="h-8 w-8 flex-shrink-0 rounded-lg border border-slate-200 object-cover shadow-sm dark:border-slate-700">
                        <?php else: ?>
                            <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg <?php echo e((string)$visual['badge']); ?>">
                                <?php echo $visual['icon']; ?>
                            </span>
                        <?php endif; ?>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full <?php echo $isUnread ? 'bg-indigo-500 dark:bg-indigo-400 shadow-lg shadow-indigo-500/50' : 'bg-slate-300 dark:bg-slate-600'; ?>"></span>
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100"><?php echo e($title); ?></p>
                            </div>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?php echo e($message); ?></p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-500"><?php 
                        $timestamp = (string)$row['created_at'];
                        $dateObj = new DateTime($timestamp);
                        echo htmlspecialchars($dateObj->format('M d, Y · H:i'), ENT_QUOTES, 'UTF-8');
                    ?></p>
                </div>
                <div class="flex flex-shrink-0 items-center gap-2">
                    <span class="text-2xl opacity-20">→</span>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <div class="px-5 py-12 text-center">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <p class="mt-3 font-medium text-slate-900 dark:text-slate-100">No notifications found</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Try adjusting your filter criteria</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>