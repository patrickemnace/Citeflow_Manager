<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/xlsx_writer.php';

require_login();
require_permission('location_manager');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
$rawRows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
$businessId = (int)($payload['business_id'] ?? 0);

if (empty($rawRows)) {
    http_response_code(400);
    exit('No data to export');
}

$business = null;
if ($businessId > 0) {
    $stmt = db()->prepare('SELECT b.*, c.name AS client_name FROM businesses b LEFT JOIN clients c ON c.id = b.client_id WHERE b.id = ? LIMIT 1');
    $stmt->execute([$businessId]);
    $business = $stmt->fetch() ?: null;
}

$napLabels = [
    'correct' => 'Correct',
    'nap_error' => 'NAP Error',
    'pending_update_edit_request' => 'Pending Update/Edit Request',
    'unable_to_claim' => 'Unable to Claim',
];

$statusDisplayOrder = [
    'not_started' => 'Not Started',
    'in_progress' => 'In Progress',
    'pending_submission' => 'Pending Submission',
    'unable_to_submit' => 'Unable to Submit',
    'live' => 'Live',
];

$writer = new XlsxWriter();

$fontDefault = 0;
$fontHeader = $writer->addFont(['bold' => true, 'color' => 'FFFFFFFF']);
$fontTitle = $writer->addFont(['bold' => true, 'color' => 'FFFFFFFF', 'size' => 14]);
$fontSectionTitle = $writer->addFont(['bold' => true, 'color' => 'FF1E293B', 'size' => 12]);
$fontGray800 = $writer->addFont(['bold' => true, 'color' => 'FF1F2937']);
$fontBlue800 = $writer->addFont(['bold' => true, 'color' => 'FF1E40AF']);
$fontOrange800 = $writer->addFont(['bold' => true, 'color' => 'FF9A3412']);
$fontRose800 = $writer->addFont(['bold' => true, 'color' => 'FF9F1239']);
$fontPurple800 = $writer->addFont(['bold' => true, 'color' => 'FF6B21A8']);
$fontGreen800 = $writer->addFont(['bold' => true, 'color' => 'FF166534']);
$fontEmerald800 = $writer->addFont(['bold' => true, 'color' => 'FF065F46']);
$fontAmber800 = $writer->addFont(['bold' => true, 'color' => 'FF92400E']);
$fontSlate700 = $writer->addFont(['bold' => true, 'color' => 'FF334155']);
$fontLabel = $writer->addFont(['bold' => true, 'color' => 'FF475569']);
$fontHyperlink = $writer->addFont(['color' => 'FF2563EB', 'underline' => true]);
$fontPlaceholder = $writer->addFont(['italic' => true, 'color' => 'FF94A3B8']);

$fillHeader = $writer->addFill('FF1E293B');
$fillZebra = $writer->addFill('FFF8FAFC');
$fillGray100 = $writer->addFill('FFF3F4F6');
$fillBlue100 = $writer->addFill('FFDBEAFE');
$fillOrange100 = $writer->addFill('FFFFEDD5');
$fillRose100 = $writer->addFill('FFFFE4E6');
$fillPurple100 = $writer->addFill('FFF3E8FF');
$fillGreen100 = $writer->addFill('FFDCFCE7');
$fillEmerald100 = $writer->addFill('FFD1FAE5');
$fillAmber100 = $writer->addFill('FFFEF3C7');
$fillSlate200 = $writer->addFill('FFE2E8F0');

$border = $writer->addBorder();

$xfHeader = $writer->addXf($fontHeader, $fillHeader, $border, 'center', true);
$xfBodyLeft = $writer->addXf($fontDefault, 0, $border, 'left');
$xfBodyLeftZebra = $writer->addXf($fontDefault, $fillZebra, $border, 'left');
$xfBodyCenter = $writer->addXf($fontDefault, 0, $border, 'center');
$xfBodyCenterZebra = $writer->addXf($fontDefault, $fillZebra, $border, 'center');
$xfHyperlink = $writer->addXf($fontHyperlink, 0, $border, 'left');
$xfHyperlinkZebra = $writer->addXf($fontHyperlink, $fillZebra, $border, 'left');
$xfPlaceholder = $writer->addXf($fontPlaceholder, 0, $border, 'center');
$xfPlaceholderZebra = $writer->addXf($fontPlaceholder, $fillZebra, $border, 'center');

