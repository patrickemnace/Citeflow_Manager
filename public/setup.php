<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$errors = [];
$done = false;
$name = '';
$email = '';

$hasUsers = false;
if (table_exists('users')) {
    $hasUsers = (int)db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'] > 0;
}
if ($hasUsers) {
    set_flash('err', 'Setup is locked. User accounts can only be managed by an admin.');
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = post('full_name');
    $email = strtolower(post('email'));
    $password = post('password');

    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        try {
            $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
            db()->exec((string)$sql);

            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Admin user already exists for this email.';
            } else {
                $insert = db()->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, "admin")');
                $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $done = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Setup failed: ' . $e->getMessage();
        }
    }
}

render_header('Setup');
?>
<section class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-6 shadow-sm sm:p-8 dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Setup</h1>
    <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Run once to initialize tables and create your admin account.</p>

    <?php if ($errors): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?php echo e(implode(' ', $errors)); ?></div>
    <?php endif; ?>

    <?php if ($done): ?>
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Setup complete. You can now log in.</div>
        <a class="mt-4 inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" href="<?php echo e(app_config()['base_url']); ?>/"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>Go to Login</a>
    <?php else: ?>
        <form method="post" class="mt-6 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Admin name</label>
                    <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" type="text" name="full_name" value="<?php echo e($name); ?>" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Admin email</label>
                    <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" type="email" name="email" value="<?php echo e($email); ?>" required>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Password</label>
                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" type="password" name="password" required minlength="8">
            </div>
            <button class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700" type="submit"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h7l-1 8 10-12h-7z"/></svg>Initialize System</button>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
