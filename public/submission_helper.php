<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('location_manager');

$businessId  = (int)($_GET['business_id']  ?? 0);
$directoryId = (int)($_GET['directory_id'] ?? 0);

if ($businessId <= 0 || $directoryId <= 0) {
    set_flash('err', 'Invalid request.');
    redirect('/businesses.php');
}

$businessStmt = db()->prepare('SELECT * FROM businesses WHERE id = ?');
$businessStmt->execute([$businessId]);
$business = $businessStmt->fetch();

$directoryStmt = db()->prepare('SELECT id, name, submission_url FROM directories WHERE id = ?');
$directoryStmt->execute([$directoryId]);
$directory = $directoryStmt->fetch();

if (!$business || !$directory) {
    set_flash('err', 'Business or directory not found.');
    redirect('/businesses.php');
}

$categories = trim((string)(($business['categories'] ?? '') !== '' ? $business['categories'] : ($business['category'] ?? '')));
$fullAddress = implode(', ', array_filter([
    trim((string)($business['address_line1'] ?? '')),
    trim((string)($business['city']          ?? '')),
    trim((string)($business['state']         ?? '')),
    trim((string)($business['postal_code']   ?? '')),
    trim((string)($business['country']       ?? '')),
]));

$sections = [
    'Core Information' => [
        ['label' => 'Business Name',     'value' => (string)($business['name']             ?? '')],
        ['label' => 'Phone',             'value' => (string)($business['phone']            ?? '')],
        ['label' => 'Website',           'value' => (string)($business['website']          ?? '')],
        ['label' => 'GBP Link',          'value' => (string)($business['gbp_link']         ?? '')],
        ['label' => 'Email',             'value' => (string)($business['email']            ?? '')],
        ['label' => 'Contact Name',      'value' => (string)($business['contact_name']     ?? '')],
        ['label' => 'In Business Since', 'value' => (string)($business['in_business_since'] ?? '')],
    ],
    'Address' => [
        ['label' => 'Address Line 1', 'value' => (string)($business['address_line1'] ?? '')],
        ['label' => 'City',           'value' => (string)($business['city']          ?? '')],
        ['label' => 'State',          'value' => (string)($business['state']         ?? '')],
        ['label' => 'Postal Code',    'value' => (string)($business['postal_code']   ?? '')],
        ['label' => 'Country',        'value' => (string)($business['country']       ?? '')],
        ['label' => 'Full Address',   'value' => $fullAddress],
    ],
    'Business Details' => [
        ['label' => 'Categories',     'value' => $categories],
        ['label' => 'Description',    'value' => (string)($business['description'] ?? '')],
        ['label' => 'Business Hours', 'value' => (string)($business['hours_json']  ?? '')],
    ],
    'Additional' => [
        ['label' => 'Payment Methods',   'value' => (string)($business['payment_methods']   ?? '')],
        ['label' => 'Social Media',      'value' => (string)($business['social_media']      ?? '')],
        ['label' => 'Login Credentials', 'value' => (string)($business['login_credentials'] ?? '')],
    ],
];

// Build "copy all" text server-side
$allLines = [];
foreach ($sections as $sectionTitle => $sectionFields) {
    $nonEmpty = array_filter($sectionFields, static fn (array $f): bool => $f['value'] !== '');
    if (!$nonEmpty) {
        continue;
    }
    $allLines[] = '=== ' . $sectionTitle . ' ===';
    foreach ($nonEmpty as $field) {
        $allLines[] = $field['label'] . ': ' . $field['value'];
    }
    $allLines[] = '';
}
$copyAllText = implode("\n", $allLines);

render_header('Submission Helper');
?>
<div class="mx-auto max-w-4xl space-y-5 pb-10">

    <div class="rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
        <a class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-slate-600 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white" href="<?php echo e(app_config()['base_url']); ?>/location_manager.php?business_id=<?php echo e((string)$businessId); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
            Back to Location Manager
        </a>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo e($business['name']); ?></h1>
                <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Submitting to: <span class="font-semibold text-slate-800 dark:text-white"><?php echo e($directory['name']); ?></span></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if (trim((string)($directory['submission_url'] ?? '')) !== ''): ?>
                    <a class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700" href="<?php echo e((string)$directory['submission_url']); ?>" target="_blank" rel="noopener noreferrer">
                        Open Submission Page
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                            <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                        </svg>
                    </a>
                <?php endif; ?>
                <button id="copy_all_btn" type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white/80 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white dark:border-slate-600 dark:bg-slate-800/70 dark:text-slate-100 dark:hover:bg-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/>
                        <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/>
                    </svg>
                    Copy All Fields
                </button>
            </div>
        </div>
    </div>

    <?php foreach ($sections as $sectionTitle => $sectionFields):
        $visibleFields = array_values(array_filter($sectionFields, static fn (array $f): bool => $f['value'] !== ''));
        if (!$visibleFields) {
            continue;
        }
    ?>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-700">
            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?php echo e($sectionTitle); ?></h2>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            <?php foreach ($visibleFields as $field): ?>
                <div class="flex items-start gap-4 px-5 py-3">
                    <div class="w-40 shrink-0 pt-0.5 text-xs font-semibold text-slate-500 dark:text-slate-400"><?php echo e($field['label']); ?></div>
                    <div class="min-w-0 flex-1 whitespace-pre-wrap break-words text-sm text-slate-800 dark:text-slate-200"><?php echo e($field['value']); ?></div>
                    <button
                        type="button"
                        class="copy-btn shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                        data-value="<?php echo e($field['value']); ?>"
                    >Copy</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
(function () {
    var copyAllText = <?php echo json_encode($copyAllText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function flashBtn(btn, msg) {
        var original = btn.textContent;
        btn.textContent = msg;
        btn.classList.add('bg-emerald-50', 'border-emerald-300', 'text-emerald-700', 'dark:bg-emerald-900/40', 'dark:border-emerald-700', 'dark:text-emerald-300');
        btn.classList.remove('text-slate-600', 'text-slate-700', 'hover:bg-slate-100', 'dark:text-slate-300', 'dark:hover:bg-slate-800');
        window.setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove('bg-emerald-50', 'border-emerald-300', 'text-emerald-700', 'dark:bg-emerald-900/40', 'dark:border-emerald-700', 'dark:text-emerald-300');
            btn.classList.add('text-slate-600', 'hover:bg-slate-100', 'dark:text-slate-300', 'dark:hover:bg-slate-800');
        }, 1800);
    }

    function copyText(text, callback) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(callback).catch(function () { fallbackCopy(text, callback); });
        } else {
            fallbackCopy(text, callback);
        }
    }

    function fallbackCopy(text, callback) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        if (callback) { callback(); }
    }

    document.querySelectorAll('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            copyText(btn.getAttribute('data-value') || '', function () { flashBtn(btn, 'Copied!'); });
        });
    });

    var copyAllBtn = document.getElementById('copy_all_btn');
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function () {
            copyText(copyAllText, function () { flashBtn(copyAllBtn, 'All Copied!'); });
        });
    }

}());
</script>
<?php render_footer(); ?>
