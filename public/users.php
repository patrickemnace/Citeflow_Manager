<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin();

$pendingDeleteUserId = 0;

$action = post('action');
$featureOptions = [
    'dashboard' => 'Dashboard',
    'businesses' => 'Business Manager',
    'location_manager' => 'Location Manager / My Assigned Citations',
    'notifications' => 'Notifications (Bell Menu)',
    'directories' => 'Directories',
];

$defaultPermissions = default_employee_permissions();

function normalize_permissions(array $permissions, array $featureOptions): array
{
    $allowed = array_keys($featureOptions);
    $filtered = array_values(array_filter($permissions, static function ($p) use ($allowed): bool {
        return in_array((string)$p, $allowed, true);
    }));

    if (!$filtered) {
        return default_employee_permissions();
    }

    if (in_array('notifications', $allowed, true) && !in_array('notifications', $filtered, true)) {
        $filtered[] = 'notifications';
    }

    return $filtered;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_user') {
    $fullName = trim(post('full_name'));
    $email = strtolower(trim(post('email')));
    $password = post('password');
    $role = post('role', 'employee');
    $designation = post('designation', '');
    $isActive = (int)post('is_active', '1');
    $permissions = isset($_POST['permissions']) && is_array($_POST['permissions'])
        ? $_POST['permissions']
        : [];

    if ($fullName === '' || $email === '' || $password === '') {
        set_flash('err', 'Name, email, and password are required.');
        redirect('/users.php');
    }

    if (strlen($password) < 8) {
        set_flash('err', 'Password must be at least 8 characters.');
        redirect('/users.php');
    }

    if (!in_array($role, ['admin', 'employee'], true)) {
        $role = 'employee';
    }

    $normalizedPermissions = $role === 'admin'
        ? ['*']
        : normalize_permissions($permissions, $featureOptions);

    $uploadError = null;
    $uploadedImagePath = save_uploaded_image('image', 'user_avatar', $uploadError);
    if ($uploadError !== null) {
        set_flash('err', $uploadError);
        redirect('/users.php');
    }

    try {
        $sql = 'INSERT INTO users (full_name, email, password_hash, role, designation, image_path, permissions, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        db()->prepare($sql)->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $designation, $uploadedImagePath ?? '', json_encode($normalizedPermissions), $isActive]);
        $newId = (int)db()->lastInsertId();
        log_activity('user', $newId, 'create', [
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
        ]);
        set_flash('ok', 'User account created.');
    } catch (Throwable $e) {
        set_flash('err', 'Unable to create user. Email may already exist.');
    }

    redirect('/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_user') {
    $userId = (int)post('user_id');
    $fullName = trim(post('full_name'));
    $email = strtolower(trim(post('email')));
    $role = post('role', 'employee');
    $designation = post('designation', '');
    $isActive = (int)post('is_active', '1');
    $newPassword = post('new_password');
    $removeImageRequested = post('remove_image') === '1';
    $permissions = isset($_POST['permissions']) && is_array($_POST['permissions'])
        ? $_POST['permissions']
        : [];

    if ($userId <= 0 || $fullName === '' || $email === '') {
        set_flash('err', 'Invalid user details.');
        redirect('/users.php');
    }

    if (!in_array($role, ['admin', 'employee'], true)) {
        $role = 'employee';
    }

    $normalizedPermissions = $role === 'admin'
        ? ['*']
        : normalize_permissions($permissions, $featureOptions);

    $current = current_user();
    if ($current && (int)$current['id'] === $userId && $role !== 'admin') {
        set_flash('err', 'You cannot remove your own admin role.');
        redirect('/users.php');
    }

    if ($current && (int)$current['id'] === $userId && $isActive !== 1) {
        set_flash('err', 'You cannot deactivate your own account.');
        redirect('/users.php');
    }

    $uploadError = null;
    $uploadedImagePath = save_uploaded_image('image', 'user_avatar', $uploadError);
    if ($uploadError !== null) {
        set_flash('err', $uploadError);
        redirect('/users.php');
    }

    $previousStmt = db()->prepare('SELECT image_path FROM users WHERE id = ?');
    $previousStmt->execute([$userId]);
    $previousRow = $previousStmt->fetch();
    $previousImagePath = trim((string)($previousRow['image_path'] ?? ''));

    $imagePath = $previousImagePath;
    if ($uploadedImagePath !== null) {
        $imagePath = $uploadedImagePath;
        if ($previousImagePath !== '' && $previousImagePath !== $uploadedImagePath) {
            delete_uploaded_asset($previousImagePath);
        }
    } elseif ($removeImageRequested && $previousImagePath !== '') {
        $imagePath = '';
        delete_uploaded_asset($previousImagePath);
    }

    try {
        if (trim($newPassword) !== '') {
            if (strlen($newPassword) < 8) {
                set_flash('err', 'New password must be at least 8 characters.');
                redirect('/users.php');
            }

            $sql = 'UPDATE users SET full_name = ?, email = ?, role = ?, designation = ?, image_path = ?, permissions = ?, is_active = ?, password_hash = ? WHERE id = ?';
            db()->prepare($sql)->execute([$fullName, $email, $role, $designation, $imagePath, json_encode($normalizedPermissions), $isActive, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        } else {
            $sql = 'UPDATE users SET full_name = ?, email = ?, role = ?, designation = ?, image_path = ?, permissions = ?, is_active = ? WHERE id = ?';
            db()->prepare($sql)->execute([$fullName, $email, $role, $designation, $imagePath, json_encode($normalizedPermissions), $isActive, $userId]);
        }

        log_activity('user', $userId, 'update', [
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
        ]);

        set_flash('ok', 'User account updated.');
    } catch (Throwable $e) {
        set_flash('err', 'Unable to update user. Email may already exist.');
    }

    redirect('/users.php');
}

if (isset($_GET['confirm_delete']) || isset($_GET['delete'])) {
    $pendingDeleteUserId = (int)($_GET['confirm_delete'] ?? $_GET['delete']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_user') {
    $userId = (int)post('delete_id');
    $current = current_user();

    if ($current && (int)$current['id'] === $userId) {
        set_flash('err', 'You cannot delete your own account.');
        redirect('/users.php');
    }

    $targetStmt = db()->prepare('SELECT email FROM users WHERE id = ?');
    $targetStmt->execute([$userId]);
    $targetUser = $targetStmt->fetch();
    $deleteStmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $deleteStmt->execute([$userId]);
    if ($deleteStmt->rowCount() > 0) {
        log_activity('user', $userId, 'delete', [
            'email' => (string)($targetUser['email'] ?? ''),
        ]);
        set_flash('ok', 'User account deleted.');
    } else {
        set_flash('warning', 'User account was not found or already deleted.');
    }
    redirect('/users.php');
}

$users = db()->query('SELECT id, full_name, email, role, designation, image_path, permissions, is_active, created_at FROM users ORDER BY created_at DESC')->fetchAll();

render_header('User Management');
?>
<section class="rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <?php if ($pendingDeleteUserId > 0): ?>
        <?php
            $pendingUserStmt = db()->prepare('SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1');
            $pendingUserStmt->execute([$pendingDeleteUserId]);
            $pendingUser = $pendingUserStmt->fetch();
        ?>
        <?php if ($pendingUser): ?>
            <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/55 dark:bg-slate-950/75"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900 shadow-2xl dark:border-amber-700 dark:bg-slate-900 dark:text-amber-200">
                    <p class="text-sm font-bold">Delete confirmation required</p>
                    <p class="mt-1 text-sm">You are about to permanently delete user: <span class="font-semibold"><?php echo e((string)$pendingUser['full_name']); ?></span> (<?php echo e((string)$pendingUser['email']); ?>).</p>
                    <p class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">Warning: This action cannot be undone.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="post">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="delete_id" value="<?php echo e((string)$pendingUser['id']); ?>">
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yes, delete user</button>
                        </form>
                        <a href="<?php echo e(app_config()['base_url']); ?>/users.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-700/80">
        <h1 class="text-lg font-bold text-slate-900 dark:text-white">User Management</h1>
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Admin-only area to create and manage admin and employee accounts.</p>
    </div>

    <div class="border-b border-slate-200 bg-white/70 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/40">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-600">Create User</h2>
        <form method="post" enctype="multipart/form-data" class="mt-3 grid gap-4 md:grid-cols-6">
            <input type="hidden" name="action" value="create_user">
            <input class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="text" name="full_name" placeholder="Full name" required>
            <input class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="email" name="email" placeholder="Email" required>
            <input class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="password" name="password" placeholder="Password" minlength="8" required>
            <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" name="role" onchange="toggleDesignation(this)">
                <option value="employee">Employee</option>
                <option value="admin">Admin</option>
            </select>
            <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" name="is_active">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <select class="rounded-lg border border-slate-300 px-3 py-2 text-sm designation-field md:col-span-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" name="designation">
                <option value="">Select Designation</option>
                <option value="VA">VA</option>
                <option value="SEO">SEO</option>
                <option value="Design">Design</option>
                <option value="DevOps">DevOps</option>
                <option value="PPC">PPC</option>
                <option value="Social">Social</option>
                <option value="Account Manager">Account Manager</option>
                <option value="Project Manager">Project Manager</option>
            </select>
            <input class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" placeholder="Upload image">
            <p class="text-xs text-slate-500 dark:text-slate-400 md:col-span-2">Any image size is accepted and automatically optimized to a maximum of 200KB.</p>
            <div class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 md:col-span-6 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                <p class="mb-2 font-semibold text-slate-700">Employee Feature Access</p>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($featureOptions as $featureKey => $featureLabel): ?>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="permissions[]" value="<?php echo e($featureKey); ?>" <?php echo in_array($featureKey, $defaultPermissions, true) ? 'checked' : ''; ?>>
                            <span><?php echo e($featureLabel); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2">Ignored when role is Admin (admins always have full access).</p>
            </div>
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 md:col-span-1" type="submit">Create</button>
        </form>
    </div>

    <div class="cf-table-wrap overflow-x-auto">
        <table class="cf-table min-w-[980px] divide-y divide-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-800">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Avatar</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Name</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Role</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Designation</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Created</th>
                    <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-900">
                <?php foreach ($users as $u): ?>
                    <?php
                        $rowPermissions = json_decode((string)($u['permissions'] ?? ''), true);
                        if (!is_array($rowPermissions)) {
                            $rowPermissions = $u['role'] === 'employee' ? default_employee_permissions() : ['*'];
                        } elseif ($u['role'] === 'employee' && !in_array('notifications', $rowPermissions, true)) {
                            $rowPermissions[] = 'notifications';
                        }
                        $avatarInitial = strtoupper(substr((string)($u['full_name'] ?? 'U'), 0, 1));
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="px-5 py-4 text-sm text-slate-600">
                            <?php if (trim((string)($u['image_path'] ?? '')) !== ''): ?>
                                <img class="h-10 w-10 rounded-full border border-slate-200 object-cover" src="<?php echo e((string)$u['image_path']); ?>" alt="<?php echo e((string)$u['full_name']); ?>">
                            <?php else: ?>
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white"><?php echo e($avatarInitial !== '' ? $avatarInitial : 'U'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-900"><?php echo e($u['full_name']); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo e($u['email']); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo e($u['role']); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo e((string)($u['designation'] ?? '-')); ?></td>
                        <td class="px-5 py-4 text-sm text-slate-600"><?php echo (int)$u['is_active'] === 1 ? 'Active' : 'Inactive'; ?></td>
                        <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400"><?php echo e((string)$u['created_at']); ?></td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" onclick="openUserEditModal('edit_user_modal_<?php echo e((string)$u['id']); ?>')" title="Edit user" aria-label="Edit user">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-8.7 8.7a2 2 0 0 1-.878.513l-2.73.78a.75.75 0 0 1-.927-.927l.78-2.73a2 2 0 0 1 .513-.878l8.7-8.7Z" /></svg>
                                </button>
                                <a class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50" href="<?php echo e(app_config()['base_url']); ?>/users.php?confirm_delete=<?php echo e((string)$u['id']); ?>" title="Delete user" aria-label="Delete user">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 2.5a1 1 0 0 0-1 1V4h-2.5a.75.75 0 0 0 0 1.5h.538l.84 10.08A2.5 2.5 0 0 0 9.12 18h1.76a2.5 2.5 0 0 0 2.492-2.42l.84-10.08h.538a.75.75 0 0 0 0-1.5h-2.5v-.5a1 1 0 0 0-1-1h-2.5Zm2 1.5h-1V4h1v0Zm-1.69 3.25a.75.75 0 0 0-1.5 0v6a.75.75 0 0 0 1.5 0v-6Zm3.38 0a.75.75 0 0 0-1.5 0v6a.75.75 0 1 0 1.5 0v-6Z" clip-rule="evenodd" /></svg>
                                </a>
                            </div>
                        </td>
                    </tr>

                    <div id="edit_user_modal_<?php echo e((string)$u['id']); ?>" class="user-edit-modal fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 px-4 py-6">
                        <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl sm:p-5 dark:border-slate-700 dark:bg-slate-900">
                            <div class="mb-3 flex items-center justify-between gap-2 border-b border-slate-200 pb-3">
                                <h3 class="text-base font-bold text-slate-900">Edit User: <?php echo e((string)$u['full_name']); ?></h3>
                                <button type="button" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" onclick="closeUserEditModal('edit_user_modal_<?php echo e((string)$u['id']); ?>')">Close</button>
                            </div>
                            <form method="post" enctype="multipart/form-data" class="grid gap-4">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?php echo e((string)$u['id']); ?>">
                                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="text" name="full_name" value="<?php echo e($u['full_name']); ?>" required>
                                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="email" name="email" value="<?php echo e($u['email']); ?>" required>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <select class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" name="role" onchange="toggleDesignationEdit(this)">
                                        <option value="employee" <?php echo $u['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                        <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <select class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" name="is_active">
                                        <option value="1" <?php echo (int)$u['is_active'] === 1 ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?php echo (int)$u['is_active'] !== 1 ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <select class="designation-field-edit w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" name="designation" <?php echo $u['role'] === 'admin' ? 'style="display:none;"' : ''; ?>>
                                    <option value="">Select Designation</option>
                                    <option value="VA" <?php echo $u['designation'] === 'VA' ? 'selected' : ''; ?>>VA</option>
                                    <option value="SEO" <?php echo $u['designation'] === 'SEO' ? 'selected' : ''; ?>>SEO</option>
                                    <option value="Design" <?php echo $u['designation'] === 'Design' ? 'selected' : ''; ?>>Design</option>
                                    <option value="DevOps" <?php echo $u['designation'] === 'DevOps' ? 'selected' : ''; ?>>DevOps</option>
                                    <option value="PPC" <?php echo $u['designation'] === 'PPC' ? 'selected' : ''; ?>>PPC</option>
                                    <option value="Social" <?php echo $u['designation'] === 'Social' ? 'selected' : ''; ?>>Social</option>
                                    <option value="Account Manager" <?php echo $u['designation'] === 'Account Manager' ? 'selected' : ''; ?>>Account Manager</option>
                                    <option value="Project Manager" <?php echo $u['designation'] === 'Project Manager' ? 'selected' : ''; ?>>Project Manager</option>
                                </select>
                                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" placeholder="Upload image">
                                <p class="-mt-2 text-xs text-slate-500 dark:text-slate-400">Any image size is accepted and automatically optimized to a maximum of 200KB.</p>
                                <?php if (trim((string)($u['image_path'] ?? '')) !== ''): ?>
                                    <label class="-mt-1 inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                        <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300">
                                        Remove current user image
                                    </label>
                                <?php endif; ?>
                                <div class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-3.5 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <p class="mb-2 font-semibold text-slate-700">Employee Feature Access</p>
                                    <div class="grid gap-1.5 sm:grid-cols-2">
                                        <?php foreach ($featureOptions as $featureKey => $featureLabel): ?>
                                            <label class="inline-flex items-center gap-2">
                                                <input type="checkbox" name="permissions[]" value="<?php echo e($featureKey); ?>" <?php echo in_array($featureKey, $rowPermissions, true) ? 'checked' : ''; ?>>
                                                <span><?php echo e($featureLabel); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" type="password" name="new_password" placeholder="New password (optional)">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" onclick="closeUserEditModal('edit_user_modal_<?php echo e((string)$u['id']); ?>')">Cancel</button>
                                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-950" type="submit">Update User</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                    <tr><td class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400" colspan="8">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
function openUserEditModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeUserEditModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function toggleDesignation(select) {
    const fields = document.querySelectorAll('.designation-field');
    fields.forEach(field => {
        field.style.display = select.value === 'employee' ? '' : 'none';
    });
}
function toggleDesignationEdit(select) {
    const parent = select.closest('form');
    if (!parent) return;
    const field = parent.querySelector('.designation-field-edit');
    if (field) {
        field.style.display = select.value === 'employee' ? '' : 'none';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const createForm = document.querySelector('form input[name="action"][value="create_user"]')?.closest('form') || document.querySelector('form[enctype="multipart/form-data"]');
    if (createForm) {
        const roleSelect = createForm.querySelector('select[name="role"]');
        if (roleSelect) toggleDesignation(roleSelect);
    }

    document.querySelectorAll('.user-edit-modal').forEach((modal) => {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeUserEditModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.user-edit-modal').forEach((modal) => {
            if (!modal.classList.contains('hidden')) {
                closeUserEditModal(modal.id);
            }
        });
    });
});
</script>
<?php render_footer(); ?>
