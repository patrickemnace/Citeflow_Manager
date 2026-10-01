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

// Classic sticky-note-pad colors. Each is just a set of CSS custom property
// values swapped via data-note-color on <body> - no dynamic Tailwind class
// generation involved, so it's robust regardless of how the Play CDN build
// rescans the DOM.
$noteColors = [
    'amber'   => ['label' => 'Yellow', 'swatch' => '#fbbf24'],
    'rose'    => ['label' => 'Pink',   'swatch' => '#fb7185'],
    'sky'     => ['label' => 'Blue',   'swatch' => '#38bdf8'],
    'emerald' => ['label' => 'Green',  'swatch' => '#34d399'],
    'violet'  => ['label' => 'Purple', 'swatch' => '#a78bfa'],
    'orange'  => ['label' => 'Orange', 'swatch' => '#fb923c'],
];
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

        :root, body[data-note-color="amber"] {
            --note-bg: #fef3c7; --note-card-bg: #fffbeb; --note-card-border: #fcd34d;
            --note-field-border: #fde68a; --note-title: #78350f; --note-subtitle: #b45309;
            --note-btn-border: #fcd34d; --note-btn-text: #92400e; --note-btn-hover-bg: #fef3c7;
        }
        body[data-note-color="rose"] {
            --note-bg: #ffe4e6; --note-card-bg: #fff1f2; --note-card-border: #fda4af;
            --note-field-border: #fecdd3; --note-title: #881337; --note-subtitle: #be123c;
            --note-btn-border: #fda4af; --note-btn-text: #9f1239; --note-btn-hover-bg: #ffe4e6;
        }
        body[data-note-color="sky"] {
            --note-bg: #e0f2fe; --note-card-bg: #f0f9ff; --note-card-border: #7dd3fc;
            --note-field-border: #bae6fd; --note-title: #0c4a6e; --note-subtitle: #0369a1;
            --note-btn-border: #7dd3fc; --note-btn-text: #075985; --note-btn-hover-bg: #e0f2fe;
        }
        body[data-note-color="emerald"] {
            --note-bg: #d1fae5; --note-card-bg: #ecfdf5; --note-card-border: #6ee7b7;
            --note-field-border: #a7f3d0; --note-title: #064e3b; --note-subtitle: #047857;
            --note-btn-border: #6ee7b7; --note-btn-text: #065f46; --note-btn-hover-bg: #d1fae5;
        }
        body[data-note-color="violet"] {
            --note-bg: #ede9fe; --note-card-bg: #f5f3ff; --note-card-border: #c4b5fd;
            --note-field-border: #ddd6fe; --note-title: #4c1d95; --note-subtitle: #6d28d9;
            --note-btn-border: #c4b5fd; --note-btn-text: #5b21b6; --note-btn-hover-bg: #ede9fe;
        }
        body[data-note-color="orange"] {
            --note-bg: #ffedd5; --note-card-bg: #fff7ed; --note-card-border: #fdba74;
            --note-field-border: #fed7aa; --note-title: #7c2d12; --note-subtitle: #c2410c;
            --note-btn-border: #fdba74; --note-btn-text: #9a3412; --note-btn-hover-bg: #ffedd5;
        }

        body { background-color: var(--note-bg); transition: background-color .15s ease; }
        .note-card { background-color: var(--note-card-bg); border-color: var(--note-card-border); transition: background-color .15s ease, border-color .15s ease; }
        .note-title { color: var(--note-title); }
        .note-subtitle, .note-label { color: var(--note-subtitle); }
        .note-field { border-color: var(--note-field-border); transition: border-color .15s ease; }
        .note-copy-btn { border-color: var(--note-btn-border); color: var(--note-btn-text); }
        .note-copy-btn:hover { background-color: var(--note-btn-hover-bg); }

        /* Subtle peeled-corner detail - a small, classic sticky-note affordance. */
        .note-fold {
            position: absolute; top: 0; right: 0; width: 26px; height: 26px;
            background: linear-gradient(135deg, transparent 50%, rgba(0, 0, 0, 0.1) 50%);
            border-top-right-radius: 1rem;
            pointer-events: none;
        }

        .note-swatch { width: 18px; height: 18px; border-radius: 9999px; border: 2px solid rgba(255,255,255,.8); box-shadow: 0 0 0 1px rgba(0,0,0,.12); cursor: pointer; transition: transform .1s ease; }
        .note-swatch:hover { transform: scale(1.15); }
        .note-swatch[aria-pressed="true"] { box-shadow: 0 0 0 2px rgba(0,0,0,.45); }
    </style>
