<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('businesses');

function normalize_external_url(string $value, bool $preserveQuery = false): string
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
    $query = trim((string)($parts['query'] ?? ''));

    $normalized = $scheme . '://' . $host . ($path !== '' ? $path : '');
    if ($preserveQuery && $query !== '') {
        $normalized .= '?' . $query;
    }

    return rtrim($normalized, '/');
}

function build_business_logo_source_url(string $website): string
{
    $normalizedWebsite = normalize_external_url($website);
    if ($normalizedWebsite === '') {
        return '';
    }

    return 'https://www.google.com/s2/favicons?sz=256&domain_url=' . rawurlencode($normalizedWebsite);
}

function is_auto_business_logo_path(string $path): bool
{
    return str_starts_with(trim($path), 'uploads/business_logo_auto_');
}

function fetch_business_logo_from_website(string $website): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $normalizedWebsite = normalize_external_url($website);
    $sourceUrl = build_business_logo_source_url($normalizedWebsite);
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
        $safeHost = 'business';
    }

    $saveError = null;
    return save_image_binary_to_uploads($body, 'business_logo_auto', 'business_logo_auto_' . $safeHost, $saveError);
}

function fetch_remote_html_document(string $url): ?string
{
    if ($url === '' || !function_exists('curl_init')) {
        return null;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CiteFlow Manager/1.0)',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'],
    ]);

    $body = curl_exec($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = strtolower((string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
    curl_close($curl);

    if (!is_string($body) || $body === '' || $statusCode >= 400) {
        return null;
    }

    if ($contentType !== '' && !str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml')) {
        return null;
    }

    return $body;
}

function flatten_schema_nodes(mixed $node): array
{
    $nodes = [];

    $walk = static function (mixed $item) use (&$walk, &$nodes): void {
        if (!is_array($item)) {
            return;
        }

        if (array_is_list($item)) {
            foreach ($item as $child) {
                $walk($child);
            }
            return;
        }

        $nodes[] = $item;
        foreach (['@graph', 'mainEntity', 'subjectOf', 'itemListElement', 'hasOfferCatalog', 'makesOffer', 'department'] as $key) {
            if (isset($item[$key])) {
                $walk($item[$key]);
            }
        }
    };

    $walk($node);

    return $nodes;
}

function extract_json_ld_nodes(string $html): array
{
    $matches = [];
    preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);

    $nodes = [];
    foreach (($matches[1] ?? []) as $payload) {
        $decoded = json_decode(html_entity_decode(trim((string)$payload), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        if ($decoded === null) {
            continue;
        }

        $nodes = array_merge($nodes, flatten_schema_nodes($decoded));
    }

    return $nodes;
}

function schema_types(array $candidate): array
{
    $types = $candidate['@type'] ?? [];
    if (is_string($types)) {
        return [$types];
    }
    if (is_array($types)) {
        return array_values(array_filter(array_map(static fn ($type): string => is_string($type) ? trim($type) : '', $types)));
    }

    return [];
}

function schema_candidate_score(array $candidate): int
{
    $score = 0;
    $types = schema_types($candidate);

    foreach ($types as $type) {
        $normalized = strtolower($type);
        if (str_contains($normalized, 'localbusiness')) {
            $score += 100;
        } elseif (str_contains($normalized, 'organization')) {
            $score += 60;
        } elseif (str_contains($normalized, 'store') || str_contains($normalized, 'restaurant') || str_contains($normalized, 'service')) {
            $score += 80;
        }
    }

    if (isset($candidate['address'])) {
        $score += 25;
    }
    if (isset($candidate['telephone'])) {
        $score += 20;
    }
    if (isset($candidate['email'])) {
        $score += 15;
    }
    if (isset($candidate['openingHours']) || isset($candidate['openingHoursSpecification'])) {
        $score += 10;
    }
    if (isset($candidate['sameAs'])) {
        $score += 5;
    }
    if (isset($candidate['name'])) {
        $score += 5;
    }

    return $score;
}

function pick_best_business_schema(array $nodes): ?array
{
    $best = null;
    $bestScore = 0;

    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }

        $score = schema_candidate_score($node);
        if ($score > $bestScore) {
            $best = $node;
            $bestScore = $score;
        }
    }

    return $best;
}

function normalize_line_collection(mixed $value): array
{
    if (is_string($value)) {
        $parts = preg_split('/\r\n|\r|\n|,|;/', $value) ?: [];
        return array_values(array_filter(array_map(static fn (string $part): string => trim($part), $parts), static fn (string $part): bool => $part !== ''));
    }

    if (!is_array($value)) {
        return [];
    }

    $result = [];
    foreach ($value as $item) {
        if (is_string($item)) {
            $item = trim($item);
            if ($item !== '') {
                $result[] = $item;
            }
            continue;
        }

        if (is_array($item)) {
            foreach (['name', 'text', '@value'] as $key) {
                if (isset($item[$key]) && is_string($item[$key]) && trim((string)$item[$key]) !== '') {
                    $result[] = trim((string)$item[$key]);
                    break;
                }
            }
        }
    }

    return array_values(array_unique($result));
}

function format_opening_hours_from_schema(array $candidate): string
{
    $lines = normalize_line_collection($candidate['openingHours'] ?? []);

    $specs = $candidate['openingHoursSpecification'] ?? null;
    if (is_array($specs)) {
        $specList = array_is_list($specs) ? $specs : [$specs];
        foreach ($specList as $spec) {
            if (!is_array($spec)) {
                continue;
            }

            $days = normalize_line_collection($spec['dayOfWeek'] ?? []);
            $dayLabel = implode(', ', array_map(static function (string $day): string {
                if (str_contains($day, '/')) {
                    $day = substr($day, strrpos($day, '/') + 1);
                }
                return trim(preg_replace('/(?<!^)([A-Z])/', ' $1', $day) ?? $day);
            }, $days));

            $opens = trim((string)($spec['opens'] ?? ''));
            $closes = trim((string)($spec['closes'] ?? ''));
            if ($dayLabel === '' || $opens === '' || $closes === '') {
                continue;
            }

            $lines[] = $dayLabel . ': ' . $opens . ' - ' . $closes;
        }
    }

    $lines = array_values(array_unique(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== '')));

    return implode("\n", $lines);
}