$xfStatus = [
    'not_started' => $writer->addXf($fontGray800, $fillGray100, $border, 'center'),
    'in_progress' => $writer->addXf($fontBlue800, $fillBlue100, $border, 'center'),
    'pending_submission' => $writer->addXf($fontOrange800, $fillOrange100, $border, 'center'),
    'unable_to_submit' => $writer->addXf($fontRose800, $fillRose100, $border, 'center'),
    'submitted' => $writer->addXf($fontPurple800, $fillPurple100, $border, 'center'),
    'live' => $writer->addXf($fontGreen800, $fillGreen100, $border, 'center'),
];
$xfStatusDefault = $writer->addXf($fontSlate700, $fillSlate200, $border, 'center');

$xfNap = [
    'correct' => $writer->addXf($fontEmerald800, $fillEmerald100, $border, 'center'),
    'nap_error' => $writer->addXf($fontRose800, $fillRose100, $border, 'center'),
    'pending_update_edit_request' => $writer->addXf($fontAmber800, $fillAmber100, $border, 'center'),
    'unable_to_claim' => $writer->addXf($fontSlate700, $fillSlate200, $border, 'center'),
];

$xfMetricTitle = $writer->addXf($fontTitle, $fillHeader, $border, 'left');
$xfMetricSection = $writer->addXf($fontSectionTitle, $fillZebra, $border, 'left');
$xfMetricLabel = $xfBodyLeft;
$xfInfoLabel = $writer->addXf($fontLabel, 0, $border, 'left');
$xfSpacer = 0;

