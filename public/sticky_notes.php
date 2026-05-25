<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('location_manager');

$config = app_config();
$base = (string)$config['base_url'];

$businessId = (int)($_GET['business_id'] ?? 0);
if ($businessId <= 0) {
    http_response_code(400);
    echo 'Invalid business id.';
    exit;
}

$stmt = db()->prepare('SELECT * FROM businesses WHERE id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();
if (!$business) {
    http_response_code(404);
    echo 'Business not found.';
    exit;
}

$fullAddress = implode(', ', array_filter([
    trim((string)($business['address_line1'] ?? '')),
    trim((string)($business['city'] ?? '')),
    trim((string)($business['state'] ?? '')),
    trim((string)($business['postal_code'] ?? '')),
    trim((string)($business['country'] ?? '')),
]));

$fields = [
    ['label' => 'Login Credentials', 'value' => (string)($business['login_credentials'] ?? '')],
    ['label' => 'Business Name', 'value' => (string)($business['name'] ?? '')],
    ['label' => 'Phone', 'value' => (string)($business['phone'] ?? '')],
    ['label' => 'Website', 'value' => (string)($business['website'] ?? '')],
    ['label' => 'GBP Link', 'value' => (string)($business['gbp_link'] ?? '')],
    ['label' => 'Email', 'value' => (string)($business['email'] ?? '')],
    ['label' => 'Contact Name', 'value' => (string)($business['contact_name'] ?? '')],
    ['label' => 'Address Line 1', 'value' => (string)($business['address_line1'] ?? '')],
    ['label' => 'City', 'value' => (string)($business['city'] ?? '')],
    ['label' => 'State', 'value' => (string)($business['state'] ?? '')],
    ['label' => 'Postal Code', 'value' => (string)($business['postal_code'] ?? '')],
    ['label' => 'Country', 'value' => (string)($business['country'] ?? '')],
    ['label' => 'Categories', 'value' => (string)(($business['categories'] ?? '') !== '' ? $business['categories'] : ($business['category'] ?? ''))],
    ['label' => 'Description', 'value' => (string)($business['description'] ?? '')],
    ['label' => 'Services', 'value' => (string)($business['services'] ?? '')],
    ['label' => 'Business Hours', 'value' => (string)($business['hours_json'] ?? '')],
    ['label' => 'Social Media', 'value' => (string)($business['social_media'] ?? '')],
    ['label' => 'Payment Methods', 'value' => (string)($business['payment_methods'] ?? '')],
];


function linkify(string $text): string
{
    $out = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $out = (string)preg_replace(
        '#(https?://[^\s<>"\')]+)#i',
        '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline break-all">$1</a>',
        $out
    );
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sticky Notes - <?php echo e((string)$business['name']); ?></title>
    <script src="<?php echo e($base); ?>/tailwind.js"></script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-amber-100 p-3 text-slate-800">
    <section class="mx-auto max-w-xl rounded-2xl border border-amber-300 bg-amber-50 p-3 shadow-xl">
        <div class="mb-3 flex items-start justify-between gap-2 border-b border-amber-200 pb-2">
            <div>
                <h1 class="text-base font-extrabold text-amber-900">Sticky Notes</h1>
                <p class="text-xs font-semibold text-amber-700"><?php echo e((string)$business['name']); ?></p>
            </div>
            <?php if (trim((string)($business['logo_path'] ?? '')) !== ''): ?>
                <img src="<?php echo e(public_asset_url((string)$business['logo_path'])); ?>" alt="Logo" class="h-16 w-28 rounded-lg border border-amber-200 bg-white object-contain p-1">
            <?php endif; ?>
        </div>

        <div class="space-y-2">
            <?php foreach ($fields as $field): ?>
                <?php
                    $value = trim((string)($field['value'] ?? ''));
                    $isCredentials = ($field['label'] === 'Login Credentials');
                ?>
                <article class="rounded-xl border border-amber-200 bg-white p-2.5 shadow-sm">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700"><?php echo e((string)$field['label']); ?></p>
                        <?php if (!$isCredentials): ?>
                            <button type="button" class="rounded-md border border-amber-300 px-2 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-100" data-copy="<?php echo e($value); ?>">Copy</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($isCredentials && $value !== ''): ?>
                        <div class="space-y-1.5">
                            <?php
                            $credLines = array_values(array_filter(array_map('trim', explode("\n", $value)), fn($l) => $l !== ''));
                            foreach ($credLines as $credLine):
                                // Strip label prefix like "Username: " or "Email: " — copy only the value part
                                $colonPos = strpos($credLine, ':');
                                $credLabel = $colonPos !== false ? substr($credLine, 0, $colonPos + 1) : '';
                                $credVal   = $colonPos !== false ? trim(substr($credLine, $colonPos + 1)) : $credLine;
                            ?>
                                <?php $isLink = stripos(trim($credLabel), 'open email') === 0; ?>
                                <div class="flex items-center justify-between gap-2">
                                    <p class="break-all text-sm text-slate-800">
                                        <?php if ($credLabel !== ''): ?>
                                            <span class="font-bold"><?php echo e($credLabel); ?></span>
                                            <?php echo ' ' . linkify($credVal); ?>
                                        <?php else: ?>
                                            <?php echo linkify($credLine); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$isLink && $credVal !== ''): ?>
                                        <button type="button" class="shrink-0 rounded-md border border-amber-300 px-2 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-100" data-copy="<?php echo e($credVal); ?>">Copy</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="whitespace-pre-wrap break-words text-sm text-slate-800"><?php echo $value !== '' ? linkify($value) : '—'; ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <script>
        const copyButtons = document.querySelectorAll('[data-copy]');
        const writeText = async (value) => {
            if (!value) {
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(value);
                return;
            }
            const temp = document.createElement('textarea');
            temp.value = value;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
        };

        copyButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                const value = button.getAttribute('data-copy') || '';
                await writeText(value);
                const old = button.textContent;
                button.textContent = 'Copied';
                setTimeout(() => {
                    button.textContent = old || 'Copy';
                }, 900);
            });
        });
    </script>
</body>
</html>
