<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';

function render_nav_link(string $href, string $label, string $icon, bool $active, int $badgeCount = 0): string
{
    $baseClass = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
    $activeClass = $active
        ? ' bg-brand-100 text-brand-700 dark:bg-brand-500/20 dark:text-brand-200'
        : ' text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100';

    $badgeHtml = '';
    if ($badgeCount > 0) {
        $badgeValue = min($badgeCount, 99);
        $badgeHtml = '<span class="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">' . e((string)$badgeValue) . '</span>';
    }

    return '<a class="' . e($baseClass . $activeClass) . '" href="' . e($href) . '">' . $icon . '<span class="sidebar-label">' . e($label) . '</span>' . $badgeHtml . '</a>';
}

function citeflow_help_tutorials(): array
{
    return [
        'dashboard.php' => [
            'title' => 'Dashboard Guide',
            'summary' => 'Use the dashboard to review performance, progress, and recent operational activity before drilling into daily work.',
            'steps' => [
                'Review the summary cards to understand total workload and current status counts.',
                'Use date filters to narrow the reporting period.',
                'Check recent activity, trends, and highlighted issues that need attention.',
                'Open Businesses, Tasks, or Reports from the navigation when deeper action is needed.',
            ],
            'tips' => [
                'Start each workday here to spot stalled tasks and newly updated records.',
                'Use the dashboard as the operational summary, not the editing workspace.',
            ],
        ],
        'businesses.php' => [
            'title' => 'Businesses Guide',
            'summary' => 'This page manages the master list of businesses and acts as the entry point for citation execution per location.',
            'steps' => [
                'Use the search bar or filters to quickly locate the business you need.',
                'Click Add Business to create a new record and fill in all profile details.',
                'Edit an existing business when its name, address, phone, or website changes.',
                'Open Location Manager from a business row to begin or review citation work for that location.',
                'Link each business to the correct client account so it appears in client portal reporting.',
                'Archive or deactivate businesses that are no longer active to keep the list clean.',
            ],
            'tips' => [
                'Accurate NAP data on the business profile is the foundation for all citation work.',
                'Use the export function to generate a quick external business list for client updates.',
                'A business can have multiple locations — use Location Manager to manage each one separately.',
            ],
        ],
        'business_form.php' => [
            'title' => 'Business Form Guide',
            'summary' => 'Use this form to create or update a business profile that will feed citation and client-facing workflows.',
            'steps' => [
                'Enter the business name, phone, email, website, and full address.',
                'Add categories, GBP link, branding, and other supporting profile fields.',
                'Review the information carefully for NAP consistency.',
                'Save the business, then continue to Location Manager if citation work should begin.',
            ],
            'tips' => [
                'Consistent NAP data reduces downstream citation errors.',
            ],
        ],
        'directories.php' => [
            'title' => 'Directories Guide',
            'summary' => 'This page stores the directory library used as the reference source for citation targets.',
            'steps' => [
                'Use filters to find directories by type, region, pricing, or difficulty.',
                'Add a new directory when a submission target is missing.',
                'Edit submission URLs and requirements when directory details change.',
                'Remove duplicates or outdated entries to keep the library clean.',
            ],
            'tips' => [
                'A clean directory library makes task creation faster and more reliable.',
            ],
        ],
        'directory_form.php' => [
            'title' => 'Directory Form Guide',
            'summary' => 'Use this form to define where citations can be submitted and what kind of target each directory is.',
            'steps' => [
                'Add the directory name, website, and submission URL.',
                'Choose the directory type, region, and pricing model.',
                'Set notes or requirements if special submission steps are needed.',
                'Save the record so it becomes available in listing workflows.',
            ],
            'tips' => [
                'Store clear notes when a directory has manual review or login requirements.',
            ],
        ],
        'tasks.php' => [
            'title' => 'Tasks Guide',
            'summary' => 'This page organizes citation work by operational status so staff can track what is active, blocked, or complete.',
            'steps' => [
                'Review the tasks grouped by their current status column.',
                'Use filters to narrow tasks by assignee, business, or priority.',
                'Open a task card to inspect details, submission notes, and next required action.',
                'Update the status, add submission notes, and attach proof files after completing work.',
                'Reassign or escalate tasks that are blocked or overdue.',
                'Use bulk updates when a batch of similar tasks are completed together.',
            ],
            'tips' => [
                'Move tasks forward consistently so dashboard reporting stays accurate and current.',
                'Always add a note when changing status so the task history is clear.',
                'Blocked tasks should have a note explaining the issue so the team can troubleshoot.',
            ],
        ],
        'location_manager.php' => [
            'title' => 'Location Manager Guide',
            'summary' => 'Location Manager is the main work area for citation execution, directory selection, and listing updates for a business.',
            'steps' => [
                'Review the business profile at the top of the page.',
                'Inspect existing citation rows and their current statuses.',
                'Add or update directory listings tied to the selected business.',
                'Record URLs, notes, proof files, and NAP issues as work progresses.',
            ],
            'tips' => [
                'Use this page as the primary workspace when actively fulfilling listings.',
                'Check NAP warnings before marking a citation as live.',
            ],
        ],
        'submission_helper.php' => [
            'title' => 'Submission Helper Guide',
            'summary' => 'Submission Helper gathers the key business and directory information needed to complete a citation submission quickly.',
            'steps' => [
                'Open the helper from the task or Location Manager context.',
                'Review the pre-filled business NAP data and verify it matches the live profile.',
                'Use the copy buttons to transfer individual fields into the target directory form.',
                'Check the directory-specific requirements panel for any special submission steps.',
                'After submitting, return to the task record and update the status and submission URL.',
            ],
            'tips' => [
                'Always verify the copied data is current before submitting to a directory.',
                'Use the helper for every submission to maintain consistency and reduce errors.',
                'Note any special directory login requirements in the task notes before closing.',
            ],
        ],
        'clients.php' => [
            'title' => 'Clients Guide',
            'summary' => 'This page manages client records that group businesses and provide access to the client portal.',
            'steps' => [
                'Create a new client record with the client name, email, phone, and account notes.',
                'Set the portal access password so the client can log into their dashboard.',
                'Link one or more businesses to the client account so their listings appear in the portal.',
                'Update client contact or portal details whenever account information changes.',
                'Review client records before sharing portal access details to ensure accuracy.',
                'Deactivate a client account when the engagement ends.',
            ],
            'tips' => [
                'Verify portal email and password details are correct before sharing with the client.',
                'Each client can have multiple businesses linked — use the linking feature accordingly.',
                'Keep client notes updated with key account details for the whole team to reference.',
            ],
        ],
        'client_form.php' => [
            'title' => 'Client Form Guide',
            'summary' => 'Use this form to create or edit a client account and define the portal access information used by the client dashboard.',
            'steps' => [
                'Enter the client name, primary email, phone, and notes.',
                'Set or update the portal password for client access.',
                'Save the client record before linking businesses.',
            ],
            'tips' => [
                'Share portal credentials securely outside the system.',
            ],
        ],
        'users.php' => [
            'title' => 'Users Guide',
            'summary' => 'Admins use this page to create staff accounts, assign roles, and control internal access levels.',
            'steps' => [
                'Create a new user with the correct name, email, and role.',
                'Adjust permissions if a user needs limited access.',
                'Deactivate accounts that should no longer use the system.',
            ],
            'tips' => [
                'Keep user permissions aligned with actual staff responsibilities.',
            ],
        ],
        'notifications.php' => [
            'title' => 'Notifications Guide',
            'summary' => 'Use notifications to review system reminders, workflow updates, and items that need follow-up.',
            'steps' => [
                'Open the page or notification menu to review unread items.',
                'Open the related record directly from each notification when needed.',
                'Mark items as read when the follow-up is complete.',
            ],
            'tips' => [
                'Treat notifications as prompts for action, not long-term storage.',
            ],
        ],
        'activity_logs.php' => [
            'title' => 'Activity Logs Guide',
            'summary' => 'Activity Logs show who made a change, what changed, and when the action happened.',
            'steps' => [
                'Use filters to narrow logs by user, entity type, or action.',
                'Review the details column for more context on important changes.',
                'Use this page for audit checks and troubleshooting.',
            ],
            'tips' => [
                'This is the best place to verify ownership of recent updates.',
            ],
        ],
        'live_user_tracking.php' => [
            'title' => 'Live User Tracking Guide',
            'summary' => 'Admins can monitor active sessions and see where staff are currently working in the application.',
            'steps' => [
                'Open the page to review active users and their current locations.',
                'Watch the auto-refresh feed to see live updates.',
                'Use this view to understand workload coverage and current activity.',
            ],
            'tips' => [
                'This page supports supervision, not task editing.',
            ],
        ],
        'reports.php' => [
            'title' => 'Reports Guide',
            'summary' => 'Reports help you summarize performance, identify gaps, and communicate progress at a higher level.',
            'steps' => [
                'Choose the metric or section you want to review.',
                'Use the filters to narrow the report scope if available.',
                'Open detail views to inspect supporting rows behind each metric.',
            ],
            'tips' => [
                'Use reports for client updates and management reviews rather than record editing.',
            ],
        ],
        'global_search.php' => [
            'title' => 'Search Guide',
            'summary' => 'Global search helps you quickly find businesses, directories, tasks, and other records without navigating through each page manually.',
            'steps' => [
                'Enter a business name, directory name, or related keyword.',
                'Review the grouped results and open the most relevant match.',
                'Use search when you already know the record you want to access.',
            ],
            'tips' => [
                'Search is best for direct access, while dashboard and lists are better for review workflows.',
            ],
        ],
        'client_dashboard.php' => [
            'title' => 'Client Dashboard Guide',
            'summary' => 'Clients use this page to view overall listing progress, business coverage, and recent citation activity.',
            'steps' => [
                'Review the top summary metrics for overall progress.',
                'Select a business if you want a more specific view.',
                'Inspect recent citation updates and live status counts.',
            ],
            'tips' => [
                'This page is read-only for clients and designed for visibility rather than internal operations.',
            ],
        ],
        'client_login.php' => [
            'title' => 'Client Portal Login Guide',
            'summary' => 'Clients sign in here using the credentials provided by their account manager.',
            'steps' => [
                'Enter the primary client email address.',
                'Enter the portal password.',
                'Click Open Client Portal to continue.',
            ],
            'tips' => [
                'Clients should contact their account manager if login credentials are missing or outdated.',
            ],
        ],
        'index.php' => [
            'title' => 'Staff Login Guide',
            'summary' => 'Staff members use this page to access the internal operations platform.',
            'steps' => [
                'Enter your staff email address and password.',
                'Click Sign In to Dashboard.',
                'If successful, continue to the main dashboard and start from the day’s priorities.',
            ],
            'tips' => [
                'Use the client portal only if you are signing in as a client, not as staff.',
                'Contact your admin if your login credentials need to be reset.',
            ],
        ],
        'task_form.php' => [
            'title' => 'Task Form Guide',
            'summary' => 'Use this form to create or edit a citation task and link it to a business location and target directory.',
            'steps' => [
                'Select the business and directory this task is associated with.',
                'Set the task type, priority level, and current status.',
                'Add notes, expected completion date, and any special instructions.',
                'Assign the task to the correct staff member if applicable.',
                'Save the task and verify it appears in the correct status group on the Tasks page.',
            ],
            'tips' => [
                'Fill in notes before saving to reduce back-and-forth communication later.',
                'Double-check the directory assignment to ensure work goes to the right listing target.',
                'Priority settings affect how tasks appear in filtered and sorted views.',
            ],
        ],
        'task_view.php' => [
            'title' => 'Task Detail Guide',
            'summary' => 'This view shows full details of a citation task including its history, current status, and submission records.',
            'steps' => [
                'Review the top section for current status, assignee, and linked business.',
                'Check the submission details area for recorded URLs, proof files, and notes.',
                'Use the edit button to update the status, notes, or submission outcome.',
                'Review the change history to see all actions taken on this task.',
                'Return to Tasks or Location Manager to continue related work.',
            ],
            'tips' => [
                'This page is ideal for a final review before marking a task complete.',
                'Keep submission URLs and proof files updated so the record is audit-ready.',
            ],
        ],
        'audits.php' => [
            'title' => 'Audits Guide',
            'summary' => 'Audits provide a structured record of changes and quality checks for compliance and operational oversight.',
            'steps' => [
                'Filter by date range, entity type, or action to narrow the audit scope.',
                'Click a row to view the full change detail for that entry.',
                'Verify that updates were made correctly and by the right person.',
                'Export audit data when external reporting or compliance records are needed.',
            ],
            'tips' => [
                'Run regular audit checks after bulk task updates to confirm accuracy.',
                'Use audit data to support client reporting on citation progress history.',
            ],
        ],
        'autofill_packs.php' => [
            'title' => 'Autofill Packs Guide',
            'summary' => 'Autofill packs store reusable business data sets that can be quickly inserted into submission forms and directory workflows.',
            'steps' => [
                'Browse existing packs to see what data sets are already available.',
                'Create a new pack by linking it to a business and filling in all required fields.',
                'Test the pack in a submission workflow to verify all data is correct.',
                'Update the pack whenever core business details like address or phone change.',
            ],
            'tips' => [
                'Keeping packs current reduces submission errors significantly.',
                'Use packs to standardize NAP data before running bulk citation workflows.',
            ],
        ],
        'autofill_helper.php' => [
            'title' => 'Autofill Helper Guide',
            'summary' => 'The autofill helper streamlines copying business data into directory submission forms accurately.',
            'steps' => [
                'Open the helper from a business or task context.',
                'Review the pre-filled business fields for accuracy.',
                'Copy individual fields into the target directory form as needed.',
                'Return to the task view to update status after the submission is complete.',
            ],
            'tips' => [
                'Always verify the copied data matches the current live business profile.',
                'Use the helper consistently for every submission to reduce manual errors.',
            ],
        ],
        'sticky_notes.php' => [
            'title' => 'Sticky Notes Guide',
            'summary' => 'Sticky notes let staff leave contextual reminders, client notes, or workflow pointers visible across the team.',
            'steps' => [
                'Create a note and attach it to a relevant business, task, or general context.',
                'Set a color or priority label to help notes stand out in busy views.',
                'Edit or archive a note when it is no longer relevant.',
                'Review notes at the start of each workday to catch pending reminders.',
            ],
            'tips' => [
                'Use sticky notes for short-term reminders, not as a replacement for task records.',
                'Archive completed notes to keep the board clean and focused.',
            ],
        ],
        'setup.php' => [
            'title' => 'System Setup Guide',
            'summary' => 'System setup covers key configuration including application settings, branding, and operational defaults.',
            'steps' => [
                'Review the current system configuration before making any changes.',
                'Update the application name, base URL, or branding options as needed.',
                'Save settings and verify the application reflects changes correctly.',
                'Revisit setup when infrastructure changes like domain or logo updates occur.',
            ],
            'tips' => [
                'Only admins should modify setup settings — changes affect the entire application.',
                'Test after saving to confirm no display or routing issues were introduced.',
            ],
        ],
        'user_tracking.php' => [
            'title' => 'User Tracking Guide',
            'summary' => 'User tracking logs page visits and session activity so admins can review staff usage patterns over time.',
            'steps' => [
                'Filter by user, date range, or page to narrow the tracking records.',
                'Review session durations to understand where time is being spent.',
                'Use the data to identify inactive accounts or unusual access patterns.',
            ],
            'tips' => [
                'Tracking data is read-only and intended for supervision, not editing.',
                'Combine this with Live User Tracking for a complete staff activity picture.',
            ],
        ],
    ];
}