// ---------------------------------------------------------------------
// Sheet 1: Business Information
// ---------------------------------------------------------------------
if ($business !== null) {
    $fontBandSub = $writer->addFont(['color' => 'FFCBD5E1', 'size' => 10]);
    $xfBandSub = $writer->addXf($fontBandSub, $fillHeader, $border, 'left');
    $xfInfoLabelZebra = $writer->addXf($fontLabel, $fillZebra, $border, 'left');
    $xfBodyLeftWrap = $writer->addXf($fontDefault, 0, $border, 'left', true);
    $xfBodyLeftWrapZebra = $writer->addXf($fontDefault, $fillZebra, $border, 'left', true);
    $xfActive = $xfStatus['live'];
    $xfInactive = $xfStatusDefault;

    $infoSheet = $writer->newSheet('Business Info', [26, 60]);

    // Letterhead band: logo sits top-left (column A), name/subtitle/date stack top-right (column B).
    $businessName = trim((string)$business['name']) !== '' ? (string)$business['name'] : 'Business Information';
    $writer->addRow($infoSheet, [
        ['value' => '', 'style' => $xfMetricTitle],
        ['value' => $businessName, 'style' => $xfMetricTitle],
    ], 30.0);
    $writer->addRow($infoSheet, [
        ['value' => '', 'style' => $xfBandSub],
        ['value' => 'Business Information Report', 'style' => $xfBandSub],
    ], 18.0);
    $writer->addRow($infoSheet, [
        ['value' => '', 'style' => $xfBandSub],
        ['value' => 'Exported ' . date('M j, Y g:i A'), 'style' => $xfBandSub],
    ], 18.0);
    $writer->addRow($infoSheet, [
        ['value' => '', 'style' => $xfBandSub],
        ['value' => '', 'style' => $xfBandSub],
    ], 16.0);
    $writer->addRow($infoSheet, [['value' => '', 'style' => $xfSpacer], ['value' => '', 'style' => $xfSpacer]]);

    $addInfoSection = static function (string $title) use ($writer, $infoSheet, $xfMetricSection): void {
        $writer->addRow($infoSheet, [
            ['value' => $title, 'style' => $xfMetricSection],
            ['value' => '', 'style' => $xfMetricSection],
        ]);
    };

    $infoRowParity = 0;
    $addInfoRow = static function (string $label, string $value, ?string $hyperlink = null, ?int $styleOverride = null) use (
        $writer,
        $infoSheet,
        &$infoRowParity,
        $xfInfoLabel,
        $xfInfoLabelZebra,
        $xfBodyLeft,
        $xfBodyLeftZebra,
        $xfBodyLeftWrap,
        $xfBodyLeftWrapZebra,
        $xfHyperlink,
        $xfHyperlinkZebra,
        $xfPlaceholder,
        $xfPlaceholderZebra
    ): void {
        $value = trim($value);
        $zebra = ($infoRowParity % 2) === 1;
        $infoRowParity++;

        $labelStyle = $zebra ? $xfInfoLabelZebra : $xfInfoLabel;
        $height = null;

        if ($styleOverride !== null && $value !== '') {
            $valueCell = ['value' => $value, 'style' => $styleOverride];
        } elseif ($hyperlink !== null && $value !== '') {
            $valueCell = ['value' => $value, 'style' => $zebra ? $xfHyperlinkZebra : $xfHyperlink];
            $valueCell['hyperlink'] = $hyperlink;
        } elseif ($value !== '') {
            $lineCount = substr_count($value, "\n") + 1;
            $needsWrap = $lineCount > 1 || strlen($value) > 70;
            if ($needsWrap) {
                if ($lineCount === 1) {
                    $lineCount = (int)ceil(strlen($value) / 70);
                }
                $height = (float)min(150, max(20, $lineCount * 15));
                $valueCell = ['value' => $value, 'style' => $zebra ? $xfBodyLeftWrapZebra : $xfBodyLeftWrap];
            } else {
                $valueCell = ['value' => $value, 'style' => $zebra ? $xfBodyLeftZebra : $xfBodyLeft];
            }
        } else {
            $valueCell = ['value' => '-', 'style' => $zebra ? $xfPlaceholderZebra : $xfPlaceholder];
        }

        $writer->addRow($infoSheet, [
            ['value' => $label, 'style' => $labelStyle],
            $valueCell,
        ], $height);
    };

    $addInfoSection('Overview');
    $statusValue = strtolower(trim((string)($business['status'] ?? '')));
    $addInfoRow('Status', status_label($statusValue !== '' ? $statusValue : 'active'), null, $statusValue === 'inactive' ? $xfInactive : $xfActive);
    $addInfoRow('Client', (string)($business['client_name'] ?? ''));
    $categoriesValue = trim((string)($business['categories'] ?? '')) !== '' ? (string)$business['categories'] : (string)($business['category'] ?? '');
    $addInfoRow('Category / Categories', $categoriesValue);

    $addInfoSection('Contact Info');
    $addInfoRow('Phone', (string)($business['phone'] ?? ''));
    $website = trim((string)($business['website'] ?? ''));
    $addInfoRow('Website', $website, preg_match('/^https?:\/\//i', $website) === 1 ? $website : null);
    $gbpLink = trim((string)($business['gbp_link'] ?? ''));
    $addInfoRow('Google Business Profile', $gbpLink, preg_match('/^https?:\/\//i', $gbpLink) === 1 ? $gbpLink : null);
    $email = trim((string)($business['email'] ?? ''));
    $addInfoRow('Email', $email, $email !== '' ? ('mailto:' . $email) : null);
    $addInfoRow('Contact Name', (string)($business['contact_name'] ?? ''));
    $addInfoRow('In Business Since', (string)($business['in_business_since'] ?? ''));

    $addInfoSection('Address');
    $addInfoRow('Address Line 1', (string)($business['address_line1'] ?? ''));
    $addInfoRow('City', (string)($business['city'] ?? ''));
    $addInfoRow('State', (string)($business['state'] ?? ''));
    $addInfoRow('Postal Code', (string)($business['postal_code'] ?? ''));
    $addInfoRow('Country', (string)($business['country'] ?? ''));

    $addInfoSection('Profile Details');
    $addInfoRow('Business Hours', (string)($business['hours_json'] ?? ''));
    $addInfoRow('Services', (string)($business['services'] ?? ''));
    $addInfoRow('Payment Methods', (string)($business['payment_methods'] ?? ''));
    $addInfoRow('Social Media', (string)($business['social_media'] ?? ''));
    $addInfoRow('Description', (string)($business['description'] ?? ''));

    // Embed the business logo in the letterhead band, top-left corner.
    $logoPath = trim((string)($business['logo_path'] ?? ''));
    if ($logoPath !== '') {
        $candidate = realpath(__DIR__ . '/' . $logoPath);
        $uploadsRoot = realpath(__DIR__ . '/uploads');
        if ($candidate !== false && $uploadsRoot !== false && str_starts_with($candidate, $uploadsRoot) && is_file($candidate)) {
            $bytes = @file_get_contents($candidate);
            if ($bytes !== false) {
                $info = @getimagesizefromstring($bytes);
                if ($info !== false) {
                    $ext = null;
                    switch ($info[2]) {
                        case IMAGETYPE_PNG:
                            $ext = 'png';
                            break;
                        case IMAGETYPE_JPEG:
                            $ext = 'jpg';
                            break;
                        case IMAGETYPE_GIF:
                            $ext = 'gif';
                            break;
                    }

                    if ($ext !== null) {
                        $maxSide = 82;
                        $imgWidth = max(1, (int)$info[0]);
                        $imgHeight = max(1, (int)$info[1]);
                        $scale = min(1.0, $maxSide / max($imgWidth, $imgHeight));
                        $displayWidth = max(1, (int)round($imgWidth * $scale));
                        $displayHeight = max(1, (int)round($imgHeight * $scale));

                        $imageId = $writer->addImage($bytes, $ext);
                        $writer->setPicture($infoSheet, $imageId, 0, 0, $displayWidth, $displayHeight);
                    }
                }
            }
        }
    }
}