function collect_named_items(mixed $value, array &$results): void
{
    if (!is_array($value)) {
        return;
    }

    if (array_is_list($value)) {
        foreach ($value as $item) {
            collect_named_items($item, $results);
        }
        return;
    }

    foreach (['name', 'serviceType'] as $key) {
        if (isset($value[$key]) && is_string($value[$key]) && trim((string)$value[$key]) !== '') {
            $results[] = trim((string)$value[$key]);
        }
    }

    foreach (['itemOffered', 'itemListElement', 'hasOfferCatalog', 'makesOffer', 'availableService'] as $key) {
        if (isset($value[$key])) {
            collect_named_items($value[$key], $results);
        }
    }
}

function extract_address_parts(mixed $address): array
{
    $parts = [
        'address_line1' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => '',
    ];

    if (is_string($address)) {
        $parts['address_line1'] = trim($address);
        return $parts;
    }

    if (!is_array($address)) {
        return $parts;
    }

    $parts['address_line1'] = trim((string)($address['streetAddress'] ?? $address['name'] ?? ''));
    $parts['city'] = trim((string)($address['addressLocality'] ?? ''));
    $parts['state'] = trim((string)($address['addressRegion'] ?? ''));
    $parts['postal_code'] = trim((string)($address['postalCode'] ?? ''));
    $country = $address['addressCountry'] ?? '';
    if (is_array($country)) {
        $country = $country['name'] ?? ($country['@value'] ?? '');
    }
    $parts['country'] = trim((string)$country);

    return $parts;
}

function extract_business_name_from_gbp_link(string $gbpLink): string
{
    $normalized = normalize_external_url($gbpLink, true);
    if ($normalized === '') {
        return '';
    }

    $parts = parse_url($normalized);
    if (!is_array($parts)) {
        return '';
    }

    $path = trim((string)($parts['path'] ?? ''), '/');
    if ($path !== '') {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        foreach ($segments as $index => $segment) {
            if (strtolower($segment) === 'place' && isset($segments[$index + 1])) {
                return trim((string)urldecode(str_replace('+', ' ', $segments[$index + 1])));
            }
        }
    }

    parse_str((string)($parts['query'] ?? ''), $query);
    $queryName = trim((string)($query['q'] ?? $query['query'] ?? ''));
    if ($queryName !== '') {
        return trim((string)urldecode(str_replace('+', ' ', $queryName)));
    }

    return '';
}

function trim_title_to_business_name(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }

    $parts = preg_split('/\s+[\-|\x{2013}|\x{2014}]\s+/u', $title) ?: [];
    return trim((string)($parts[0] ?? $title));
}

