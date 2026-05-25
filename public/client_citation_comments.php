<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
$user = current_user();

if (!is_admin() && !has_permission('location_manager')) {
    set_flash('err', 'You are not authorized to access this page.');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'mark_comment_updated') {
    $commentId = (int)($_POST['comment_id'] ?? 0);

    if ($commentId <= 0) {
        set_flash('err', 'Invalid comment selection.');
        redirect('/client_citation_comments.php');
    }

    $accessSql = "SELECT ccc.id
                  FROM client_citation_comments ccc
                  INNER JOIN listing_tasks lt ON lt.id = ccc.listing_task_id
                  WHERE ccc.id = ?";
    $accessParams = [$commentId];

    if (!is_admin()) {
        $accessSql .= ' AND lt.assigned_to = ?';
        $accessParams[] = (int)($user['id'] ?? 0);
    }

    $accessStmt = db()->prepare($accessSql . ' LIMIT 1');
    $accessStmt->execute($accessParams);
    if (!$accessStmt->fetch()) {
        set_flash('err', 'You are not allowed to update this comment.');
        redirect('/client_citation_comments.php');
    }

    db()->prepare('UPDATE client_citation_comments
                   SET resolution_status = "updated", resolved_by = ?, resolved_at = NOW(), updated_at = NOW()
                   WHERE id = ?')
        ->execute([(int)($user['id'] ?? 0), $commentId]);

    set_flash('ok', 'Comment marked as updated.');

    $redirectUrl = app_config()['base_url'] . '/client_citation_comments.php';
    $redirectQuery = trim((string)($_POST['return_query'] ?? ''));
    if ($redirectQuery !== '') {
        $redirectUrl .= '?' . ltrim($redirectQuery, '?');
    }
    redirect($redirectUrl);
}

$search = trim((string)($_GET['q'] ?? ''));
$rowsLimit = max(25, min(300, (int)($_GET['limit'] ?? 100)));

$sql = "SELECT ccc.id,
               ccc.comment_text,
               ccc.created_at,
               ccc.updated_at,
           ccc.resolution_status,
           ccc.resolved_at,
           ccc.resolved_by,
               c.id AS client_id,
               c.name AS client_name,
               b.id AS business_id,
               b.name AS business_name,
           b.logo_path AS business_logo,
               d.name AS directory_name,
           d.logo_path AS directory_logo,
               lt.id AS listing_task_id,
               lt.status AS citation_status,
               lt.assigned_to,
           u.full_name AS assigned_to_name,
           ru.full_name AS resolved_by_name
        FROM client_citation_comments ccc
        INNER JOIN listing_tasks lt ON lt.id = ccc.listing_task_id
        INNER JOIN businesses b ON b.id = lt.business_id
        INNER JOIN clients c ON c.id = ccc.client_id
        INNER JOIN directories d ON d.id = lt.directory_id
        LEFT JOIN users u ON u.id = lt.assigned_to
    LEFT JOIN users ru ON ru.id = ccc.resolved_by
        WHERE 1 = 1";
$params = [];

if (!is_admin()) {
    $sql .= ' AND lt.assigned_to = ?';
    $params[] = (int)($user['id'] ?? 0);
}

if ($search !== '') {
    $sql .= ' AND (
        c.name LIKE ?
        OR b.name LIKE ?
        OR d.name LIKE ?
        OR ccc.comment_text LIKE ?
    )';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= ' ORDER BY lt.id DESC, ccc.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$allCommentRows = $stmt->fetchAll();

// Group comments by listing_task_id
$groupedComments = [];
$taskSummary = [];
foreach ($allCommentRows as $commentRow) {
    $taskId = (int)($commentRow['listing_task_id'] ?? 0);
    if (!isset($groupedComments[$taskId])) {
        $groupedComments[$taskId] = [];
        $taskSummary[$taskId] = [
            'client_name' => (string)($commentRow['client_name'] ?? ''),
            'client_id' => (int)($commentRow['client_id'] ?? 0),
            'business_name' => (string)($commentRow['business_name'] ?? ''),
            'business_id' => (int)($commentRow['business_id'] ?? 0),
            'business_logo' => (string)($commentRow['business_logo'] ?? ''),
            'directory_name' => (string)($commentRow['directory_name'] ?? ''),
            'directory_logo' => (string)($commentRow['directory_logo'] ?? ''),
            'citation_status' => (string)($commentRow['citation_status'] ?? ''),
            'assigned_to_name' => (string)($commentRow['assigned_to_name'] ?? ''),
            'first_comment_at' => (string)($commentRow['created_at'] ?? ''),
        ];
    }
    $groupedComments[$taskId][] = $commentRow;
}

// Apply row limit by task count
$taskIds = array_keys($groupedComments);
if (count($taskIds) > $rowsLimit) {
    $taskIds = array_slice($taskIds, 0, $rowsLimit);
    $groupedComments = array_intersect_key($groupedComments, array_flip($taskIds));
    $taskSummary = array_intersect_key($taskSummary, array_flip($taskIds));
}

$updatedCommentsCount = 0;
$pendingCommentsCount = 0;
foreach ($allCommentRows as $commentRow) {
    $status = strtolower(trim((string)($commentRow['resolution_status'] ?? 'pending')));
    if ($status === 'updated') {
        $updatedCommentsCount++;
    } else {
        $pendingCommentsCount++;
    }
}

$returnQuery = http_build_query([
    'q' => $search,
    'limit' => $rowsLimit,
]);

render_header('Client Citation Comments');
?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/80">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-slate-100">Client Citation Comments</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                <?php if (is_admin()): ?>
                    Showing all comments submitted by clients across citations.
                <?php else: ?>
                    Showing client comments only for citations assigned to you.
                <?php endif; ?>
            </p>
        </div>
        <form method="get" class="flex flex-wrap items-center gap-2">
            <input
                type="search"
                name="q"
                value="<?php echo e($search); ?>"
                placeholder="Search client, business, directory, or comment..."
                class="w-[21rem] max-w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-400"
            >
            <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700">Search</button>
            <?php if ($search !== ''): ?>
                <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" href="<?php echo e(app_config()['base_url']); ?>/client_citation_comments.php">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/80">
    <div class="mb-3 flex items-center justify-between gap-2">
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Total visible comments: <?php echo e((string)count($allCommentRows)); ?> · Tasks with comments: <?php echo e((string)count($groupedComments)); ?></p>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Pending: <?php echo e((string)$pendingCommentsCount); ?>
            </span>
            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Updated: <?php echo e((string)$updatedCommentsCount); ?>
            </span>
        </div>
    </div>

    <div class="cf-table-wrap overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="cf-table w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300 hidden sm:table-cell">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300 hidden md:table-cell">Business</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300 hidden lg:table-cell">Directory</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">Citation</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300 hidden md:table-cell">Assigned Employee</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">Comments</th>
                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-300">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900/30">
                <?php foreach ($groupedComments as $taskId => $comments): ?>
                    <?php
                        $summary = $taskSummary[$taskId];
                        $bizLogo = trim((string)($summary['business_logo'] ?? ''));
                        $dirLogo = trim((string)($summary['directory_logo'] ?? ''));
                        
                        // Count pending vs updated
                        $taskPendingCount = 0;
                        $taskUpdatedCount = 0;
                        foreach ($comments as $comment) {
                            $status = strtolower(trim((string)($comment['resolution_status'] ?? 'pending')));
                            if ($status === 'updated') {
                                $taskUpdatedCount++;
                            } else {
                                $taskPendingCount++;
                            }
                        }
                        
                        // Row background tone
                        $hasPending = $taskPendingCount > 0;
                        $rowToneClass = $hasPending
                            ? 'odd:bg-amber-50/35 even:bg-amber-50/55 dark:odd:bg-amber-900/20 dark:even:bg-amber-900/35'
                            : 'odd:bg-emerald-50/45 even:bg-emerald-50/70 dark:odd:bg-emerald-900/20 dark:even:bg-emerald-900/35';
                    ?>
                    <tr class="<?php echo e($rowToneClass); ?>">
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap"><?php echo e((string)$summary['first_comment_at']); ?></td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800 dark:text-slate-100 hidden sm:table-cell"><?php echo e((string)$summary['client_name']); ?></td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hidden md:table-cell">
                            <div class="flex items-center gap-2">
                                <?php if ($bizLogo !== ''): ?>
                                    <img class="h-7 w-7 shrink-0 rounded-md border border-slate-200 object-cover dark:border-slate-600" src="<?php echo e(public_asset_url($bizLogo)); ?>" alt="<?php echo e((string)$summary['business_name']); ?> logo">
                                <?php else: ?>
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-100 text-[11px] font-bold uppercase text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        <?php echo e(substr((string)$summary['business_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="hidden lg:inline"><?php echo e((string)$summary['business_name']); ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hidden lg:table-cell">
                            <div class="flex items-center gap-2">
                                <?php if ($dirLogo !== ''): ?>
                                    <img class="h-7 w-7 shrink-0 rounded-md border border-slate-200 object-cover dark:border-slate-600" src="<?php echo e(public_asset_url($dirLogo)); ?>" alt="<?php echo e((string)$summary['directory_name']); ?> logo">
                                <?php else: ?>
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-100 text-[11px] font-bold uppercase text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        <?php echo e(substr((string)$summary['directory_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span><?php echo e((string)$summary['directory_name']); ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                            <div class="inline-block">
                                <p class="font-semibold text-slate-800 dark:text-slate-100">#<?php echo e((string)(int)$taskId); ?></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e(status_label((string)($summary['citation_status'] ?? ''))); ?></p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200 hidden md:table-cell"><?php echo e((string)($summary['assigned_to_name'] ?: 'Unassigned')); ?></td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition dark:border-sky-700 dark:bg-sky-900/30 dark:text-sky-200 dark:hover:bg-sky-900/45 comment-modal-trigger" data-task-id="<?php echo e((string)$taskId); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3.196 12.87c-.263.26-.36.64-.27 1.01.112.53.567.9 1.07.9.05 0 .1-.002.15-.01l.113-.013A4.972 4.972 0 0 0 10 15c4.418 0 8-1.79 8-4s-3.582-4-8-4-8 1.79-8 4c0 .364.074.718.208 1.05a2.001 2.001 0 0 1 .596 1.82Z" /></svg>
                                <?php echo e((string)count($comments)); ?> comment<?php echo count($comments) !== 1 ? 's' : ''; ?>
                            </button>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200 text-right whitespace-nowrap">
                            <div class="flex flex-col items-end gap-1">
                                <div class="flex items-center gap-1.5">
                                    <?php if ($taskPendingCount > 0): ?>
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Pending: <?php echo e((string)$taskPendingCount); ?></span>
                                    <?php endif; ?>
                                    <?php if ($taskUpdatedCount > 0): ?>
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.75 7.812a1 1 0 0 1-1.42.006L3.29 10.329a1 1 0 1 1 1.42-1.408l3.54 3.57 7.04-7.095a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>
                                            <?php echo e((string)$taskUpdatedCount); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$groupedComments): ?>
                    <tr>
                        <td class="px-4 py-4 text-sm text-slate-500 dark:text-slate-300" colspan="8">No client comments found for the current filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Comments Detail Modal -->
<div id="commentsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 opacity-0 pointer-events-none transition-opacity duration-200" aria-hidden="true">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-y-auto dark:bg-slate-900 dark:ring-1 dark:ring-slate-700">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-5 py-4 flex items-center justify-between gap-3 dark:bg-slate-900 dark:border-slate-700">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Task #<span id="modalTaskId"></span> Comments</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-300">All comments submitted for this citation</p>
            </div>
            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" id="closeCommentsModal" aria-label="Close modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>
        </div>
        <div id="commentsModalContent" class="p-5 space-y-4"></div>
    </div>
</div>

<script>
(() => {
    const commentsData = <?php echo json_encode($groupedComments, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const modal = document.getElementById('commentsModal');
    const modalTaskId = document.getElementById('modalTaskId');
    const modalContent = document.getElementById('commentsModalContent');
    const closeBtn = document.getElementById('closeCommentsModal');
    const triggers = document.querySelectorAll('.comment-modal-trigger');

    const openModal = (taskId) => {
        const comments = commentsData[taskId] || [];
        modalTaskId.textContent = String(taskId);
        modalContent.innerHTML = '';

        comments.forEach((comment) => {
            const isUpdated = (comment.resolution_status || 'pending').toLowerCase() === 'updated';
            const commentBox = document.createElement('div');
            commentBox.className = isUpdated
                ? 'rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-700 dark:bg-emerald-900/25'
                : 'rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/25';

            let statusHtml = '';
            let actionHtml = '';
            
            if (isUpdated) {
                statusHtml = `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.75 7.812a1 1 0 0 1-1.42.006L3.29 10.329a1 1 0 1 1 1.42-1.408l3.54 3.57 7.04-7.095a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>
                    Updated
                </span>`;
                if (comment.resolved_by_name) {
                    statusHtml += `<p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">by ${comment.resolved_by_name}${comment.resolved_at ? ' · ' + comment.resolved_at : ''}</p>`;
                }
            } else {
                statusHtml = `<span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Pending</span>`;
                // Add Mark Updated button for pending comments
                actionHtml = `
                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="mark_comment_updated">
                        <input type="hidden" name="comment_id" value="${comment.id}">
                        <input type="hidden" name="return_query" value="">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100 transition dark:border-sky-700 dark:bg-sky-900/30 dark:text-sky-200 dark:hover:bg-sky-900/45">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 0 1 1 1v1.06a6.002 6.002 0 0 1 4.94 4.94H17a1 1 0 1 1 0 2h-1.06a6.002 6.002 0 0 1-4.94 4.94V17a1 1 0 1 1-2 0v-1.06a6.002 6.002 0 0 1-4.94-4.94H3a1 1 0 1 1 0-2h1.06A6.002 6.002 0 0 1 9 4.06V3a1 1 0 0 1 1-1Zm0 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" /></svg>
                            Mark Updated
                        </button>
                    </form>
                `;
            }

            commentBox.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-2">
                    <p class="text-xs font-semibold ${isUpdated ? 'text-emerald-900 dark:text-emerald-200' : 'text-amber-900 dark:text-amber-200'}">Comment on ${comment.created_at}</p>
                    ${statusHtml}
                </div>
                <p class="text-sm ${isUpdated ? 'text-emerald-900 dark:text-emerald-100' : 'text-amber-900 dark:text-amber-100'} whitespace-pre-wrap break-words">${comment.comment_text}</p>
                ${actionHtml}
            `;
            modalContent.appendChild(commentBox);
        });

        if (comments.length === 0) {
            modalContent.innerHTML = '<p class="text-sm text-slate-500 dark:text-slate-300 py-4">No comments found for this task.</p>';
        }

        modal.classList.remove('opacity-0', 'pointer-events-none');
    };

    const closeModal = () => {
        modal.classList.add('opacity-0', 'pointer-events-none');
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const taskId = parseInt(trigger.getAttribute('data-task-id'), 10);
            openModal(taskId);
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
