<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('location_manager');

$user    = current_user();
$baseUrl = rtrim((string)(app_config()['base_url'] ?? ''), '/');

$taskStmt = db()->prepare("SELECT lt.id,
 lt.business_id,
 lt.directory_id,
 lt.status,
 lt.priority_level,
 lt.citation_type,
 lt.submitted_url,
 lt.updated_at,
 b.name        AS business_name,
 b.logo_path   AS business_logo,
 b.city,
 b.state,
 d.name        AS directory_name,
 d.logo_path   AS directory_logo,
 (SELECT COUNT(*) FROM client_citation_comments ccc
  WHERE ccc.listing_task_id = lt.id) AS comment_count
  FROM listing_tasks lt
  JOIN businesses b  ON b.id = lt.business_id
  JOIN directories d ON d.id = lt.directory_id
  WHERE lt.assigned_to = ?
  ORDER BY b.name ASC, lt.updated_at DESC, lt.id DESC");
$taskStmt->execute([(int)($user['id'] ?? 0)]);
$tasks = $taskStmt->fetchAll();

$statusCounts    = [];
$totalComments   = 0;
$tasksByBusiness = [];
foreach ($tasks as $task) {
$statusKey = strtolower(trim((string)($task['status'] ?? 'not_started')));
$statusCounts[$statusKey] = ($statusCounts[$statusKey] ?? 0) + 1;
$totalComments += (int)($task['comment_count'] ?? 0);

$businessId = (int)($task['business_id'] ?? 0);
if (!isset($tasksByBusiness[$businessId])) {
$tasksByBusiness[$businessId] = [
'business_id'   => $businessId,
'business_name' => (string)($task['business_name'] ?? ''),
'business_logo' => (string)($task['business_logo'] ?? ''),
'city'          => (string)($task['city'] ?? ''),
'state'         => (string)($task['state'] ?? ''),
'tasks'         => [],
];
}

$tasksByBusiness[$businessId]['tasks'][] = $task;
}

$taskModalPayload = [];
foreach ($tasks as $task) {
$taskModalPayload[] = [
'id' => (int)($task['id'] ?? 0),
'status' => strtolower(trim((string)($task['status'] ?? 'not_started'))),
'priority' => strtolower(trim((string)($task['priority_level'] ?? 'medium'))),
'citation_type' => (string)($task['citation_type'] ?? ''),
'updated_at' => (string)($task['updated_at'] ?? ''),
'submitted_url' => (string)($task['submitted_url'] ?? ''),
'comment_count' => (int)($task['comment_count'] ?? 0),
'business_id' => (int)($task['business_id'] ?? 0),
'business_name' => (string)($task['business_name'] ?? ''),
'business_logo' => (string)($task['business_logo'] ?? ''),
'directory_name' => (string)($task['directory_name'] ?? ''),
'directory_logo' => (string)($task['directory_logo'] ?? ''),
];
}

$statusTone = [
'not_started' => 'bg-slate-100 text-slate-700',
'in_progress'  => 'bg-blue-100 text-blue-700',
'submitted'    => 'bg-violet-100 text-violet-700',
'live'         => 'bg-emerald-100 text-emerald-700',
'needs_edit'   => 'bg-amber-100 text-amber-700',
'rejected'     => 'bg-rose-100 text-rose-700',
];

$priorityTone = [
'low'    => 'bg-slate-100 text-slate-700',
'medium' => 'bg-sky-100 text-sky-700',
'high'   => 'bg-rose-100 text-rose-700',
];

render_header('My Assigned Citations');
?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
<div class="flex flex-wrap items-center justify-between gap-3">
<div>
<h1 class="text-xl font-bold text-slate-900 dark:text-white">My Assigned Citations</h1>
<p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Your current citation workload, grouped in one employee-friendly view.</p>
</div>
<button type="button"
   data-metric-card="1"
   data-metric-key="all"
   data-metric-label="All Assigned Citations"
   class="inline-flex rounded-xl border border-slate-200 bg-white/80 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-brand-50 hover:border-brand-300 transition dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100">
Total Assigned: <?php echo e((string)count($tasks)); ?>
</button>
</div>

<?php if ($tasks): ?>
<div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
<?php foreach ($statusCounts as $statusKey => $count): ?>
<button type="button"
   data-metric-card="1"
   data-metric-key="status:<?php echo e($statusKey); ?>"
   data-metric-label="<?php echo e(status_label($statusKey)); ?> Citations"
   class="group block rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-brand-300 hover:bg-brand-50 dark:border-slate-700 dark:bg-slate-800/70 dark:hover:border-brand-600 dark:hover:bg-brand-950/30">
<p class="text-xs font-bold uppercase tracking-wide text-slate-500 group-hover:text-brand-600 dark:text-slate-400"><?php echo e(status_label($statusKey)); ?></p>
<p class="mt-1 text-2xl font-extrabold text-slate-900 group-hover:text-brand-700 dark:text-white"><?php echo e((string)$count); ?></p>
<p class="mt-0.5 text-xs text-slate-400 group-hover:text-brand-500 dark:text-slate-500">Open details modal</p>
</button>
<?php endforeach; ?>
<button type="button"
   data-metric-card="1"
   data-metric-key="comments"
   data-metric-label="Citations With Client Comments"
   class="group block rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 transition hover:border-sky-400 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:hover:border-sky-600">
<p class="text-xs font-bold uppercase tracking-wide text-sky-600 dark:text-sky-400">Client Comments</p>
<p class="mt-1 text-2xl font-extrabold text-sky-700 dark:text-sky-300"><?php echo e((string)$totalComments); ?></p>
<p class="mt-0.5 text-xs text-sky-400 group-hover:text-sky-600 dark:text-sky-500">Open details modal</p>
</button>
</div>
<?php endif; ?>
</section>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
<div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
<h2 class="text-lg font-bold text-slate-900 dark:text-white">Assigned Queue by Business</h2>
<p class="text-sm text-slate-500 dark:text-slate-300">Citations are grouped per business for easier tracking.</p>
</div>

<?php if ($tasks): ?>
<div class="divide-y divide-slate-100 dark:divide-slate-800">
<?php foreach ($tasksByBusiness as $businessGroup): ?>
<?php
$businessLocation = trim((string)$businessGroup['city'] . ', ' . (string)$businessGroup['state'], ' ,');
$businessTasks    = $businessGroup['tasks'];
$collapseId       = 'business_citations_' . (int)$businessGroup['business_id'];
$bizLogo          = trim((string)$businessGroup['business_logo']);
?>
<article class="px-5 py-4">
<div class="mb-3 flex flex-wrap items-start justify-between gap-3">
<div class="flex min-w-0 items-center gap-3">
<?php if ($bizLogo !== ''): ?>
<img src="<?php echo e($baseUrl . '/' . ltrim($bizLogo, '/')); ?>"
     alt="<?php echo e((string)$businessGroup['business_name']); ?> logo"
     class="h-10 w-10 flex-shrink-0 rounded-lg border border-slate-200 object-contain bg-white p-0.5 dark:border-slate-600"
     onerror="this.style.display='none'">
<?php else: ?>
<div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-xs font-bold text-slate-500 dark:border-slate-600 dark:bg-slate-800">
<?php echo e(mb_strtoupper(mb_substr((string)$businessGroup['business_name'], 0, 2))); ?>
</div>
<?php endif; ?>
<div class="min-w-0">
<a href="<?php echo e($baseUrl); ?>/businesses.php?id=<?php echo e((string)$businessGroup['business_id']); ?>"
   class="text-base font-bold text-slate-900 hover:text-brand-600 hover:underline dark:text-white dark:hover:text-brand-400">
<?php echo e((string)$businessGroup['business_name']); ?>
</a>
<p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
<?php echo e($businessLocation !== '' ? $businessLocation : 'Location not set'); ?> &middot; <?php echo e((string)count($businessTasks)); ?> citation(s)
</p>
</div>
</div>
<div class="flex items-center gap-2">
<button type="button"
        class="business-collapse-toggle inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
        data-target-id="<?php echo e($collapseId); ?>"
        aria-expanded="true">
<svg xmlns="http://www.w3.org/2000/svg" class="collapse-chevron h-3.5 w-3.5 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
</svg>
<span class="collapse-label">Hide citations</span>
</button>
<a class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700"
   href="<?php echo e($baseUrl); ?>/location_manager.php?business_id=<?php echo e((string)$businessGroup['business_id']); ?>&open_citations=1">Open Location Manager</a>
</div>
</div>

<div id="<?php echo e($collapseId); ?>" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
<?php foreach ($businessTasks as $task): ?>
<?php
$statusKey    = strtolower(trim((string)($task['status'] ?? 'not_started')));
$priorityKey  = strtolower(trim((string)($task['priority_level'] ?? 'medium')));
$dirLogo      = trim((string)($task['directory_logo'] ?? ''));
$commentCount = (int)($task['comment_count'] ?? 0);
?>
<div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900">
<div class="flex flex-wrap items-start justify-between gap-2">
<div class="min-w-0 flex-1">
<div class="flex flex-wrap items-center gap-2">
<?php if ($dirLogo !== ''): ?>
<img src="<?php echo e($baseUrl . '/' . ltrim($dirLogo, '/')); ?>"
     alt="<?php echo e((string)($task['directory_name'] ?? '')); ?> logo"
     class="h-5 w-5 flex-shrink-0 rounded object-contain"
     onerror="this.style.display='none'">
<?php endif; ?>
<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo e((string)($task['directory_name'] ?? '')); ?></p>
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?php echo e($statusTone[$statusKey] ?? 'bg-slate-100 text-slate-700'); ?>"><?php echo e(status_label($statusKey)); ?></span>
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold <?php echo e($priorityTone[$priorityKey] ?? 'bg-slate-100 text-slate-700'); ?>"><?php echo e(status_label($priorityKey)); ?> Priority</span>
<?php if ($commentCount > 0): ?>
<a href="<?php echo e($baseUrl); ?>/client_citation_comments.php?task_id=<?php echo e((string)$task['id']); ?>"
   class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-700 hover:bg-sky-200 dark:bg-sky-900/50 dark:text-sky-300 dark:hover:bg-sky-800/60">
<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
<path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
</svg>
<?php echo e((string)$commentCount); ?> comment<?php echo $commentCount !== 1 ? 's' : ''; ?>
</a>
<?php endif; ?>
</div>
<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Type: <?php echo e((string)($task['citation_type'] ?? '')); ?> &middot; Updated: <?php echo e((string)($task['updated_at'] ?? '')); ?></p>
</div>
<div class="flex items-center gap-2">
<?php if (trim((string)($task['submitted_url'] ?? '')) !== ''): ?>
<a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
   href="<?php echo e((string)$task['submitted_url']); ?>" target="_blank" rel="noopener noreferrer">Open Citation URL</a>
<?php endif; ?>
<a href="<?php echo e($baseUrl); ?>/task_view.php?id=<?php echo e((string)$task['id']); ?>"
   class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-950/30">View Task</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</article>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="px-5 py-8 text-sm text-slate-500 dark:text-slate-400">No citations are currently assigned to you.</div>
<?php endif; ?>
</section>

<div id="metric_details_modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-4" role="dialog" aria-modal="true" aria-labelledby="metric_modal_title">
<div class="w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
<div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
<h3 id="metric_modal_title" class="text-lg font-bold text-slate-900 dark:text-white">Citation Details</h3>
<button type="button" id="metric_modal_close" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Close</button>
</div>
<div class="max-h-[70vh] overflow-auto p-4">
<div id="metric_modal_empty" class="hidden rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-300">No citations found for this metric.</div>
<div id="metric_modal_list" class="space-y-2"></div>
</div>
</div>
</div>
<script>
const modalTasks = <?php echo json_encode($taskModalPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

const metricModal = document.getElementById('metric_details_modal');
const metricModalTitle = document.getElementById('metric_modal_title');
const metricModalClose = document.getElementById('metric_modal_close');
const metricModalList = document.getElementById('metric_modal_list');
const metricModalEmpty = document.getElementById('metric_modal_empty');

function normalizeAssetPath(path) {
if (!path) {
return '';
}
if (path.startsWith('http://') || path.startsWith('https://')) {
return path;
}
return `${baseUrl}/${String(path).replace(/^\/+/, '')}`;
}

function filterTasksByMetric(metricKey) {
if (metricKey === 'all') {
return modalTasks;
}
if (metricKey === 'comments') {
return modalTasks.filter((task) => Number(task.comment_count || 0) > 0);
}
if (metricKey.startsWith('status:')) {
const status = metricKey.slice('status:'.length);
return modalTasks.filter((task) => String(task.status || '') === status);
}
return [];
}

function renderMetricRows(rows) {
metricModalList.innerHTML = '';
if (!rows.length) {
metricModalEmpty.classList.remove('hidden');
return;
}

metricModalEmpty.classList.add('hidden');
rows.forEach((task) => {
const businessLogo = normalizeAssetPath(task.business_logo || '');
const directoryLogo = normalizeAssetPath(task.directory_logo || '');
const hasComments = Number(task.comment_count || 0) > 0;

const item = document.createElement('article');
item.className = 'rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70';
item.innerHTML = `
<div class="flex flex-wrap items-start justify-between gap-3">
<div class="min-w-0 flex-1 space-y-2">
<div class="flex flex-wrap items-center gap-2">
${businessLogo ? `<img src="${businessLogo}" alt="${String(task.business_name || '').replace(/"/g, '&quot;')} logo" class="h-7 w-7 rounded-md border border-slate-200 bg-white object-contain p-0.5">` : '<div class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-slate-100 text-[10px] font-bold text-slate-500">B</div>'}
<p class="truncate text-sm font-semibold text-slate-900 dark:text-white">${String(task.business_name || '')}</p>
</div>
<div class="flex flex-wrap items-center gap-2">
${directoryLogo ? `<img src="${directoryLogo}" alt="${String(task.directory_name || '').replace(/"/g, '&quot;')} logo" class="h-5 w-5 rounded object-contain">` : ''}
<span class="text-xs font-medium text-slate-700 dark:text-slate-200">${String(task.directory_name || '')}</span>
<span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">${String(task.status || '').replace(/_/g, ' ')}</span>
<span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">${String(task.priority || '')} priority</span>
${hasComments ? `<span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-700 dark:bg-sky-900/50 dark:text-sky-300">${Number(task.comment_count)} comment(s)</span>` : ''}
</div>
<p class="text-xs text-slate-500 dark:text-slate-400">Type: ${String(task.citation_type || '')} | Updated: ${String(task.updated_at || '')}</p>
</div>
<div class="flex items-center gap-2">
${task.submitted_url ? `<a class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" href="${String(task.submitted_url)}" target="_blank" rel="noopener noreferrer">Citation URL</a>` : ''}
<a class="rounded-lg border border-brand-300 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-950/30" href="${baseUrl}/task_view.php?id=${Number(task.id || 0)}">View Task</a>
</div>
</div>
`;

metricModalList.appendChild(item);
});
}

document.querySelectorAll('[data-metric-card="1"]').forEach((card) => {
card.addEventListener('click', () => {
const metricKey = String(card.getAttribute('data-metric-key') || 'all');
const metricLabel = String(card.getAttribute('data-metric-label') || 'Citation Details');
const rows = filterTasksByMetric(metricKey);
metricModalTitle.textContent = `${metricLabel} (${rows.length})`;
renderMetricRows(rows);
metricModal.classList.remove('hidden');
metricModal.classList.add('flex');
});
});

function closeMetricModal() {
metricModal.classList.add('hidden');
metricModal.classList.remove('flex');
}

if (metricModalClose) {
metricModalClose.addEventListener('click', closeMetricModal);
}

if (metricModal) {
metricModal.addEventListener('click', (event) => {
if (event.target === metricModal) {
closeMetricModal();
}
});
}

document.addEventListener('keydown', (event) => {
if (event.key === 'Escape' && metricModal && !metricModal.classList.contains('hidden')) {
closeMetricModal();
}
});

document.querySelectorAll('.business-collapse-toggle').forEach((button) => {
button.addEventListener('click', () => {
const targetId = button.getAttribute('data-target-id') || '';
const target = targetId ? document.getElementById(targetId) : null;
if (!target) {
return;
}

const isHidden = target.classList.toggle('hidden');
button.setAttribute('aria-expanded', isHidden ? 'false' : 'true');

const label = button.querySelector('.collapse-label');
if (label) {
label.textContent = isHidden ? 'Show citations' : 'Hide citations';
}

const chevron = button.querySelector('.collapse-chevron');
if (chevron) {
chevron.classList.toggle('rotate-180', isHidden);
}
});
});
</script>
<?php render_footer(); ?>
