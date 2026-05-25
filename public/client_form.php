<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
$data = [
    'name' => '',
    'brand_name' => '',
    'website' => '',
    'primary_email' => '',
    'primary_phone' => '',
    'notes' => '',
    'is_active' => '1',
];

if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $data = array_merge($data, array_map('strval', $row));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        $data[$key] = post($key, (string)$value);
    }

    $portalPassword = post('portal_password');
    $portalPasswordConfirm = post('portal_password_confirm');
    $portalPasswordHash = '';
    $formError = null;

    if ($data['name'] === '') {
        $formError = 'Client name is required.';
    } elseif ($data['primary_email'] !== '') {
        $emailCheck = db()->prepare('SELECT id FROM clients WHERE LOWER(primary_email) = LOWER(?) AND id <> ? LIMIT 1');
        $emailCheck->execute([$data['primary_email'], $id]);
        if ($emailCheck->fetch()) {
            $formError = 'Primary email is already used by another client.';
        }
    }

    if ($formError === null) {
        if (($portalPassword !== '' || $portalPasswordConfirm !== '') && $data['primary_email'] === '') {
            $formError = 'Primary email is required to enable client portal login.';
        } elseif ($portalPassword !== '' || $portalPasswordConfirm !== '') {
            if (strlen($portalPassword) < 8) {
                $formError = 'Client portal password must be at least 8 characters.';
            } elseif ($portalPassword !== $portalPasswordConfirm) {
                $formError = 'Client portal passwords do not match.';
            } else {
                $portalPasswordHash = password_hash($portalPassword, PASSWORD_DEFAULT);
            }
        }
    }

    if ($formError === null) {
        if ($id > 0) {
            if ($portalPasswordHash !== '') {
                db()->prepare('UPDATE clients SET name=?, brand_name=?, website=?, primary_email=?, portal_password_hash=?, primary_phone=?, notes=?, is_active=? WHERE id=?')
                    ->execute([$data['name'], $data['brand_name'], $data['website'], $data['primary_email'], $portalPasswordHash, $data['primary_phone'], $data['notes'], (int)$data['is_active'], $id]);
            } else {
                db()->prepare('UPDATE clients SET name=?, brand_name=?, website=?, primary_email=?, primary_phone=?, notes=?, is_active=? WHERE id=?')
                    ->execute([$data['name'], $data['brand_name'], $data['website'], $data['primary_email'], $data['primary_phone'], $data['notes'], (int)$data['is_active'], $id]);
            }
            log_activity('client', $id, 'update', ['name' => $data['name']]);
            set_flash('ok', 'Client updated.');
        } else {
            db()->prepare('INSERT INTO clients (name, brand_name, website, primary_email, portal_password_hash, primary_phone, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$data['name'], $data['brand_name'], $data['website'], $data['primary_email'], $portalPasswordHash, $data['primary_phone'], $data['notes'], (int)$data['is_active']]);
            $newId = (int)db()->lastInsertId();
            log_activity('client', $newId, 'create', ['name' => $data['name']]);
            set_flash('ok', 'Client created.');
        }
        redirect('/clients.php');
    } else {
        set_flash('err', $formError);
    }
}

render_header($id > 0 ? 'Edit Client' : 'Add Client');
?>
<section class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <h1 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo $id > 0 ? 'Edit Client' : 'Add Client'; ?></h1>
    <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Use clients to separate brands and improve reporting rollups.</p>

    <form method="post" class="mt-6 space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Client Name *</label>
                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="name" value="<?php echo e($data['name']); ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Brand Name</label>
                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="brand_name" value="<?php echo e($data['brand_name']); ?>">
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Website</label>
                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="website" value="<?php echo e($data['website']); ?>">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Primary Email</label>
                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="primary_email" value="<?php echo e($data['primary_email']); ?>">
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Primary Phone</label>
                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="primary_phone" value="<?php echo e($data['primary_phone']); ?>">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Active</label>
                <select class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="is_active">
                    <option value="1" <?php echo $data['is_active'] === '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo $data['is_active'] === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-800">Client Portal Access</p>
            <p class="mt-1 text-xs text-slate-500">Clients can sign in at <?php echo e(app_config()['base_url']); ?>/client_portal.php using their primary email and password.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700"><?php echo $id > 0 ? 'New Portal Password' : 'Portal Password'; ?></label>
                    <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" type="password" name="portal_password" minlength="8" placeholder="At least 8 characters">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700"><?php echo $id > 0 ? 'Confirm New Password' : 'Confirm Password'; ?></label>
                    <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" type="password" name="portal_password_confirm" minlength="8" placeholder="Repeat password">
                </div>
            </div>
            <?php if ($id > 0): ?>
                <p class="mt-2 text-xs text-slate-500">Leave password fields blank to keep the current client portal password.</p>
            <?php endif; ?>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Notes</label>
            <textarea class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="notes" rows="5"><?php echo e($data['notes']); ?></textarea>
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" type="submit">Save Client</button>
            <a class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="<?php echo e(app_config()['base_url']); ?>/clients.php">Cancel</a>
        </div>
    </form>
</section>
<?php render_footer(); ?>