<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('audits');

render_header('Audits');
?>
<section class="rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-6 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Audit Module</h1>
    <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Audit date tracking fields were removed from citation workflows.</p>
    <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Use <a class="text-brand-700 hover:underline dark:text-brand-300" href="<?php echo e(app_config()['base_url']); ?>/location_manager.php">Location Manager</a> for active citation management.</p>
</section>
<?php render_footer(); ?>
