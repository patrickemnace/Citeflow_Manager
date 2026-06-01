<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_permission('directories');

function normalize_directory_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    $parts = parse_url($value);
    if (!is_array($parts) || trim((string)($parts['host'] ?? '')) === '') {
        return '';
    }

    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $host = strtolower((string)$parts['host']);
    $path = trim((string)($parts['path'] ?? ''));

    return rtrim($scheme . '://' . $host . ($path !== '' ? $path : ''), '/');
}

function generate_directory_submission_url(string $website, string $directoryType): string
{
    $normalizedWebsite = normalize_directory_url($website);
    if ($normalizedWebsite === '') {
        return '';
    }

    $parts = parse_url($normalizedWebsite);
    if (!is_array($parts) || trim((string)($parts['host'] ?? '')) === '') {
        return '';
    }

    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $host = strtolower((string)$parts['host']);
    $baseUrl = $scheme . '://' . $host;

    $hostPatterns = [
        'google.' => 'https://business.google.com/us/business-profile/',
        'bing.com' => 'https://www.bingplaces.com/',
        'facebook.com' => 'https://www.facebook.com/pages/create/',
        'yelp.' => 'https://biz.yelp.com/',
        'tripadvisor.' => 'https://www.tripadvisor.com/Owners',
        'yellowpages.' => $baseUrl . '/free-listing',
        'chamberofcommerce.' => $baseUrl . '/add-business',
    ];

    foreach ($hostPatterns as $needle => $targetUrl) {
        if (str_contains($host, $needle)) {
            return $targetUrl;
        }
    }

    $defaultPaths = [
        'General' => '/add-business',
        'Search' => '/add-listing',
        'Map' => '/add-listing',
        'Review' => '/write-review',
        'SMB' => '/add-business',
        'Social' => '/signup',
        'Industry Specific' => '/add-business',
        'Data Aggregator' => '/submit',
        'Local Chamber' => '/join',
    ];

    $path = $defaultPaths[$directoryType] ?? '/add-business';

    return $baseUrl . $path;
}

function infer_directory_region(string $host): string
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return 'Global';
    }

    $regionMap = [
        '.ca' => 'Canada',
        '.co.uk' => 'UK',
        '.uk' => 'UK',
        '.com.au' => 'Australia',
        '.au' => 'Australia',
        '.de' => 'Europe',
        '.fr' => 'Europe',
        '.it' => 'Europe',
        '.es' => 'Europe',
        '.nl' => 'Europe',
        '.se' => 'Europe',
        '.no' => 'Europe',
        '.dk' => 'Europe',
        '.fi' => 'Europe',
        '.eu' => 'Europe',
        '.sg' => 'Asia Pacific',
        '.ph' => 'Asia Pacific',
        '.my' => 'Asia Pacific',
        '.id' => 'Asia Pacific',
        '.in' => 'Asia Pacific',
        '.jp' => 'Asia Pacific',
        '.nz' => 'Asia Pacific',
        '.ae' => 'Middle East',
        '.sa' => 'Middle East',
        '.qa' => 'Middle East',
        '.br' => 'Latin America',
        '.mx' => 'Latin America',
        '.ar' => 'Latin America',
        '.cl' => 'Latin America',
        '.co' => 'Latin America',
        '.za' => 'Africa',
        '.ng' => 'Africa',
        '.ke' => 'Africa',
    ];

    foreach ($regionMap as $suffix => $region) {
        if (str_ends_with($host, $suffix)) {
            return $region;
        }
    }

    if (str_ends_with($host, '.us')) {
        return 'USA';
    }

    return 'Global';
}