function get_citeflow_help_tutorial(string $currentFile): ?array
{
    $tutorials = citeflow_help_tutorials();

    return $tutorials[$currentFile] ?? null;
}

function get_citeflow_help_audience(?array $user, ?array $client): string
{
    if ($client !== null) {
        return 'client';
    }

    if ($user !== null && is_admin()) {
        return 'admin';
    }

    if ($user !== null) {
        return 'employee';
    }

    return 'guest';
}

function build_citeflow_help_tutorial(string $currentFile, string $audience): ?array
{
    $tutorial = get_citeflow_help_tutorial($currentFile);
    if ($tutorial === null) {
        return null;
    }

    $roleContent = [
        'admin' => [
            'label' => 'Admin Focus',
            'summary' => 'As an admin, focus on oversight, setup quality, user management, client visibility, and audit accuracy.',
            'tips' => [
                'Use logs, notifications, and live tracking to supervise activity and catch issues early.',
                'Keep clients, users, and directory data clean so operational reporting stays reliable.',
            ],
        ],
        'employee' => [
            'label' => 'Employee Focus',
            'summary' => 'As an employee, focus on accurate record updates, consistent task movement, and clean submission notes.',
            'tips' => [
                'Update statuses immediately after completing work so dashboards stay current.',
                'Use Location Manager and Submission Helper as your main daily execution tools.',
            ],
        ],
        'client' => [
            'label' => 'Client Focus',
            'summary' => 'As a client, use the portal to monitor progress, review live counts, and check recent listing updates.',
            'tips' => [
                'The client portal is read-only and intended for visibility rather than internal operations.',
                'Contact your account manager if business or status details look incorrect.',
            ],
        ],
        'guest' => [
            'label' => 'Access Guide',
            'summary' => 'Use the correct login area to enter the system based on whether you are staff or a client.',
            'tips' => [
                'Staff should use the main login page.',
                'Clients should use the dedicated client portal.',
            ],
        ],
    ];

    $roleInfo = $roleContent[$audience] ?? $roleContent['guest'];
    $tutorial['audience'] = $audience;
    $tutorial['role_label'] = $roleInfo['label'];
    $tutorial['role_summary'] = $roleInfo['summary'];
    $tutorial['role_tips'] = $roleInfo['tips'];
    $tutorial['help_key'] = $audience . ':' . $currentFile;

    return $tutorial;
}