function extract_business_profile_from_website(string $website, string $gbpLink = ''): array
{
    $normalizedWebsite = normalize_external_url($website);
    $profile = [
        'name' => '',
        'website' => $normalizedWebsite,
        'gbp_link' => normalize_external_url($gbpLink, true),
        'phone' => '',
        'email' => '',
        'address_line1' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => '',
        'category' => '',
        'categories' => '',
        'description' => '',
        'services' => '',
        'hours_json' => '',
        'payment_methods' => '',
        'social_media' => '',
        'contact_name' => '',
        'in_business_since' => '',
        'logo_preview_url' => build_business_logo_source_url($normalizedWebsite),
    ];

    if ($normalizedWebsite === '') {
        if ($profile['gbp_link'] !== '') {
            $profile['name'] = extract_business_name_from_gbp_link($profile['gbp_link']);
        }
        return $profile;
    }

    $html = fetch_remote_html_document($normalizedWebsite);
    if ($html === null) {
        if ($profile['gbp_link'] !== '' && $profile['name'] === '') {
            $profile['name'] = extract_business_name_from_gbp_link($profile['gbp_link']);
        }
        return $profile;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xpath = $loaded ? new DOMXPath($dom) : null;
    $metaValues = [];
    $title = '';
    if ($xpath instanceof DOMXPath) {
        $title = trim((string)($xpath->evaluate('string(//title)') ?? ''));
        foreach ($xpath->query('//meta[@name or @property]') ?: [] as $metaNode) {
            if (!$metaNode instanceof DOMElement) {
                continue;
            }

            $key = strtolower(trim((string)($metaNode->getAttribute('name') ?: $metaNode->getAttribute('property'))));
            $value = trim((string)$metaNode->getAttribute('content'));
            if ($key !== '' && $value !== '' && !isset($metaValues[$key])) {
                $metaValues[$key] = $value;
            }
        }
    }

    $schema = pick_best_business_schema(extract_json_ld_nodes($html)) ?? [];
    $address = extract_address_parts($schema['address'] ?? null);
    $socialLines = [];
    if (isset($schema['sameAs'])) {
        $socialLines = normalize_line_collection($schema['sameAs']);
    }
    if ($xpath instanceof DOMXPath) {
        foreach ($xpath->query('//a[@href]') ?: [] as $linkNode) {
            if (!$linkNode instanceof DOMElement) {
                continue;
            }

            $href = trim((string)$linkNode->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            if ($profile['email'] === '' && str_starts_with(strtolower($href), 'mailto:')) {
                $profile['email'] = trim(substr($href, 7));
            }

            if ($profile['phone'] === '' && str_starts_with(strtolower($href), 'tel:')) {
                $profile['phone'] = trim(substr($href, 4));
            }

            foreach (['facebook.com', 'instagram.com', 'linkedin.com', 'x.com', 'twitter.com', 'youtube.com', 'tiktok.com'] as $socialHost) {
                if (str_contains(strtolower($href), $socialHost)) {
                    $socialLines[] = $href;
                    break;
                }
            }
        }
    }

    $categories = normalize_line_collection($schema['keywords'] ?? ($schema['category'] ?? []));
    if ($categories === []) {
        foreach (schema_types($schema) as $type) {
            if (in_array($type, ['Thing', 'Organization', 'LocalBusiness'], true)) {
                continue;
            }
            $categories[] = trim((string)preg_replace('/(?<!^)([A-Z])/', ' $1', $type));
        }
    }

    $services = [];
    collect_named_items($schema['hasOfferCatalog'] ?? [], $services);
    collect_named_items($schema['makesOffer'] ?? [], $services);
    collect_named_items($schema['availableService'] ?? [], $services);

    $contactName = '';
    foreach (['founder', 'employee', 'contactPoint'] as $key) {
        $value = $schema[$key] ?? null;
        if (is_array($value)) {
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    if (is_array($item) && is_string($item['name'] ?? null) && trim((string)$item['name']) !== '') {
                        $contactName = trim((string)$item['name']);
                        break 2;
                    }
                }
            } elseif (is_string($value['name'] ?? null) && trim((string)$value['name']) !== '') {
                $contactName = trim((string)$value['name']);
                break;
            }
        }
    }

    $profile['name'] = trim((string)($schema['name'] ?? $metaValues['og:site_name'] ?? $metaValues['application-name'] ?? trim_title_to_business_name($title)));
    $profile['website'] = normalize_external_url((string)($schema['url'] ?? $normalizedWebsite)) ?: $normalizedWebsite;
    $profile['phone'] = trim((string)($schema['telephone'] ?? $profile['phone']));
    $profile['email'] = trim((string)($schema['email'] ?? $profile['email']));
    $profile['address_line1'] = $address['address_line1'];
    $profile['city'] = $address['city'];
    $profile['state'] = $address['state'];
    $profile['postal_code'] = $address['postal_code'];
    $profile['country'] = $address['country'];
    $profile['contact_name'] = $contactName;
    $profile['in_business_since'] = trim((string)($schema['foundingDate'] ?? $schema['foundingDateTime'] ?? ''));
    $profile['description'] = trim((string)($schema['description'] ?? $metaValues['description'] ?? $metaValues['og:description'] ?? ''));
    $normalizedCategories = array_values(array_unique(array_filter(array_map('trim', $categories), static fn (string $value): bool => $value !== '')));
    $profile['categories'] = implode("\n", array_slice($normalizedCategories, 0, 10));
    $profile['category'] = trim((string)($categories[0] ?? ''));
    $normalizedServices = array_values(array_unique(array_filter(array_map('trim', $services), static fn (string $service): bool => $service !== '')));
    $profile['services'] = implode("\n", array_slice($normalizedServices, 0, 12));
    $profile['hours_json'] = format_opening_hours_from_schema($schema);
    $profile['payment_methods'] = implode(', ', normalize_line_collection($schema['paymentAccepted'] ?? []));
    $normalizedSocialLines = array_values(array_unique(array_filter(array_map('trim', $socialLines), static fn (string $value): bool => $value !== '')));
    $profile['social_media'] = implode("\n", array_slice($normalizedSocialLines, 0, 10));

    if ($profile['name'] === '' && $profile['gbp_link'] !== '') {
        $profile['name'] = extract_business_name_from_gbp_link($profile['gbp_link']);
    }

    return $profile;
}