// ---------------------------------------------------------------------
// Sheet 2: Citations
// ---------------------------------------------------------------------
$citationsSheet = $writer->newSheet('Citations', [30, 20, 20, 26, 42, 36, 16, 20, 36]);
$writer->addRow($citationsSheet, [
    ['value' => 'Directory', 'style' => $xfHeader],
    ['value' => 'Citation Type', 'style' => $xfHeader],
    ['value' => 'Status', 'style' => $xfHeader],
    ['value' => 'NAP Status', 'style' => $xfHeader],
    ['value' => 'Citation URL', 'style' => $xfHeader],
    ['value' => 'Proof URL', 'style' => $xfHeader],
    ['value' => 'Assignee', 'style' => $xfHeader],
    ['value' => 'Updated', 'style' => $xfHeader],
    ['value' => 'Notes', 'style' => $xfHeader],
], 22.0);
$writer->freezeHeader($citationsSheet);
$writer->enableAutoFilter($citationsSheet);

$statusCounts = ['not_started' => 0, 'in_progress' => 0, 'pending_submission' => 0, 'unable_to_submit' => 0, 'live' => 0];
$napCounts = ['correct' => 0, 'nap_error' => 0, 'pending_update_edit_request' => 0, 'unable_to_claim' => 0];

$exportedCount = 0;
foreach ($rawRows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $directory = trim((string)($row['directory'] ?? ''));
    if ($directory === '') {
        continue;
    }

    $citationType = trim((string)($row['citation_type'] ?? ''));
    $status = trim((string)($row['status'] ?? ''));
    $napStatus = trim((string)($row['nap_status'] ?? ''));
    $url = trim((string)($row['url'] ?? ''));
    $proofUrl = trim((string)($row['proof_url'] ?? ''));
    $assignee = trim((string)($row['assignee'] ?? ''));
    $updated = trim((string)($row['updated'] ?? ''));
    $notes = trim((string)($row['notes'] ?? ''));

    $zebra = ($exportedCount % 2) === 1;
    $leftStyle = $zebra ? $xfBodyLeftZebra : $xfBodyLeft;
    $centerStyle = $zebra ? $xfBodyCenterZebra : $xfBodyCenter;
    $placeholderStyle = $zebra ? $xfPlaceholderZebra : $xfPlaceholder;
    $hyperlinkStyle = $zebra ? $xfHyperlinkZebra : $xfHyperlink;

    $statusStyle = $xfStatus[$status] ?? $xfStatusDefault;
    $napStyle = $napStatus !== '' ? ($xfNap[$napStatus] ?? $xfStatusDefault) : $placeholderStyle;

    $urlIsLinkable = $url !== '' && preg_match('/^https?:\/\//i', $url) === 1;
    $proofUrlIsLinkable = $proofUrl !== '' && preg_match('/^https?:\/\//i', $proofUrl) === 1;

    $cells = [
        ['value' => $directory, 'style' => $leftStyle],
        ['value' => $citationType !== '' ? $citationType : '-', 'style' => $centerStyle],
        ['value' => status_label($status), 'style' => $statusStyle],
        ['value' => $napStatus !== '' ? ($napLabels[$napStatus] ?? status_label($napStatus)) : '-', 'style' => $napStyle],
        $urlIsLinkable
            ? ['value' => $url, 'style' => $hyperlinkStyle, 'hyperlink' => $url]
            : ['value' => $url !== '' ? $url : '-', 'style' => $url !== '' ? $leftStyle : $placeholderStyle],
        $proofUrlIsLinkable
            ? ['value' => $proofUrl, 'style' => $hyperlinkStyle, 'hyperlink' => $proofUrl]
            : ['value' => $proofUrl !== '' ? $proofUrl : '-', 'style' => $proofUrl !== '' ? $leftStyle : $placeholderStyle],
        ['value' => $assignee !== '' ? $assignee : 'Unassigned', 'style' => $leftStyle],
        ['value' => $updated !== '' ? $updated : '-', 'style' => $centerStyle],
        ['value' => $notes, 'style' => $leftStyle],
    ];
    $writer->addRow($citationsSheet, $cells);
    $exportedCount++;

    if (array_key_exists($status, $statusCounts)) {
        $statusCounts[$status]++;
    }
    if ($napStatus !== '' && array_key_exists($napStatus, $napCounts)) {
        $napCounts[$napStatus]++;
    }
}