function render_header(string $title): void
{
    $config = app_config();
    $user = current_user();
    $client = current_client();
    $base = $config['base_url'];
    $flash = flash();
    $logoUrl = $base . '/branding/MDR_logo.png';
    $faviconUrl = $base . '/branding/MDR_logo.png';
    $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $currentFile = strtolower((string)basename($requestPath));
    $titleLabel = trim($title) !== '' ? $title : 'Dashboard';
    $helpAudience = get_citeflow_help_audience($user, $client);
    $helpTutorial = build_citeflow_help_tutorial($currentFile, $helpAudience);
    $helpSeenAt = trim((string)($user['guide_seen_at'] ?? $client['guide_seen_at'] ?? ''));
    $helpDismissedAt = trim((string)($user['guide_dismissed_at'] ?? $client['guide_dismissed_at'] ?? ''));
    $helpShouldAutoShow = $helpTutorial !== null && ($user !== null || $client !== null) && $helpSeenAt === '' && $helpDismissedAt === '';
    $isAdminUser = $user ? is_admin() : false;
    $notificationPreviewRows = $user && !$isAdminUser ? recent_notifications($user, 0) : [];
    $notificationReturnUrl = (string)($_SERVER['REQUEST_URI'] ?? ($base . '/dashboard.php'));

    if ($user) {
        sync_operational_notifications($user);
        update_user_presence($user, $titleLabel);
    } elseif ($client) {
        update_client_presence($client, $titleLabel);
    }
    $notificationCount = $user ? unread_notification_count($user) : 0;

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - ' . e($config['app_name']) . '</title>';
    echo '<link rel="icon" type="image/png" href="' . e($faviconUrl) . '">';
    echo '<link rel="apple-touch-icon" href="' . e($faviconUrl) . '">';
    echo '<script>(function(){try{var d=document.documentElement;var m=localStorage.getItem("cf-theme-mode");var a=localStorage.getItem("cf-theme-accent")||"indigo";var c=localStorage.getItem("cf-sidebar-compact");if(m==="dark"){d.classList.add("dark");}d.classList.add("theme-"+a);if(c==="on"){d.classList.add("sidebar-compact");}}catch(e){}})()</script>';
    echo '<style>:root{--cf-bg-light:#f1f5f9;--cf-bg-dark:#020617;--cf-bg-grad-light:radial-gradient(circle at 8% 10%,rgba(125,211,252,.22),transparent 33%),radial-gradient(circle at 92% 86%,rgba(167,139,250,.14),transparent 38%),linear-gradient(180deg,#f8fafc 0%,#eef2ff 100%);--cf-bg-grad-dark:radial-gradient(circle at 10% 12%,rgba(56,189,248,.18),transparent 30%),radial-gradient(circle at 88% 84%,rgba(129,140,248,.14),transparent 36%),linear-gradient(180deg,#020617 0%,#0b1120 100%);}html{background-color:var(--cf-bg-light);background-image:var(--cf-bg-grad-light);color:#1e293b;}html.dark{background-color:var(--cf-bg-dark) !important;background-image:var(--cf-bg-grad-dark) !important;color:#e2e8f0 !important;}html.dark body{background-color:var(--cf-bg-dark);background-image:var(--cf-bg-grad-dark);color:#e2e8f0;}</style>';
    echo '<script src="' . e($base) . '/tailwind.js"></script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<script>tailwind.config={darkMode:"class",theme:{extend:{fontFamily:{sans:["Manrope","ui-sans-serif","sans-serif"]},colors:{brand:{50:"rgb(var(--brand-50) / <alpha-value>)",100:"rgb(var(--brand-100) / <alpha-value>)",500:"rgb(var(--brand-500) / <alpha-value>)",600:"rgb(var(--brand-600) / <alpha-value>)",700:"rgb(var(--brand-700) / <alpha-value>)"}}}}};</script>';
    echo '<style>
:root{--brand-50:238 242 255;--brand-100:224 231 255;--brand-500:99 102 241;--brand-600:79 70 229;--brand-700:67 56 202;}
body.theme-emerald,html.theme-emerald{--brand-50:236 253 245;--brand-100:209 250 229;--brand-500:16 185 129;--brand-600:5 150 105;--brand-700:4 120 87;}
body.theme-rose,html.theme-rose{--brand-50:255 241 242;--brand-100:255 228 230;--brand-500:244 63 94;--brand-600:225 29 72;--brand-700:190 18 60;}
body.theme-indigo,html.theme-indigo{--brand-50:238 242 255;--brand-100:224 231 255;--brand-500:99 102 241;--brand-600:79 70 229;--brand-700:67 56 202;}
body.theme-cyan,html.theme-cyan{--brand-50:236 254 255;--brand-100:207 250 254;--brand-500:6 182 212;--brand-600:8 145 178;--brand-700:14 116 144;}
body.theme-amber,html.theme-amber{--brand-50:255 251 235;--brand-100:254 243 199;--brand-500:245 158 11;--brand-600:217 119 6;--brand-700:180 83 9;}
body.theme-slate,html.theme-slate{--brand-50:248 250 252;--brand-100:241 245 249;--brand-500:100 116 139;--brand-600:71 85 105;--brand-700:51 65 85;}
body{background-color:var(--cf-bg-light);background-image:var(--cf-bg-grad-light);color:#1e293b;}
body.dark{background-color:var(--cf-bg-dark);background-image:var(--cf-bg-grad-dark);color:#e2e8f0;}
html.dark body{background-color:var(--cf-bg-dark);background-image:var(--cf-bg-grad-dark);color:#e2e8f0;}
html.dark .bg-white{background-color:#0f172a !important;}
html.dark .bg-slate-50{background-color:#0b1220 !important;}
html.dark .border-slate-100,html.dark .border-slate-200,html.dark .border-slate-300{border-color:#1e293b !important;}
html.dark .text-slate-900{color:#f8fafc !important;}
html.dark .text-slate-800{color:#e2e8f0 !important;}
html.dark .text-slate-700{color:#cbd5e1 !important;}
html.dark .text-slate-600,html.dark .text-slate-500{color:#94a3b8 !important;}
html.dark input,html.dark select,html.dark textarea{background-color:#0b1220 !important;color:#e2e8f0 !important;border-color:#334155 !important;}
html.dark .hover\:bg-slate-50:hover{background-color:#1e293b !important;}
html.dark .hover\:bg-slate-100:hover{background-color:#1e293b !important;}
html.dark .bg-slate-900{background-color:rgb(var(--brand-600)) !important;}
html.dark .hover\:bg-slate-950:hover{background-color:rgb(var(--brand-700)) !important;}
body.sidebar-compact #app_sidebar{width:5.5rem;}
body.sidebar-compact #app_content_wrap{padding-left:5.5rem;}
body.sidebar-compact .sidebar-label,body.sidebar-compact .sidebar-subtitle,body.sidebar-compact .sidebar-user{display:none;}
body.sidebar-compact #app_sidebar nav a{justify-content:center;}
body.sidebar-compact #app_sidebar nav a svg{margin-right:0;}
@media (max-width:1023px){
    body.sidebar-compact #app_content_wrap{padding-left:0;}
}
.cf-tour-active{outline:2px solid rgb(var(--brand-600)) !important;outline-offset:3px;background-color:rgb(var(--brand-50)) !important;border-radius:1rem;}
.cf-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;}
.cf-table{width:100%;}
.cf-throbber-overlay{position:fixed;inset:0;z-index:120;display:flex;align-items:center;justify-content:center;background:rgba(2,6,23,0.45);backdrop-filter:blur(2px);opacity:0;visibility:hidden;transition:opacity .18s ease,visibility .18s ease;pointer-events:none;}
.cf-throbber-overlay.is-active{opacity:1;visibility:visible;pointer-events:auto;}
.cf-throbber-spinner{width:2.4rem;height:2.4rem;border:3px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:999px;animation:cf-spin .75s linear infinite;filter:drop-shadow(0 4px 14px rgba(2,6,23,.35));}
.cf-inline-spinner{display:inline-block;width:1rem;height:1rem;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:999px;animation:cf-spin .75s linear infinite;vertical-align:middle;}
button.is-submitting,input.is-submitting{pointer-events:none;opacity:.92;}
@keyframes cf-spin{to{transform:rotate(360deg);}}
@media (max-width:640px){
    .cf-table-wrap{margin-left:-0.5rem;margin-right:-0.5rem;padding-left:0.5rem;padding-right:0.5rem;}
    .cf-table th,.cf-table td{padding-left:0.75rem !important;padding-right:0.75rem !important;}
    .cf-table th{white-space:nowrap;}
    .cf-mobile-stack{display:grid;gap:0.75rem;}
    .cf-mobile-stack > *{width:100%;}
}
</style>';
    echo '</head><body class="min-h-screen bg-slate-100 text-slate-800 font-sans">';
    echo '<div id="global_throbber" class="cf-throbber-overlay" aria-live="polite" aria-busy="false" aria-hidden="true">';
    echo '<span class="cf-throbber-spinner" aria-hidden="true"></span>';
    echo '</div>';

    if ($user) {
        $dashboardIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>';
        $businessIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 9h.01"/><path d="M9 12h.01"/><path d="M9 15h.01"/><path d="M15 9h.01"/><path d="M15 12h.01"/><path d="M15 15h.01"/></svg>';
        $directoryIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18"/><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M8 11h8"/><path d="M8 15h6"/></svg>';
        $taskIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
        $reportIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/></svg>';
        $auditIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg>';
        $notificationIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>';
        $clientIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M7 20h10"/><path d="M9 8h6"/><path d="M8 12h8"/></svg>';
        $usersIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="4"/><path d="M17 11a4 4 0 1 0 0-8"/><path d="M3 21a6 6 0 0 1 12 0"/><path d="M17 21a6 6 0 0 0-3-5.2"/></svg>';
        $logsIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg>';
        $liveTrackingIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l2 5 4-10 2 5h6"/><circle cx="5" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>';
        $commentsIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>';
        $isDashboardRoute = in_array($currentFile, ['dashboard.php'], true);
        $isBusinessRoute = in_array($currentFile, ['businesses.php', 'business_form.php', 'location_manager.php', 'submission_helper.php'], true);
        $isDirectoryRoute = in_array($currentFile, ['directories.php', 'directory_form.php'], true);
        $isTaskRoute = in_array($currentFile, ['tasks.php'], true);
        $isNotificationRoute = in_array($currentFile, ['notifications.php'], true);
        $isAuditRoute = in_array($currentFile, ['audits.php'], true);
        $isClientRoute = in_array($currentFile, ['clients.php', 'client_form.php'], true);
        $isUsersRoute = in_array($currentFile, ['users.php'], true);
        $isLogsRoute = in_array($currentFile, ['activity_logs.php'], true);
        $isLiveTrackingRoute = in_array($currentFile, ['live_user_tracking.php'], true);
        $isClientCommentsRoute = in_array($currentFile, ['client_citation_comments.php'], true);

        $pendingClientCommentsCount = 0;
        if ($isAdminUser || has_permission('location_manager')) {
            try {
                $pendingSql = 'SELECT COUNT(*) AS c
                               FROM client_citation_comments ccc
                               INNER JOIN listing_tasks lt ON lt.id = ccc.listing_task_id
                               WHERE (ccc.resolution_status IS NULL OR ccc.resolution_status <> "updated")';
                $pendingParams = [];

                if (!$isAdminUser) {
                    $pendingSql .= ' AND lt.assigned_to = ?';
                    $pendingParams[] = (int)($user['id'] ?? 0);
                }

                $pendingStmt = db()->prepare($pendingSql);
                $pendingStmt->execute($pendingParams);
                $pendingClientCommentsCount = (int)($pendingStmt->fetch()['c'] ?? 0);
            } catch (Throwable $e) {
                // Fallback for older schemas before resolution_status is available.
                $pendingSql = 'SELECT COUNT(*) AS c
                               FROM client_citation_comments ccc
                               INNER JOIN listing_tasks lt ON lt.id = ccc.listing_task_id
                               WHERE 1 = 1';
                $pendingParams = [];

                if (!$isAdminUser) {
                    $pendingSql .= ' AND lt.assigned_to = ?';
                    $pendingParams[] = (int)($user['id'] ?? 0);
                }

                $pendingStmt = db()->prepare($pendingSql);
                $pendingStmt->execute($pendingParams);
                $pendingClientCommentsCount = (int)($pendingStmt->fetch()['c'] ?? 0);
            }
        }

        echo '<div class="h-screen overflow-hidden">';
        echo '<div id="sidebar_overlay" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>';
        echo '<aside id="app_sidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-slate-200 bg-white/95 p-4 backdrop-blur transition-transform duration-200 dark:border-slate-800 dark:bg-slate-900/95 lg:translate-x-0">';
        echo '<div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-4 dark:border-slate-800">';
        echo '<div class="flex items-center gap-3">';
        echo '<img src="' . e($logoUrl) . '" alt="CiteFlow Manager logo" class="h-11 w-11 rounded-xl border border-slate-200 object-cover dark:border-slate-700">';
        echo '<div><p class="sidebar-label text-sm font-extrabold text-slate-900 dark:text-slate-100">CiteFlow Manager</p><p class="sidebar-subtitle text-xs text-slate-500 dark:text-slate-400">Smarter Listings, Better Reach</p></div>';
        echo '</div>';
        echo '</div>';

        echo '<nav class="mt-5 space-y-1">';
        if (has_permission('dashboard')) {
            echo render_nav_link($base . '/dashboard.php', 'Dashboard', $dashboardIcon, $isDashboardRoute);
        }
        if (has_permission('businesses')) {
            echo render_nav_link($base . '/businesses.php', 'Businesses', $businessIcon, $isBusinessRoute);
        }
        if (!$isAdminUser && has_permission('location_manager')) {
            echo render_nav_link($base . '/tasks.php', 'My Assigned Citations', $taskIcon, $isTaskRoute);
        }
        if (has_permission('directories')) {
            echo render_nav_link($base . '/directories.php', 'Directories', $directoryIcon, $isDirectoryRoute);
        }
        if (is_admin() || has_permission('location_manager')) {
            echo render_nav_link($base . '/client_citation_comments.php', 'Client Comments', $commentsIcon, $isClientCommentsRoute, $pendingClientCommentsCount);
        }
        if ($isAdminUser && has_permission('notifications')) {
            echo render_nav_link($base . '/notifications.php', 'Notifications', $notificationIcon, $isNotificationRoute);
        }
        if (is_admin()) {
            echo render_nav_link($base . '/clients.php', 'Clients', $clientIcon, $isClientRoute);
            echo render_nav_link($base . '/users.php', 'Users', $usersIcon, $isUsersRoute);
            echo render_nav_link($base . '/live_user_tracking.php', 'Live User Tracking', $liveTrackingIcon, $isLiveTrackingRoute);
            echo render_nav_link($base . '/activity_logs.php', 'Activity Logs', $logsIcon, $isLogsRoute);
        }
        echo '</nav>';

        echo '</aside>';

        echo '<div id="app_content_wrap" class="lg:pl-72 h-screen overflow-hidden transition-all duration-200">';
        echo '<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-900/85">';
        echo '<div class="flex h-16 items-center justify-between px-4 sm:px-6">';
        echo '<div class="flex items-center gap-3">';
        echo '<button id="sidebar_toggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 lg:hidden">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>';
        echo '</button>';
        echo '<div><p class="text-sm font-bold text-slate-900 dark:text-slate-100">' . e($titleLabel) . '</p><p class="text-xs text-slate-500 dark:text-slate-400">CiteFlow Manager / ' . e($titleLabel) . '</p></div>';
        echo '</div>';
        echo '<form method="get" action="' . e($base) . '/global_search.php" class="hidden items-center gap-2 lg:flex">';
        echo '<label for="global_quick_search" class="sr-only">Global Search</label>';
        echo '<input id="global_quick_search" name="q" type="search" placeholder="Search businesses, citations, directories..." class="w-[26rem] rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">';
        echo '<button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700">Search</button>';
        echo '</form>';
        echo '<div class="flex items-center gap-2">';
        if ($helpTutorial !== null) {
            echo '<button id="open_help_tutorial" type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.09 9a3 3 0 1 1 5.82 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>';
            echo '<span class="hidden sm:inline">Help</span>';
            echo '</button>';
        }
        if ($isAdminUser) {
            echo '<a class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" href="' . e($base) . '/notifications.php" aria-label="Notifications">' . $notificationIcon;
            if ($notificationCount > 0) {
                echo '<span class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.2rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">' . e((string)$notificationCount) . '</span>';
            }
            echo '</a>';
        } else {
            echo '<div id="notification_menu_wrap" class="relative">';
            echo '<button id="notification_menu_button" type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" aria-haspopup="true" aria-expanded="false" aria-label="Notifications">' . $notificationIcon;
            if ($notificationCount > 0) {
                echo '<span class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.2rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">' . e((string)min($notificationCount, 99)) . '</span>';
            }
            echo '</button>';
            echo '<div id="notification_menu_panel" class="fixed left-2 right-2 top-[4.5rem] z-40 hidden max-h-[calc(100dvh-5.5rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-[24rem] sm:max-w-[calc(100vw-2rem)] sm:max-h-none">';
            echo '<div class="mb-3 flex items-center justify-between gap-2">';
            echo '<div><p class="text-sm font-bold text-slate-900 dark:text-slate-100">Latest Notifications</p><p class="text-xs text-slate-500 dark:text-slate-400">Showing all recent items</p></div>';
            echo '<a class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" href="' . e($base) . '/notifications.php?read_all=1&from=' . rawurlencode($notificationReturnUrl) . '">Mark all read</a>';
            echo '</div>';
            echo '<div class="max-h-[calc(100dvh-12rem)] space-y-2 overflow-y-auto sm:max-h-[26rem]">';
            foreach ($notificationPreviewRows as $row) {
                $isUnread = (int)($row['is_read'] ?? 0) === 0;
                $titleText = (string)($row['title'] ?? 'Notification');
                $messageText = (string)($row['message'] ?? '');
                $businessLogo = notification_business_logo_path($row);
                $visualHaystack = strtolower(trim($titleText . ' ' . $messageText));
                $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>';
                $iconClass = 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300';
                if (str_contains($visualHaystack, 'business') || str_contains($visualHaystack, 'client') || str_contains($visualHaystack, 'location')) {
                    $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 9h.01"/><path d="M9 12h.01"/><path d="M9 15h.01"/><path d="M15 9h.01"/><path d="M15 12h.01"/><path d="M15 15h.01"/></svg>';
                    $iconClass = 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300';
                } elseif (str_contains($visualHaystack, 'citation') || str_contains($visualHaystack, 'directory') || str_contains($visualHaystack, 'listing') || str_contains($visualHaystack, 'submission')) {
                    $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
                    $iconClass = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
                } elseif (str_contains($visualHaystack, 'delete') || str_contains($visualHaystack, 'removed') || str_contains($visualHaystack, 'failed') || str_contains($visualHaystack, 'error')) {
                    $iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';
                    $iconClass = 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300';
                }
                $actionUrl = trim((string)($row['action_url'] ?? ''));
                $readUrl = $base . '/notifications.php?read=' . (int)$row['id'] . '&from=' . rawurlencode($notificationReturnUrl);
                if ($actionUrl !== '') {
                    $readUrl .= '&next=' . rawurlencode($actionUrl);
                }
                $targetUrl = $isUnread ? $readUrl : ($actionUrl !== '' ? $actionUrl : $readUrl);
                $itemClasses = $isUnread
                    ? 'block rounded-xl border border-indigo-300 bg-indigo-50/70 p-3 transition-all hover:bg-indigo-100/80 dark:border-indigo-700/40 dark:bg-indigo-950/35 dark:hover:bg-indigo-900/40'
                    : 'block rounded-xl border border-slate-200 bg-slate-50 p-3 transition-all hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800/40 dark:hover:bg-slate-800/60';
                echo '<a class="' . e($itemClasses) . '" href="' . e((string)$targetUrl) . '">';
                echo '<div class="flex items-start gap-2">';
                if ($businessLogo !== '') {
                    echo '<img src="' . e($businessLogo) . '" alt="Business logo" class="h-7 w-7 shrink-0 rounded-lg border border-slate-200 object-cover shadow-sm dark:border-slate-700">';
                } else {
                    echo '<span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ' . e($iconClass) . '">' . $iconSvg . '</span>';
                }
                if ($isUnread) {
                    echo '<span class="mt-0.5 inline-block h-2 w-2 flex-shrink-0 rounded-full bg-indigo-500 dark:bg-indigo-400"></span>';
                }
                echo '<div class="flex-1 min-w-0">';
                echo '<p class="text-sm font-semibold text-slate-900 dark:text-slate-100">' . e($titleText) . '</p>';
                echo '<p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300 line-clamp-2">' . e($messageText) . '</p>';
                echo '<p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">' . e((string)$row['created_at']) . '</p>';
                echo '</div>';
                echo '</div>';
                echo '</a>';
            }
            if (!$notificationPreviewRows) {
                echo '<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">No notifications yet.</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '<div id="user_menu_wrap" class="relative">';
        echo '<button id="user_menu_button" type="button" class="inline-flex items-center gap-2 rounded-xl px-2 py-1.5 text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" aria-haspopup="true" aria-expanded="false">';
        if (!empty($user['image_path'])) {
            echo '<img src="' . e($user['image_path']) . '" alt="' . e($user['full_name']) . '" class="h-9 w-9 rounded-full object-cover">';
        } else {
            $initials = strtoupper(mb_substr((string)($user['full_name'] ?? ''), 0, 1));
            echo '<div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white">' . e($initials) . '</div>';
        }
        echo '<span class="hidden max-w-[11rem] truncate text-sm font-semibold text-slate-900 dark:text-slate-100 sm:inline">' . e($user['full_name']) . '</span>';
        echo '</button>';

        echo '<div id="user_menu_panel" class="absolute right-0 z-40 mt-2 hidden w-72 rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl dark:border-slate-700 dark:bg-slate-900">';
        echo '<div class="mb-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">';
        echo '<p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">' . e(!empty($user['designation']) ? $user['designation'] : role_label($user)) . '</p>';
        echo '</div>';
        echo '<button id="theme_mode_toggle" type="button" class="mb-2 inline-flex w-full items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" aria-label="Toggle dark mode" title="Toggle dark mode">';
        echo '<span>Dark Mode</span>';
        echo '<span class="inline-flex items-center">';
        echo '<svg id="theme_mode_icon_sun" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M6.34 17.66l-1.41 1.41"/><path d="M19.07 4.93l-1.41 1.41"/></svg>';
        echo '<svg id="theme_mode_icon_moon" xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3c0 .36.02.72.07 1.08A7 7 0 0 0 19.92 12c.36.05.72.07 1.08.07z"/></svg>';
        echo '</span>';
        echo '</button>';
        echo '<button id="open_theme_settings" type="button" class="mb-2 inline-flex w-full items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Theme Settings</button>';
        echo '<button id="toggle_compact_sidebar" type="button" class="mb-2 inline-flex w-full items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Compact Sidebar</button>';
        echo '<a class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-950 dark:bg-slate-700 dark:hover:bg-slate-600" href="' . e($base) . '/logout.php">Logout</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</header>';

        echo '<main class="h-[calc(100vh-4rem)] overflow-y-auto p-4 sm:p-6 lg:p-8">';
    } else {
        echo '<main class="min-h-screen">';
    }

    if ($helpTutorial !== null) {
        echo '<div id="help_tutorial_modal" data-help-key="' . e((string)$helpTutorial['help_key']) . '" data-guide-auto-show="' . ($helpShouldAutoShow ? '1' : '0') . '" data-guide-seen="' . ($helpSeenAt !== '' ? '1' : '0') . '" data-guide-state-url="' . e($base . '/user_guide_state.php') . '" class="fixed inset-0 z-[70] hidden flex items-center justify-center p-6">';
        echo '<div id="help_tutorial_backdrop" class="absolute inset-0 bg-slate-950/60"></div>';
        echo '<div class="relative flex w-full max-w-2xl max-h-[calc(100vh-4rem)] flex-col rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">';
        echo '<div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-700">';
        echo '<div>';
        echo '<p class="text-xs font-bold uppercase tracking-wide text-brand-600 dark:text-brand-300">Feature Help</p>';
        echo '<h2 class="mt-1 text-xl font-extrabold text-slate-900 dark:text-slate-100">' . e((string)$helpTutorial['title']) . '</h2>';
        echo '<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">' . e((string)$helpTutorial['summary']) . '</p>';
        echo '</div>';
        echo '<div class="flex shrink-0 items-center gap-2">';
        echo '<button id="start_tour_btn" type="button" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
        echo '<span>Take Tour</span>';
        echo '</button>';
        echo '<button id="close_help_tutorial" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" aria-label="Close help tutorial">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '<div id="tour_controls" style="display:none" class="items-center gap-3 border-b border-brand-200 bg-brand-50 px-6 py-3 shrink-0 dark:border-brand-900 dark:bg-brand-950/20">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand-600 dark:text-brand-300" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
        echo '<span id="tour_step_counter" class="text-xs font-bold text-brand-700 dark:text-brand-300">Step 1 of ?</span>';
        echo '<div class="ml-auto flex gap-2">';
        echo '<button id="tour_prev_btn" type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200 disabled:opacity-40">&#8592; Prev</button>';
        echo '<button id="tour_next_btn" type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700">Next &#8594;</button>';
        echo '<button id="end_tour_btn" type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300">Exit Tour</button>';
        echo '</div>';
        echo '</div>';
        echo '<div class="grid gap-6 overflow-y-auto flex-1 px-6 py-5 md:grid-cols-[1.15fr_0.85fr]">';
        echo '<section>';
        echo '<h3 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Step by Step</h3>';
        echo '<ol id="help_tutorial_steps_list" class="mt-3 space-y-3">';
        foreach (($helpTutorial['steps'] ?? []) as $index => $step) {
            echo '<li class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">';
            echo '<span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">' . e((string)($index + 1)) . '</span>';
            echo '<span class="text-sm text-slate-700 dark:text-slate-200">' . e((string)$step) . '</span>';
            echo '</li>';
        }
        echo '</ol>';
        echo '</section>';
        echo '<aside>';
        echo '<div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950/30">';
        echo '<h3 class="text-sm font-bold uppercase tracking-wide text-sky-700 dark:text-sky-300">Quick Tips</h3>';
        echo '<ul class="mt-3 space-y-2">';
        foreach (($helpTutorial['tips'] ?? []) as $tip) {
            echo '<li class="flex items-start gap-2 text-sm text-sky-900 dark:text-sky-100">';
            echo '<span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-sky-500 dark:bg-sky-300"></span>';
            echo '<span>' . e((string)$tip) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '<div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800/50">';
        echo '<p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Need more help?</p>';
        echo '<p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Use this guide as a quick refresher before editing records or changing statuses.</p>';
        echo '</div>';
        echo '<div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/25">';
        echo '<p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">' . e((string)$helpTutorial['role_label']) . '</p>';
        echo '<p class="mt-1 text-sm text-emerald-900 dark:text-emerald-100">' . e((string)$helpTutorial['role_summary']) . '</p>';
        echo '<ul class="mt-3 space-y-2">';
        foreach (($helpTutorial['role_tips'] ?? []) as $roleTip) {
            echo '<li class="flex items-start gap-2 text-sm text-emerald-900 dark:text-emerald-100">';
            echo '<span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500 dark:bg-emerald-300"></span>';
            echo '<span>' . e((string)$roleTip) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</aside>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        if (!$user && $client) {
            echo '<button id="open_help_tutorial_floating" type="button" class="fixed bottom-5 right-5 z-[60] inline-flex items-center gap-2 rounded-full bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-brand-700">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.09 9a3 3 0 1 1 5.82 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>';
            echo '<span>Help</span>';
            echo '</button>';
        }
    }

    if ($flash) {
        $flashType = (string)($flash['type'] ?? 'err');
        $classes = 'mb-4 rounded-xl border px-4 py-3 text-sm font-medium';
        if ($flashType === 'ok') {
            $classes .= ' border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200';
        } elseif ($flashType === 'warning' || $flashType === 'warn') {
            $classes .= ' border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200';
        } elseif ($flashType === 'info') {
            $classes .= ' border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200';
        } else {
            $classes .= ' border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-200';
        }
        echo '<div class="' . e($classes) . '">' . e($flash['message']) . '</div>';
    }

    if ($helpTutorial !== null) {
        echo '<section id="page_help_hint" data-help-key="' . e((string)$helpTutorial['help_key']) . '" class="hidden mb-4 rounded-2xl border border-brand-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/70 px-4 py-4 shadow-sm dark:border-brand-900 dark:from-slate-900 dark:via-brand-950/30 dark:to-slate-800">';
        echo '<div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">';
        echo '<div>';
        echo '<p class="text-xs font-bold uppercase tracking-wide text-brand-700 dark:text-brand-300">Tutorial Available</p>';
        echo '<h2 class="mt-1 text-base font-bold text-slate-900 dark:text-slate-100">' . e((string)$helpTutorial['title']) . '</h2>';
        echo '<p class="mt-1 text-sm text-slate-600 dark:text-slate-300">' . e((string)$helpTutorial['summary']) . '</p>';
        echo '</div>';
        echo '<div class="flex flex-wrap gap-2">';
        echo '<button id="open_help_tutorial_inline" type="button" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Open Guide</button>';
        echo '<button id="dismiss_page_help_hint" type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Dismiss</button>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }
}

function render_footer(): void
{
    echo '</main>';
    if (current_user()) {
                echo '<div id="theme_settings_panel" class="fixed right-4 top-20 z-50 hidden w-72 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl dark:border-slate-700 dark:bg-slate-900">';
                echo '<p class="text-sm font-bold text-slate-900 dark:text-slate-100">Theme Settings</p>';
                echo '<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Accent Color</p>';
                echo '<div class="mt-3 grid grid-cols-3 gap-2">';
                echo '<button class="accent-btn rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" data-accent="indigo">Indigo</button>';
                echo '<button class="accent-btn rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" data-accent="emerald">Emerald</button>';
                echo '<button class="accent-btn rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" data-accent="rose">Rose</button>';
                echo '<button class="accent-btn rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" data-accent="cyan">Cyan</button>';
                echo '<button class="accent-btn rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" data-accent="amber">Amber</button>';
                echo '<button class="accent-btn rounded-lg border border-slate-300 px-2 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" data-accent="slate">Slate</button>';
                echo '</div>';
                echo '</div>';

                echo '<footer class="border-t border-slate-200 bg-white py-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">CiteFlow Manager</footer>';
                $trackingBase = rtrim((string)app_config()['base_url'], '/');
                $trackingUrl = $trackingBase . '/user_tracking.php';
                echo '<script>window.citeflowTracking=' . json_encode([
                    'enabled' => true,
                    'url' => $trackingUrl,
                    'path' => normalize_tracking_path(),
                    'title' => current_tracking_page_title(),
                ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
                echo '<script>
(function(){
    var body=document.body;
    var throbber=document.getElementById("global_throbber");
    var sidebar=document.getElementById("app_sidebar");
    var sidebarToggle=document.getElementById("sidebar_toggle");
    var sidebarOverlay=document.getElementById("sidebar_overlay");
    var modeToggle=document.getElementById("theme_mode_toggle");
    var modeIconSun=document.getElementById("theme_mode_icon_sun");
    var modeIconMoon=document.getElementById("theme_mode_icon_moon");
    var notificationMenuBtn=document.getElementById("notification_menu_button");
    var notificationMenuPanel=document.getElementById("notification_menu_panel");
    var userMenuWrap=document.getElementById("user_menu_wrap");
    var userMenuBtn=document.getElementById("user_menu_button");
    var userMenuPanel=document.getElementById("user_menu_panel");
    var settingsBtn=document.getElementById("open_theme_settings");
    var settingsPanel=document.getElementById("theme_settings_panel");
    var compactSidebarBtn=document.getElementById("toggle_compact_sidebar");
    var accentBtns=document.querySelectorAll(".accent-btn");
    var helpTutorialBtn=document.getElementById("open_help_tutorial");
    var helpTutorialInlineBtn=document.getElementById("open_help_tutorial_inline");
    var dismissPageHelpHintBtn=document.getElementById("dismiss_page_help_hint");
    var pageHelpHint=document.getElementById("page_help_hint");
    var helpTutorialFloatingBtn=document.getElementById("open_help_tutorial_floating");
    var helpTutorialModal=document.getElementById("help_tutorial_modal");
    var helpTutorialBackdrop=document.getElementById("help_tutorial_backdrop");
    var helpTutorialCloseBtn=document.getElementById("close_help_tutorial");
    var startTourBtn=document.getElementById("start_tour_btn");
    var tourControls=document.getElementById("tour_controls");
    var tourStepCounter=document.getElementById("tour_step_counter");
    var tourPrevBtn=document.getElementById("tour_prev_btn");
    var tourNextBtn=document.getElementById("tour_next_btn");
    var endTourBtn=document.getElementById("end_tour_btn");
    var tourStepItems=[];
    var tourIndex=-1;
    var guideAutoShow=false;
    var guideSeenPersisted=false;
    var guideStateUrl="";
    var guideGlobalHidden=localStorage.getItem("cf-help-global-hidden") === "1";

    if(helpTutorialModal){
        guideAutoShow = helpTutorialModal.getAttribute("data-guide-auto-show") === "1";
        guideSeenPersisted = helpTutorialModal.getAttribute("data-guide-seen") === "1";
        guideStateUrl = helpTutorialModal.getAttribute("data-guide-state-url") || "";
    }

    function persistGuideState(action){
        if(!guideStateUrl){ return; }
        fetch(guideStateUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({ action: action })
        }).catch(function(){});
    }

    var html=document.documentElement;
    var storedMode=localStorage.getItem("cf-theme-mode") || "light";
    var storedAccent=localStorage.getItem("cf-theme-accent") || "indigo";
    var storedCompact=localStorage.getItem("cf-sidebar-compact") || "off";

    if(storedMode==="dark"){ html.classList.add("dark"); body.classList.add("dark"); }
    if(storedCompact==="on"){ html.classList.add("sidebar-compact"); body.classList.add("sidebar-compact"); }
    html.classList.remove("theme-indigo","theme-emerald","theme-rose","theme-cyan","theme-amber","theme-slate");
    body.classList.remove("theme-indigo","theme-emerald","theme-rose","theme-cyan","theme-amber","theme-slate");
    html.classList.add("theme-"+storedAccent);
    body.classList.add("theme-"+storedAccent);

    function showThrobber(){
        if(!throbber){ return; }
        throbber.classList.add("is-active");
        throbber.setAttribute("aria-busy", "true");
        throbber.setAttribute("aria-hidden", "false");
    }
    function hideThrobber(){
        if(!throbber){ return; }
        throbber.classList.remove("is-active");
        throbber.setAttribute("aria-busy", "false");
        throbber.setAttribute("aria-hidden", "true");
    }

    hideThrobber();
    window.addEventListener("pageshow", hideThrobber);

    function activateSubmitSpinner(form, submitter){
        if(!(form instanceof HTMLFormElement)){ return; }
        if(form.getAttribute("data-submitting") === "1"){ return; }
        form.setAttribute("data-submitting", "1");

        var actionButton = submitter;
        if(!(actionButton instanceof HTMLButtonElement) && !(actionButton instanceof HTMLInputElement)){
            actionButton = form.querySelector("button[type=submit],input[type=submit]");
        }

        if(actionButton instanceof HTMLButtonElement){
            if(actionButton.getAttribute("data-spinner-applied") !== "1"){
                actionButton.style.minWidth = actionButton.offsetWidth + "px";
                actionButton.dataset.originalHtml = actionButton.innerHTML;
                actionButton.innerHTML = "<span class=\"cf-inline-spinner\" aria-hidden=\"true\"></span>";
                actionButton.setAttribute("data-spinner-applied", "1");
            }
            actionButton.classList.add("is-submitting");
            actionButton.disabled = true;
        } else if(actionButton instanceof HTMLInputElement){
            actionButton.classList.add("is-submitting");
            actionButton.disabled = true;
        }
    }

    document.addEventListener("submit", function(event){
        var form = event.target;
        if(!(form instanceof HTMLFormElement)){ return; }
        if(form.getAttribute("data-no-throbber") === "1"){ return; }
        activateSubmitSpinner(form, event.submitter || null);
        showThrobber();
    }, true);

    document.addEventListener("click", function(event){
        if(event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey){
            return;
        }

        var target = event.target;
        if(!(target instanceof Element)){ return; }
        var link = target.closest("a[href]");
        if(!link || !(link instanceof HTMLAnchorElement)){ return; }
        if(link.getAttribute("data-no-throbber") === "1"){ return; }
        if(link.getAttribute("target") === "_blank"){ return; }
        if(link.hasAttribute("download")){ return; }

        var href = link.getAttribute("href") || "";
        if(href === "" || href.charAt(0) === "#"){ return; }
        if(href.indexOf("javascript:") === 0 || href.indexOf("mailto:") === 0 || href.indexOf("tel:") === 0){ return; }

        try {
            var url = new URL(link.href, window.location.href);
            if(url.origin !== window.location.origin){ return; }
            if(url.searchParams.has("delete")){ return; }
        } catch (_err) {
            return;
        }

        showThrobber();
    }, true);

        function syncThemeToggleIcon(){
            var dark=html.classList.contains("dark");
            if(modeIconSun){ modeIconSun.classList.toggle("hidden", dark); }
            if(modeIconMoon){ modeIconMoon.classList.toggle("hidden", !dark); }
            if(modeToggle){
                modeToggle.setAttribute("aria-label", dark ? "Switch to light mode" : "Switch to dark mode");
                modeToggle.setAttribute("title", dark ? "Switch to light mode" : "Switch to dark mode");
            }
        }
        syncThemeToggleIcon();

    if(modeToggle){
        modeToggle.addEventListener("click", function(){
            html.classList.toggle("dark");
            body.classList.toggle("dark");
            localStorage.setItem("cf-theme-mode", html.classList.contains("dark") ? "dark" : "light");
                        syncThemeToggleIcon();
        });
    }

    accentBtns.forEach(function(btn){
        btn.addEventListener("click", function(){
            var accent=btn.getAttribute("data-accent") || "indigo";
            html.classList.remove("theme-indigo","theme-emerald","theme-rose","theme-cyan","theme-amber","theme-slate");
            body.classList.remove("theme-indigo","theme-emerald","theme-rose","theme-cyan","theme-amber","theme-slate");
            html.classList.add("theme-"+accent);
            body.classList.add("theme-"+accent);
            localStorage.setItem("cf-theme-accent", accent);
        });
    });

    if(compactSidebarBtn){
        compactSidebarBtn.addEventListener("click", function(){
            html.classList.toggle("sidebar-compact");
            body.classList.toggle("sidebar-compact");
            localStorage.setItem("cf-sidebar-compact", body.classList.contains("sidebar-compact") ? "on" : "off");
        });
    }

    function openHelpTutorial(){
        if(!helpTutorialModal){ return; }
        helpTutorialModal.classList.remove("hidden");
        body.classList.add("overflow-hidden");
        var helpKey = helpTutorialModal.getAttribute("data-help-key") || "";
        if(helpKey){
            localStorage.setItem("cf-help-seen:" + helpKey, "1");
        }
        if(!guideSeenPersisted){
            persistGuideState("seen");
            guideSeenPersisted = true;
        }
    }

    function endTour(){
        tourIndex=-1;
        tourStepItems.forEach(function(s){
            s.style.outline="";
            s.style.outlineOffset="";
            s.style.backgroundColor="";
        });
        if(tourControls){ tourControls.style.display="none"; }
        if(startTourBtn){ startTourBtn.classList.remove("hidden"); }
    }

    function highlightTourStep(index){
        tourStepItems.forEach(function(s,i){
            s.style.outline="";
            s.style.outlineOffset="";
            s.style.backgroundColor="";
            if(i===index){
                s.style.outline="2px solid rgb(var(--brand-600))";
                s.style.outlineOffset="3px";
                s.style.backgroundColor="rgba(var(--brand-50),1)";
                s.scrollIntoView({block:"nearest",behavior:"smooth"});
            }
        });
        if(tourStepCounter){ tourStepCounter.textContent="Step "+(index+1)+" of "+tourStepItems.length; }
        if(tourPrevBtn){ tourPrevBtn.disabled=(index===0); }
        if(tourNextBtn){ tourNextBtn.textContent=(index===tourStepItems.length-1)?"Done \u2713":"Next \u2192"; }
    }

    function startTour(){
        var stepsEl=document.getElementById("help_tutorial_steps_list");
        if(!stepsEl){ return; }
        tourStepItems=Array.prototype.slice.call(stepsEl.querySelectorAll("li"));
        if(!tourStepItems.length){ return; }
        tourIndex=0;
        if(tourControls){ tourControls.style.display="flex"; }
        if(startTourBtn){ startTourBtn.classList.add("hidden"); }
        highlightTourStep(tourIndex);
    }

    function closeHelpTutorial(){
        if(!helpTutorialModal){ return; }
        helpTutorialModal.classList.add("hidden");
        body.classList.remove("overflow-hidden");
        endTour();
    }

    if(helpTutorialBtn){
        helpTutorialBtn.addEventListener("click", openHelpTutorial);
    }
    if(helpTutorialInlineBtn){
        helpTutorialInlineBtn.addEventListener("click", openHelpTutorial);
    }
    if(helpTutorialFloatingBtn){
        helpTutorialFloatingBtn.addEventListener("click", openHelpTutorial);
    }
    if(helpTutorialCloseBtn){
        helpTutorialCloseBtn.addEventListener("click", closeHelpTutorial);
    }
    if(helpTutorialBackdrop){
        helpTutorialBackdrop.addEventListener("click", closeHelpTutorial);
    }
    document.addEventListener("keydown", function(event){
        if(event.key === "Escape"){
            closeHelpTutorial();
        }
    });
    if(startTourBtn){
        startTourBtn.addEventListener("click", startTour);
    }
    if(tourPrevBtn){
        tourPrevBtn.addEventListener("click", function(){
            if(tourIndex>0){ tourIndex--; highlightTourStep(tourIndex); }
        });
    }
    if(tourNextBtn){
        tourNextBtn.addEventListener("click", function(){
            if(tourIndex<tourStepItems.length-1){ tourIndex++; highlightTourStep(tourIndex); }
            else{ endTour(); }
        });
    }
    if(endTourBtn){
        endTourBtn.addEventListener("click", endTour);
    }

    if(pageHelpHint){
        var pageHelpKey = pageHelpHint.getAttribute("data-help-key") || "";
        var isHintDismissed = !guideAutoShow || guideGlobalHidden || (pageHelpKey && (localStorage.getItem("cf-help-tip-hidden:" + pageHelpKey) === "1" || localStorage.getItem("cf-help-dismissed:" + pageHelpKey) === "1"));
        if(isHintDismissed){
            pageHelpHint.classList.add("hidden");
        } else {
            pageHelpHint.classList.remove("hidden");
        }
        if(dismissPageHelpHintBtn){
            dismissPageHelpHintBtn.addEventListener("click", function(){
                pageHelpHint.classList.add("hidden");
                localStorage.setItem("cf-help-global-hidden", "1");
                if(pageHelpKey){
                    localStorage.setItem("cf-help-tip-hidden:" + pageHelpKey, "1");
                    localStorage.setItem("cf-help-dismissed:" + pageHelpKey, "1");
                    localStorage.setItem("cf-help-seen:" + pageHelpKey, "1");
                }
                persistGuideState("dismiss");
                guideSeenPersisted = true;
            });
        }
    }

    if(helpTutorialModal){
        if(guideAutoShow && !guideGlobalHidden){
            window.setTimeout(function(){
                openHelpTutorial();
            }, 700);
        }
    }

    function openUserMenu(){
        if(!userMenuPanel || !userMenuBtn){ return; }
        userMenuPanel.classList.remove("hidden");
        userMenuBtn.setAttribute("aria-expanded", "true");
    }
    function closeNotificationMenu(){
        if(!notificationMenuPanel || !notificationMenuBtn){ return; }
        notificationMenuPanel.classList.add("hidden");
        notificationMenuBtn.setAttribute("aria-expanded", "false");
    }
    function closeUserMenu(){
        if(!userMenuPanel || !userMenuBtn){ return; }
        userMenuPanel.classList.add("hidden");
        userMenuBtn.setAttribute("aria-expanded", "false");
    }

    if(notificationMenuBtn && notificationMenuPanel){
        notificationMenuBtn.addEventListener("click", function(){
            notificationMenuPanel.classList.toggle("hidden");
            notificationMenuBtn.setAttribute("aria-expanded", notificationMenuPanel.classList.contains("hidden") ? "false" : "true");
            closeUserMenu();
        });
    }

    if(userMenuBtn && userMenuPanel){
        userMenuBtn.addEventListener("click", function(){
            userMenuPanel.classList.toggle("hidden");
            userMenuBtn.setAttribute("aria-expanded", userMenuPanel.classList.contains("hidden") ? "false" : "true");
            closeNotificationMenu();
        });
    }

    if(settingsBtn && settingsPanel){
        settingsBtn.addEventListener("click", function(){
            settingsPanel.classList.toggle("hidden");
        });
        document.addEventListener("click", function(e){
            var t=e.target;
            if(!(t instanceof Element)){ return; }
            if(notificationMenuPanel && !t.closest("#notification_menu_button") && !t.closest("#notification_menu_panel")){
                closeNotificationMenu();
            }
            if(userMenuPanel && !t.closest("#user_menu_button") && !t.closest("#user_menu_panel")){
                closeUserMenu();
            }
            if(!t.closest("#open_theme_settings") && !t.closest("#theme_settings_panel")){
                settingsPanel.classList.add("hidden");
            }
        });
    }

    function closeSidebar(){
        if(!sidebar){ return; }
        sidebar.classList.add("-translate-x-full");
        if(sidebarOverlay){ sidebarOverlay.classList.add("hidden"); }
    }
    function openSidebar(){
        if(!sidebar){ return; }
        sidebar.classList.remove("-translate-x-full");
        if(sidebarOverlay){ sidebarOverlay.classList.remove("hidden"); }
    }

    if(sidebarToggle){
        sidebarToggle.addEventListener("click", function(){
            if(!sidebar){ return; }
            if(sidebar.classList.contains("-translate-x-full")) { openSidebar(); } else { closeSidebar(); }
        });
    }
    if(sidebarOverlay){
        sidebarOverlay.addEventListener("click", closeSidebar);
    }
    window.addEventListener("resize", function(){
        if(window.innerWidth >= 1024){
            if(sidebar){ sidebar.classList.remove("-translate-x-full"); }
            if(sidebarOverlay){ sidebarOverlay.classList.add("hidden"); }
        } else {
            if(sidebar){ sidebar.classList.add("-translate-x-full"); }
        }
    });

    var trackingConfig = window.citeflowTracking || null;
    if(trackingConfig && trackingConfig.enabled && trackingConfig.url){
        var lastHeartbeatAt = 0;
        var lastInteractionAt = 0;
        function sendHeartbeat(force){
            var now = Date.now();
            if(!force && now - lastHeartbeatAt < 10000){
                return;
            }
            lastHeartbeatAt = now;

            var currentPath = window.location.pathname + window.location.search;
            var pageTitle = document.title.replace(/\s+-\s+CiteFlow Manager$/, "") || trackingConfig.title || "Dashboard";
            var payload = JSON.stringify({
                current_path: currentPath,
                page_title: pageTitle
            });

            if(navigator.sendBeacon){
                var blob = new Blob([payload], {type: "application/json"});
                var beaconSent = navigator.sendBeacon(trackingConfig.url, blob);
                if(beaconSent){
                    return;
                }
            }

            fetch(trackingConfig.url, {
                method: "POST",
                credentials: "same-origin",
                keepalive: true,
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: payload
            }).catch(function(){
                // Fallback for hosts that block beacon/keepalive POST semantics.
                var pingUrl = trackingConfig.url
                    + "?ping=1"
                    + "&current_path=" + encodeURIComponent(currentPath)
                    + "&page_title=" + encodeURIComponent(pageTitle);
                fetch(pingUrl, {
                    method: "GET",
                    credentials: "same-origin",
                    cache: "no-store"
                }).catch(function(){});
            });
        }

        sendHeartbeat(true);
        window.setInterval(function(){
            if(document.visibilityState === "visible"){
                sendHeartbeat(false);
            }
        }, 15000);
        document.addEventListener("visibilitychange", function(){
            if(document.visibilityState === "visible"){
                sendHeartbeat(true);
            }
        });
        ["click", "keydown", "scroll", "pointerdown"].forEach(function(eventName){
            window.addEventListener(eventName, function(){
                var now = Date.now();
                if(now - lastInteractionAt < 8000){
                    return;
                }
                lastInteractionAt = now;
                sendHeartbeat(true);
            }, {passive: true});
        });
        window.addEventListener("beforeunload", function(){
            sendHeartbeat(true);
        });
    }

    var liveTrackingPanel = document.getElementById("live_user_tracking_panel");
    if(liveTrackingPanel){
        var list = document.getElementById("live_user_tracking_list");
        var emptyState = document.getElementById("live_user_tracking_empty");
        var clientList = document.getElementById("live_client_tracking_list");
        var clientEmptyState = document.getElementById("live_client_tracking_empty");
        var clientCount = document.getElementById("live_client_tracking_count");
        var stamp = document.getElementById("live_user_tracking_stamp");
        var feedUrl = liveTrackingPanel.getAttribute("data-feed-url") || "";
        var refreshMs = parseInt(liveTrackingPanel.getAttribute("data-refresh-ms") || "10000", 10);

        function renderTrackingRows(items){
            if(!list || !emptyState){
                return;
            }

            list.innerHTML = "";
            if(!items || !items.length){
                emptyState.classList.remove("hidden");
                return;
            }

            emptyState.classList.add("hidden");
            items.forEach(function(item){
                var article = document.createElement("article");
                article.className = "rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/30";

                var top = document.createElement("div");
                top.className = "flex items-center justify-between gap-3";

                var name = document.createElement("p");
                name.className = "text-sm font-semibold text-slate-900 dark:text-white";
                name.textContent = item.full_name || "Unknown user";

                var badge = document.createElement("span");
                badge.className = "inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300";
                badge.textContent = "Online " + (item.relative_online_for || "0s");

                top.appendChild(name);
                top.appendChild(badge);

                var meta = document.createElement("p");
                meta.className = "mt-1 text-xs text-slate-500 dark:text-slate-400";
                meta.textContent = (item.designation || item.role_label || "User") + " · " + (item.page_title || "Active page");

                var path = document.createElement("p");
                path.className = "mt-1 text-xs font-medium text-slate-700 dark:text-slate-300";
                path.textContent = item.current_path || "/";

                var lastSeen = document.createElement("p");
                lastSeen.className = "mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400";
                lastSeen.textContent = "Last seen " + (item.relative_last_seen || "Just now");

                article.appendChild(top);
                article.appendChild(meta);
                article.appendChild(path);
                article.appendChild(lastSeen);
                list.appendChild(article);
            });
        }

        function renderClientTrackingRows(items){
            if(!clientList || !clientEmptyState){
                return;
            }

            clientList.innerHTML = "";
            if(!items || !items.length){
                clientEmptyState.classList.remove("hidden");
                if(clientCount){ clientCount.textContent = "0 online"; }
                return;
            }

            clientEmptyState.classList.add("hidden");
            if(clientCount){ clientCount.textContent = String(items.length) + " online"; }

            items.forEach(function(item){
                var article = document.createElement("article");
                article.className = "rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-900 dark:bg-sky-950/30";

                var top = document.createElement("div");
                top.className = "flex items-center justify-between gap-3";

                var name = document.createElement("p");
                name.className = "text-sm font-semibold text-slate-900 dark:text-white";
                name.textContent = item.client_name || "Unknown client";

                var badge = document.createElement("span");
                badge.className = "inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-900/50 dark:text-sky-300";
                badge.textContent = "Online " + (item.relative_online_for || "0s");

                top.appendChild(name);
                top.appendChild(badge);

                var meta = document.createElement("p");
                meta.className = "mt-1 text-xs text-slate-500 dark:text-slate-400";
                meta.textContent = (item.primary_email || "") + " · " + (item.page_title || "Client Portal");

                var path = document.createElement("p");
                path.className = "mt-1 text-xs font-medium text-slate-700 dark:text-slate-300";
                path.textContent = item.current_path || "/";

                var lastSeen = document.createElement("p");
                lastSeen.className = "mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400";
                lastSeen.textContent = "Last seen " + (item.relative_last_seen || "Just now");

                article.appendChild(top);
                article.appendChild(meta);
                article.appendChild(path);
                article.appendChild(lastSeen);
                clientList.appendChild(article);
            });
        }

        function refreshLiveTracking(){
            if(!feedUrl){
                return;
            }

            fetch(feedUrl, {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(function(response){ return response.ok ? response.json() : null; })
            .then(function(data){
                if(!data){
                    return;
                }
                if(Array.isArray(data.users)){
                    renderTrackingRows(data.users);
                }
                if(Array.isArray(data.clients)){
                    renderClientTrackingRows(data.clients);
                }
                if(stamp){
                    stamp.textContent = data.generated_at_label || "Updated just now";
                }
            })
            .catch(function(){});
        }

        refreshLiveTracking();
        window.setInterval(function(){
            if(document.visibilityState === "visible"){
                refreshLiveTracking();
            }
        }, isNaN(refreshMs) ? 10000 : refreshMs);
    }
})();
</script>';
        echo '</div>';
    }
    echo '</body></html>';
}