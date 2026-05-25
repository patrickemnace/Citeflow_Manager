<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin();

$entityFilter = trim((string)($_GET['entity'] ?? ''));
$actionFilter = trim((string)($_GET['action'] ?? ''));
$actorFilter = (int)($_GET['actor'] ?? 0);
$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate = trim((string)($_GET['to_date'] ?? ''));

$where = [];
$params = [];

if ($entityFilter !== '') {
    $where[] = 'al.entity_type = ?';
    $params[] = $entityFilter;
}

if ($actionFilter !== '') {
    $where[] = 'al.action = ?';
    $params[] = $actionFilter;
}

if ($actorFilter > 0) {
    $where[] = 'al.actor_id = ?';
    $params[] = $actorFilter;
}

if ($fromDate !== '') {
    $where[] = 'DATE(al.created_at) >= ?';
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $where[] = 'DATE(al.created_at) <= ?';
    $params[] = $toDate;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

function entity_label(string $entityType): string
{
    return match ($entityType) {
        'listing_task' => 'Citation',
        'business' => 'Business',
        'directory' => 'Directory',
        'user' => 'User',
        'client' => 'Client',
        default => status_label($entityType),
    };
}

function action_label(string $action): string
{
    return match ($action) {
        'create' => 'Added',
        'update' => 'Updated',
        'delete' => 'Removed',
        default => status_label($action),
    };
}

function activity_details_summary(string $entityType, string $action, mixed $detailsRaw): string
{
    $detailsText = trim((string)$detailsRaw);
    if ($detailsText === '') {
        return '-';
    }

    $decoded = json_decode($detailsText, true);
    if (!is_array($decoded)) {
        return $detailsText;
    }

    $status = isset($decoded['status']) ? status_label((string)$decoded['status']) : '';
    $napStatus = isset($decoded['nap_status']) ? status_label((string)$decoded['nap_status']) : '';
    $priority = isset($decoded['priority_level']) ? status_label((string)$decoded['priority_level']) : '';
    $parts = [];

    if ($entityType === 'listing_task') {
        if ($action === 'create') {
            $summary = 'Citation added';
            if (isset($decoded['business_id'])) {
                $summary .= ' for business #' . (string)$decoded['business_id'];
            }
            if (isset($decoded['directory_id'])) {
                $summary .= ' in directory #' . (string)$decoded['directory_id'];
            }
            if ($status !== '') {
                $parts[] = 'Status: ' . $status;
            }
            if ($napStatus !== '') {
                $parts[] = 'NAP: ' . $napStatus;
            }
            if ($priority !== '') {
                $parts[] = 'Priority: ' . $priority;
            }
            return $summary . (count($parts) ? '. ' . implode(', ', $parts) . '.' : '.');
        }

        if ($action === 'update') {
            $parts[] = 'Citation updated';
            if ($status !== '') {
                $parts[] = 'Status: ' . $status;
            }
            if ($napStatus !== '') {
                $parts[] = 'NAP: ' . $napStatus;
            }
            if ($priority !== '') {
                $parts[] = 'Priority: ' . $priority;
            }
            if (isset($decoded['citation_type']) && trim((string)$decoded['citation_type']) !== '') {
                $parts[] = 'Type: ' . (string)$decoded['citation_type'];
            }
            return implode('. ', array_slice($parts, 0, 4)) . '.';
        }

        if ($action === 'delete') {
            $summary = 'Citation removed';
            if (isset($decoded['directory_name']) && trim((string)$decoded['directory_name']) !== '') {
                $summary .= ' from ' . (string)$decoded['directory_name'];
            }
            if ($status !== '') {
                $summary .= '. Previous status: ' . $status;
            }
            return $summary . '.';
        }
    }

    if ($entityType === 'business') {
        if (isset($decoded['name']) && trim((string)$decoded['name']) !== '') {
            $parts[] = 'Name: ' . (string)$decoded['name'];
        }
        if (isset($decoded['status']) && trim((string)$decoded['status']) !== '') {
            $parts[] = 'Status: ' . status_label((string)$decoded['status']);
        }
        if (isset($decoded['city']) || isset($decoded['state'])) {
            $city = trim((string)($decoded['city'] ?? ''));
            $state = trim((string)($decoded['state'] ?? ''));
            $loc = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);
            if ($loc !== '') {
                $parts[] = 'Location: ' . $loc;
            }
        }
    } elseif ($entityType === 'directory') {
        if (isset($decoded['name']) && trim((string)$decoded['name']) !== '') {
            $parts[] = 'Name: ' . (string)$decoded['name'];
        }
        if (isset($decoded['country']) && trim((string)$decoded['country']) !== '') {
            $parts[] = 'Country: ' . (string)$decoded['country'];
        }
        if (array_key_exists('is_active', $decoded)) {
            $parts[] = 'Status: ' . ((int)$decoded['is_active'] === 1 ? 'Active' : 'Inactive');
        }
    } elseif ($entityType === 'user') {
        if (isset($decoded['email']) && trim((string)$decoded['email']) !== '') {
            $parts[] = 'Email: ' . (string)$decoded['email'];
        }
        if (isset($decoded['role']) && trim((string)$decoded['role']) !== '') {
            $parts[] = 'Role: ' . status_label((string)$decoded['role']);
        }
        if (array_key_exists('is_active', $decoded)) {
            $parts[] = 'Status: ' . ((int)$decoded['is_active'] === 1 ? 'Active' : 'Inactive');
        }
    } elseif ($entityType === 'client') {
        if (isset($decoded['name']) && trim((string)$decoded['name']) !== '') {
            $parts[] = 'Name: ' . (string)$decoded['name'];
        }
    }

    if ($parts) {
        return implode(' | ', array_slice($parts, 0, 4));
    }

    $fallback = [];
    foreach ($decoded as $k => $v) {
        if (is_scalar($v) && trim((string)$v) !== '') {
            $fallback[] = status_label((string)$k) . ': ' . (string)$v;
        }
        if (count($fallback) >= 4) {
            break;
        }
    }

    return $fallback ? implode(' | ', $fallback) : '-';
}