function infer_directory_defaults(string $website): array
{
    $defaults = [
        'directory_type' => 'General',
        'country' => 'Global',
        'priority_level' => 'medium',
        'requires_login' => '1',
        'pricing_model' => 'free',
    ];

    $normalizedWebsite = normalize_directory_url($website);
    if ($normalizedWebsite === '') {
        return $defaults;
    }

    $parts = parse_url($normalizedWebsite);
    $host = strtolower(trim((string)($parts['host'] ?? '')));
    $path = strtolower(trim((string)($parts['path'] ?? '')));
    if ($host === '') {
        return $defaults;
    }

    $defaults['country'] = infer_directory_region($host);

    if (str_contains($host, 'google.')) {
        $defaults['directory_type'] = 'Search';
        $defaults['priority_level'] = 'high';
        $defaults['country'] = 'Global';
        $defaults['pricing_model'] = 'free';
        return $defaults;
    }

    $rules = [
        'bing.com' => ['directory_type' => 'Search', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'free'],
        'yahoo.com' => ['directory_type' => 'Search', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'maps.apple.' => ['directory_type' => 'Map', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'free'],
        'mapquest.' => ['directory_type' => 'Map', 'priority_level' => 'medium', 'requires_login' => '0', 'pricing_model' => 'free'],
        'waze.com' => ['directory_type' => 'Map', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'facebook.com' => ['directory_type' => 'Social', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'free'],
        'instagram.com' => ['directory_type' => 'Social', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'linkedin.com' => ['directory_type' => 'Social', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'tiktok.com' => ['directory_type' => 'Social', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'x.com' => ['directory_type' => 'Social', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'twitter.com' => ['directory_type' => 'Social', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'nextdoor.com' => ['directory_type' => 'Social', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'free'],
        'yelp.' => ['directory_type' => 'Review', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'free'],
        'tripadvisor.' => ['directory_type' => 'Review', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'free'],
        'trustpilot.' => ['directory_type' => 'Review', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'bbb.org' => ['directory_type' => 'Review', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'angi.com' => ['directory_type' => 'Review', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'thumbtack.com' => ['directory_type' => 'Review', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'yellowpages.' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'superpages.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'dexknows.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'local.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'merchantcircle.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'citysquares.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'showmelocal.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'brownbook.net' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        '2findlocal.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'tuugo.' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'opendi.' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'hotfrog.' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'cylex.' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'foursquare.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'manta.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'free'],
        'alignable.com' => ['directory_type' => 'SMB', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'crunchbase.com' => ['directory_type' => 'Industry Specific', 'priority_level' => 'medium', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'zoominfo.com' => ['directory_type' => 'Data Aggregator', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'yelpbiz.com' => ['directory_type' => 'Review', 'priority_level' => 'high', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'chambermaster.com' => ['directory_type' => 'Local Chamber', 'priority_level' => 'low', 'requires_login' => '1', 'pricing_model' => 'paid'],
        'chamberofcommerce.' => ['directory_type' => 'Local Chamber', 'priority_level' => 'low', 'requires_login' => '0', 'pricing_model' => 'paid'],
    ];

    foreach ($rules as $needle => $rule) {
        if (str_contains($host, $needle)) {
            return array_merge($defaults, $rule);
        }
    }

    if (str_contains($host, 'review')) {
        $defaults['directory_type'] = 'Review';
        $defaults['priority_level'] = 'medium';
    } elseif (str_contains($host, 'map')) {
        $defaults['directory_type'] = 'Map';
        $defaults['priority_level'] = 'medium';
    } elseif (str_contains($host, 'social')) {
        $defaults['directory_type'] = 'Social';
        $defaults['priority_level'] = 'medium';
        $defaults['requires_login'] = '1';
    } elseif (str_contains($host, 'chamber')) {
        $defaults['directory_type'] = 'Local Chamber';
        $defaults['priority_level'] = 'low';
        $defaults['requires_login'] = '0';
        $defaults['pricing_model'] = 'paid';
    } elseif (str_contains($host, 'directory') || str_contains($host, 'listing')) {
        $defaults['directory_type'] = 'SMB';
    }

    $pricingSignal = $host . ' ' . $path;
    if (
        str_contains($pricingSignal, 'premium') ||
        str_contains($pricingSignal, 'membership') ||
        str_contains($pricingSignal, 'subscription') ||
        str_contains($pricingSignal, 'sponsor') ||
        str_contains($pricingSignal, 'advertis') ||
        str_contains($pricingSignal, 'pricing') ||
        str_contains($pricingSignal, 'plans') ||
        str_contains($pricingSignal, 'packages') ||
        str_contains($pricingSignal, 'upgrade') ||
        str_contains($pricingSignal, 'pro-listing') ||
        str_contains($pricingSignal, 'featured-listing') ||
        str_contains($pricingSignal, 'enhanced-profile') ||
        str_contains($pricingSignal, 'claim-business') ||
        str_contains($pricingSignal, 'paid')
    ) {
        $defaults['pricing_model'] = 'paid';
    }

    return $defaults;
}

function build_directory_logo_source_url(string $website): string
{
    $normalizedWebsite = normalize_directory_url($website);
    if ($normalizedWebsite === '') {
        return '';
    }

    return 'https://www.google.com/s2/favicons?sz=256&domain_url=' . rawurlencode($normalizedWebsite);
}

function is_auto_directory_logo_path(string $path): bool
{
    return str_starts_with(trim($path), 'uploads/directory_logo_auto_');
}

function duplicate_directory_exists(string $name, string $website, int $excludeId = 0): bool
{
    $name = trim($name);
    $website = normalize_directory_url($website);
    if ($name === '' && $website === '') {
        return false;
    }

    $sql = 'SELECT id FROM directories WHERE (name = ? OR website = ?)';
    $params = [$name, $website];
    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetch();
}

function fetch_directory_logo_from_website(string $website): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $normalizedWebsite = normalize_directory_url($website);
    $sourceUrl = build_directory_logo_source_url($normalizedWebsite);
    if ($normalizedWebsite === '' || $sourceUrl === '') {
        return null;
    }

    $parts = parse_url($normalizedWebsite);
    $host = strtolower(trim((string)($parts['host'] ?? '')));
    if ($host === '') {
        return null;
    }

    $curl = curl_init($sourceUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'CiteFlow Manager/1.0',
        CURLOPT_HTTPHEADER => ['Accept: image/png,image/*;q=0.8,*/*;q=0.5'],
    ]);

    $body = curl_exec($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($body) || $body === '' || $statusCode >= 400) {
        return null;
    }

    $safeHost = trim((string)preg_replace('/[^a-z0-9]+/i', '_', $host), '_');
    if ($safeHost === '') {
        $safeHost = 'directory';
    }

    $saveError = null;
    return save_image_binary_to_uploads($body, 'directory_logo_auto', 'directory_logo_auto_' . $safeHost, $saveError);
}

function suggest_directory_name_from_host(string $website): string
{
    $url = normalize_directory_url($website);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    $host = strtolower(trim((string)($parts['host'] ?? '')));
    if ($host === '') {
        return '';
    }

    $host = preg_replace('/^www\./', '', $host);
    $label = explode('.', (string)$host)[0] ?? '';
    $label = trim((string)preg_replace('/[^a-z0-9]+/i', ' ', $label));

    return ucwords($label);
}

function generate_directory_type_notes(string $website): string
{
    $defaults = infer_directory_defaults($website);
    $type = (string)($defaults['directory_type'] ?? 'General');
    $region = (string)($defaults['country'] ?? 'Global');
    $priorityLevel = strtolower((string)($defaults['priority_level'] ?? 'medium'));
    $requiresLogin = (string)($defaults['requires_login'] ?? '1') === '1';
    $pricingModel = strtolower((string)($defaults['pricing_model'] ?? 'free'));

    $typeShort = [
        'General' => 'General listing',
        'Search' => 'Search engine',
        'Map' => 'Map listing',
        'Review' => 'Review platform',
        'SMB' => 'SMB directory',
        'Social' => 'Social profile',
        'Industry Specific' => 'Industry-specific',
        'Data Aggregator' => 'Data aggregator',
        'Local Chamber' => 'Local chamber',
    ];

    $shortType = $typeShort[$type] ?? $typeShort['General'];
    $difficultyLabelMap = ['low' => 'Easy', 'medium' => 'Moderate', 'high' => 'Hard'];
    $difficultyLabel = $difficultyLabelMap[$priorityLevel] ?? 'Moderate';
    $pricingLabel = $pricingModel === 'paid' ? 'Paid' : 'Free';
    $loginLabel = $requiresLogin ? 'Login' : 'No login';

    return $shortType . ' - ' . $difficultyLabel . ', ' . $pricingLabel . ', ' . $loginLabel . ', ' . $region;
}

function fetch_directory_autofill_profile(string $website): array
{
    $profile = [
        'name' => suggest_directory_name_from_host($website),
        'notes' => generate_directory_type_notes($website),
    ];

    if (!function_exists('curl_init')) {
        return $profile;
    }

    $url = normalize_directory_url($website);
    if ($url === '') {
        return $profile;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'CiteFlow Manager/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'],
    ]);
    $html = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($html) || $html === '' || $status >= 400) {
        return $profile;
    }

    if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']{2,})["\'][^>]*>/i', $html, $m) ||
        preg_match('/<meta[^>]+content=["\']([^"\']{2,})["\'][^>]+property=["\']og:site_name["\'][^>]*>/i', $html, $m)) {
        $name = trim((string)$m[1]);
        if ($name !== '') {
            $profile['name'] = $name;
        }
    } elseif (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$m[1])));
        if ($title !== '') {
            $profile['name'] = trim((string)preg_replace('/\s*[\-|\|].*$/', '', $title));
        }
    }

    return $profile;
}

if (isset($_GET['action']) && $_GET['action'] === 'autofill_directory' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $website = normalize_directory_url(trim((string)($_GET['website'] ?? '')));
    $profile = fetch_directory_autofill_profile($website);
    echo json_encode($profile);
    exit;
}

$user = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [
    'name' => '',
    'logo_path' => '',
    'website' => '',
    'submission_url' => '',
    'directory_type' => 'General',
    'country' => 'Global',
    'priority_level' => 'medium',
    'requires_login' => '1',
    'pricing_model' => 'free',
    'notes' => '',
    'is_active' => '1',
];

$directoryTypeOptions = [
    'General' => 'General Directory',
    'Search' => 'Search Engine',
    'Map' => 'Map Listing',
    'Review' => 'Review Platform',
    'SMB' => 'SMB Directory',
    'Social' => 'Social Platform',
    'Industry Specific' => 'Industry Specific',
    'Data Aggregator' => 'Data Aggregator',
    'Local Chamber' => 'Local Chamber / Association',
];

$regionOptions = [
    'Global' => 'Global',
    'USA' => 'United States',
    'Canada' => 'Canada',
    'UK' => 'United Kingdom',
    'Australia' => 'Australia',
    'Europe' => 'Europe',
    'Asia Pacific' => 'Asia Pacific',
    'Middle East' => 'Middle East',
    'Latin America' => 'Latin America',
    'Africa' => 'Africa',
];

if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM directories WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $data = array_merge($data, array_map('strval', $existing));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $previousActive = (int)($data['is_active'] ?? 1);
    $previousName = (string)($data['name'] ?? '');
    foreach ($data as $k => $v) {
        $data[$k] = post($k, (string)$v);
    }

    $data['website'] = normalize_directory_url((string)$data['website']);
    $inferredDefaults = infer_directory_defaults((string)$data['website']);
    if ($id === 0) {
        if ((string)$data['directory_type'] === 'General') {
            $data['directory_type'] = (string)$inferredDefaults['directory_type'];
        }
        if ((string)$data['country'] === 'Global') {
            $data['country'] = (string)$inferredDefaults['country'];
        }
        if ((string)$data['priority_level'] === 'medium') {
            $data['priority_level'] = (string)$inferredDefaults['priority_level'];
        }
        if ((string)$data['requires_login'] === '1') {
            $data['requires_login'] = (string)$inferredDefaults['requires_login'];
        }
        if ((string)$data['pricing_model'] === 'free') {
            $data['pricing_model'] = (string)$inferredDefaults['pricing_model'];
        }
    }
    if (trim((string)$data['submission_url']) === '') {
        $data['submission_url'] = generate_directory_submission_url((string)$data['website'], (string)$data['directory_type']);
    } else {
        $data['submission_url'] = normalize_directory_url((string)$data['submission_url']);
    }

    $previousLogoPath = trim((string)$data['logo_path']);
    $removeLogoRequested = post('remove_logo') === '1';

    $uploadError = null;
    $hasUploadError = false;
    $uploadedLogoPath = save_uploaded_image('logo', 'directory_logo', $uploadError);
    if ($uploadError !== null) {
        set_flash('err', $uploadError);
        $hasUploadError = true;
    } elseif ($uploadedLogoPath !== null) {
        if ($previousLogoPath !== '' && $previousLogoPath !== $uploadedLogoPath) {
            delete_uploaded_asset($previousLogoPath);
        }
        $data['logo_path'] = $uploadedLogoPath;
    } elseif ($removeLogoRequested && $previousLogoPath !== '') {
        delete_uploaded_asset($previousLogoPath);
        $data['logo_path'] = '';
    } elseif (!$removeLogoRequested && $data['website'] !== '' && ($previousLogoPath === '' || is_auto_directory_logo_path($previousLogoPath))) {
        $autoLogoPath = fetch_directory_logo_from_website((string)$data['website']);
        if ($autoLogoPath !== null) {
            if ($previousLogoPath !== '' && $previousLogoPath !== $autoLogoPath && is_auto_directory_logo_path($previousLogoPath)) {
                delete_uploaded_asset($previousLogoPath);
            }
            $data['logo_path'] = $autoLogoPath;
        }
    }

    if ($hasUploadError) {
        // upload error already handled
    } elseif (!array_key_exists((string)$data['directory_type'], $directoryTypeOptions)) {
        set_flash('err', 'Please select a valid directory type.');
    } elseif (!array_key_exists((string)$data['country'], $regionOptions)) {
        set_flash('err', 'Please select a valid region.');
    } elseif (!in_array((string)$data['pricing_model'], ['free', 'paid'], true)) {
        set_flash('err', 'Please select a valid pricing model.');
    } elseif ($data['name'] === '' || $data['website'] === '') {
        set_flash('err', 'Name and website are required.');
    } elseif (duplicate_directory_exists((string)$data['name'], (string)$data['website'], $id)) {
        set_flash('err', 'A directory with the same name or website already exists.');
    } else {
        if ($id > 0) {
            $sql = 'UPDATE directories SET name=?, logo_path=?, website=?, submission_url=?, directory_type=?, country=?, priority_level=?, requires_login=?, pricing_model=?, notes=?, is_active=? WHERE id=?';
            db()->prepare($sql)->execute([
                $data['name'], $data['logo_path'], $data['website'], $data['submission_url'], $data['directory_type'], $data['country'], $data['priority_level'],
                (int)$data['requires_login'], $data['pricing_model'], $data['notes'], (int)$data['is_active'], $id
            ]);
            log_activity('directory', $id, 'update', [
                'name' => $data['name'],
                'directory_type' => $data['directory_type'],
                'country' => $data['country'],
                'is_active' => (int)$data['is_active'],
            ]);

            $actorName = (string)($user['full_name'] ?? 'A user');
            $actionUrl = app_config()['base_url'] . '/directories.php';
            $currentActive = (int)$data['is_active'];
            if ($previousActive !== $currentActive) {
                create_notification(
                    null,
                    'Directory status updated: ' . (string)$data['name'],
                    $actorName . ' marked ' . (string)$data['name'] . ' as ' . ($currentActive === 1 ? 'active' : 'inactive') . '.',
                    $actionUrl,
                    $currentActive === 1 ? 'success' : 'warning'
                );
            } elseif ($previousName !== (string)$data['name']) {
                create_notification(
                    null,
                    'Directory renamed: ' . (string)$data['name'],
                    $actorName . ' renamed directory from ' . $previousName . ' to ' . (string)$data['name'] . '.',
                    $actionUrl,
                    'info'
                );
            }
            set_flash('ok', 'Directory updated.');
        } else {
            $sql = 'INSERT INTO directories (name, logo_path, website, submission_url, directory_type, country, priority_level, requires_login, pricing_model, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            db()->prepare($sql)->execute([
                $data['name'], $data['logo_path'], $data['website'], $data['submission_url'], $data['directory_type'], $data['country'], $data['priority_level'],
                (int)$data['requires_login'], $data['pricing_model'], $data['notes'], (int)$data['is_active']
            ]);
            $newId = (int)db()->lastInsertId();
            log_activity('directory', $newId, 'create', [
                'name' => $data['name'],
                'directory_type' => $data['directory_type'],
                'country' => $data['country'],
                'is_active' => (int)$data['is_active'],
            ]);

            $actorName = (string)($user['full_name'] ?? 'A user');
            create_notification(
                null,
                'New directory added: ' . (string)$data['name'],
                $actorName . ' added directory ' . (string)$data['name'] . ' (' . (string)$data['country'] . ').',
                app_config()['base_url'] . '/directories.php',
                'info'
            );
            set_flash('ok', 'Directory added.');
        }
        redirect('/directories.php');
    }
}

render_header($id > 0 ? 'Edit Directory' : 'Add Directory');
?>
<section class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <h1 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo $id > 0 ? 'Edit Directory' : 'Add Directory'; ?></h1>
    <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Configuration for citation sources.</p>

    <form method="post" enctype="multipart/form-data" class="mt-6 space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Website *</label>
                <input id="directory_website" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="website" value="<?php echo e($data['website']); ?>" required>
                <p class="mt-1 text-xs text-slate-500">Paste the directory website URL and CiteFlow will auto-generate name, submission URL, logo, type, region, submission difficulty, pricing model, login requirement, and notes. You can still override any field.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Name *</label>
                <input id="directory_name" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="name" value="<?php echo e($data['name']); ?>" required>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Submission URL</label>
            <input id="submission_url" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="submission_url" value="<?php echo e($data['submission_url']); ?>">
            <p class="mt-1 text-xs text-slate-500">Auto-generated from the website and directory type. You can still edit it manually.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Directory Logo</label>
            <input id="directory_logo_upload" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" type="file" name="logo" accept="image/*">
            <p class="mt-1 text-xs text-slate-500">Any image size is accepted. CiteFlow auto-optimizes logos to a maximum of 200KB. If no file is uploaded, it will also try to fetch and optimize the website logo automatically.</p>
            <?php if (trim((string)$data['logo_path']) !== ''): ?>
                <img class="mt-2 h-14 w-14 rounded-lg border border-slate-200 object-cover" src="<?php echo e(public_asset_url((string)$data['logo_path'])); ?>" alt="Directory logo">
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300">
                    Remove current logo
                </label>
            <?php else: ?>
                <?php $directoryInitial = strtoupper(substr(trim((string)$data['name']), 0, 1)) ?: '?'; ?>
                <div class="mt-2 flex items-center gap-3">
                    <img id="directory_logo_auto_preview" class="hidden h-14 w-14 rounded-lg border border-slate-200 object-cover" alt="Auto-generated directory logo preview">
                    <div class="inline-flex h-14 w-14 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-lg font-bold text-slate-600" id="directory_logo_fallback"><?php echo e($directoryInitial); ?></div>
                    <p id="directory_logo_auto_hint" class="text-xs text-slate-500">Logo preview will appear here when a match is found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Type</label>
                <select id="directory_type" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="directory_type">
                    <?php foreach ($directoryTypeOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>" <?php echo (string)$data['directory_type'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Region</label>
                <select id="directory_country" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="country">
                    <?php foreach ($regionOptions as $value => $label): ?>
                        <option value="<?php echo e($value); ?>" <?php echo (string)$data['country'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Submission Difficulty</label>
                <select id="directory_priority_level" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="priority_level">
                    <option value="low" <?php echo $data['priority_level'] === 'low' ? 'selected' : ''; ?>>Easy</option>
                    <option value="medium" <?php echo $data['priority_level'] === 'medium' ? 'selected' : ''; ?>>Moderate</option>
                    <option value="high" <?php echo $data['priority_level'] === 'high' ? 'selected' : ''; ?>>Hard</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Pricing Model</label>
                <select id="directory_pricing_model" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="pricing_model">
                    <option value="free" <?php echo $data['pricing_model'] === 'free' ? 'selected' : ''; ?>>Free</option>
                    <option value="paid" <?php echo $data['pricing_model'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Requires Login</label>
                <select id="directory_requires_login" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="requires_login">
                    <option value="1" <?php echo $data['requires_login'] === '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo $data['requires_login'] === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Active</label>
                <select class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="is_active">
                    <option value="1" <?php echo $data['is_active'] === '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo $data['is_active'] === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Notes</label>
            <textarea id="directory_notes" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="notes" rows="4"><?php echo e($data['notes']); ?></textarea>
            <p class="mt-1 text-xs text-slate-500">Auto-populated as a directory summary based on type, region, submission difficulty, pricing model, and login behavior. You can edit it manually.</p>
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            <button class="whitespace-nowrap rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" type="submit">Save Directory</button>
            <a class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="<?php echo e(app_config()['base_url']); ?>/directories.php">Cancel</a>
        </div>
    </form>
</section>
<script>
const directoryNameInput = document.getElementById('directory_name');
const directoryLogoFallback = document.getElementById('directory_logo_fallback');
const directoryWebsiteInput = document.getElementById('directory_website');
const submissionUrlInput = document.getElementById('submission_url');
const directoryTypeInput = document.getElementById('directory_type');
const directoryCountryInput = document.getElementById('directory_country');
const directoryPriorityInput = document.getElementById('directory_priority_level');
const directoryPricingModelInput = document.getElementById('directory_pricing_model');
const directoryRequiresLoginInput = document.getElementById('directory_requires_login');
const directoryLogoUploadInput = document.getElementById('directory_logo_upload');
const directoryLogoAutoPreview = document.getElementById('directory_logo_auto_preview');
const directoryLogoAutoHint = document.getElementById('directory_logo_auto_hint');

if (directoryNameInput && directoryLogoFallback) {
    const updateDirectoryInitial = () => {
        const value = (directoryNameInput.value || '').trim();
        directoryLogoFallback.textContent = value ? value.charAt(0).toUpperCase() : '?';
    };

    directoryNameInput.addEventListener('input', updateDirectoryInitial);
    updateDirectoryInitial();
}

if (directoryWebsiteInput && submissionUrlInput && directoryTypeInput && directoryCountryInput && directoryPriorityInput && directoryPricingModelInput && directoryRequiresLoginInput) {
    const normalizeUrl = (value) => {
        const trimmed = (value || '').trim();
        if (!trimmed) {
            return '';
        }

        let candidate = trimmed;
        if (!/^https?:\/\//i.test(candidate)) {
            candidate = 'https://' + candidate.replace(/^\/+/, '');
        }

        try {
            const parsed = new URL(candidate);
            return `${parsed.protocol}//${parsed.host}${parsed.pathname === '/' ? '' : parsed.pathname}`.replace(/\/$/, '');
        } catch (error) {
            return '';
        }
    };

    const inferRegion = (host) => {
        const value = (host || '').toLowerCase();
        if (!value) {
            return 'Global';
        }

        const regionMap = [
            ['.ca', 'Canada'],
            ['.co.uk', 'UK'],
            ['.uk', 'UK'],
            ['.com.au', 'Australia'],
            ['.au', 'Australia'],
            ['.de', 'Europe'],
            ['.fr', 'Europe'],
            ['.it', 'Europe'],
            ['.es', 'Europe'],
            ['.nl', 'Europe'],
            ['.se', 'Europe'],
            ['.no', 'Europe'],
            ['.dk', 'Europe'],
            ['.fi', 'Europe'],
            ['.eu', 'Europe'],
            ['.sg', 'Asia Pacific'],
            ['.ph', 'Asia Pacific'],
            ['.my', 'Asia Pacific'],
            ['.id', 'Asia Pacific'],
            ['.in', 'Asia Pacific'],
            ['.jp', 'Asia Pacific'],
            ['.nz', 'Asia Pacific'],
            ['.ae', 'Middle East'],
            ['.sa', 'Middle East'],
            ['.qa', 'Middle East'],
            ['.br', 'Latin America'],
            ['.mx', 'Latin America'],
            ['.ar', 'Latin America'],
            ['.cl', 'Latin America'],
            ['.co', 'Latin America'],
            ['.za', 'Africa'],
            ['.ng', 'Africa'],
            ['.ke', 'Africa'],
            ['.us', 'USA'],
        ];

        for (const [suffix, region] of regionMap) {
            if (value.endsWith(suffix)) {
                return region;
            }
        }

        return 'Global';
    };

    const inferDirectoryDefaults = (website) => {
        const normalizedWebsite = normalizeUrl(website);
        const defaults = {
            directoryType: 'General',
            country: 'Global',
            priorityLevel: 'medium',
            pricingModel: 'free',
            requiresLogin: '1',
        };

        if (!normalizedWebsite) {
            return defaults;
        }

        let parsed;
        try {
            parsed = new URL(normalizedWebsite);
        } catch (error) {
            return defaults;
        }

        const host = parsed.host.toLowerCase();
        const path = parsed.pathname.toLowerCase();
        defaults.country = inferRegion(host);

        if (host.includes('google.')) {
            return { ...defaults, directoryType: 'Search', country: 'Global', priorityLevel: 'high', pricingModel: 'free', requiresLogin: '1' };
        }

        const rules = [
            ['bing.com', { directoryType: 'Search', priorityLevel: 'high', pricingModel: 'free', requiresLogin: '1' }],
            ['yahoo.com', { directoryType: 'Search', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['maps.apple.', { directoryType: 'Map', priorityLevel: 'high', pricingModel: 'free', requiresLogin: '1' }],
            ['mapquest.', { directoryType: 'Map', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '0' }],
            ['waze.com', { directoryType: 'Map', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['facebook.com', { directoryType: 'Social', priorityLevel: 'high', pricingModel: 'free', requiresLogin: '1' }],
            ['instagram.com', { directoryType: 'Social', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['linkedin.com', { directoryType: 'Social', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['tiktok.com', { directoryType: 'Social', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['x.com', { directoryType: 'Social', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['twitter.com', { directoryType: 'Social', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['nextdoor.com', { directoryType: 'Social', priorityLevel: 'high', pricingModel: 'free', requiresLogin: '1' }],
            ['yelp.', { directoryType: 'Review', priorityLevel: 'high', pricingModel: 'free', requiresLogin: '1' }],
            ['tripadvisor.', { directoryType: 'Review', priorityLevel: 'high', pricingModel: 'free', requiresLogin: '1' }],
            ['trustpilot.', { directoryType: 'Review', priorityLevel: 'medium', pricingModel: 'paid', requiresLogin: '1' }],
            ['bbb.org', { directoryType: 'Review', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['angi.com', { directoryType: 'Review', priorityLevel: 'high', pricingModel: 'paid', requiresLogin: '1' }],
            ['thumbtack.com', { directoryType: 'Review', priorityLevel: 'high', pricingModel: 'paid', requiresLogin: '1' }],
            ['yellowpages.', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'paid', requiresLogin: '1' }],
            ['superpages.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'paid', requiresLogin: '1' }],
            ['dexknows.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'paid', requiresLogin: '1' }],
            ['local.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'paid', requiresLogin: '1' }],
            ['merchantcircle.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['citysquares.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['showmelocal.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['brownbook.net', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['2findlocal.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['tuugo.', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['opendi.', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['manta.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['foursquare.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['hotfrog.', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['cylex.', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' }],
            ['alignable.com', { directoryType: 'SMB', priorityLevel: 'medium', pricingModel: 'paid', requiresLogin: '1' }],
            ['crunchbase.com', { directoryType: 'Industry Specific', priorityLevel: 'medium', pricingModel: 'paid', requiresLogin: '1' }],
            ['zoominfo.com', { directoryType: 'Data Aggregator', priorityLevel: 'high', pricingModel: 'paid', requiresLogin: '1' }],
            ['yelpbiz.com', { directoryType: 'Review', priorityLevel: 'high', pricingModel: 'paid', requiresLogin: '1' }],
            ['chambermaster.com', { directoryType: 'Local Chamber', priorityLevel: 'low', pricingModel: 'paid', requiresLogin: '1' }],
            ['chamberofcommerce.', { directoryType: 'Local Chamber', priorityLevel: 'low', pricingModel: 'paid', requiresLogin: '0' }],
        ];

        for (const [needle, rule] of rules) {
            if (host.includes(needle)) {
                return { ...defaults, ...rule };
            }
        }

        if (host.includes('review')) {
            return { ...defaults, directoryType: 'Review', priorityLevel: 'medium' };
        }
        if (host.includes('map')) {
            return { ...defaults, directoryType: 'Map', priorityLevel: 'medium' };
        }
        if (host.includes('social')) {
            return { ...defaults, directoryType: 'Social', priorityLevel: 'medium', pricingModel: 'free', requiresLogin: '1' };
        }
        if (host.includes('chamber')) {
            return { ...defaults, directoryType: 'Local Chamber', priorityLevel: 'low', pricingModel: 'paid', requiresLogin: '0' };
        }
        const pricingSignal = `${host} ${path}`;
        if (
            pricingSignal.includes('premium') ||
            pricingSignal.includes('membership') ||
            pricingSignal.includes('subscription') ||
            pricingSignal.includes('sponsor') ||
            pricingSignal.includes('advertis') ||
            pricingSignal.includes('pricing') ||
            pricingSignal.includes('plans') ||
            pricingSignal.includes('packages') ||
            pricingSignal.includes('upgrade') ||
            pricingSignal.includes('pro-listing') ||
            pricingSignal.includes('featured-listing') ||
            pricingSignal.includes('enhanced-profile') ||
            pricingSignal.includes('claim-business') ||
            pricingSignal.includes('paid')
        ) {
            return { ...defaults, pricingModel: 'paid' };
        }
        if (host.includes('directory') || host.includes('listing')) {
            return { ...defaults, directoryType: 'SMB' };
        }

        return defaults;
    };

    const generateSubmissionUrl = (website, directoryType) => {
        const normalizedWebsite = normalizeUrl(website);
        if (!normalizedWebsite) {
            return '';
        }

        let parsed;
        try {
            parsed = new URL(normalizedWebsite);
        } catch (error) {
            return '';
        }

        const baseUrl = `${parsed.protocol}//${parsed.host}`;
        const host = parsed.host.toLowerCase();
        const hostPatterns = [
            ['google.', 'https://business.google.com/us/business-profile/'],
            ['bing.com', 'https://www.bingplaces.com/'],
            ['facebook.com', 'https://www.facebook.com/pages/create/'],
            ['yelp.', 'https://biz.yelp.com/'],
            ['tripadvisor.', 'https://www.tripadvisor.com/Owners'],
            ['yellowpages.', `${baseUrl}/free-listing`],
            ['chamberofcommerce.', `${baseUrl}/add-business`],
        ];

        for (const [needle, targetUrl] of hostPatterns) {
            if (host.includes(needle)) {
                return targetUrl;
            }
        }

        const defaultPaths = {
            'General': '/add-business',
            'Search': '/add-listing',
            'Map': '/add-listing',
            'Review': '/write-review',
            'SMB': '/add-business',
            'Social': '/signup',
            'Industry Specific': '/add-business',
            'Data Aggregator': '/submit',
            'Local Chamber': '/join',
        };

        return `${baseUrl}${defaultPaths[directoryType] || '/add-business'}`;
    };

    const buildLogoPreviewUrl = (website) => {
        const normalizedWebsite = normalizeUrl(website);
        if (!normalizedWebsite) {
            return '';
        }

        return `https://www.google.com/s2/favicons?sz=256&domain_url=${encodeURIComponent(normalizedWebsite)}`;
    };

    const updateLogoPreview = () => {
        if (!directoryLogoFallback || !directoryLogoAutoPreview) {
            return;
        }

        if (directoryLogoUploadInput && directoryLogoUploadInput.files && directoryLogoUploadInput.files.length > 0) {
            directoryLogoAutoPreview.classList.add('hidden');
            directoryLogoFallback.classList.remove('hidden');
            if (directoryLogoAutoHint) {
                directoryLogoAutoHint.textContent = 'Manual upload selected.';
            }
            return;
        }

        const previewUrl = buildLogoPreviewUrl(directoryWebsiteInput.value);
        if (!previewUrl) {
            directoryLogoAutoPreview.classList.add('hidden');
            directoryLogoFallback.classList.remove('hidden');
            if (directoryLogoAutoHint) {
                directoryLogoAutoHint.textContent = 'Logo preview will appear here when a match is found.';
            }
            return;
        }

        directoryLogoAutoPreview.onload = () => {
            directoryLogoAutoPreview.classList.remove('hidden');
            directoryLogoFallback.classList.add('hidden');
            if (directoryLogoAutoHint) {
                directoryLogoAutoHint.textContent = 'This PNG logo will be fetched and stored locally when you save.';
            }
        };
        directoryLogoAutoPreview.onerror = () => {
            directoryLogoAutoPreview.classList.add('hidden');
            directoryLogoFallback.classList.remove('hidden');
            if (directoryLogoAutoHint) {
                directoryLogoAutoHint.textContent = 'No logo preview found yet for this website.';
            }
        };
        if (directoryLogoAutoPreview.src !== previewUrl) {
            directoryLogoAutoPreview.src = previewUrl;
        }
    };

    let lastSuggestedDefaults = inferDirectoryDefaults(directoryWebsiteInput.value);
    let lastGeneratedSubmissionUrl = generateSubmissionUrl(directoryWebsiteInput.value, directoryTypeInput.value);
    const initialSubmissionUrl = (submissionUrlInput.value || '').trim();
    let directoryTypeManuallyEdited = (directoryTypeInput.value || '') !== lastSuggestedDefaults.directoryType;
    let directoryCountryManuallyEdited = (directoryCountryInput.value || '') !== lastSuggestedDefaults.country;
    let directoryPriorityManuallyEdited = (directoryPriorityInput.value || '') !== lastSuggestedDefaults.priorityLevel;
    let directoryPricingModelManuallyEdited = (directoryPricingModelInput.value || '') !== lastSuggestedDefaults.pricingModel;
    let directoryRequiresLoginManuallyEdited = (directoryRequiresLoginInput.value || '') !== lastSuggestedDefaults.requiresLogin;
    let submissionUrlManuallyEdited = initialSubmissionUrl !== '' && initialSubmissionUrl !== lastGeneratedSubmissionUrl;

    const syncSelectValue = (input, key, nextValue, manuallyEdited) => {
        const currentValue = input.value || '';
        if (!manuallyEdited || currentValue === lastSuggestedDefaults[key]) {
            input.value = nextValue;
        }
    };

    const syncSuggestedFields = () => {
        const nextDefaults = inferDirectoryDefaults(directoryWebsiteInput.value);
        syncSelectValue(directoryTypeInput, 'directoryType', nextDefaults.directoryType, directoryTypeManuallyEdited);
        syncSelectValue(directoryCountryInput, 'country', nextDefaults.country, directoryCountryManuallyEdited);
        syncSelectValue(directoryPriorityInput, 'priorityLevel', nextDefaults.priorityLevel, directoryPriorityManuallyEdited);
        syncSelectValue(directoryPricingModelInput, 'pricingModel', nextDefaults.pricingModel, directoryPricingModelManuallyEdited);
        syncSelectValue(directoryRequiresLoginInput, 'requiresLogin', nextDefaults.requiresLogin, directoryRequiresLoginManuallyEdited);

        const nextSubmissionUrl = generateSubmissionUrl(directoryWebsiteInput.value, directoryTypeInput.value);
        if (!submissionUrlManuallyEdited || (submissionUrlInput.value || '').trim() === lastGeneratedSubmissionUrl) {
            submissionUrlInput.value = nextSubmissionUrl;
            submissionUrlInput.dataset.autogenerated = '1';
        }
        lastSuggestedDefaults = nextDefaults;
        lastGeneratedSubmissionUrl = nextSubmissionUrl;
        updateLogoPreview();
    };

    submissionUrlInput.addEventListener('input', () => {
        const currentValue = (submissionUrlInput.value || '').trim();
        submissionUrlManuallyEdited = currentValue !== '' && currentValue !== lastGeneratedSubmissionUrl;
        if (!submissionUrlManuallyEdited) {
            submissionUrlInput.dataset.autogenerated = '1';
        } else {
            delete submissionUrlInput.dataset.autogenerated;
        }
    });

    directoryTypeInput.addEventListener('change', () => {
        directoryTypeManuallyEdited = (directoryTypeInput.value || '') !== lastSuggestedDefaults.directoryType;
        syncSuggestedFields();
    });
    directoryCountryInput.addEventListener('change', () => {
        directoryCountryManuallyEdited = (directoryCountryInput.value || '') !== lastSuggestedDefaults.country;
    });
    directoryPriorityInput.addEventListener('change', () => {
        directoryPriorityManuallyEdited = (directoryPriorityInput.value || '') !== lastSuggestedDefaults.priorityLevel;
    });
    directoryPricingModelInput.addEventListener('change', () => {
        directoryPricingModelManuallyEdited = (directoryPricingModelInput.value || '') !== lastSuggestedDefaults.pricingModel;
    });
    directoryRequiresLoginInput.addEventListener('change', () => {
        directoryRequiresLoginManuallyEdited = (directoryRequiresLoginInput.value || '') !== lastSuggestedDefaults.requiresLogin;
    });
    directoryWebsiteInput.addEventListener('input', syncSuggestedFields);
    if (directoryLogoUploadInput) {
        directoryLogoUploadInput.addEventListener('change', updateLogoPreview);
    }
    syncSuggestedFields();
}

// Name and notes autofill
(function () {
    const nameInput = document.getElementById('directory_name');
    const notesTextarea = document.getElementById('directory_notes');
    const websiteInput = document.getElementById('directory_website');
    if (!nameInput || !notesTextarea || !websiteInput) return;

    let nameManuallyEdited = (nameInput.value || '').trim() !== '';
    let lastAutofillName = '';

    let notesManuallyEdited = (notesTextarea.value || '').trim() !== '';
    let notesDebounceTimer = null;
    let lastAutofillNotes = '';

    nameInput.addEventListener('input', () => {
        const current = (nameInput.value || '').trim();
        nameManuallyEdited = current !== '' && current !== lastAutofillName;
    });

    notesTextarea.addEventListener('input', () => {
        const current = (notesTextarea.value || '').trim();
        notesManuallyEdited = current !== '' && current !== lastAutofillNotes;
    });

    const autofillProfile = () => {
        const website = (websiteInput.value || '').trim();
        if (!website || (nameManuallyEdited && notesManuallyEdited)) return;

        const base = <?php echo json_encode(app_config()['base_url']); ?>;
        fetch(`${base}/directory_form.php?action=autofill_directory&website=${encodeURIComponent(website)}`)
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                if (!nameManuallyEdited && data.name && data.name.trim() !== '') {
                    lastAutofillName = data.name.trim();
                    nameInput.value = lastAutofillName;
                }
                if (!notesManuallyEdited && data.notes && data.notes.trim() !== '') {
                    lastAutofillNotes = data.notes.trim();
                    notesTextarea.value = lastAutofillNotes;
                }
            })
            .catch(() => {});
    };

    websiteInput.addEventListener('input', () => {
        clearTimeout(notesDebounceTimer);
        notesDebounceTimer = setTimeout(autofillProfile, 900);
    });
}());
</script>
<?php render_footer(); ?>