</head>
<body class="min-h-screen p-3 text-slate-800" data-note-color="amber">
    <section class="note-card relative mx-auto max-w-xl overflow-hidden rounded-2xl border-[1.5px] p-3 shadow-xl">
        <div class="note-fold"></div>

        <div class="mb-2 flex items-start justify-between gap-2 border-b pb-2" style="border-color: var(--note-field-border);">
            <div>
                <h1 class="note-title flex items-center gap-1.5 text-base font-extrabold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a1 1 0 0 1 1 1v.1a5.002 5.002 0 0 1 4 4.9c0 1.657-.8 3-2 3.874V13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-1.126C5.8 11 5 9.657 5 8a5.002 5.002 0 0 1 4-4.9V3a1 1 0 0 1 1-1Z"/><path d="M8.5 15.5h3a1.5 1.5 0 0 1-3 0Z"/></svg>
                    Sticky Notes
                </h1>
                <p class="note-subtitle text-xs font-semibold"><?php echo e((string)$business['name']); ?></p>
            </div>
            <div class="flex items-center gap-2">
                <?php if (trim((string)($business['logo_path'] ?? '')) !== ''): ?>
                    <img src="<?php echo e(public_asset_url((string)$business['logo_path'])); ?>" alt="Logo" class="h-14 w-24 rounded-lg border bg-white object-contain p-1" style="border-color: var(--note-field-border);">
                <?php endif; ?>
                <button type="button" id="close_sticky_notes" title="Close" aria-label="Close" class="note-copy-btn shrink-0 rounded-md border px-2 py-1 text-xs font-bold leading-none hover:bg-opacity-80">&times;</button>
            </div>
        </div>

        <div class="mb-3 flex items-center gap-2">
            <span class="note-subtitle text-[10px] font-bold uppercase tracking-wide">Color</span>
            <div class="flex items-center gap-1.5" id="note_color_picker" role="group" aria-label="Note color">
                <?php foreach ($noteColors as $colorKey => $colorInfo): ?>
                    <button
                        type="button"
                        class="note-swatch"
                        style="background-color: <?php echo e($colorInfo['swatch']); ?>;"
                        data-note-color="<?php echo e($colorKey); ?>"
                        title="<?php echo e($colorInfo['label']); ?>"
                        aria-label="<?php echo e($colorInfo['label']); ?>"
                        aria-pressed="<?php echo $colorKey === 'amber' ? 'true' : 'false'; ?>"
                    ></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="space-y-2">
            <?php foreach ($fields as $field): ?>
                <?php
                    $value = trim((string)($field['value'] ?? ''));
                    $isCredentials = ($field['label'] === 'Login Credentials');
                ?>
                <article class="note-field rounded-xl border bg-white/70 p-2.5 shadow-sm">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <p class="note-label text-[11px] font-bold uppercase tracking-wide"><?php echo e((string)$field['label']); ?></p>
                        <?php if (!$isCredentials): ?>
                            <button type="button" class="note-copy-btn inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-semibold" data-copy="<?php echo e($value); ?>"><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="1.5"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg><span class="note-copy-btn-label">Copy</span></button>
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
                                        <button type="button" class="note-copy-btn inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-semibold" data-copy="<?php echo e($credVal); ?>"><svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="1.5"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg><span class="note-copy-btn-label">Copy</span></button>
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
        const businessId = <?php echo json_encode((string)$businessId); ?>;

        // --- Color theme: persisted per-browser (not per-business) so the
        // chosen "paper color" stays consistent across every sticky note. ---
        (function () {
            const STORAGE_KEY = 'cf_sticky_note_color';
            const picker = document.getElementById('note_color_picker');
            const swatches = picker ? Array.from(picker.querySelectorAll('[data-note-color]')) : [];

            const applyColor = (color) => {
                document.body.setAttribute('data-note-color', color);
                swatches.forEach((btn) => {
                    btn.setAttribute('aria-pressed', btn.getAttribute('data-note-color') === color ? 'true' : 'false');
                });
            };

            let saved = null;
            try {
                saved = localStorage.getItem(STORAGE_KEY);
            } catch (e) {}
            if (saved) {
                applyColor(saved);
            }

            swatches.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const color = btn.getAttribute('data-note-color');
                    applyColor(color);
                    try {
                        localStorage.setItem(STORAGE_KEY, color);
                    } catch (e) {}
                });
            });
        }());

        // --- Close button ---
        const closeBtn = document.getElementById('close_sticky_notes');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => window.close());
        }

        // --- Copy buttons ---
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
                const label = button.querySelector('.note-copy-btn-label');
                if (!label) {
                    return;
                }
                const old = label.textContent;
                label.textContent = 'Copied';
                setTimeout(() => {
                    label.textContent = old || 'Copy';
                }, 900);
            });
        });

        // --- Cross-tab single-instance coordination ---
        // Answers "is a sticky notes window open?" pings from location_manager.php
        // tabs, and focuses/navigates itself when asked, so only one sticky
        // notes window ever exists regardless of which tab asks for it.
        if ('BroadcastChannel' in window) {
            const channel = new BroadcastChannel('cf-sticky-notes');
            channel.addEventListener('message', (event) => {
                const data = event.data || {};
                if (data.type === 'cf-sticky-notes-ping') {
                    channel.postMessage({ type: 'cf-sticky-notes-here' });
                    return;
                }
                if (data.type === 'cf-sticky-notes-focus') {
                    window.focus();
                    if (data.businessId && data.businessId !== businessId) {
                        window.location.href = <?php echo json_encode($base); ?> + '/sticky_notes.php?business_id=' + encodeURIComponent(data.businessId);
                    }
                }
            });
        }
    </script>
</body>
</html>