if ($exportedCount === 0) {
    http_response_code(400);
    exit('No data to export');
}

// ---------------------------------------------------------------------
// Sheet 3: Metrics
// ---------------------------------------------------------------------
$metricsSheet = $writer->newSheet('Metrics', [36, 14]);
$writer->addRow($metricsSheet, [
    ['value' => 'Citation Metrics Summary', 'style' => $xfMetricTitle],
    ['value' => '', 'style' => $xfMetricTitle],
], 26.0);
$writer->addRow($metricsSheet, [
    ['value' => 'Generated ' . date('M j, Y g:i A') . ' - ' . $exportedCount . ' citation(s) exported', 'style' => $xfMetricLabel],
    ['value' => '', 'style' => $xfMetricLabel],
]);
$writer->addRow($metricsSheet, [['value' => '', 'style' => $xfSpacer], ['value' => '', 'style' => $xfSpacer]]);

$writer->addRow($metricsSheet, [
    ['value' => 'Citation Status', 'style' => $xfMetricSection],
    ['value' => '', 'style' => $xfMetricSection],
]);
foreach ($statusDisplayOrder as $key => $label) {
    $writer->addRow($metricsSheet, [
        ['value' => $label, 'style' => $xfMetricLabel],
        ['value' => (string)$statusCounts[$key], 'style' => $xfStatus[$key], 'type' => 'number'],
    ]);
}

$writer->addRow($metricsSheet, [['value' => '', 'style' => $xfSpacer], ['value' => '', 'style' => $xfSpacer]]);

$writer->addRow($metricsSheet, [
    ['value' => 'Live URL Status (live citations only)', 'style' => $xfMetricSection],
    ['value' => '', 'style' => $xfMetricSection],
]);
foreach ($napLabels as $key => $label) {
    $writer->addRow($metricsSheet, [
        ['value' => $label, 'style' => $xfMetricLabel],
        ['value' => (string)$napCounts[$key], 'style' => $xfNap[$key], 'type' => 'number'],
    ]);
}

$writer->addRow($metricsSheet, [['value' => '', 'style' => $xfSpacer], ['value' => '', 'style' => $xfSpacer]]);
$writer->addRow($metricsSheet, [
    ['value' => 'Total Citations Exported', 'style' => $xfMetricTitle],
    ['value' => (string)$exportedCount, 'style' => $xfMetricTitle, 'type' => 'number'],
]);

$binary = $writer->output();
$filename = 'citations-export-' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($binary));
header('Cache-Control: no-store');
echo $binary;
exit;
