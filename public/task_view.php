<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('location_manager');

$user = current_user();
$taskId = (int)($_GET['id'] ?? 0);

if ($taskId <= 0) {
	set_flash('err', 'Invalid task ID.');
	redirect('/tasks.php');
}

$taskStmt = db()->prepare(
	"SELECT lt.*,
			b.name AS business_name,
			b.logo_path AS business_logo,
			b.city AS business_city,
			b.state AS business_state,
			d.name AS directory_name,
			d.logo_path AS directory_logo,
			u.full_name AS assigned_user_name
	 FROM listing_tasks lt
	 JOIN businesses b ON b.id = lt.business_id
	 JOIN directories d ON d.id = lt.directory_id
	 LEFT JOIN users u ON u.id = lt.assigned_to
	 WHERE lt.id = ?
	 LIMIT 1"
);
$taskStmt->execute([$taskId]);
$task = $taskStmt->fetch();

if (!$task) {
	set_flash('err', 'Task not found.');
	redirect('/tasks.php');
}

$isAdmin = is_admin();
$assignedTo = (int)($task['assigned_to'] ?? 0);
$viewerId = (int)($user['id'] ?? 0);
if (!$isAdmin && ($assignedTo <= 0 || $assignedTo !== $viewerId)) {
	set_flash('err', 'You are not allowed to view this task.');
	redirect('/tasks.php');
}

$status = strtolower(trim((string)($task['status'] ?? 'not_started')));
$priority = strtolower(trim((string)($task['priority_level'] ?? 'medium')));
$citationType = (string)($task['citation_type'] ?? '');
$submittedUrl = trim((string)($task['submitted_url'] ?? ''));
$notes = trim((string)($task['notes'] ?? ''));
$napStatus = (string)($task['nap_status'] ?? '');
$updatedAt = (string)($task['updated_at'] ?? '');
$createdAt = (string)($task['created_at'] ?? '');
$baseUrl = rtrim((string)(app_config()['base_url'] ?? ''), '/');

render_header('Task Details');
?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div>
			<h1 class="text-xl font-bold text-slate-900 dark:text-white">Task #<?php echo e((string)$taskId); ?></h1>
			<p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Detailed citation information and links.</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="<?php echo e($baseUrl); ?>/tasks.php" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Back to Tasks</a>
			<a href="<?php echo e($baseUrl); ?>/location_manager.php?business_id=<?php echo e((string)($task['business_id'] ?? 0)); ?>&open_citations=1" class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700">Open in Location Manager</a>
		</div>
	</div>
</section>

<section class="grid gap-4 lg:grid-cols-2">
	<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
		<h2 class="mb-3 text-base font-bold text-slate-900 dark:text-white">Business</h2>
		<div class="flex items-center gap-3">
			<?php $bizLogo = trim((string)($task['business_logo'] ?? '')); ?>
			<?php if ($bizLogo !== ''): ?>
				<img src="<?php echo e($baseUrl . '/' . ltrim($bizLogo, '/')); ?>" alt="Business logo" class="h-12 w-12 rounded-lg border border-slate-200 bg-white object-contain p-1 dark:border-slate-600" onerror="this.style.display='none'">
			<?php else: ?>
				<div class="flex h-12 w-12 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-xs font-bold text-slate-500 dark:border-slate-600 dark:bg-slate-800">
					<?php echo e(mb_strtoupper(mb_substr((string)($task['business_name'] ?? ''), 0, 2))); ?>
				</div>
			<?php endif; ?>
			<div>
				<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo e((string)($task['business_name'] ?? '')); ?></p>
				<p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e(trim((string)($task['business_city'] ?? '') . ', ' . (string)($task['business_state'] ?? ''), ' ,')); ?></p>
			</div>
		</div>
	</article>

	<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
		<h2 class="mb-3 text-base font-bold text-slate-900 dark:text-white">Directory</h2>
		<div class="flex items-center gap-3">
			<?php $dirLogo = trim((string)($task['directory_logo'] ?? '')); ?>
			<?php if ($dirLogo !== ''): ?>
				<img src="<?php echo e($baseUrl . '/' . ltrim($dirLogo, '/')); ?>" alt="Directory logo" class="h-12 w-12 rounded-lg border border-slate-200 bg-white object-contain p-1 dark:border-slate-600" onerror="this.style.display='none'">
			<?php else: ?>
				<div class="flex h-12 w-12 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-xs font-bold text-slate-500 dark:border-slate-600 dark:bg-slate-800">
					<?php echo e(mb_strtoupper(mb_substr((string)($task['directory_name'] ?? ''), 0, 2))); ?>
				</div>
			<?php endif; ?>
			<div>
				<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo e((string)($task['directory_name'] ?? '')); ?></p>
				<p class="text-xs text-slate-500 dark:text-slate-400">Citation target directory</p>
			</div>
		</div>
	</article>
</section>

<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
	<h2 class="mb-3 text-base font-bold text-slate-900 dark:text-white">Task Info</h2>
	<dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
		<div>
			<dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</dt>
			<dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e(status_label($status)); ?></dd>
		</div>
		<div>
			<dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Priority</dt>
			<dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e(status_label($priority)); ?></dd>
		</div>
		<div>
			<dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">NAP Status</dt>
			<dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e($napStatus !== '' ? status_label($napStatus) : 'N/A'); ?></dd>
		</div>
		<div>
			<dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Citation Type</dt>
			<dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e($citationType !== '' ? $citationType : 'N/A'); ?></dd>
		</div>
		<div>
			<dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Assigned To</dt>
			<dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e((string)($task['assigned_user_name'] ?? 'Unassigned')); ?></dd>
		</div>
		<div>
			<dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Last Updated</dt>
			<dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e($updatedAt !== '' ? $updatedAt : 'N/A'); ?></dd>
		</div>
	</dl>

	<div class="mt-4 grid gap-3 sm:grid-cols-2">
		<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
			<p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Submitted URL</p>
			<?php if ($submittedUrl !== ''): ?>
				<a href="<?php echo e($submittedUrl); ?>" target="_blank" rel="noopener noreferrer" class="mt-2 block break-all text-sm font-semibold text-brand-700 hover:underline dark:text-brand-400"><?php echo e($submittedUrl); ?></a>
			<?php else: ?>
				<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No submitted URL yet.</p>
			<?php endif; ?>
		</div>
		<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
			<p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Created At</p>
			<p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white"><?php echo e($createdAt !== '' ? $createdAt : 'N/A'); ?></p>
		</div>
	</div>

	<?php if ($notes !== ''): ?>
		<div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
			<p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Notes</p>
			<p class="mt-2 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-200"><?php echo e($notes); ?></p>
		</div>
	<?php endif; ?>
</section>
<?php render_footer(); ?>