$sql = "SELECT al.id, al.actor_id, al.entity_type, al.entity_id, al.action, al.details, al.created_at,
               u.full_name AS actor_name, u.email AS actor_email
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.actor_id
        $whereSql
        ORDER BY al.created_at DESC
        LIMIT 500";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$entities = db()->query('SELECT DISTINCT entity_type FROM activity_logs ORDER BY entity_type ASC')->fetchAll();
$actions = db()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action ASC')->fetchAll();
$actors = db()->query('SELECT DISTINCT u.id, u.full_name, u.email
                       FROM activity_logs al
                       JOIN users u ON u.id = al.actor_id
                       ORDER BY u.full_name ASC')->fetchAll();

render_header('Activity Logs');
?>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Activity Logs</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400">Audit trail for create, update, and delete actions across the system.</p>
    </div>

    <!-- Enhanced Filter Section -->
    <form method="get" class="grid gap-4 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-slate-50/50 px-5 py-4 dark:border-slate-700 dark:from-slate-800/50 dark:to-slate-800/30">
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">Entity Type</label>
                <select class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" name="entity">
                    <option value="">All entities</option>
                    <?php foreach ($entities as $entry): ?>
                        <?php $value = (string)$entry['entity_type']; ?>
                        <option value="<?php echo e($value); ?>" <?php echo $entityFilter === $value ? 'selected' : ''; ?>><?php echo e(entity_label($value)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">Action</label>
                <select class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" name="action">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $entry): ?>
                        <?php $value = (string)$entry['action']; ?>
                        <option value="<?php echo e($value); ?>" <?php echo $actionFilter === $value ? 'selected' : ''; ?>><?php echo e(action_label($value)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">Actor</label>
                <select class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" name="actor">
                    <option value="0">All actors</option>
                    <?php foreach ($actors as $actor): ?>
                        <?php $value = (int)$actor['id']; ?>
                        <option value="<?php echo e((string)$value); ?>" <?php echo $actorFilter === $value ? 'selected' : ''; ?>><?php echo e((string)$actor['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Date Range Filters -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">From Date</label>
                <input type="date" name="from_date" value="<?php echo e($fromDate); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-400 mb-1">To Date</label>
                <input type="date" name="to_date" value="<?php echo e($toDate); ?>" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
            </div>
            <div class="flex flex-col justify-end gap-2">
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 dark:bg-brand-700 dark:hover:bg-brand-600" type="submit">Apply Filters</button>
                <a class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700" href="<?php echo e(app_config()['base_url']); ?>/activity_logs.php">Reset All</a>
            </div>
        </div>
    </form>

    <div class="cf-table-wrap overflow-x-auto">
        <table class="cf-table min-w-[1000px] divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-gradient-to-r from-slate-50 to-slate-50/50 dark:from-slate-800/50 dark:to-slate-800/30">
                <tr>
                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-widest text-slate-600 dark:text-slate-400">Timestamp</th>
                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-widest text-slate-600 dark:text-slate-400">Actor</th>
                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-widest text-slate-600 dark:text-slate-400">Entity</th>
                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-widest text-slate-600 dark:text-slate-400">Action</th>
                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-widest text-slate-600 dark:text-slate-400">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                <?php foreach ($rows as $row): ?>
                    <?php
                        $entityType = (string)$row['entity_type'];
                        $actionType = (string)$row['action'];
                        $detailsText = activity_details_summary($entityType, $actionType, (string)($row['details'] ?? ''));
                    ?>
                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <td class="px-5 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-slate-700 dark:text-slate-300"><?php 
                                $timestamp = (string)$row['created_at'];
                                $date = new DateTime($timestamp);
                                echo htmlspecialchars($date->format('M d, Y'), ENT_QUOTES, 'UTF-8');
                            ?></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400"><?php 
                                echo htmlspecialchars($date->format('H:i:s'), ENT_QUOTES, 'UTF-8');
                            ?></div>
                        </td>
                        <td class="px-5 py-4">
                            <?php if ((int)($row['actor_id'] ?? 0) > 0): ?>
                                <div class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?php echo e((string)($row['actor_name'] ?? 'Unknown')); ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-500"><?php echo e((string)($row['actor_email'] ?? '')); ?></div>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">System</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?php echo e(entity_label($entityType)); ?></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">ID: <?php echo e((string)$row['entity_id']); ?></div>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap">
                            <?php 
                            $actionBadgeColor = match($actionType) {
                                'create' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                'update' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                'delete' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                default => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
                            };
                            ?>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?php echo $actionBadgeColor; ?>"><?php echo e(action_label($actionType)); ?></span>
                        </td>
                        <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                            <p class="line-clamp-2"><?php echo e($detailsText); ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400" colspan="5">
                            <p class="font-medium">No activity logs found</p>
                            <p class="text-xs mt-1">Try adjusting your filter criteria</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
