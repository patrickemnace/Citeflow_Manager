<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

if (current_client()) {
    redirect('/client_dashboard.php');
}

$hasUsers = false;
if (table_exists('users')) {
    $hasUsers = (int)db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'] > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(post('email'));
    $password = post('password');

    if (login_client($email, $password)) {
        set_flash('ok', 'Welcome to your client portal.');
        redirect('/client_dashboard.php');
    }

    set_flash('err', 'Invalid client credentials.');
    redirect('/client_portal.php');
}

$config = app_config();
$flash = flash();
$base = (string)$config['base_url'];
$logoUrl = $base . '/branding/MDR_logo.png';
$faviconUrl = $base . '/branding/MDR_logo.png';
$directoryLogoRows = [];
if (table_exists('directories')) {
    try {
        $directoryCandidates = db()->query('SELECT name, logo_path FROM directories WHERE is_active = 1')->fetchAll();
        $commonDirectoryRows = [];
        $otherDirectoryRows = [];

        foreach ($directoryCandidates as $directoryCandidate) {
            $directoryName = strtolower(trim((string)($directoryCandidate['name'] ?? '')));
            $isCommonDirectory = str_contains($directoryName, 'google')
                || str_contains($directoryName, 'facebook')
                || str_contains($directoryName, 'yelp')
                || str_contains($directoryName, 'bing')
                || str_contains($directoryName, 'apple')
                || str_contains($directoryName, 'tripadvisor')
                || str_contains($directoryName, 'foursquare')
                || str_contains($directoryName, 'yellow pages')
                || str_contains($directoryName, 'yellowpages');

            if ($isCommonDirectory) {
                $commonDirectoryRows[] = $directoryCandidate;
                continue;
            }

            $otherDirectoryRows[] = $directoryCandidate;
        }

        if ($commonDirectoryRows) {
            shuffle($commonDirectoryRows);
        }
        if ($otherDirectoryRows) {
            shuffle($otherDirectoryRows);
        }

        $directoryLogoRows = array_slice(array_merge($commonDirectoryRows, $otherDirectoryRows), 0, 8);
    } catch (Throwable $error) {
        $directoryLogoRows = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Sign In - <?php echo e((string)$config['app_name']); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e($faviconUrl); ?>">
    <link rel="apple-touch-icon" href="<?php echo e($faviconUrl); ?>">
    <script>
        (() => {
            try {
                const storedTheme = localStorage.getItem('cf-login-theme');
                if (storedTheme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (error) {
                // Ignore storage access issues and keep default light mode.
            }
        })();
    </script>
    <script src="<?php echo e($base); ?>/tailwind.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Manrope', 'ui-sans-serif', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        .cf-throbber-overlay{position:fixed;inset:0;z-index:120;display:flex;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);backdrop-filter:blur(2px);opacity:0;visibility:hidden;transition:opacity .18s ease,visibility .18s ease;pointer-events:none;}
        .cf-throbber-overlay.is-active{opacity:1;visibility:visible;pointer-events:auto;}
        .cf-throbber-spinner{width:2.4rem;height:2.4rem;border:3px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:999px;animation:cf-spin .75s linear infinite;filter:drop-shadow(0 4px 14px rgba(2,6,23,.35));}
        .cf-inline-spinner{display:inline-block;width:1rem;height:1rem;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:999px;animation:cf-spin .75s linear infinite;vertical-align:middle;}
        button.is-submitting,input.is-submitting{pointer-events:none;opacity:.92;}
        .cf-validation-icon{width:1.1rem;height:1.1rem;display:block;}
        .cf-split-shell{display:grid;min-height:100dvh;}
        .cf-side-light{position:relative;overflow:hidden;background:#ffffff;}
        .cf-side-light::before{content:'';position:absolute;top:-10rem;left:-9rem;width:24rem;height:24rem;border-radius:999px;background:radial-gradient(circle,rgba(52,211,153,.14),rgba(52,211,153,0));pointer-events:none;}
        .cf-side-light::after{content:'';position:absolute;right:-8rem;bottom:-8rem;width:20rem;height:20rem;border-radius:999px;background:radial-gradient(circle,rgba(22,163,74,.12),rgba(22,163,74,0));pointer-events:none;}
        .cf-side-dark{position:relative;overflow:hidden;background:linear-gradient(180deg,#0d4f8b 0%,#0a4374 100%);transition:background .45s ease;}
        .cf-side-dark.is-client{background:linear-gradient(180deg,#0f5f47 0%,#0a3f31 100%);}
        .cf-side-dark::before{content:'';position:absolute;inset:-20% auto auto -10%;width:24rem;height:24rem;border-radius:999px;background:radial-gradient(circle,rgba(255,255,255,.08),rgba(255,255,255,0));pointer-events:none;}
        .cf-side-dark::after{content:'';position:absolute;right:-6rem;bottom:-6rem;width:18rem;height:18rem;border-radius:999px;background:radial-gradient(circle,rgba(255,255,255,.06),rgba(255,255,255,0));pointer-events:none;}
        .cf-illustration-card{position:relative;display:flex;align-items:center;justify-content:center;border-radius:2rem;background:transparent;box-shadow:none;}
        .cf-directory-gallery{position:relative;height:clamp(14rem,34vh,22rem);overflow:hidden;}
        .cf-directory-tile{position:absolute;display:flex;align-items:center;justify-content:center;width:5.2rem;height:3.1rem;padding:.2rem;}
        .cf-directory-tile img{width:100%;height:100%;object-fit:contain;display:block;filter:drop-shadow(0 8px 14px rgba(15,23,42,.22));}
        .cf-directory-tile--hero{top:4%;left:10%;transform:rotate(-6deg);}
        .cf-directory-tile--wide{top:8%;left:44%;transform:rotate(4deg);}
        .cf-directory-tile--tall{top:27%;left:67%;transform:rotate(-5deg);}
        .cf-directory-tile--mid{top:33%;left:28%;transform:rotate(5deg);}
        .cf-directory-tile--small-a{top:48%;left:8%;transform:rotate(-3deg);}
        .cf-directory-tile--small-b{top:60%;left:40%;transform:rotate(3deg);}
        .cf-directory-tile--small-c{top:57%;left:72%;transform:rotate(-4deg);}
        .cf-directory-tile--footer{top:74%;left:22%;transform:rotate(4deg);}
        .cf-left-copy{transition:opacity .22s ease,transform .22s ease;}
        .cf-left-copy.is-updating{opacity:.25;transform:translateY(4px);}
        .cf-directory-fallback{font-size:1.05rem;font-weight:800;color:#1e3a8a;line-height:1;}
        .cf-form-shell{position:relative;z-index:1;width:min(100%,24rem);}
        .cf-login-tabs{display:flex;gap:1.25rem;font-size:.875rem;font-weight:600;color:rgba(255,255,255,.8);}
        .cf-login-tab{position:relative;display:inline-block;padding-bottom:.55rem;transition:color .2s ease;}
        .cf-login-tab::after{content:'';position:absolute;left:0;right:0;bottom:0;height:2px;border-radius:999px;background:rgba(255,255,255,.72);transform:scaleX(0);transform-origin:center;transition:transform .25s ease;}
        .cf-login-tab-active{color:#fff;}
        .cf-login-tab-active::after{transform:scaleX(1);}
        .cf-flip-stage{perspective:1200px;}
        .cf-flip-card{position:relative;display:grid;transform-style:preserve-3d;transition:transform .58s cubic-bezier(.22,.61,.36,1);}
        .cf-flip-card.is-client{transform:rotateY(180deg);}
        .cf-flip-face{grid-area:1 / 1;backface-visibility:hidden;-webkit-backface-visibility:hidden;}
        .cf-flip-face-back{transform:rotateY(180deg);}
        .cf-flip-card.is-client .cf-flip-face-front{pointer-events:none;}
        .cf-flip-card:not(.is-client) .cf-flip-face-back{pointer-events:none;}
        .cf-mini-bar{position:relative;display:inline-block;padding-bottom:.55rem;}
        .cf-mini-bar::after{content:'';position:absolute;left:0;right:0;bottom:0;height:2px;border-radius:999px;background:rgba(255,255,255,.72);}
        .dark .cf-side-light{background:linear-gradient(180deg,#071326 0%,#0f172a 100%);}
        .dark .cf-side-light::before{background:radial-gradient(circle,rgba(74,222,128,.18),rgba(74,222,128,0));}
        .dark .cf-side-light::after{background:radial-gradient(circle,rgba(22,163,74,.14),rgba(22,163,74,0));}
        .dark .cf-side-dark{background:linear-gradient(180deg,#082032 0%,#030712 100%);}
        .dark .cf-side-dark.is-client{background:linear-gradient(180deg,#064e3b 0%,#022c22 100%);}
        .dark .cf-directory-tile img{filter:drop-shadow(0 10px 18px rgba(2,6,23,.6));}
        .dark .cf-directory-fallback{color:#bfdbfe;}
        @media (max-width: 1023px){
            .cf-split-shell{min-height:100vh;}
            .cf-side-light,.cf-side-dark{padding-top:1.5rem;padding-bottom:1.5rem;}
            .cf-directory-gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;height:auto;min-height:12rem;}
            .cf-directory-tile{position:static;width:100%;height:3.4rem;padding:.15rem;transform:none !important;}
        }
        @media (min-width: 1024px){.cf-split-shell{grid-template-columns:minmax(0,1fr) minmax(28rem,42vw);}}
        @keyframes cf-spin{to{transform:rotate(360deg);}}
    </style>
</head>
<body class="min-h-screen overflow-x-hidden overflow-y-auto bg-slate-100 font-sans text-slate-800 transition-colors dark:bg-slate-950 dark:text-slate-100">
    <div id="global_throbber" class="cf-throbber-overlay" aria-live="polite" aria-busy="false" aria-hidden="true">
        <span class="cf-throbber-spinner" aria-hidden="true"></span>
    </div>
    <main class="cf-split-shell">
        <section class="cf-side-light flex items-center px-6 py-4 sm:px-10 sm:py-6 lg:px-16 lg:py-8">
            <div class="relative z-10 mx-auto flex w-full max-w-2xl flex-col justify-between gap-6">
                <div class="max-w-lg">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-700 dark:text-emerald-300 cf-left-copy" data-left-portal>Client Portal</p>
                    <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-5xl cf-left-copy" data-left-heading>Welcome back.</h1>
                    <p class="mt-3 text-xl font-semibold tracking-tight text-slate-800 dark:text-slate-100 cf-left-copy" data-left-brand>CiteFlow Manager</p>
                    <p class="mt-4 text-lg leading-8 text-slate-600 dark:text-slate-300 cf-left-copy" data-left-description>Review updates, shared records, and portal activity in one clean client workspace.</p>
                </div>
                <div class="cf-illustration-card p-4 sm:p-5">
                    <?php if ($directoryLogoRows): ?>
                        <div class="cf-directory-gallery w-full" aria-label="Directory logo collage">
                            <?php $tileClasses = ['cf-directory-tile--hero','cf-directory-tile--wide','cf-directory-tile--tall','cf-directory-tile--mid','cf-directory-tile--small-a','cf-directory-tile--small-b','cf-directory-tile--small-c','cf-directory-tile--footer']; ?>
                            <?php foreach ($directoryLogoRows as $index => $directoryRow): ?>
                                <?php
                                    $tileClass = $tileClasses[$index % count($tileClasses)] ?? '';
                                    $directoryName = trim((string)($directoryRow['name'] ?? 'Directory'));
                                    $directoryLogoPath = trim((string)($directoryRow['logo_path'] ?? ''));
                                    $directoryInitial = strtoupper(substr($directoryName, 0, 1));
                                ?>
                                <div class="cf-directory-tile <?php echo e($tileClass); ?>" title="<?php echo e($directoryName); ?>">
                                    <?php if ($directoryLogoPath !== ''): ?>
                                        <img src="<?php echo e(public_asset_url($directoryLogoPath)); ?>" alt="<?php echo e($directoryName); ?> logo">
                                    <?php else: ?>
                                        <span class="cf-directory-fallback"><?php echo e($directoryInitial !== '' ? $directoryInitial : 'D'); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <section class="cf-side-dark is-client flex items-center justify-center px-6 py-4 sm:px-10 sm:py-6 lg:px-16 lg:py-8" data-login-column>
            <article class="cf-form-shell text-white">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <img src="<?php echo e($logoUrl); ?>" alt="CiteFlow Manager logo" class="h-12 w-auto max-w-[220px] brightness-0 invert">
                        <p class="mt-3 text-lg font-semibold tracking-tight text-white">CiteFlow Manager</p>
                        <div class="mt-4 cf-login-tabs">
                            <a class="cf-login-tab" href="<?php echo e($base); ?>/" data-login-toggle="staff">Staff login</a>
                            <a class="cf-login-tab cf-login-tab-active" href="<?php echo e($base); ?>/client_portal.php" data-login-toggle="client">Client portal</a>
                        </div>
                    </div>
                    <button id="theme_toggle" type="button" aria-label="Toggle dark and light mode" class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/15 dark:border-white/30 dark:bg-white/10 dark:text-white dark:hover:bg-white/20">
                        <span id="theme_toggle_label">Dark</span>
                    </button>
                </div>

                <?php if ($flash): ?>
                    <?php $isOk = ($flash['type'] ?? '') === 'ok'; ?>
                    <div class="mb-4 rounded-2xl border px-4 py-3 text-sm <?php echo $isOk ? 'border-emerald-300/50 bg-emerald-400/15 text-emerald-50' : 'border-rose-300/50 bg-rose-400/15 text-rose-50'; ?>">
                        <?php echo e((string)($flash['message'] ?? '')); ?>
                    </div>
                <?php endif; ?>

                <div class="cf-flip-stage" data-login-flip data-login-default="client">
                    <div class="cf-flip-card is-client" data-login-card>
                        <section class="cf-flip-face cf-flip-face-front">
                            <?php if ($hasUsers): ?>
                                <form method="post" action="<?php echo e($base); ?>/" class="space-y-3.5" data-inline-validation>
                                    <div data-field-validation>
                                        <label class="mb-2 block text-sm font-semibold text-white/88">Email Address</label>
                                        <div class="relative">
                                            <input class="w-full rounded-xl border border-white/15 bg-white px-4 py-3 pr-11 text-sm text-slate-900 placeholder-slate-400 outline-none transition focus:border-sky-300 focus:ring-2 focus:ring-sky-200/30" type="email" name="email" placeholder="you@example.com" data-validate="email" data-field-label="Email address" required>
                                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-300" data-validation-icon aria-hidden="true"></span>
                                        </div>
                                        <p class="mt-1 min-h-[1.25rem] text-xs font-medium text-white/70" data-validation-message aria-live="polite"></p>
                                    </div>
                                    <div data-field-validation>
                                        <label class="mb-2 block text-sm font-semibold text-white/88">Password</label>
                                        <div class="relative">
                                            <input class="w-full rounded-xl border border-white/15 bg-white px-4 py-3 pr-11 text-sm text-slate-900 placeholder-slate-400 outline-none transition focus:border-sky-300 focus:ring-2 focus:ring-sky-200/30" type="password" name="password" placeholder="••••••••" data-validate="password" data-field-label="Password" required>
                                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-300" data-validation-icon aria-hidden="true"></span>
                                        </div>
                                        <p class="mt-1 min-h-[1.25rem] text-xs font-medium text-white/70" data-validation-message aria-live="polite"></p>
                                    </div>
                                    <button class="mt-2 inline-flex min-w-[9rem] items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-sky-800 transition hover:bg-sky-50" type="submit">Login</button>
                                </form>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <div class="rounded-2xl border border-amber-200/35 bg-amber-300/10 px-4 py-3 text-sm text-amber-50">
                                        Create your first admin account to continue.
                                    </div>
                                    <a class="inline-flex w-full items-center justify-center rounded-xl bg-amber-400 px-4 py-3 text-sm font-semibold text-slate-900 transition hover:bg-amber-300" href="<?php echo e($base); ?>/setup.php">Create Admin Account</a>
                                </div>
                            <?php endif; ?>
                        </section>
                        <section class="cf-flip-face cf-flip-face-back">
                            <form method="post" class="space-y-3.5" data-inline-validation>
                                <div data-field-validation>
                                    <label class="mb-2 block text-sm font-semibold text-white/88">Email Address</label>
                                    <div class="relative">
                                        <input class="w-full rounded-xl border border-white/15 bg-white px-4 py-3 pr-11 text-sm text-slate-900 placeholder-slate-400 outline-none transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-200/30" type="email" name="email" placeholder="your@email.com" data-validate="email" data-field-label="Email address" required>
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-300" data-validation-icon aria-hidden="true"></span>
                                    </div>
                                    <p class="mt-1 min-h-[1.25rem] text-xs font-medium text-white/70" data-validation-message aria-live="polite"></p>
                                </div>
                                <div data-field-validation>
                                    <label class="mb-2 block text-sm font-semibold text-white/88">Portal Password</label>
                                    <div class="relative">
                                        <input class="w-full rounded-xl border border-white/15 bg-white px-4 py-3 pr-11 text-sm text-slate-900 placeholder-slate-400 outline-none transition focus:border-emerald-300 focus:ring-2 focus:ring-emerald-200/30" type="password" name="password" placeholder="••••••••" data-validate="password" data-field-label="Portal password" required>
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-300" data-validation-icon aria-hidden="true"></span>
                                    </div>
                                    <p class="mt-1 min-h-[1.25rem] text-xs font-medium text-white/70" data-validation-message aria-live="polite"></p>
                                </div>
                                <button class="mt-2 inline-flex min-w-[11rem] items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50" type="submit">Open Client Portal</button>
                            </form>
                        </section>
                    </div>
                </div>
            </article>
        </section>
    </main>
    <script>
        (() => {
            const root = document.documentElement;
            const toggle = document.getElementById('theme_toggle');
            const label = document.getElementById('theme_toggle_label');
            const throbber = document.getElementById('global_throbber');
            const loginColumn = document.querySelector('[data-login-column]');
            const flipStage = document.querySelector('[data-login-flip]');
            const flipCard = document.querySelector('[data-login-card]');
            const loginToggleLinks = Array.from(document.querySelectorAll('a[data-login-toggle]'));
            const checkIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="cf-validation-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.2 7.261a1 1 0 0 1-1.42 0l-3.3-3.327a1 1 0 1 1 1.42-1.408L8.8 11.84l6.49-6.544a1 1 0 0 1 1.414-.006Z" clip-rule="evenodd" /></svg>';
            const warningIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="cf-validation-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l5.58 9.92c.75 1.334-.213 2.981-1.742 2.981H4.42c-1.53 0-2.492-1.647-1.742-2.98l5.58-9.921ZM10 7a.75.75 0 0 0-.75.75v3.5a.75.75 0 0 0 1.5 0v-3.5A.75.75 0 0 0 10 7Zm0 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>';
            const leftPortal = document.querySelector('[data-left-portal]');
            const leftHeading = document.querySelector('[data-left-heading]');
            const leftBrand = document.querySelector('[data-left-brand]');
            const leftDescription = document.querySelector('[data-left-description]');
            const leftCopyNodes = Array.from(document.querySelectorAll('.cf-left-copy'));
            const directoryTiles = Array.from(document.querySelectorAll('.cf-directory-tile'));
            const tileClasses = ['cf-directory-tile--hero','cf-directory-tile--wide','cf-directory-tile--tall','cf-directory-tile--mid','cf-directory-tile--small-a','cf-directory-tile--small-b','cf-directory-tile--small-c','cf-directory-tile--footer'];
            const leftCopyByView = {
                staff: {
                    portal: 'Staff Portal',
                    heading: 'Welcome back.',
                    brand: 'CiteFlow Manager',
                    description: 'Manage citations, tasks, reports, and team activity from one focused workspace.'
                },
                client: {
                    portal: 'Client Portal',
                    heading: 'Welcome back.',
                    brand: 'CiteFlow Manager',
                    description: 'Review updates, shared records, and portal activity in one clean client workspace.'
                }
            };

            const validationFields = Array.from(document.querySelectorAll('input[data-validate]'));

            const setFieldState = (input, state, message) => {
                const wrapper = input.closest('[data-field-validation]');
                if (!wrapper) {
                    return;
                }

                const icon = wrapper.querySelector('[data-validation-icon]');
                const messageNode = wrapper.querySelector('[data-validation-message]');

                input.classList.remove('border-emerald-400', 'focus:border-emerald-500', 'focus:ring-emerald-400/20', 'border-amber-400', 'focus:border-amber-500', 'focus:ring-amber-400/20');
                if (icon) {
                    icon.classList.remove('text-emerald-500', 'text-amber-500', 'text-slate-300', 'dark:text-slate-500');
                }
                if (messageNode) {
                    messageNode.classList.remove('text-emerald-600', 'dark:text-emerald-300', 'text-amber-600', 'dark:text-amber-300', 'text-slate-500', 'dark:text-slate-400');
                }

                if (state === 'valid') {
                    input.classList.add('border-emerald-400', 'focus:border-emerald-500', 'focus:ring-emerald-400/20');
                    if (icon) {
                        icon.innerHTML = checkIcon;
                        icon.classList.add('text-emerald-500');
                    }
                    if (messageNode) {
                        messageNode.textContent = message;
                        messageNode.classList.add('text-emerald-600', 'dark:text-emerald-300');
                    }
                    return;
                }

                if (state === 'warning') {
                    input.classList.add('border-amber-400', 'focus:border-amber-500', 'focus:ring-amber-400/20');
                    if (icon) {
                        icon.innerHTML = warningIcon;
                        icon.classList.add('text-amber-500');
                    }
                    if (messageNode) {
                        messageNode.textContent = message;
                        messageNode.classList.add('text-amber-600', 'dark:text-amber-300');
                    }
                    return;
                }

                if (icon) {
                    icon.innerHTML = '';
                    icon.classList.add('text-slate-300', 'dark:text-slate-500');
                }
                if (messageNode) {
                    messageNode.textContent = '';
                    messageNode.classList.add('text-slate-500', 'dark:text-slate-400');
                }
            };

            const updateFieldValidation = (input, force = false) => {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const value = input.value.trim();
                const mode = input.dataset.validate || '';
                const labelText = input.dataset.fieldLabel || 'This field';
                const touched = force || input.dataset.touched === '1';

                if (value === '') {
                    setFieldState(input, touched ? 'warning' : 'idle', touched ? `${labelText} is required.` : '');
                    return;
                }

                if (mode === 'email') {
                    if (input.validity.valid) {
                        setFieldState(input, 'valid', 'Valid email format.');
                    } else {
                        setFieldState(input, 'warning', 'Enter a valid email address.');
                    }
                    return;
                }

                if (mode === 'password') {
                    setFieldState(input, 'valid', 'Password looks ready.');
                }
            };

            const showThrobber = () => {
                if (!throbber) {
                    return;
                }
                throbber.classList.add('is-active');
                throbber.setAttribute('aria-busy', 'true');
                throbber.setAttribute('aria-hidden', 'false');
            };

            const hideThrobber = () => {
                if (!throbber) {
                    return;
                }
                throbber.classList.remove('is-active');
                throbber.setAttribute('aria-busy', 'false');
                throbber.setAttribute('aria-hidden', 'true');
            };

            hideThrobber();
            window.addEventListener('pageshow', hideThrobber);

            const updateLeftCopy = (view) => {
                const copy = leftCopyByView[view] || leftCopyByView.client;
                leftCopyNodes.forEach((node) => node.classList.add('is-updating'));

                window.setTimeout(() => {
                    if (leftPortal) {
                        leftPortal.textContent = copy.portal;
                        leftPortal.classList.toggle('text-sky-600', view === 'staff');
                        leftPortal.classList.toggle('dark:text-sky-300', view === 'staff');
                        leftPortal.classList.toggle('text-emerald-700', view !== 'staff');
                        leftPortal.classList.toggle('dark:text-emerald-300', view !== 'staff');
                    }
                    if (leftHeading) {
                        leftHeading.textContent = copy.heading;
                    }
                    if (leftBrand) {
                        leftBrand.textContent = copy.brand;
                    }
                    if (leftDescription) {
                        leftDescription.textContent = copy.description;
                    }
                    leftCopyNodes.forEach((node) => node.classList.remove('is-updating'));
                }, 110);
            };

            const shuffleTileLayout = () => {
                if (directoryTiles.length <= 1) {
                    return;
                }

                const classPool = tileClasses.slice(0, directoryTiles.length);
                for (let index = classPool.length - 1; index > 0; index -= 1) {
                    const swapIndex = Math.floor(Math.random() * (index + 1));
                    const temp = classPool[index];
                    classPool[index] = classPool[swapIndex];
                    classPool[swapIndex] = temp;
                }

                directoryTiles.forEach((tile, index) => {
                    tile.classList.remove(...tileClasses);
                    tile.classList.add(classPool[index % classPool.length]);
                });
            };

            const setLoginView = (view, updateHash = true) => {
                if (!flipCard) {
                    return;
                }

                const isClientView = view === 'client';
                loginColumn?.classList.toggle('is-client', isClientView);
                flipCard.classList.toggle('is-client', isClientView);
                updateLeftCopy(isClientView ? 'client' : 'staff');
                shuffleTileLayout();
                loginToggleLinks.forEach((link) => {
                    const isActive = link.dataset.loginToggle === view;
                    link.classList.toggle('cf-login-tab-active', isActive);
                    link.setAttribute('aria-current', isActive ? 'page' : 'false');
                });

                if (updateHash) {
                    const targetHash = isClientView ? '#client' : '#staff';
                    if (window.location.hash !== targetHash) {
                        window.history.replaceState(null, '', targetHash);
                    }
                }
            };

            if (flipCard && loginToggleLinks.length > 0) {
                const defaultView = flipStage?.getAttribute('data-login-default') === 'client' ? 'client' : 'staff';
                const hashView = window.location.hash === '#client' ? 'client' : (window.location.hash === '#staff' ? 'staff' : defaultView);
                setLoginView(hashView, false);

                loginToggleLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        const view = link.dataset.loginToggle === 'client' ? 'client' : 'staff';
                        setLoginView(view);
                    });
                });
            }

            validationFields.forEach((input) => {
                setFieldState(input, 'idle', '');
                input.addEventListener('input', () => {
                    input.dataset.touched = '1';
                    updateFieldValidation(input, true);
                });
                input.addEventListener('blur', () => {
                    input.dataset.touched = '1';
                    updateFieldValidation(input, true);
                });
            });

            document.addEventListener('invalid', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement) || !target.matches('input[data-validate]')) {
                    return;
                }
                target.dataset.touched = '1';
                updateFieldValidation(target, true);
            }, true);

            const activateSubmitSpinner = (form, submitter) => {
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }
                if (form.getAttribute('data-submitting') === '1') {
                    return;
                }
                form.setAttribute('data-submitting', '1');

                let actionButton = submitter;
                if (!(actionButton instanceof HTMLButtonElement) && !(actionButton instanceof HTMLInputElement)) {
                    actionButton = form.querySelector('button[type="submit"],input[type="submit"]');
                }

                if (actionButton instanceof HTMLButtonElement) {
                    if (actionButton.getAttribute('data-spinner-applied') !== '1') {
                        actionButton.style.minWidth = `${actionButton.offsetWidth}px`;
                        actionButton.dataset.originalHtml = actionButton.innerHTML;
                        actionButton.innerHTML = '<span class="cf-inline-spinner" aria-hidden="true"></span>';
                        actionButton.setAttribute('data-spinner-applied', '1');
                    }
                    actionButton.classList.add('is-submitting');
                    actionButton.disabled = true;
                } else if (actionButton instanceof HTMLInputElement) {
                    actionButton.classList.add('is-submitting');
                    actionButton.disabled = true;
                }
            };

            document.addEventListener('submit', (event) => {
                activateSubmitSpinner(event.target, event.submitter || null);
                showThrobber();
            }, true);
            document.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                const link = target.closest('a[href]');
                if (!link || !(link instanceof HTMLAnchorElement)) {
                    return;
                }

                if (link.hasAttribute('data-login-toggle')) {
                    return;
                }

                const href = link.getAttribute('href') || '';
                if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                    return;
                }

                if (link.target === '_blank' || link.hasAttribute('download')) {
                    return;
                }

                showThrobber();
            }, true);

            if (!toggle || !label) {
                return;
            }

            const syncLabel = () => {
                label.textContent = root.classList.contains('dark') ? 'Light' : 'Dark';
            };

            syncLabel();
            toggle.addEventListener('click', () => {
                root.classList.toggle('dark');
                localStorage.setItem('cf-login-theme', root.classList.contains('dark') ? 'dark' : 'light');
                syncLabel();
            });
        })();
    </script>
</body>
</html>