function apply_autofill_profile_to_business_data(array $data, array $profile): array
{
    $overwritableDefaults = [
        'country' => 'USA',
        'hours_json' => 'Mon-Fri: 9 AM - 5 PM; Sat: 10 AM - 2 PM; Sun: Closed',
    ];

    foreach ([
        'name',
        'website',
        'gbp_link',
        'phone',
        'email',
        'address_line1',
        'city',
        'state',
        'postal_code',
        'country',
        'categories',
        'description',
        'services',
        'hours_json',
        'payment_methods',
        'social_media',
        'contact_name',
        'in_business_since',
    ] as $field) {
        $current = trim((string)($data[$field] ?? ''));
        $suggested = trim((string)($profile[$field] ?? ''));
        $defaultValue = trim((string)($overwritableDefaults[$field] ?? ''));

        if ($suggested === '') {
            continue;
        }

        if ($current === '' || ($defaultValue !== '' && $current === $defaultValue)) {
            $data[$field] = $suggested;
        }
    }

    return $data;
}

function duplicate_business_exists(array $data, int $excludeId = 0): bool
{
    $name = trim((string)($data['name'] ?? ''));
    $addressLine1 = trim((string)($data['address_line1'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $state = trim((string)($data['state'] ?? ''));
    $postalCode = trim((string)($data['postal_code'] ?? ''));

    if ($name === '' || $addressLine1 === '' || $city === '' || $state === '' || $postalCode === '') {
        return false;
    }

    $sql = 'SELECT id FROM businesses WHERE name = ? AND address_line1 = ? AND city = ? AND state = ? AND postal_code = ?';
    $params = [$name, $addressLine1, $city, $state, $postalCode];
    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetch();
}

if (($_GET['action'] ?? '') === 'autofill_business_profile') {
    $website = normalize_external_url((string)($_GET['website'] ?? ''));
    $gbpLink = normalize_external_url((string)($_GET['gbp_link'] ?? ''), true);

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(extract_business_profile_from_website($website, $gbpLink), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$user = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$data = [
    'client_id' => '',
    'name' => '',
    'logo_path' => '',
    'phone' => '',
    'website' => '',
    'gbp_link' => '',
    'email' => '',
    'address_line1' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => 'USA',
    'category' => '',
    'categories' => '',
    'description' => '',
    'services' => '',
    'hours_json' => 'Mon-Fri: 9 AM - 5 PM; Sat: 10 AM - 2 PM; Sun: Closed',
    'login_credentials' => '',
    'payment_methods' => '',
    'in_business_since' => '',
    'social_media' => '',
    'contact_name' => '',
    'status' => 'active',
];

$clientOptions = active_clients();

if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM businesses WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $data = array_merge($data, $existing);
        if (($data['categories'] ?? '') === '' && ($data['category'] ?? '') !== '') {
            $data['categories'] = (string)$data['category'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $previousStatus = (string)($data['status'] ?? '');
    $previousName = (string)($data['name'] ?? '');
    foreach ($data as $k => $v) {
        $data[$k] = post($k, (string)$v);
    }

    $data['website'] = normalize_external_url((string)$data['website']);
    $data['gbp_link'] = normalize_external_url((string)$data['gbp_link'], true);
    $data = apply_autofill_profile_to_business_data(
        $data,
        extract_business_profile_from_website((string)$data['website'], (string)$data['gbp_link'])
    );

    $previousLogoPath = trim((string)$data['logo_path']);
    $removeLogoRequested = post('remove_logo') === '1';

    $uploadError = null;
    $hasUploadError = false;
    $uploadedLogoPath = save_uploaded_image('logo', 'business_logo', $uploadError);
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
    } elseif (!$removeLogoRequested && $data['website'] !== '' && ($previousLogoPath === '' || is_auto_business_logo_path($previousLogoPath))) {
        $autoLogoPath = fetch_business_logo_from_website((string)$data['website']);
        if ($autoLogoPath !== null) {
            if ($previousLogoPath !== '' && $previousLogoPath !== $autoLogoPath && is_auto_business_logo_path($previousLogoPath)) {
                delete_uploaded_asset($previousLogoPath);
            }
            $data['logo_path'] = $autoLogoPath;
        }
    }

    if ($data['categories'] !== '') {
        $categoryLines = preg_split('/\r\n|\r|\n/', $data['categories']);
        $data['category'] = trim((string)($categoryLines[0] ?? ''));
    }

    if ($hasUploadError) {
        // upload error already handled
    } elseif ($data['name'] === '' || $data['phone'] === '' || $data['address_line1'] === '' || $data['city'] === '' || $data['state'] === '' || $data['postal_code'] === '') {
        set_flash('err', 'Please fill all required fields.');
    } elseif (duplicate_business_exists($data, $id)) {
        set_flash('err', 'A business with the same name and address already exists.');
    } else {
        if ($id > 0) {
            $sql = 'UPDATE businesses SET client_id=?, name=?, logo_path=?, phone=?, website=?, gbp_link=?, email=?, address_line1=?, city=?, state=?, postal_code=?, country=?, category=?, categories=?, description=?, services=?, hours_json=?, login_credentials=?, payment_methods=?, in_business_since=?, social_media=?, contact_name=?, status=? WHERE id=?';
            db()->prepare($sql)->execute([
                $data['client_id'] !== '' ? (int)$data['client_id'] : null, $data['name'], $data['logo_path'], $data['phone'], $data['website'], $data['gbp_link'], $data['email'], $data['address_line1'], $data['city'],
                $data['state'], $data['postal_code'], $data['country'], $data['category'], $data['categories'], $data['description'], $data['services'], $data['hours_json'],
                $data['login_credentials'], $data['payment_methods'], $data['in_business_since'], $data['social_media'], $data['contact_name'], $data['status'], $id
            ]);
            log_activity('business', $id, 'update', [
                'name' => $data['name'],
                'status' => $data['status'],
                'city' => $data['city'],
                'state' => $data['state'],
            ]);

            $actorName = (string)($user['full_name'] ?? 'A user');
            $actionUrl = app_config()['base_url'] . '/location_manager.php?business_id=' . $id;
            $statusLabel = ucwords((string)$data['status']);
            if ($previousStatus !== (string)$data['status']) {
                $kind = ((string)$data['status'] === 'inactive' || (string)$data['status'] === 'pending') ? 'warning' : 'info';
                create_notification(
                    null,
                    'Business status updated: ' . (string)$data['name'],
                    $actorName . ' changed ' . (string)$data['name'] . ' status from ' . ucwords($previousStatus) . ' to ' . $statusLabel . '.',
                    $actionUrl,
                    $kind
                );
            } elseif ($previousName !== (string)$data['name']) {
                create_notification(
                    null,
                    'Business renamed: ' . (string)$data['name'],
                    $actorName . ' renamed business from ' . $previousName . ' to ' . (string)$data['name'] . '.',
                    $actionUrl,
                    'info'
                );
            }
            set_flash('ok', 'Business updated.');
        } else {
            $sql = 'INSERT INTO businesses (client_id, name, logo_path, phone, website, gbp_link, email, address_line1, city, state, postal_code, country, category, categories, description, services, hours_json, login_credentials, payment_methods, in_business_since, social_media, contact_name, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            db()->prepare($sql)->execute([
                $data['client_id'] !== '' ? (int)$data['client_id'] : null, $data['name'], $data['logo_path'], $data['phone'], $data['website'], $data['gbp_link'], $data['email'], $data['address_line1'], $data['city'],
                $data['state'], $data['postal_code'], $data['country'], $data['category'], $data['categories'], $data['description'], $data['services'], $data['hours_json'],
                $data['login_credentials'], $data['payment_methods'], $data['in_business_since'], $data['social_media'], $data['contact_name'], $data['status'], $user['id']
            ]);
            $newId = (int)db()->lastInsertId();
            log_activity('business', $newId, 'create', [
                'name' => $data['name'],
                'status' => $data['status'],
                'city' => $data['city'],
                'state' => $data['state'],
            ]);

            $actorName = (string)($user['full_name'] ?? 'A user');
            create_notification(
                null,
                'New business added: ' . (string)$data['name'],
                $actorName . ' added ' . (string)$data['name'] . ' (' . (string)$data['city'] . ', ' . (string)$data['state'] . ').',
                app_config()['base_url'] . '/location_manager.php?business_id=' . $newId,
                'info'
            );
            set_flash('ok', 'Business created.');
        }
        redirect('/businesses.php');
    }
}

render_header($id > 0 ? 'Edit Business' : 'Add Business');
?>
<section class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-gradient-to-r from-brand-50 via-white to-brand-100/60 p-5 shadow-sm sm:p-6 dark:border-slate-700 dark:from-slate-900 dark:via-brand-950/35 dark:to-slate-800">
    <h1 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo $id > 0 ? 'Edit Business' : 'Add Business'; ?></h1>
    <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">Keep business data standardized for faster citation submission.</p>

    <form method="post" enctype="multipart/form-data" class="mt-6 space-y-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Client</label>
                <select class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="client_id">
                    <option value="">No client</option>
                    <?php foreach ($clientOptions as $client): ?>
                        <option value="<?php echo e((string)$client['id']); ?>" <?php echo (string)$data['client_id'] === (string)$client['id'] ? 'selected' : ''; ?>><?php echo e((string)$client['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Name *</label>
                <input id="business_name" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="name" value="<?php echo e($data['name']); ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Phone *</label>
                <input class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="phone" value="<?php echo e($data['phone']); ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Status</label>
                <select class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="status">
                    <?php foreach (['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive'] as $sv => $sl): ?>
                        <option value="<?php echo $sv; ?>"<?php echo $data['status'] === $sv ? ' selected' : ''; ?>><?php echo $sl; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Website</label>
                <input id="business_website" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="website" value="<?php echo e($data['website']); ?>">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">GBP Link</label>
                <input id="business_gbp_link" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="gbp_link" value="<?php echo e((string)$data['gbp_link']); ?>" placeholder="https://www.google.com/maps/place/...">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Email</label>
                <input id="business_email" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="email" value="<?php echo e($data['email']); ?>">
            </div>
        </div>

        <p class="text-xs text-slate-500">CiteFlow will try to read the business website metadata and structured data, then suggest the profile fields below. If a GBP link is provided, it is stored and can help identify the business name when the website is limited.</p>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Business Logo</label>
            <input id="business_logo_upload" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" type="file" name="logo" accept="image/*">
            <p class="mt-1 text-xs text-slate-500">Any image size is accepted. CiteFlow auto-optimizes logos to a maximum of 200KB. If no file is uploaded, it will also try to fetch and optimize the website logo automatically.</p>
            <?php if (trim((string)$data['logo_path']) !== ''): ?>
                <img class="mt-2 h-14 w-14 rounded-lg border border-slate-200 object-cover" src="<?php echo e(public_asset_url((string)$data['logo_path'])); ?>" alt="Business logo">
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300">
                    Remove current logo
                </label>
            <?php else: ?>
                <?php $businessInitial = strtoupper(substr(trim((string)$data['name']), 0, 1)) ?: '?'; ?>
                <div class="mt-2 flex items-center gap-3">
                    <img id="business_logo_auto_preview" class="hidden h-14 w-14 rounded-lg border border-slate-200 object-cover" alt="Auto-generated business logo preview">
                    <div class="inline-flex h-14 w-14 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-lg font-bold text-slate-600" id="business_logo_fallback"><?php echo e($businessInitial); ?></div>
                    <p id="business_logo_auto_hint" class="text-xs text-slate-500">Logo preview will appear here when a match is found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Address *</label>
            <input id="business_address_line1" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="address_line1" value="<?php echo e($data['address_line1']); ?>" required>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">City *</label>
                <input id="business_city" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="city" value="<?php echo e($data['city']); ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">State *</label>
                <input id="business_state" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="state" value="<?php echo e($data['state']); ?>" required>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Postal Code *</label>
                <input id="business_postal_code" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="postal_code" value="<?php echo e($data['postal_code']); ?>" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Country</label>
                <input id="business_country" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="country" value="<?php echo e($data['country']); ?>">
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Contact Name</label>
                <input id="business_contact_name" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="contact_name" value="<?php echo e((string)$data['contact_name']); ?>">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">In Business Since</label>
                <input id="business_in_business_since" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="in_business_since" value="<?php echo e((string)$data['in_business_since']); ?>" placeholder="e.g. 2014 or March 2014">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Categories</label>
            <textarea id="business_categories" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="categories" rows="3" placeholder="Primary category on first line, additional categories on new lines"><?php echo e((string)$data['categories']); ?></textarea>
            <p class="mt-1 text-xs text-slate-500">The first category is also retained in the legacy category field for compatibility.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Description</label>
            <textarea id="business_description" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="description" rows="4"><?php echo e($data['description']); ?></textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Services</label>
            <textarea id="business_services" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="services" rows="4" placeholder="Service 1&#10;Service 2&#10;Service 3"><?php echo e((string)$data['services']); ?></textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Business Hours</label>
            <textarea id="business_hours_json" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="hours_json" rows="3" placeholder="Mon-Fri: 9 AM - 5 PM; Sat: 10 AM - 2 PM; Sun: Closed"><?php echo e($data['hours_json']); ?></textarea>
            <p class="mt-1 text-xs text-slate-500">Use plain, human-readable format. JSON is no longer needed.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Login Credentials</label>
            <textarea class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="login_credentials" rows="4" placeholder="Directory login emails, usernames, passwords, notes"><?php echo e((string)$data['login_credentials']); ?></textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Payment Methods</label>
            <textarea id="business_payment_methods" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="payment_methods" rows="3" placeholder="Cash, Visa, MasterCard, Bank Transfer, etc."><?php echo e((string)$data['payment_methods']); ?></textarea>
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold text-slate-700">Social Media</label>
            <textarea id="business_social_media" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" name="social_media" rows="4" placeholder="Facebook: ...&#10;Instagram: ...&#10;LinkedIn: ..."><?php echo e((string)$data['social_media']); ?></textarea>
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700" type="submit">Save Business</button>
            <a class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" href="<?php echo e(app_config()['base_url']); ?>/businesses.php">Cancel</a>
        </div>
    </form>
</section>
<script>
(function () {
    const businessNameInput = document.getElementById('business_name');
    const businessPhoneInput = document.querySelector('input[name="phone"]');
    const businessWebsiteInput = document.getElementById('business_website');
    const businessGbpLinkInput = document.getElementById('business_gbp_link');
    const businessEmailInput = document.getElementById('business_email');
    const businessAddressInput = document.getElementById('business_address_line1');
    const businessCityInput = document.getElementById('business_city');
    const businessStateInput = document.getElementById('business_state');
    const businessPostalCodeInput = document.getElementById('business_postal_code');
    const businessCountryInput = document.getElementById('business_country');
    const businessContactNameInput = document.getElementById('business_contact_name');
    const businessInBusinessSinceInput = document.getElementById('business_in_business_since');
    const businessCategoriesInput = document.getElementById('business_categories');
    const businessDescriptionInput = document.getElementById('business_description');
    const businessServicesInput = document.getElementById('business_services');
    const businessHoursInput = document.getElementById('business_hours_json');
    const businessPaymentMethodsInput = document.getElementById('business_payment_methods');
    const businessSocialMediaInput = document.getElementById('business_social_media');
    const businessLogoFallback = document.getElementById('business_logo_fallback');
    const businessLogoUploadInput = document.getElementById('business_logo_upload');
    const businessLogoAutoPreview = document.getElementById('business_logo_auto_preview');
    const businessLogoAutoHint = document.getElementById('business_logo_auto_hint');

    const updateBusinessInitial = () => {
        if (!businessNameInput || !businessLogoFallback) {
            return;
        }

        const value = (businessNameInput.value || '').trim();
        businessLogoFallback.textContent = value ? value.charAt(0).toUpperCase() : '?';
    };

    if (businessNameInput && businessLogoFallback) {
        businessNameInput.addEventListener('input', updateBusinessInitial);
        updateBusinessInitial();
    }

    if (!businessWebsiteInput || !businessGbpLinkInput) {
        return;
    }

    const fieldInputs = {
        name: businessNameInput,
        phone: businessPhoneInput,
        email: businessEmailInput,
        address_line1: businessAddressInput,
        city: businessCityInput,
        state: businessStateInput,
        postal_code: businessPostalCodeInput,
        country: businessCountryInput,
        contact_name: businessContactNameInput,
        in_business_since: businessInBusinessSinceInput,
        categories: businessCategoriesInput,
        description: businessDescriptionInput,
        services: businessServicesInput,
        hours_json: businessHoursInput,
        payment_methods: businessPaymentMethodsInput,
        social_media: businessSocialMediaInput,
    };

    const defaultValues = {
        country: 'USA',
        hours_json: 'Mon-Fri: 9 AM - 5 PM; Sat: 10 AM - 2 PM; Sun: Closed',
    };

    let lastSuggestedValues = {};
    const manualEdited = {};

    const markManualState = (field) => {
        const input = fieldInputs[field];
        if (!input) {
            return;
        }

        const current = (input.value || '').trim();
        const last = (lastSuggestedValues[field] || '').trim();
        const defaultValue = (defaultValues[field] || '').trim();
        manualEdited[field] = current !== '' && current !== last && current !== defaultValue;
    };

    Object.keys(fieldInputs).forEach((field) => {
        const input = fieldInputs[field];
        if (!input) {
            return;
        }

        const eventName = input.tagName === 'TEXTAREA' ? 'input' : 'input';
        input.addEventListener(eventName, () => {
            markManualState(field);
            if (field === 'name') {
                updateBusinessInitial();
            }
        });
        markManualState(field);
    });

    const applySuggestedValue = (field, value) => {
        const input = fieldInputs[field];
        if (!input) {
            return;
        }

        const suggested = (value || '').trim();
        if (!suggested) {
            return;
        }

        const current = (input.value || '').trim();
        const last = (lastSuggestedValues[field] || '').trim();
        const defaultValue = (defaultValues[field] || '').trim();
        if (!manualEdited[field] || current === last || current === defaultValue) {
            input.value = suggested;
            if (field === 'name') {
                updateBusinessInitial();
            }
        }
        lastSuggestedValues[field] = suggested;
        markManualState(field);
    };

    const updateLogoPreview = (previewUrl) => {
        if (!businessLogoAutoPreview || !businessLogoFallback) {
            return;
        }

        if (businessLogoUploadInput && businessLogoUploadInput.files && businessLogoUploadInput.files.length > 0) {
            businessLogoAutoPreview.classList.add('hidden');
            businessLogoFallback.classList.remove('hidden');
            if (businessLogoAutoHint) {
                businessLogoAutoHint.textContent = 'Manual upload selected.';
            }
            return;
        }

        const value = (previewUrl || '').trim();
        if (!value) {
            businessLogoAutoPreview.classList.add('hidden');
            businessLogoFallback.classList.remove('hidden');
            if (businessLogoAutoHint) {
                businessLogoAutoHint.textContent = 'Logo preview will appear here when a match is found.';
            }
            return;
        }

        businessLogoAutoPreview.onload = () => {
            businessLogoAutoPreview.classList.remove('hidden');
            businessLogoFallback.classList.add('hidden');
            if (businessLogoAutoHint) {
                businessLogoAutoHint.textContent = 'This logo will be fetched and stored locally when you save.';
            }
        };
        businessLogoAutoPreview.onerror = () => {
            businessLogoAutoPreview.classList.add('hidden');
            businessLogoFallback.classList.remove('hidden');
            if (businessLogoAutoHint) {
                businessLogoAutoHint.textContent = 'No logo preview found yet for this website.';
            }
        };
        if (businessLogoAutoPreview.src !== value) {
            businessLogoAutoPreview.src = value;
        }
    };

    let debounceHandle = 0;
    let requestSerial = 0;

    const triggerAutofill = () => {
        window.clearTimeout(debounceHandle);
        debounceHandle = window.setTimeout(async () => {
            const website = (businessWebsiteInput.value || '').trim();
            const gbpLink = (businessGbpLinkInput.value || '').trim();
            if (!website && !gbpLink) {
                updateLogoPreview('');
                return;
            }

            const currentSerial = ++requestSerial;
            const params = new URLSearchParams({
                action: 'autofill_business_profile',
                website,
                gbp_link: gbpLink,
            });

            try {
                const response = await fetch(`${window.location.pathname}?${params.toString()}`, {
                    headers: {
                        'X-Requested-With': 'fetch',
                    },
                });
                if (!response.ok) {
                    return;
                }

                const profile = await response.json();
                if (currentSerial !== requestSerial || !profile || typeof profile !== 'object') {
                    return;
                }

                Object.keys(fieldInputs).forEach((field) => {
                    applySuggestedValue(field, profile[field] || '');
                });
                updateLogoPreview(profile.logo_preview_url || '');
            } catch (error) {
                // Ignore transient network/parser failures in the form.
            }
        }, 700);
    };

    businessWebsiteInput.addEventListener('input', triggerAutofill);
    businessGbpLinkInput.addEventListener('input', triggerAutofill);
    if (businessLogoUploadInput) {
        businessLogoUploadInput.addEventListener('change', () => updateLogoPreview(''));
    }

    triggerAutofill();
})();
</script>
<?php render_footer(); ?>
