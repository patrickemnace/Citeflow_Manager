<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_login();
require_permission('location_manager');
$user = current_user();
$businessId = (int)($_GET['business_id'] ?? 0);

if ($businessId <= 0) {
    set_flash('err', 'Invalid business id.');
    redirect('/businesses.php');
}

$businessStmt = db()->prepare('SELECT * FROM businesses WHERE id = ?');
$businessStmt->execute([$businessId]);
$business = $businessStmt->fetch();

if (!$business) {
    set_flash('err', 'Business not found.');
    redirect('/businesses.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_business_info') {
    $name = trim((string)post('name'));
    $phone = trim((string)post('phone'));
    $website = trim((string)post('website'));
    $gbpLink = trim((string)post('gbp_link'));
    $email = trim((string)post('email'));
    $contactName = trim((string)post('contact_name'));
    $inBusinessSince = trim((string)post('in_business_since'));
    $categories = trim((string)post('categories'));
    $addressLine1 = trim((string)post('address_line1'));
    $city = trim((string)post('city'));
    $state = trim((string)post('state'));
    $postalCode = trim((string)post('postal_code'));
    $country = trim((string)post('country'));
    $hoursJson = trim((string)post('hours_json'));
    $services = trim((string)post('services'));
    $paymentMethods = trim((string)post('payment_methods'));
    $socialMedia = trim((string)post('social_media'));
    $loginCredentials = trim((string)post('login_credentials'));
    $description = trim((string)post('description'));

    if ($name === '') {
        set_flash('err', 'Business name is required.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if ($phone === '') {
        set_flash('err', 'Business phone is required.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    $primaryCategory = '';
    if ($categories !== '') {
        $categoryLines = preg_split('/\r\n|\r|\n/', $categories);
        if (is_array($categoryLines)) {
            foreach ($categoryLines as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    $primaryCategory = $line;
                    break;
                }
            }
        }
    }

    db()->prepare('UPDATE businesses
                   SET name = ?, phone = ?, website = ?, gbp_link = ?, email = ?, contact_name = ?, in_business_since = ?,
                       categories = ?, category = ?, address_line1 = ?, city = ?, state = ?, postal_code = ?, country = ?,
                       hours_json = ?, services = ?, payment_methods = ?, social_media = ?, login_credentials = ?, description = ?,
                       updated_at = NOW()
                   WHERE id = ?')
        ->execute([
            $name,
            $phone,
            $website,
            $gbpLink,
            $email,
            $contactName,
            $inBusinessSince,
            $categories,
            $primaryCategory,
            $addressLine1,
            $city,
            $state,
            $postalCode,
            $country,
            $hoursJson,
            $services,
            $paymentMethods,
            $socialMedia,
            $loginCredentials,
            $description,
            $businessId,
        ]);

    log_activity('business', $businessId, 'update', [
        'source' => 'location_manager_modal',
    ]);

    set_flash('ok', 'Business information updated.');
    redirect('/location_manager.php?business_id=' . $businessId);
}

$assignableUsers = db()->query("SELECT id, full_name, role, designation
                                                                FROM users
                                                                WHERE is_active = 1
                                                                ORDER BY full_name ASC")->fetchAll();
$assignableUserIds = array_values(array_map(static fn (array $row): int => (int)($row['id'] ?? 0), $assignableUsers));
$completeness = business_completeness($business);
$allowedCitationStatuses = ['not_started', 'in_progress', 'pending_submission', 'unable_to_submit', 'live'];
$allowedNapStatuses = ['correct', 'nap_error', 'pending_update_edit_request', 'unable_to_claim'];
$pendingLocationBulkDeleteRows = [];

function has_uploaded_submission_proof(string $fieldName): bool
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return false;
    }

    return (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function store_submission_screenshot(string $fieldName, ?string &$error = null): ?array
{
    $error = null;
    $path = save_uploaded_image_in_uploads($fieldName, 'submission_proof', $error, 'submission_screenshots');
    if ($path === null) {
        if ($error !== null) {
            if ($error === 'Only JPG, PNG, WEBP, and GIF images are allowed.') {
                $error = 'Only JPG, PNG, WEBP, and GIF proof screenshots are allowed.';
            } elseif ($error === 'Image size is too large.' || $error === 'Image is too large. It could not be optimized to 200KB.') {
                $error = 'Proof image size is too large.';
            } elseif ($error === 'Image is too large and cannot be optimized on this server.') {
                $error = 'Proof image optimization is unavailable on this server.';
            } elseif ($error === 'Upload failed. Please try again.') {
                $error = 'Proof upload failed. Please try again.';
            } elseif ($error === 'Invalid uploaded file.') {
                $error = 'Invalid proof file.';
            } elseif ($error === 'Unable to save uploaded image.') {
                $error = 'Unable to save proof screenshot.';
            }
        }
        return null;
    }

    $file = $_FILES[$fieldName] ?? null;
    $size = is_array($file) ? (int)($file['size'] ?? 0) : 0;
    $originalName = is_array($file) ? trim((string)($file['name'] ?? '')) : '';
    $filename = basename($path);

    return [
        'path' => $path,
        'original_name' => $originalName !== '' ? $originalName : $filename,
        'size' => $size,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'add_citation') {
    $directoryId = (int)post('directory_id');
    $status = post('status', 'not_started');
    $assignedTo = (int)post('assigned_to');
    $priorityLevel = post('priority_level', 'medium');
    $submittedUrl = trim((string)post('submitted_url'));
    $notes = post('notes');
    $citationType = post('citation_type', 'Manual Submission');
    $napStatus = post('nap_status', 'correct');

    if ($directoryId <= 0) {
        set_flash('err', 'Please select a directory.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if (!in_array($status, $allowedCitationStatuses, true)) {
        set_flash('err', 'Invalid citation status selected.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if (!in_array($napStatus, $allowedNapStatuses, true)) {
        set_flash('err', 'Invalid NAP status selected.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if ($assignedTo > 0 && !in_array($assignedTo, $assignableUserIds, true)) {
        set_flash('err', 'Selected assignee is invalid or inactive.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if ($status === 'live' && $submittedUrl === '') {
        set_flash('err', 'Citation URL is required when status is Live.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if ($status === 'unable_to_submit' && trim((string)$notes) === '') {
        set_flash('err', 'Comment is required when status is Unable to Submit.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if ($status === 'pending_submission' && !has_uploaded_submission_proof('submission_proof')) {
        set_flash('err', 'Please upload a proof screenshot when status is Pending Submission.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    $availableCheck = db()->prepare('SELECT id, name, website FROM directories WHERE id = ? AND is_active = 1 AND id NOT IN (SELECT directory_id FROM listing_tasks WHERE business_id = ?)');
    $availableCheck->execute([$directoryId, $businessId]);
    $availableRow = $availableCheck->fetch();

    if (!$availableRow) {
        set_flash('err', 'That directory is already linked to this business or is no longer available.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    try {
        $proofError = null;
        $uploadedProof = store_submission_screenshot('submission_proof', $proofError);
        if ($proofError !== null) {
            set_flash('err', $proofError);
            redirect('/location_manager.php?business_id=' . $businessId);
        }

        $sql = 'INSERT INTO listing_tasks (business_id, directory_id, assigned_to, status, nap_status, priority_level, submitted_url, notes, citation_type, created_by, last_action_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        db()->prepare($sql)->execute([$businessId, $directoryId, $assignedTo > 0 ? $assignedTo : null, $status, $napStatus, $priorityLevel, $submittedUrl, $notes, $citationType, $user['id']]);
        $newId = (int)db()->lastInsertId();

        if (is_array($uploadedProof)) {
            db()->prepare('INSERT INTO submission_proofs (listing_task_id, file_name, original_name, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)')
                ->execute([$newId, $uploadedProof['path'], $uploadedProof['original_name'], (int)$uploadedProof['size'], (int)$user['id']]);
        }

        log_activity('listing_task', $newId, 'create', [
            'business_id' => $businessId,
            'directory_id' => $directoryId,
            'status' => $status,
            'nap_status' => $napStatus,
            'priority_level' => $priorityLevel,
            'citation_type' => $citationType,
        ]);
        $directoryName = (string)($availableRow['name'] ?? 'Directory');
        $actorName = (string)($user['full_name'] ?? 'A user');
        $statusLabel = ucwords(str_replace('_', ' ', (string)$status));
        $actionUrl = app_config()['base_url'] . '/location_manager.php?business_id=' . $businessId . '&open_citations=1';

        create_notification(
            null,
            'Citation added: ' . $directoryName,
            $actorName . ' added a citation for ' . (string)$business['name'] . ' on ' . $directoryName . ' (' . $statusLabel . ').',
            $actionUrl,
            'info'
        );

        if ($assignedTo > 0 && $assignedTo !== (int)$user['id']) {
            create_notification(
                $assignedTo,
                'New citation assigned to you',
                $actorName . ' assigned ' . (string)$business['name'] . ' / ' . $directoryName . ' to you.',
                $actionUrl,
                'info'
            );
        }

        $citationsNotice = 'Citation added for this location.';
        if (isset($_SESSION['flash'])) {
            unset($_SESSION['flash']);
        }
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode($citationsNotice));
    } catch (Throwable $e) {
        set_flash('err', 'Citation already exists for this business and directory.');
    }

    redirect('/location_manager.php?business_id=' . $businessId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'add_citations_bulk') {
    $directoryIds = $_POST['directory_ids'] ?? [];
    if (!is_array($directoryIds)) {
        $directoryIds = [];
    }

    $directoryIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $directoryIds), static fn (int $value): bool => $value > 0)));
    $status = 'not_started';
    $assignedTo = (int)post('assigned_to');
    $priorityLevel = post('priority_level', 'medium');
    $notes = post('notes');
    $citationType = post('citation_type', 'Manual Submission');

    if (!$directoryIds) {
        set_flash('err', 'Select at least one directory to add citations.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    if ($assignedTo > 0 && !in_array($assignedTo, $assignableUserIds, true)) {
        set_flash('err', 'Selected assignee is invalid or inactive.');
        redirect('/location_manager.php?business_id=' . $businessId);
    }

    $placeholders = implode(',', array_fill(0, count($directoryIds), '?'));
    $lookupSql = 'SELECT d.id, d.name
                  FROM directories d
                  WHERE d.is_active = 1
                    AND d.id IN (' . $placeholders . ')
                    AND d.id NOT IN (SELECT lt.directory_id FROM listing_tasks lt WHERE lt.business_id = ?)';
    $lookupStmt = db()->prepare($lookupSql);
    $lookupStmt->execute(array_merge($directoryIds, [$businessId]));
    $availableRows = $lookupStmt->fetchAll();

    $availableById = [];
    foreach ($availableRows as $row) {
        $availableById[(int)($row['id'] ?? 0)] = $row;
    }

    $insertSql = 'INSERT INTO listing_tasks (business_id, directory_id, assigned_to, status, nap_status, priority_level, submitted_url, notes, citation_type, created_by, last_action_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
    $insertStmt = db()->prepare($insertSql);

    $createdCount = 0;
    $skippedCount = 0;

    foreach ($directoryIds as $directoryId) {
        if (!isset($availableById[$directoryId])) {
            $skippedCount++;
            continue;
        }

        try {
            $insertStmt->execute([
                $businessId,
                $directoryId,
                $assignedTo > 0 ? $assignedTo : null,
                $status,
                'correct',
                $priorityLevel,
                '',
                $notes,
                $citationType,
                $user['id'],
            ]);
            $newId = (int)db()->lastInsertId();
            $createdCount++;

            log_activity('listing_task', $newId, 'create', [
                'business_id' => $businessId,
                'directory_id' => $directoryId,
                'status' => $status,
                'nap_status' => 'correct',
                'priority_level' => $priorityLevel,
                'citation_type' => $citationType,
                'initial_selection' => true,
            ]);
        } catch (Throwable $e) {
            $skippedCount++;
        }
    }

    if ($createdCount > 0) {
        $actorName = (string)($user['full_name'] ?? 'A user');
        $actionUrl = app_config()['base_url'] . '/location_manager.php?business_id=' . $businessId . '&open_citations=1';
        $statusLabel = ucwords(str_replace('_', ' ', $status));

        create_notification(
            null,
            'Initial citations added',
            $actorName . ' added ' . $createdCount . ' citation(s) for ' . (string)$business['name'] . ' with initial status ' . $statusLabel . '.',
            $actionUrl,
            'info'
        );

        if ($assignedTo > 0 && $assignedTo !== (int)$user['id']) {
            create_notification(
                $assignedTo,
                'New citations assigned to you',
                $actorName . ' assigned ' . $createdCount . ' citation(s) for ' . (string)$business['name'] . ' to you.',
                $actionUrl,
                'info'
            );
        }
    }

    if ($createdCount > 0) {
        $citationsNotice = $createdCount > 0 && $skippedCount > 0
            ? ($createdCount . ' citation(s) added. ' . $skippedCount . ' skipped (already linked or unavailable).')
            : ($createdCount . ' citation(s) added for initial submission. Finalize later in View Associated Citations.');

        if (isset($_SESSION['flash'])) {
            unset($_SESSION['flash']);
        }
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode($citationsNotice));
    }

    redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('No citations were added. Selected directories may already be linked or unavailable.') . '&citations_notice_type=err');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'mark_citation_in_progress') {
    header('Content-Type: application/json; charset=utf-8');

    $citationId = (int)post('citation_id');
    if ($citationId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid citation id.']);
        exit;
    }

    $citationStmt = db()->prepare('SELECT lt.id, lt.status, d.name AS directory_name
                                   FROM listing_tasks lt
                                   JOIN directories d ON d.id = lt.directory_id
                                   WHERE lt.id = ? AND lt.business_id = ?
                                   LIMIT 1');
    $citationStmt->execute([$citationId, $businessId]);
    $citation = $citationStmt->fetch();

    if (!$citation) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Citation not found.']);
        exit;
    }

    $currentStatus = (string)($citation['status'] ?? 'not_started');
    if (in_array($currentStatus, ['live', 'pending_submission'], true)) {
        echo json_encode(['ok' => true, 'status' => $currentStatus]);
        exit;
    }

    if ($currentStatus !== 'in_progress') {
        db()->prepare('UPDATE listing_tasks SET status = ?, last_action_at = NOW() WHERE id = ? AND business_id = ?')
            ->execute(['in_progress', $citationId, $businessId]);

        log_activity('listing_task', $citationId, 'update', [
            'business_id' => $businessId,
            'status' => 'in_progress',
            'trigger' => 'submit_listing_click',
        ]);

        $actorName = (string)($user['full_name'] ?? 'A user');
        $directoryName = (string)($citation['directory_name'] ?? 'Directory');
        create_notification(
            null,
            'Citation in progress: ' . $directoryName,
            $actorName . ' started submission for ' . (string)$business['name'] . ' / ' . $directoryName . '.',
            app_config()['base_url'] . '/location_manager.php?business_id=' . $businessId . '&open_citations=1',
            'info'
        );
    }

    echo json_encode(['ok' => true, 'status' => 'in_progress']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_citation') {
    $citationId = (int)post('citation_id');
    $status = post('status');
    $assignedTo = (int)post('assigned_to');
    $priorityLevel = post('priority_level', 'medium');
    $submittedUrl = trim((string)post('submitted_url'));
    $notes = post('notes');
    $citationType = post('citation_type', 'Manual Submission');
    $napStatus = post('nap_status', 'correct');

    if (!in_array($status, $allowedCitationStatuses, true)) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Invalid citation status selected.') . '&citations_notice_type=err');
    }

    if (!in_array($napStatus, $allowedNapStatuses, true)) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Invalid NAP status selected.') . '&citations_notice_type=err');
    }

    if ($assignedTo > 0 && !in_array($assignedTo, $assignableUserIds, true)) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Selected assignee is invalid or inactive.') . '&citations_notice_type=err');
    }

    if ($status === 'live' && $submittedUrl === '') {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Citation URL is required when status is Live.') . '&citations_notice_type=err');
    }

    if ($status === 'unable_to_submit' && trim((string)$notes) === '') {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Comment is required when status is Unable to Submit.') . '&citations_notice_type=err');
    }

    $existingStmt = db()->prepare('SELECT lt.assigned_to, lt.status, d.name AS directory_name, d.website AS directory_website
                                   FROM listing_tasks lt
                                   JOIN directories d ON d.id = lt.directory_id
                                   WHERE lt.id = ? AND lt.business_id = ?
                                   LIMIT 1');
    $existingStmt->execute([$citationId, $businessId]);
    $existingCitation = $existingStmt->fetch();
    if (!$existingCitation) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Citation not found.') . '&citations_notice_type=err');
    }

    if ($status === 'pending_submission' && !has_uploaded_submission_proof('edit_submission_proof')) {
        $proofCountStmt = db()->prepare('SELECT COUNT(*) AS c FROM submission_proofs WHERE listing_task_id = ?');
        $proofCountStmt->execute([$citationId]);
        $proofCount = (int)($proofCountStmt->fetch()['c'] ?? 0);
        if ($proofCount <= 0) {
            redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Please upload a proof screenshot when status is Pending Submission.') . '&citations_notice_type=err');
        }
    }

    $proofError = null;
    $uploadedProof = store_submission_screenshot('edit_submission_proof', $proofError);
    if ($proofError !== null) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode((string)$proofError) . '&citations_notice_type=err');
    }

    $sql = 'UPDATE listing_tasks SET assigned_to = ?, status = ?, nap_status = ?, priority_level = ?, submitted_url = ?, notes = ?, citation_type = ?, last_action_at = NOW() WHERE id = ? AND business_id = ?';
    db()->prepare($sql)->execute([$assignedTo > 0 ? $assignedTo : null, $status, $napStatus, $priorityLevel, $submittedUrl, $notes, $citationType, $citationId, $businessId]);

    if (is_array($uploadedProof)) {
        db()->prepare('INSERT INTO submission_proofs (listing_task_id, file_name, original_name, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$citationId, $uploadedProof['path'], $uploadedProof['original_name'], (int)$uploadedProof['size'], (int)$user['id']]);
    }

    log_activity('listing_task', $citationId, 'update', [
        'business_id' => $businessId,
        'status' => $status,
        'nap_status' => $napStatus,
        'priority_level' => $priorityLevel,
        'citation_type' => $citationType,
    ]);

    $previousAssignedTo = (int)($existingCitation['assigned_to'] ?? 0);
    $currentAssignedTo = $assignedTo > 0 ? $assignedTo : 0;
    $previousStatus = (string)($existingCitation['status'] ?? '');
    $directoryName = (string)($existingCitation['directory_name'] ?? 'Directory');
    $actorName = (string)($user['full_name'] ?? 'A user');
    $statusLabel = ucwords(str_replace('_', ' ', (string)$status));
    $actionUrl = app_config()['base_url'] . '/location_manager.php?business_id=' . $businessId . '&open_citations=1';

    if ($currentAssignedTo > 0 && $currentAssignedTo !== $previousAssignedTo && $currentAssignedTo !== (int)$user['id']) {
        create_notification(
            $currentAssignedTo,
            'Citation assigned to you',
            $actorName . ' assigned ' . (string)$business['name'] . ' / ' . $directoryName . ' to you.',
            $actionUrl,
            'info'
        );
    }

    if ($previousStatus !== $status && $status === 'live') {
        create_notification(
            null,
            'Citation is now live: ' . $directoryName,
            (string)$business['name'] . ' / ' . $directoryName . ' is now live.',
            $actionUrl,
            'success'
        );
    }

    redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Citation updated successfully.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'bulk_assign_citations') {
    $assignedTo = (int)post('bulk_assigned_to');
    $citationIds = $_POST['citation_ids'] ?? [];

    if (!is_array($citationIds)) {
        $citationIds = [];
    }

    $citationIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $citationIds), static fn (int $value): bool => $value > 0)));

    if (!$citationIds) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Select at least one citation to apply bulk assignment.') . '&citations_notice_type=err');
    }

    if ($assignedTo > 0 && !in_array($assignedTo, $assignableUserIds, true)) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Selected assignee is invalid or inactive.') . '&citations_notice_type=err');
    }

    $placeholders = implode(',', array_fill(0, count($citationIds), '?'));
    $lookupSql = 'SELECT lt.id, lt.assigned_to, d.name AS directory_name
                  FROM listing_tasks lt
                  JOIN directories d ON d.id = lt.directory_id
                  WHERE lt.business_id = ? AND lt.id IN (' . $placeholders . ')';
    $lookupStmt = db()->prepare($lookupSql);
    $lookupStmt->execute(array_merge([$businessId], $citationIds));
    $selectedRows = $lookupStmt->fetchAll();

    if (count($selectedRows) !== count($citationIds)) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Some selected citations are invalid or no longer available.') . '&citations_notice_type=err');
    }

    $updateSql = 'UPDATE listing_tasks
                  SET assigned_to = ?, last_action_at = NOW()
                  WHERE business_id = ? AND id IN (' . $placeholders . ')';
    $updateParams = array_merge([$assignedTo > 0 ? $assignedTo : null, $businessId], $citationIds);
    db()->prepare($updateSql)->execute($updateParams);

    $actorName = (string)($user['full_name'] ?? 'A user');
    $actionUrl = app_config()['base_url'] . '/location_manager.php?business_id=' . $businessId . '&open_citations=1';

    foreach ($selectedRows as $row) {
        log_activity('listing_task', (int)$row['id'], 'bulk_assign', [
            'business_id' => $businessId,
            'directory_name' => (string)($row['directory_name'] ?? ''),
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
        ]);
    }

    if ($assignedTo > 0 && $assignedTo !== (int)$user['id']) {
        create_notification(
            $assignedTo,
            'Bulk citation assignment',
            $actorName . ' assigned ' . count($citationIds) . ' citations for ' . (string)$business['name'] . ' to you.',
            $actionUrl,
            'info'
        );
    }

    redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Bulk assignment applied to ' . count($citationIds) . ' citation(s).'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (post('action') === 'request_bulk_delete_citations' || isset($_POST['request_bulk_delete_citations']))) {
    $citationIds = $_POST['citation_ids'] ?? [];

    if (!is_array($citationIds)) {
        $citationIds = [];
    }

    $citationIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $citationIds), static fn (int $value): bool => $value > 0)));

    if (!$citationIds) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Select at least one citation to delete.') . '&citations_notice_type=warning');
    }

    $placeholders = implode(',', array_fill(0, count($citationIds), '?'));
    $lookupSql = 'SELECT lt.id, lt.status, d.name AS directory_name
                  FROM listing_tasks lt
                  JOIN directories d ON d.id = lt.directory_id
                  WHERE lt.business_id = ? AND lt.id IN (' . $placeholders . ')';
    $lookupStmt = db()->prepare($lookupSql);
    $lookupStmt->execute(array_merge([$businessId], $citationIds));
    $selectedRows = $lookupStmt->fetchAll();

    if (count($selectedRows) !== count($citationIds)) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Some selected citations are invalid or no longer available.') . '&citations_notice_type=warning');
    }

    $pendingLocationBulkDeleteRows = $selectedRows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'bulk_delete_citations') {
    $citationIds = $_POST['citation_ids'] ?? [];

    if (!is_array($citationIds)) {
        $citationIds = [];
    }

    $citationIds = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $citationIds), static fn (int $value): bool => $value > 0)));

    if (!$citationIds) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Select at least one citation to delete.') . '&citations_notice_type=warning');
    }

    $placeholders = implode(',', array_fill(0, count($citationIds), '?'));
    $lookupSql = 'SELECT lt.id, lt.status, d.name AS directory_name
                  FROM listing_tasks lt
                  JOIN directories d ON d.id = lt.directory_id
                  WHERE lt.business_id = ? AND lt.id IN (' . $placeholders . ')';
    $lookupStmt = db()->prepare($lookupSql);
    $lookupStmt->execute(array_merge([$businessId], $citationIds));
    $selectedRows = $lookupStmt->fetchAll();

    if (count($selectedRows) !== count($citationIds)) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Some selected citations are invalid or no longer available.') . '&citations_notice_type=err');
    }

    $deleteSql = 'DELETE FROM listing_tasks WHERE business_id = ? AND id IN (' . $placeholders . ')';
    db()->prepare($deleteSql)->execute(array_merge([$businessId], $citationIds));

    $actorName = (string)($user['full_name'] ?? 'A user');
    $actionUrl = app_config()['base_url'] . '/location_manager.php?business_id=' . $businessId . '&open_citations=1';

    foreach ($selectedRows as $row) {
        log_activity('listing_task', (int)$row['id'], 'delete', [
            'business_id' => $businessId,
            'directory_name' => (string)($row['directory_name'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'bulk' => true,
        ]);
    }

    create_notification(
        null,
        'Bulk citations deleted',
        $actorName . ' deleted ' . count($citationIds) . ' citation(s) from ' . (string)$business['name'] . '.',
        $actionUrl,
        'warning'
    );

    $citationsNotice = 'Deleted ' . count($citationIds) . ' selected citation(s).';
    if (isset($_SESSION['flash'])) {
        unset($_SESSION['flash']);
    }
    redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode($citationsNotice));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_citation') {
    $citationId = (int)post('citation_id');
    if ($citationId <= 0) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Invalid citation.') . '&citations_notice_type=err');
    }

    $citationStmt = db()->prepare('SELECT lt.id, lt.status, d.name AS directory_name
                                   FROM listing_tasks lt
                                   JOIN directories d ON d.id = lt.directory_id
                                   WHERE lt.id = ? AND lt.business_id = ?
                                   LIMIT 1');
    $citationStmt->execute([$citationId, $businessId]);
    $citation = $citationStmt->fetch();

    if (!$citation) {
        redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode('Citation not found.') . '&citations_notice_type=err');
    }

    db()->prepare('DELETE FROM listing_tasks WHERE id = ? AND business_id = ?')->execute([$citationId, $businessId]);

    $directoryName = (string)($citation['directory_name'] ?? 'Directory');
    $actorName = (string)($user['full_name'] ?? 'A user');
    $actionUrl = app_config()['base_url'] . '/location_manager.php?business_id=' . $businessId . '&open_citations=1';

    log_activity('listing_task', $citationId, 'delete', [
        'business_id' => $businessId,
        'directory_name' => $directoryName,
        'status' => (string)($citation['status'] ?? ''),
    ]);

    create_notification(
        null,
        'Citation deleted: ' . $directoryName,
        $actorName . ' removed citation ' . $directoryName . ' from ' . (string)$business['name'] . '.',
        $actionUrl,
        'warning'
    );

    $citationsNotice = 'Citation deleted.';
    if (isset($_SESSION['flash'])) {
        unset($_SESSION['flash']);
    }
    redirect('/location_manager.php?business_id=' . $businessId . '&open_citations=1&citations_notice=' . rawurlencode($citationsNotice));
}

$directoriesStmt = db()->prepare('SELECT id, name, submission_url, website, directory_type, country, priority_level, requires_login
                                                                    FROM directories
                                                                    WHERE is_active = 1
                                                                        AND id NOT IN (
                                                                                SELECT directory_id
                                                                                FROM listing_tasks
                                                                                WHERE business_id = ?
                                                                        )
                                                                    ORDER BY name ASC');
$directoriesStmt->execute([$businessId]);
$directories = $directoriesStmt->fetchAll();

$listSql = 'SELECT lt.id, lt.assigned_to, lt.status, lt.nap_status, lt.priority_level, lt.submitted_url, lt.notes, lt.citation_type, lt.updated_at, d.id AS directory_id, d.name AS directory_name, d.logo_path AS directory_logo_path, d.submission_url, d.website AS directory_website, u.full_name AS assignee_name, u.image_path AS assignee_image_path,
                   (
                       SELECT sp.file_name
                       FROM submission_proofs sp
                       WHERE sp.listing_task_id = lt.id
                       ORDER BY sp.created_at DESC, sp.id DESC
                       LIMIT 1
                   ) AS latest_proof_file,
                   (
                       SELECT sp.original_name
                       FROM submission_proofs sp
                       WHERE sp.listing_task_id = lt.id
                       ORDER BY sp.created_at DESC, sp.id DESC
                       LIMIT 1
                   ) AS latest_proof_name
            FROM listing_tasks lt
            JOIN directories d ON d.id = lt.directory_id
            LEFT JOIN users u ON u.id = lt.assigned_to
            WHERE lt.business_id = ?
            ORDER BY lt.updated_at DESC';
$listStmt = db()->prepare($listSql);
$listStmt->execute([$businessId]);
$citations = $listStmt->fetchAll();
$citationsModalNotice = trim((string)($_GET['citations_notice'] ?? ''));
if (strlen($citationsModalNotice) > 300) {
    $citationsModalNotice = substr($citationsModalNotice, 0, 300);
}
$citationsModalNoticeType = in_array($_GET['citations_notice_type'] ?? '', ['err', 'warning', 'ok'], true)
    ? (string)$_GET['citations_notice_type']
    : 'ok';
if ($citationsModalNotice !== '' && isset($_SESSION['flash'])) {
    unset($_SESSION['flash']);
}
$openCitationsOnLoad = (string)($_GET['open_citations'] ?? '') === '1' || $citationsModalNotice !== '';

function render_clickable_text(string $text): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = array_map('trim', explode("\n", $normalized));
    $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    $value = trim(implode("\n", $lines));
    if ($value === '') {
        return '—';
    }

    $pattern = '/(https?:\/\/[^\s<>"]+|www\.[^\s<>"]+|[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i';
    $parts = preg_split($pattern, $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return nl2br(e($value));
    }

    $html = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (preg_match('/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $part)) {
            $html .= '<a class="text-sky-600 hover:underline break-all" href="mailto:' . e($part) . '">' . e($part) . '</a>';
            continue;
        }

        if (preg_match('/^(https?:\/\/|www\.)/i', $part)) {
            $href = preg_match('/^www\./i', $part) ? 'https://' . $part : $part;
            $html .= '<a class="text-sky-600 hover:underline break-all" href="' . e($href) . '" target="_blank" rel="noopener noreferrer">' . e($part) . '</a>';
            continue;
        }

        $html .= nl2br(e($part));
    }

    return $html;
}


render_header('Location Manager');
?>
<?php if ($pendingLocationBulkDeleteRows): ?>
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/55 dark:bg-slate-950/75"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-900 shadow-2xl dark:border-amber-700 dark:bg-slate-900 dark:text-amber-200">
            <p class="text-sm font-bold">Bulk delete confirmation required</p>
            <p class="mt-1 text-sm">You are about to permanently delete <?php echo e((string)count($pendingLocationBulkDeleteRows)); ?> citation(s) for this business.</p>
            <p class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">Warning: This action cannot be undone.</p>
            <div class="mt-2 max-h-28 overflow-y-auto rounded-lg border border-amber-200 bg-white/70 px-3 py-2 text-xs dark:border-amber-800 dark:bg-slate-900/40">
                <?php foreach ($pendingLocationBulkDeleteRows as $pendingCitationRow): ?>
                    <div><?php echo e((string)($pendingCitationRow['directory_name'] ?? 'Directory')); ?></div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <form method="post" class="inline-flex gap-2">
                    <input type="hidden" name="action" value="bulk_delete_citations">
                    <?php foreach ($pendingLocationBulkDeleteRows as $pendingCitationRow): ?>
                        <input type="hidden" name="citation_ids[]" value="<?php echo e((string)($pendingCitationRow['id'] ?? 0)); ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yes, delete selected</button>
                </form>
                <a href="<?php echo e(app_config()['base_url']); ?>/location_manager.php?business_id=<?php echo e((string)$businessId); ?>&open_citations=1" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<section class="mb-6 grid gap-6 lg:grid-cols-3">
    <article class="rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 p-5 shadow-sm lg:col-span-2">
        <div class="flex items-start gap-4">
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Location Manager: <?php echo e($business['name']); ?></h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Detailed profile and citation workflow for this business location.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button id="open_sticky_notes" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 px-3 py-2 text-xs font-semibold text-amber-800 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/50">Open Sticky Notes</button>
                    <button id="open_business_edit_modal" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-300 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/30 px-3 py-2 text-xs font-semibold text-sky-800 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900/50">Edit Business Info</button>
                </div>
            </div>
            <?php if (trim((string)($business['logo_path'] ?? '')) !== ''): ?>
                <div class="flex-shrink-0">
                    <img class="h-20 w-40 rounded-xl border border-slate-200 dark:border-slate-700 object-contain bg-white dark:bg-slate-800 p-2" src="<?php echo e(public_asset_url((string)$business['logo_path'])); ?>" alt="Business logo">
                </div>
            <?php endif; ?>
        </div>

        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Phone</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo $business['phone'] ? '<a href="tel:' . e($business['phone']) . '" class="text-sky-600 dark:text-sky-400 hover:underline">' . e($business['phone']) . '</a>' : '—'; ?></dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Website</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo $business['website'] ? '<a href="' . e($business['website']) . '" target="_blank" rel="noopener noreferrer" class="text-sky-600 dark:text-sky-400 hover:underline break-all">' . e($business['website']) . '</a>' : '—'; ?></dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">GBP Link</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo ($business['gbp_link'] ?? '') ? '<a href="' . e((string)$business['gbp_link']) . '" target="_blank" rel="noopener noreferrer" class="text-sky-600 dark:text-sky-400 hover:underline break-all">' . e((string)$business['gbp_link']) . '</a>' : '—'; ?></dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Email</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo $business['email'] ? '<a href="mailto:' . e($business['email']) . '" class="text-sky-600 dark:text-sky-400 hover:underline">' . e($business['email']) . '</a>' : '—'; ?></dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Contact Name</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo e((string)($business['contact_name'] ?? '')); ?></dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">In Business Since</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo e((string)($business['in_business_since'] ?? '')); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Categories</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo render_clickable_text((string)(($business['categories'] ?? '') !== '' ? $business['categories'] : $business['category'])); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Address</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo render_clickable_text($business['address_line1'] . ', ' . $business['city'] . ', ' . $business['state'] . ' ' . $business['postal_code'] . ', ' . $business['country']); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Business Hours</dt><dd class="text-sm font-medium whitespace-pre-wrap text-slate-800 dark:text-slate-200"><?php echo e((string)$business['hours_json']); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Services</dt><dd class="text-sm font-medium whitespace-pre-wrap text-slate-800 dark:text-slate-200"><?php echo e((string)($business['services'] ?? '')); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Payment Methods</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo render_clickable_text((string)($business['payment_methods'] ?? '')); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Social Media</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo render_clickable_text((string)($business['social_media'] ?? '')); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Login Credentials</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo render_clickable_text((string)($business['login_credentials'] ?? '')); ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Description</dt><dd class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo render_clickable_text((string)$business['description']); ?></dd></div>
        </dl>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 p-5 shadow-sm lg:col-span-1">
        <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-slate-900 dark:text-white">Business Completeness</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Required listing data coverage before submission.</p>
                </div>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e(completeness_badge_class((int)$completeness['score'])); ?>"><?php echo e((string)$completeness['score']); ?>%</span>
            </div>
            <?php if ($completeness['missing']): ?>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Missing: <?php echo e(implode(', ', array_slice($completeness['missing'], 0, 5))); ?><?php echo count($completeness['missing']) > 5 ? '...' : ''; ?></p>
            <?php else: ?>
                <p class="mt-3 text-xs text-emerald-600">All critical business fields are filled.</p>
            <?php endif; ?>
        </div>

        <button id="open_citations_modal" type="button" class="mb-3 inline-flex w-full items-center justify-center rounded-lg border border-brand-600 bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">View Associated Citations</button>
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Add Citation (Initial Selection)</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Search and select one or multiple directories to create initial citations. Final completion is done later in View Associated Citations.</p>

        <form method="post" enctype="multipart/form-data" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="add_citations_bulk">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">Directories</label>
                <input id="directory_picker_search" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2.5 dark:placeholder-slate-400" type="search" placeholder="Search directories by name, type, region, website...">
                <?php if (!$directories): ?>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">All active directories already have citations for this business.</p>
                <?php else: ?>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button id="select_visible_directories" type="button" class="rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Select Visible</button>
                        <button id="clear_directory_selection" type="button" class="rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Clear Selection</button>
                        <span id="selected_directories_count" class="text-xs font-semibold text-slate-500 dark:text-slate-400">0 selected</span>
                    </div>
                    <div id="directory_picker_list" class="mt-2 max-h-56 space-y-1 overflow-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2">
                        <?php foreach ($directories as $d): ?>
                            <?php
                                $directoryType = trim((string)($d['directory_type'] ?? 'General'));
                                $directoryRegion = trim((string)($d['country'] ?? 'Global'));
                                $directoryWebsite = trim((string)($d['website'] ?? ''));
                                $searchText = strtolower(trim($d['name'] . ' ' . $directoryType . ' ' . $directoryRegion . ' ' . $directoryWebsite));
                            ?>
                            <label class="directory-picker-item flex cursor-pointer items-start gap-2 rounded-lg border border-transparent bg-white dark:bg-slate-700 px-2.5 py-2 hover:border-slate-300 dark:hover:border-slate-600" data-search="<?php echo e($searchText); ?>">
                                <input class="directory-picker-checkbox mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-600 text-brand-600" type="checkbox" name="directory_ids[]" value="<?php echo e((string)$d['id']); ?>">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-800 dark:text-white"><?php echo e((string)$d['name']); ?></span>
                                    <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400"><?php echo e($directoryType); ?> · <?php echo e($directoryRegion); ?></span>
                                    <?php if ($directoryWebsite !== ''): ?>
                                        <span class="block truncate text-[11px] text-slate-400 dark:text-slate-500"><?php echo e($directoryWebsite); ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <p id="directory_picker_no_results" class="hidden px-2 py-2 text-xs text-slate-500">No directories match your search.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">Citation Type</label>
                <select class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2.5" name="citation_type">
                    <option value="Manual Submission">Manual Submission</option>
                    <option value="Citation Builder">Citation Builder</option>
                    <option value="Key Citation">Key Citation</option>
                    <option value="Niche Citation">Niche Citation</option>
                    <option value="Competitor Citation">Competitor Citation</option>
                    <option value="Lead Aggregators">Lead Aggregators</option>
                </select>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-3">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Initial Status</p>
                <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-white">Not Started (Automatic)</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Status changes to In Progress automatically when Submit Listing is clicked.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">Assigned To</label>
                    <select class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2.5" name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php foreach ($assignableUsers as $assignableUser): ?>
                            <option value="<?php echo e((string)$assignableUser['id']); ?>" <?php echo (int)$assignableUser['id'] === (int)$user['id'] ? 'selected' : ''; ?>><?php echo e((string)$assignableUser['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">Priority</label>
                    <select class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2.5" name="priority_level">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">Notes</label>
                <textarea class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2.5" name="notes" rows="3"></textarea>
            </div>
            <button class="w-full rounded-lg bg-brand-600 dark:bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 dark:hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-slate-300 dark:disabled:bg-slate-700" type="submit" <?php echo !$directories ? 'disabled' : ''; ?>>Add Selected Citations</button>
        </form>
    </article>
</section>

<div id="citations_modal" class="fixed inset-0 z-40<?php echo $openCitationsOnLoad ? '' : ' hidden'; ?>">
    <div id="citations_modal_backdrop" class="absolute inset-0 bg-slate-900/55 dark:bg-slate-950/75"></div>
    <div class="relative mx-auto mt-4 w-[96vw] max-w-7xl px-2 pb-4 sm:px-4">
        <section class="flex max-h-[calc(100vh-2rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-5 py-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Associated Citations</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Review initial citations here, then complete and finalize submissions by updating statuses.</p>
                </div>
                <button id="close_citations_modal" type="button" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Close</button>
            </div>
            <?php if ($citationsModalNotice !== ''): ?>
                <?php
                    $noticeClasses = 'rounded-lg border px-3 py-2 text-sm font-semibold ';
                    if ($citationsModalNoticeType === 'err') {
                        $noticeClasses .= 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200';
                    } elseif ($citationsModalNoticeType === 'warning') {
                        $noticeClasses .= 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200';
                    } else {
                        $noticeClasses .= 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200';
                    }
                ?>
                <div class="border-b border-slate-200 dark:border-slate-700 px-5 py-3">
                    <div class="<?php echo e($noticeClasses); ?>">
                        <?php echo e($citationsModalNotice); ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="border-b border-slate-200 dark:border-slate-700 px-5 py-3">
                <div class="flex w-full flex-col gap-2">
                    <div class="flex w-full flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                        <div class="flex w-full sm:flex-1 flex-wrap items-center gap-2">
                        <input id="citations_search" class="w-full sm:flex-1 sm:min-w-[180px] rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2 text-sm dark:placeholder-slate-400" type="search" placeholder="Search citations...">
                        <select id="citations_type_filter" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2 text-sm hidden sm:inline-block">
                            <option value="">All Types</option>
                            <option value="Manual Submission">Manual Submission</option>
                            <option value="Citation Builder">Citation Builder</option>
                            <option value="Key Citation">Key Citation</option>
                            <option value="Niche Citation">Niche Citation</option>
                            <option value="Competitor Citation">Competitor Citation</option>
                            <option value="Lead Aggregators">Lead Aggregators</option>
                        </select>
                        <button id="citations_sort_btn" type="button" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Sort: Newest</button>
                        <button id="citations_export" type="button" class="rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hidden sm:inline-block">Export CSV</button>
                        </div>
                        <div class="flex w-full sm:w-auto flex-wrap items-center justify-between sm:justify-end gap-2">
                            <input type="hidden" form="bulk_assign_form" name="action" value="bulk_assign_citations">
                            <select form="bulk_assign_form" name="bulk_assigned_to" class="rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-3 py-2 text-sm text-xs">
                                <option value="">Assignee</option>
                                <?php foreach ($assignableUsers as $assignableUser): ?>
                                    <option value="<?php echo e((string)$assignableUser['id']); ?>"><?php echo e((string)$assignableUser['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button form="bulk_assign_form" type="submit" class="rounded-lg bg-brand-600 dark:bg-brand-700 px-2 sm:px-3 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-brand-700 dark:hover:bg-brand-600 whitespace-nowrap">Assign</button>
                            <input type="hidden" form="bulk_delete_form" name="action" value="request_bulk_delete_citations">
                            <button id="bulk_delete_submit" form="bulk_delete_form" type="submit" class="rounded-lg bg-rose-600 dark:bg-rose-700 px-2 sm:px-3 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-rose-700 dark:hover:bg-rose-600 whitespace-nowrap">Delete</button>
                        </div>
                    </div>
                    <button id="toggle_metrics_btn" type="button" aria-expanded="true" aria-controls="citations_metrics_content" class="w-full sm:hidden rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-between">
                        <span>Metrics</span>
                        <span id="toggle_metrics_icon" class="text-sm">▲</span>
                    </button>
                    <div id="citations_metrics_panel" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2.5">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Quick Metrics</p>
                        <div id="citations_metrics_content" class="mt-2 grid gap-2 sm:grid-cols-2 auto-rows-max">
                            <section class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-900/40 px-2.5 py-2 min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Citation Status</p>
                                <div class="mt-1.5 flex items-center gap-1.5 overflow-x-auto whitespace-nowrap pb-1 [scrollbar-width:thin]">
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700" data-metric-kind="status" data-metric-value="not_started" aria-pressed="false">Not Started <span class="citation-metric-count min-w-[20px] rounded-full bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700" data-metric-kind="status" data-metric-value="in_progress" aria-pressed="false">In Progress <span class="citation-metric-count min-w-[20px] rounded-full bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700" data-metric-kind="status" data-metric-value="pending_submission" aria-pressed="false">Pending <span class="citation-metric-count min-w-[20px] rounded-full bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700" data-metric-kind="status" data-metric-value="unable_to_submit" aria-pressed="false">Unable to Submit <span class="citation-metric-count min-w-[20px] rounded-full bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700" data-metric-kind="status" data-metric-value="live" aria-pressed="false">Live <span class="citation-metric-count min-w-[20px] rounded-full bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                </div>
                            </section>
                            <section class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white/70 dark:bg-slate-900/40 px-2.5 py-2 min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Live URL Status</p>
                                <div class="mt-1.5 flex items-center gap-1.5 overflow-x-auto whitespace-nowrap pb-1 [scrollbar-width:thin]">
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/30" data-metric-kind="nap" data-metric-value="correct" aria-pressed="false">Correct NAP <span class="citation-metric-count min-w-[20px] rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-900/20 px-2.5 py-1 text-[11px] font-semibold text-rose-800 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/30" data-metric-kind="nap" data-metric-value="nap_error" aria-pressed="false">NAP Error <span class="citation-metric-count min-w-[20px] rounded-full bg-rose-100 dark:bg-rose-900/40 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/40 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:text-amber-100 hover:bg-amber-100 dark:hover:bg-amber-900/60" data-metric-kind="nap" data-metric-value="pending_update_edit_request" aria-pressed="false">Pending Update/Edit Request <span class="citation-metric-count min-w-[20px] rounded-full bg-amber-100 dark:bg-amber-800/70 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                    <button type="button" class="citation-metric-btn inline-flex items-center gap-1 rounded-full border border-slate-400 dark:border-slate-500 bg-slate-100 dark:bg-slate-700 px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:text-slate-100 hover:bg-slate-200 dark:hover:bg-slate-600" data-metric-kind="nap" data-metric-value="unable_to_claim" aria-pressed="false">Unable to Claim <span class="citation-metric-count min-w-[20px] rounded-full bg-slate-200 dark:bg-slate-600 px-1.5 py-0.5 text-center" data-metric-count>0</span></button>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto py-3">
                <form id="bulk_assign_form" method="post"></form>
                <form id="bulk_delete_form" method="post"></form>
                <table id="citations_table" class="min-w-[1080px] table-fixed divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="w-[5%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"><input id="citations_select_all" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-brand-600"></th>
                            <th class="w-[6%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">#</th>
                            <th class="w-[24%] px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Directory</th>
                            <th class="w-[14%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Citation Type</th>
                            <th class="w-[12%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                            <th class="w-[12%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Priority</th>
                            <th class="w-[18%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Live URL Status</th>
                            <th class="w-[12%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Assignee</th>
                            <th class="w-[10%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Updated</th>
                            <th class="w-[20%] px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Action</th>
                        </tr>
                    </thead>
                    <tbody id="citations_tbody" class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-900">
                        <?php foreach ($citations as $idx => $c): ?>
                            <?php
                                $statusColors = [
                                    'not_started' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
                                    'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'pending_submission' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                    'unable_to_submit' => 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',
                                    'submitted' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                    'live' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                ];
                                $statusBadgeClass = $statusColors[$c['status']] ?? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200';
                                $statusBadgeLabel = status_label((string)($c['status'] ?? ''));
                                $noteText = trim((string)($c['notes'] ?? ''));
                                $assigneeFullName = trim((string)($c['assignee_name'] ?? ''));
                                $assigneeImagePath = trim((string)($c['assignee_image_path'] ?? ''));
                                $assigneeInitial = strtoupper(substr($assigneeFullName !== '' ? $assigneeFullName : 'U', 0, 1));
                                $assigneeFirstName = 'Unassigned';
                                if ($assigneeFullName !== '') {
                                    $nameParts = preg_split('/\s+/', $assigneeFullName);
                                    if (is_array($nameParts) && isset($nameParts[0]) && trim((string)$nameParts[0]) !== '') {
                                        $assigneeFirstName = (string)$nameParts[0];
                                    }
                                }
                                $rowStatus = (string)($c['status'] ?? 'not_started');
                                $rowNapStatus = $rowStatus === 'live' ? (string)($c['nap_status'] ?? 'correct') : '';
                                $rowStatusHighlightMap = [
                                    'not_started' => 'bg-slate-50/60 dark:bg-slate-800/40',
                                    'in_progress' => 'bg-blue-50/60 dark:bg-blue-900/20',
                                    'pending_submission' => 'bg-orange-50/60 dark:bg-orange-900/20',
                                    'unable_to_submit' => 'bg-rose-50/60 dark:bg-rose-900/20',
                                    'live' => 'bg-emerald-50/60 dark:bg-emerald-900/20',
                                ];
                                $rowHighlightClass = $rowStatusHighlightMap[$rowStatus] ?? 'bg-slate-50/60 dark:bg-slate-800/40';
                            ?>
                            <tr
                                class="align-middle <?php echo e($rowHighlightClass); ?> hover:bg-slate-100 dark:hover:bg-slate-700/70"
                                data-citation-id="<?php echo e((string)$c['id']); ?>"
                                data-directory="<?php echo e((string)$c['directory_name']); ?>"
                                data-directory-website="<?php echo e((string)($c['directory_website'] ?? '')); ?>"
                                data-citation-type="<?php echo e((string)$c['citation_type']); ?>"
                                data-status="<?php echo e($rowStatus); ?>"
                                data-nap-status="<?php echo e($rowNapStatus); ?>"
                                data-assigned-to="<?php echo e((string)($c['assigned_to'] ?? '')); ?>"
                                data-priority-level="<?php echo e((string)($c['priority_level'] ?? 'medium')); ?>"
                                data-url="<?php echo e((string)($c['submitted_url'] ?? '')); ?>"
                                data-proof-url="<?php echo e(trim((string)($c['latest_proof_file'] ?? '')) !== '' ? public_asset_url((string)$c['latest_proof_file']) : ''); ?>"
                                data-assignee="<?php echo e($assigneeFirstName); ?>"
                                data-updated="<?php echo e((string)($c['updated_at'] ?? '')); ?>"
                                data-notes="<?php echo e((string)$noteText); ?>"
                            >
                                <td class="px-3 py-2.5 align-middle text-center text-xs text-slate-500 dark:text-slate-400"><input type="checkbox" class="citation-bulk-checkbox h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-brand-600" name="citation_ids[]" value="<?php echo e((string)$c['id']); ?>" form="bulk_assign_form"></td>
                                <td class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 dark:text-slate-400"><?php echo e((string)((int)$idx + 1)); ?></td>
                                <td class="px-3 py-2.5 text-sm font-semibold text-slate-900 dark:text-white">
                                    <div class="flex items-center gap-1.5">
                                        <?php if (trim((string)($c['directory_logo_path'] ?? '')) !== ''): ?>
                                            <img class="h-7 w-7 rounded-md border border-slate-200 dark:border-slate-700 object-cover" src="<?php echo e(public_asset_url((string)$c['directory_logo_path'])); ?>" alt="Directory logo">
                                        <?php endif; ?>
                                        <span class="truncate"><?php echo e($c['directory_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs text-slate-600 dark:text-slate-300"><?php echo e((string)$c['citation_type']); ?></td>
                                <td class="px-3 py-2.5 text-center"><span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold <?php echo e($statusBadgeClass); ?>"><?php echo e($statusBadgeLabel); ?></span></td>
                                <td class="px-3 py-2.5 text-center text-xs text-slate-600 dark:text-slate-300"><?php echo e(status_label((string)($c['priority_level'] ?? 'medium'))); ?></td>
                                <td class="px-3 py-2.5 text-center text-xs text-slate-600 dark:text-slate-300">
                                    <?php if (($c['status'] ?? '') !== 'live'): ?>
                                        <span class="text-slate-400 dark:text-slate-600">-</span>
                                    <?php else: ?>
                                        <?php if (($c['nap_status'] ?? 'correct') === 'nap_error'): ?>
                                            <span class="inline-block rounded-full bg-rose-100 dark:bg-rose-900 px-2 py-0.5 text-[11px] font-semibold text-rose-800 dark:text-rose-200">NAP Error</span>
                                        <?php elseif (($c['nap_status'] ?? 'correct') === 'pending_update_edit_request'): ?>
                                            <span class="inline-block rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:text-amber-100">Pending Update/Edit Request</span>
                                        <?php elseif (($c['nap_status'] ?? 'correct') === 'unable_to_claim'): ?>
                                            <span class="inline-block rounded-full bg-slate-200 dark:bg-slate-700 px-2 py-0.5 text-[11px] font-semibold text-slate-700 dark:text-slate-100">Unable to Claim</span>
                                        <?php else: ?>
                                            <span class="inline-block rounded-full bg-emerald-100 dark:bg-emerald-900 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:text-emerald-200">Correct</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs text-slate-600 dark:text-slate-300">
                                    <?php if ($assigneeFullName === ''): ?>
                                        <?php echo e($assigneeFirstName); ?>
                                    <?php elseif ($assigneeImagePath !== ''): ?>
                                        <img
                                            class="mx-auto h-8 w-8 rounded-full border border-slate-200 object-cover"
                                            src="<?php echo e($assigneeImagePath); ?>"
                                            alt="<?php echo e($assigneeFullName); ?>"
                                            title="<?php echo e($assigneeFullName); ?>"
                                        >
                                    <?php else: ?>
                                        <div
                                            class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-[11px] font-bold text-white"
                                            title="<?php echo e($assigneeFullName); ?>"
                                            aria-label="<?php echo e($assigneeFullName); ?>"
                                        >
                                            <?php echo e($assigneeInitial !== '' ? $assigneeInitial : 'U'); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs text-slate-500 dark:text-slate-400"><?php echo e((string)$c['updated_at']); ?></td>
                                <td class="px-3 py-2.5 text-center overflow-visible">
                                    <div class="relative flex items-center justify-center gap-1.5">
                                        <?php if ((string)($c['status'] ?? '') === 'live' && trim((string)($c['submitted_url'] ?? '')) !== ''): ?>
                                            <a
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 transition hover:bg-emerald-50 dark:hover:bg-emerald-900/30"
                                                href="<?php echo e((string)$c['submitted_url']); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label="View live citation"
                                                title="View live citation"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2.06 12C3.14 7.99 6.77 5 11 5c4.23 0 7.86 2.99 8.94 7-1.08 4.01-4.71 7-8.94 7-4.23 0-7.86-2.99-8.94-7z"/>
                                                    <circle cx="11" cy="12" r="3"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (trim((string)($c['submission_url'] ?? '')) !== '' && !in_array((string)($c['status'] ?? ''), ['live', 'pending_submission'], true)): ?>
                                            <a class="submit-listing-trigger inline-flex h-7 w-7 items-center justify-center rounded-md border border-brand-200 dark:border-brand-800 bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-400 hover:bg-brand-100 dark:hover:bg-brand-900/50" href="<?php echo e((string)$c['submission_url']); ?>" target="_blank" rel="noopener noreferrer" data-citation-id="<?php echo e((string)$c['id']); ?>" data-submission-url="<?php echo e((string)$c['submission_url']); ?>" title="Submit listing" aria-label="Submit listing" onclick="openStickyNotesWindow();">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h10.5A1.75 1.75 0 0 1 17 4.75v6.5A1.75 1.75 0 0 1 15.25 13H12a.75.75 0 0 0 0 1.5h3.25a3.25 3.25 0 0 0 3.25-3.25v-6.5A3.25 3.25 0 0 0 15.25 1.5H4.75A3.25 3.25 0 0 0 1.5 4.75v10.5a3.25 3.25 0 0 0 3.25 3.25h6.5a.75.75 0 0 0 0-1.5H4.75A1.75 1.75 0 0 1 3 15.25v-10.5Z" />
                                                    <path d="M19 1.75a.75.75 0 0 0-1.06-1.06l-10.5 10.5a.75.75 0 1 0 1.06 1.06L19 1.75Z" />
                                                    <path d="M13.3 2.75H16a.75.75 0 0 0 0-1.5h-3.5a.75.75 0 0 0-.75.75v3.5a.75.75 0 0 0 1.5 0V2.75Z" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($noteText !== ''): ?>
                                            <button
                                                type="button"
                                                class="note-trigger inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 transition hover:border-brand-300 dark:hover:border-brand-700 hover:bg-brand-50 dark:hover:bg-brand-900/30 hover:text-brand-700 dark:hover:text-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-300 dark:focus:ring-brand-700"
                                                data-note="<?php echo e($noteText); ?>"
                                                aria-label="View note"
                                                aria-expanded="false"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h10.5A1.75 1.75 0 0 1 17 4.75v6.5A1.75 1.75 0 0 1 15.25 13H8.31L5.2 16.11a.75.75 0 0 1-1.28-.53V13.3A1.75 1.75 0 0 1 3 11.75v-7Z" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (trim((string)($c['latest_proof_file'] ?? '')) !== ''): ?>
                                            <button
                                                type="button"
                                                class="proof-preview-trigger inline-flex h-7 w-7 items-center justify-center rounded-md border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 transition hover:bg-emerald-50 dark:hover:bg-emerald-900/30 focus:outline-none focus:ring-2 focus:ring-emerald-300 dark:focus:ring-emerald-700"
                                                data-proof-url="<?php echo e(public_asset_url((string)$c['latest_proof_file'])); ?>"
                                                data-proof-label="<?php echo e((string)($c['directory_name'] ?? 'Citation')); ?> proof"
                                                aria-label="View proof image"
                                                title="View proof"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M1 4.75A1.75 1.75 0 0 1 2.75 3h14.5A1.75 1.75 0 0 1 19 4.75v10.5A1.75 1.75 0 0 1 17.25 17H2.75A1.75 1.75 0 0 1 1 15.25V4.75Zm4.5 1.5a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5Zm-2.8 8.9 3.56-3.56a1 1 0 0 1 1.41 0l1.59 1.6 2.84-2.84a1 1 0 0 1 1.41 0l2.79 2.79v2.11H2.7v-.1Z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="citation-edit-trigger inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 text-slate-700 hover:bg-slate-100" title="Edit citation" aria-label="Edit citation">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-8.7 8.7a2 2 0 0 1-.878.513l-2.73.78a.75.75 0 0 1-.927-.927l.78-2.73a2 2 0 0 1 .513-.878l8.7-8.7Z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="citations_no_results" class="hidden">
                            <td class="px-5 py-6 text-sm text-slate-500" colspan="10">No citations match your filter/search.</td>
                        </tr>
                        <?php if (!$citations): ?>
                            <tr data-empty-row="1"><td class="px-5 py-6 text-sm text-slate-500" colspan="10">No citations yet for this business.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div id="proof_preview_modal" class="fixed inset-0 z-50 hidden">
    <div id="proof_preview_modal_backdrop" class="absolute inset-0 bg-slate-900/70"></div>
    <div class="relative mx-auto mt-8 w-[95vw] max-w-4xl px-2 sm:px-0">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 id="proof_preview_title" class="text-lg font-bold text-slate-900">Proof Preview</h3>
                <button id="close_proof_preview_modal" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Close</button>
            </div>
            <div class="max-h-[78vh] overflow-auto bg-slate-50 p-4">
                <img id="proof_preview_image" class="mx-auto max-h-[70vh] w-auto max-w-full rounded-lg border border-slate-200 bg-white object-contain p-1" src="" alt="Proof screenshot preview">
            </div>
        </section>
    </div>
</div>

<div id="business_edit_modal" class="fixed inset-0 z-50 hidden">
    <div id="business_edit_modal_backdrop" class="absolute inset-0 bg-slate-900/55"></div>
    <div class="relative mx-auto mt-6 w-[95vw] max-w-3xl px-2 sm:px-0">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 class="text-lg font-bold text-slate-900">Edit Business Information</h3>
                <button id="close_business_edit_modal" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Close</button>
            </div>
            <form method="post" class="max-h-[78vh] space-y-3 overflow-y-auto px-5 py-4">
                <input type="hidden" name="action" value="update_business_info">

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Business Name</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="name" value="<?php echo e((string)($business['name'] ?? '')); ?>" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Phone</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="phone" value="<?php echo e((string)($business['phone'] ?? '')); ?>" required>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Website</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="website" value="<?php echo e((string)($business['website'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">GBP Link</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="gbp_link" value="<?php echo e((string)($business['gbp_link'] ?? '')); ?>">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="email" value="<?php echo e((string)($business['email'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Contact Name</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="contact_name" value="<?php echo e((string)($business['contact_name'] ?? '')); ?>">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">In Business Since</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="in_business_since" value="<?php echo e((string)($business['in_business_since'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Country</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="country" value="<?php echo e((string)($business['country'] ?? '')); ?>">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Categories (one per line)</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="categories" rows="3"><?php echo e((string)(($business['categories'] ?? '') !== '' ? $business['categories'] : ($business['category'] ?? ''))); ?></textarea>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Address Line</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="address_line1" value="<?php echo e((string)($business['address_line1'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">City</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="city" value="<?php echo e((string)($business['city'] ?? '')); ?>">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">State</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="state" value="<?php echo e((string)($business['state'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Postal Code</label>
                        <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="postal_code" value="<?php echo e((string)($business['postal_code'] ?? '')); ?>">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Business Hours</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="hours_json" rows="4"><?php echo e((string)($business['hours_json'] ?? '')); ?></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Services</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="services" rows="3"><?php echo e((string)($business['services'] ?? '')); ?></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Payment Methods</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="payment_methods" rows="2"><?php echo e((string)($business['payment_methods'] ?? '')); ?></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Social Media</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="social_media" rows="2"><?php echo e((string)($business['social_media'] ?? '')); ?></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Login Credentials</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="login_credentials" rows="2"><?php echo e((string)($business['login_credentials'] ?? '')); ?></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Description</label>
                    <textarea class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="description" rows="3"><?php echo e((string)($business['description'] ?? '')); ?></textarea>
                </div>

                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-950" type="submit">Save Business Information</button>
            </form>
        </section>
    </div>
</div>

<div id="citation_edit_modal" class="fixed inset-0 z-50 hidden">
    <div id="citation_edit_modal_backdrop" class="absolute inset-0 bg-slate-900/55"></div>
    <div class="relative mx-auto mt-10 w-[95vw] max-w-lg px-2 sm:px-0">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 class="text-lg font-bold text-slate-900">Edit Citation</h3>
                <button id="close_citation_edit_modal" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Close</button>
            </div>
            <form method="post" enctype="multipart/form-data" class="max-h-[72vh] space-y-3 overflow-y-auto px-5 py-4">
                <input type="hidden" name="action" value="update_citation">
                <input type="hidden" id="edit_citation_id" name="citation_id" value="">
                <input type="hidden" id="edit_has_existing_proof" name="has_existing_proof" value="0">

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Citation Type</label>
                    <select id="edit_citation_type" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="citation_type">
                        <option value="Manual Submission">Manual Submission</option>
                        <option value="Citation Builder">Citation Builder</option>
                        <option value="Key Citation">Key Citation</option>
                        <option value="Niche Citation">Niche Citation</option>
                        <option value="Competitor Citation">Competitor Citation</option>
                        <option value="Lead Aggregators">Lead Aggregators</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Status</label>
                    <select id="edit_citation_status" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="status">
                        <?php foreach ($allowedCitationStatuses as $s): ?>
                            <option value="<?php echo e($s); ?>"><?php echo e(status_label($s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Assigned To</label>
                        <select id="edit_assigned_to" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($assignableUsers as $assignableUser): ?>
                                <option value="<?php echo e((string)$assignableUser['id']); ?>"><?php echo e((string)$assignableUser['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Priority</label>
                        <select id="edit_priority_level" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="priority_level">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>

                <div id="edit_nap_status_row">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Live URL Status</label>
                    <select id="edit_nap_status" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="nap_status">
                        <option value="correct">Correct</option>
                        <option value="nap_error">NAP Error</option>
                        <option value="pending_update_edit_request">Pending Update/Edit Request</option>
                        <option value="unable_to_claim">Unable to Claim</option>
                    </select>
                </div>

                <div id="edit_submission_proof_row">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Submission Proof (Screenshot)</label>
                    <input class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" id="edit_submission_proof" type="file" name="edit_submission_proof" accept="image/*">
                    <p class="mt-1 text-xs text-slate-500">Any image size is accepted and automatically optimized to a maximum of 200KB.</p>
                    <p class="mt-1 text-xs text-slate-500" id="edit_submission_proof_hint">Required when status is Pending Submission and no existing proof is on record.</p>
                </div>

                <div id="edit_citation_url_row">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Citation URL</label>
                    <input id="edit_citation_url" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="submitted_url" value="">
                    <p class="mt-1 text-xs text-slate-500">Required when status is Live.</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Notes</label>
                    <div class="mb-1 flex items-center gap-1.5">
                        <select class="note-snippet-select flex-1 rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                            <option value="">Insert note snippet...</option>
                            <option value="Pending verification email.">Pending verification email.</option>
                            <option value="Rejected: duplicate listing.">Rejected: duplicate listing.</option>
                            <option value="Needs additional business proof.">Needs additional business proof.</option>
                            <option value="Pending submission, awaiting review.">Pending submission, awaiting review.</option>
                            <option value="Live listing confirmed.">Live listing confirmed.</option>
                        </select>
                        <button type="button" class="note-snippet-apply rounded-lg border border-slate-300 px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Add</button>
                    </div>
                    <textarea id="edit_citation_notes" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm" name="notes" rows="4"></textarea>
                </div>

                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-950" type="submit">Update Citation</button>
            </form>
        </section>
    </div>
</div>

<div id="note_popover" class="pointer-events-none fixed z-50 hidden w-80 max-w-[calc(100vw-1rem)] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 shadow-xl opacity-0 scale-95 transition duration-150 ease-out">
    <p class="mb-1 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Notes</p>
    <p id="note_popover_content" class="max-h-56 overflow-auto whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-200"></p>
</div>

<script>
    let stickyNotesWindow = null;
    const stickyNotesButton = document.getElementById('open_sticky_notes');
    const openBusinessEditModalBtn = document.getElementById('open_business_edit_modal');
    const businessEditModal = document.getElementById('business_edit_modal');
    const closeBusinessEditModalBtn = document.getElementById('close_business_edit_modal');
    const businessEditModalBackdrop = document.getElementById('business_edit_modal_backdrop');
    const directorySelect = document.getElementById('directory_id');
    const submissionUrlLink = document.getElementById('submission_url_link');
    const submissionUrlEmpty = document.getElementById('submission_url_empty');
    const citationsModal = document.getElementById('citations_modal');
    const openCitationsModalBtn = document.getElementById('open_citations_modal');
    const closeCitationsModalBtn = document.getElementById('close_citations_modal');
    const citationsModalBackdrop = document.getElementById('citations_modal_backdrop');
    const toggleMetricsBtn = document.getElementById('toggle_metrics_btn');
    const citationsMetricsContent = document.getElementById('citations_metrics_content');
    const toggleMetricsIcon = document.getElementById('toggle_metrics_icon');
    const citationEditModal = document.getElementById('citation_edit_modal');
    const closeCitationEditModalBtn = document.getElementById('close_citation_edit_modal');
    const citationEditModalBackdrop = document.getElementById('citation_edit_modal_backdrop');
    const proofPreviewModal = document.getElementById('proof_preview_modal');
    const proofPreviewBackdrop = document.getElementById('proof_preview_modal_backdrop');
    const closeProofPreviewModalBtn = document.getElementById('close_proof_preview_modal');
    const proofPreviewImage = document.getElementById('proof_preview_image');
    const proofPreviewTitle = document.getElementById('proof_preview_title');
    const editCitationIdInput = document.getElementById('edit_citation_id');
    const editHasExistingProofInput = document.getElementById('edit_has_existing_proof');
    const editCitationTypeInput = document.getElementById('edit_citation_type');
    const editCitationStatusInput = document.getElementById('edit_citation_status');
    const editNapStatusInput = document.getElementById('edit_nap_status');
    const editNapStatusRow = document.getElementById('edit_nap_status_row');
    const editAssignedToInput = document.getElementById('edit_assigned_to');
    const editPriorityLevelInput = document.getElementById('edit_priority_level');
    const editCitationUrlRow = document.getElementById('edit_citation_url_row');
    const editCitationUrlInput = document.getElementById('edit_citation_url');
    const editSubmissionProofRow = document.getElementById('edit_submission_proof_row');
    const editSubmissionProofInput = document.getElementById('edit_submission_proof');
    const editSubmissionProofHint = document.getElementById('edit_submission_proof_hint');
    const editCitationNotesInput = document.getElementById('edit_citation_notes');
    const addCitationsActionInput = document.querySelector('input[name="action"][value="add_citations_bulk"]');
    const addCitationsForm = addCitationsActionInput ? addCitationsActionInput.closest('form') : null;
    const addCitationNotesInput = addCitationsForm ? addCitationsForm.querySelector('textarea[name="notes"]') : null;
    const addCitationStatusInput = document.getElementById('add_citation_status');
    const addNapStatusRow = document.getElementById('add_nap_status_row');
    const addNapStatusInput = document.getElementById('add_nap_status');
    const addCitationUrlRow = document.getElementById('add_citation_url_row');
    const addCitationUrlInput = document.getElementById('add_citation_url');
    const addSubmissionProofRow = document.getElementById('add_submission_proof_row');
    const addSubmissionProofInput = document.getElementById('add_submission_proof');
    const addSubmissionProofHint = document.getElementById('add_submission_proof_hint');
    const directoryPickerSearch = document.getElementById('directory_picker_search');
    const directoryPickerList = document.getElementById('directory_picker_list');
    const directoryPickerNoResults = document.getElementById('directory_picker_no_results');
    const directoryPickerItems = Array.from(document.querySelectorAll('.directory-picker-item'));
    const directoryPickerCheckboxes = Array.from(document.querySelectorAll('.directory-picker-checkbox'));
    const selectVisibleDirectoriesBtn = document.getElementById('select_visible_directories');
    const clearDirectorySelectionBtn = document.getElementById('clear_directory_selection');
    const selectedDirectoriesCount = document.getElementById('selected_directories_count');
    let currentEditDirectoryWebsite = '';

    const getSelectedDirectoryWebsite = () => {
        if (!directorySelect) {
            return '';
        }
        const selectedOption = directorySelect.options[directorySelect.selectedIndex];
        return selectedOption ? (selectedOption.getAttribute('data-directory-website') || '').trim() : '';
    };

    const openStickyNotesWindow = () => {
        const stickyUrl = '<?php echo e(app_config()['base_url']); ?>/sticky_notes.php?business_id=<?php echo e((string)$businessId); ?>';
        const features = 'popup=yes,width=430,height=760,resizable=yes,scrollbars=yes';
        if (stickyNotesWindow && !stickyNotesWindow.closed) {
            stickyNotesWindow.location.href = stickyUrl;
            stickyNotesWindow.focus();
            return;
        }

        stickyNotesWindow = window.open(stickyUrl, 'cfStickyNotes', features);
        if (stickyNotesWindow) {
            stickyNotesWindow.focus();
        }
    };

    if (stickyNotesButton) {
        stickyNotesButton.addEventListener('click', openStickyNotesWindow);
    }

    const openBusinessEditModal = () => {
        if (!businessEditModal) {
            return;
        }
        businessEditModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeBusinessEditModal = () => {
        if (!businessEditModal) {
            return;
        }
        businessEditModal.classList.add('hidden');
        if ((citationsModal && !citationsModal.classList.contains('hidden')) || (proofPreviewModal && !proofPreviewModal.classList.contains('hidden'))) {
            return;
        }
        document.body.classList.remove('overflow-hidden');
    };

    if (openBusinessEditModalBtn) {
        openBusinessEditModalBtn.addEventListener('click', openBusinessEditModal);
    }
    if (closeBusinessEditModalBtn) {
        closeBusinessEditModalBtn.addEventListener('click', closeBusinessEditModal);
    }
    if (businessEditModalBackdrop) {
        businessEditModalBackdrop.addEventListener('click', closeBusinessEditModal);
    }

    const openCitationsModal = () => {
        if (!citationsModal) {
            return;
        }
        citationsModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeCitationsModal = () => {
        if (!citationsModal) {
            return;
        }
        citationsModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        if (citationEditModal) {
            citationEditModal.classList.add('hidden');
        }
    };

    const openCitationEditModal = (row) => {
        if (!citationEditModal || !editCitationIdInput || !editCitationTypeInput || !editCitationStatusInput || !editCitationNotesInput) {
            return;
        }

        editCitationIdInput.value = row.getAttribute('data-citation-id') || '';
        editCitationTypeInput.value = row.getAttribute('data-citation-type') || 'Manual Submission';
        editCitationStatusInput.value = row.getAttribute('data-status') || 'not_started';
        if (editNapStatusInput) editNapStatusInput.value = row.getAttribute('data-nap-status') || 'correct';
        if (editAssignedToInput) editAssignedToInput.value = row.getAttribute('data-assigned-to') || '';
        if (editPriorityLevelInput) editPriorityLevelInput.value = row.getAttribute('data-priority-level') || 'medium';
        if (editCitationUrlInput) editCitationUrlInput.value = row.getAttribute('data-url') || '';
        currentEditDirectoryWebsite = (row.getAttribute('data-directory-website') || '').trim();
        editCitationNotesInput.value = row.getAttribute('data-notes') || '';
        if (editHasExistingProofInput) {
            editHasExistingProofInput.value = (row.getAttribute('data-proof-url') || '').trim() !== '' ? '1' : '0';
        }
        if (editSubmissionProofInput) {
            editSubmissionProofInput.value = '';
        }

        updateCitationFieldsByStatus('edit');

        citationEditModal.classList.remove('hidden');
    };

    const closeCitationEditModal = () => {
        if (!citationEditModal) {
            return;
        }
        citationEditModal.classList.add('hidden');
    };

    const openProofPreviewModal = (proofUrl, proofLabel) => {
        if (!proofPreviewModal || !proofPreviewImage) {
            return;
        }

        if (!proofUrl) {
            return;
        }

        proofPreviewImage.src = proofUrl;
        if (proofPreviewTitle) {
            proofPreviewTitle.textContent = proofLabel && proofLabel.trim() !== '' ? proofLabel : 'Proof Preview';
        }
        proofPreviewModal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeProofPreviewModal = () => {
        if (!proofPreviewModal || !proofPreviewImage) {
            return;
        }

        proofPreviewModal.classList.add('hidden');
        proofPreviewImage.src = '';
        if (!citationsModal || citationsModal.classList.contains('hidden')) {
            document.body.classList.remove('overflow-hidden');
        }
    };

    if (openCitationsModalBtn) {
        openCitationsModalBtn.addEventListener('click', openCitationsModal);
    }
    if (closeCitationsModalBtn) {
        closeCitationsModalBtn.addEventListener('click', closeCitationsModal);
    }
    if (citationsModalBackdrop) {
        citationsModalBackdrop.addEventListener('click', closeCitationsModal);
    }

    // Toggle metrics panel on mobile
    if (toggleMetricsBtn && citationsMetricsContent) {
        const mobileViewport = window.matchMedia('(max-width: 639px)');
        let metricsVisible = true;
        const updateMetricsToggle = () => {
            const isMobile = mobileViewport.matches;

            if (!isMobile) {
                citationsMetricsContent.classList.remove('hidden');
                toggleMetricsBtn.setAttribute('aria-expanded', 'true');
                if (toggleMetricsIcon) {
                    toggleMetricsIcon.textContent = '▲';
                }
                return;
            }

            if (metricsVisible) {
                citationsMetricsContent.classList.remove('hidden');
                toggleMetricsBtn.setAttribute('aria-expanded', 'true');
                if (toggleMetricsIcon) {
                    toggleMetricsIcon.textContent = '▲';
                }
            } else {
                citationsMetricsContent.classList.add('hidden');
                toggleMetricsBtn.setAttribute('aria-expanded', 'false');
                if (toggleMetricsIcon) {
                    toggleMetricsIcon.textContent = '▼';
                }
            }
        };
        toggleMetricsBtn.addEventListener('click', () => {
            metricsVisible = !metricsVisible;
            updateMetricsToggle();
        });

        if (typeof mobileViewport.addEventListener === 'function') {
            mobileViewport.addEventListener('change', updateMetricsToggle);
        } else if (typeof mobileViewport.addListener === 'function') {
            mobileViewport.addListener(updateMetricsToggle);
        }

        updateMetricsToggle();
    }

    const openCitationsOnLoad = <?php echo $openCitationsOnLoad ? 'true' : 'false'; ?>;
    if (openCitationsOnLoad) {
        document.body.classList.add('overflow-hidden');
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('open_citations');
            url.searchParams.delete('citations_notice');
            window.history.replaceState({}, document.title, url.toString());
        }
    }
    if (closeCitationEditModalBtn) {
        closeCitationEditModalBtn.addEventListener('click', closeCitationEditModal);
    }
    if (citationEditModalBackdrop) {
        citationEditModalBackdrop.addEventListener('click', closeCitationEditModal);
    }

    if (proofPreviewBackdrop) {
        proofPreviewBackdrop.addEventListener('click', closeProofPreviewModal);
    }
    if (closeProofPreviewModalBtn) {
        closeProofPreviewModalBtn.addEventListener('click', closeProofPreviewModal);
    }

    document.querySelectorAll('.citation-edit-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!(row instanceof HTMLTableRowElement)) {
                return;
            }
            openCitationEditModal(row);
        });
    });

    document.querySelectorAll('.proof-preview-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            const proofUrl = button.getAttribute('data-proof-url') || '';
            const proofLabel = button.getAttribute('data-proof-label') || 'Proof Preview';
            openProofPreviewModal(proofUrl, proofLabel);
        });
    });

    const updateSelectedDirectoriesCount = () => {
        if (!selectedDirectoriesCount) {
            return;
        }

        const selectedCount = directoryPickerCheckboxes.filter((checkbox) => checkbox.checked).length;
        selectedDirectoriesCount.textContent = `${selectedCount} selected`;
    };

    const applyDirectoryPickerFilter = () => {
        if (!directoryPickerItems.length) {
            return;
        }

        const query = (directoryPickerSearch ? directoryPickerSearch.value : '').trim().toLowerCase();
        let visibleCount = 0;
        directoryPickerItems.forEach((item) => {
            const searchValue = (item.getAttribute('data-search') || '').toLowerCase();
            const show = query === '' || searchValue.includes(query);
            item.classList.toggle('hidden', !show);
            if (show) {
                visibleCount += 1;
            }
        });

        if (directoryPickerNoResults) {
            directoryPickerNoResults.classList.toggle('hidden', visibleCount !== 0);
        }
    };

    if (directoryPickerSearch) {
        directoryPickerSearch.addEventListener('input', applyDirectoryPickerFilter);
    }
    if (selectVisibleDirectoriesBtn) {
        selectVisibleDirectoriesBtn.addEventListener('click', () => {
            directoryPickerItems.forEach((item) => {
                if (!item.classList.contains('hidden')) {
                    const checkbox = item.querySelector('.directory-picker-checkbox');
                    if (checkbox instanceof HTMLInputElement) {
                        checkbox.checked = true;
                    }
                }
            });
            updateSelectedDirectoriesCount();
        });
    }
    if (clearDirectorySelectionBtn) {
        clearDirectorySelectionBtn.addEventListener('click', () => {
            directoryPickerCheckboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
            updateSelectedDirectoriesCount();
        });
    }
    directoryPickerCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateSelectedDirectoriesCount);
    });

    applyDirectoryPickerFilter();
    updateSelectedDirectoriesCount();

    const setFieldVisibility = (rowEl, shouldShow) => {
        if (!rowEl) {
            return;
        }

        rowEl.classList.toggle('hidden', !shouldShow);
    };

    const requiredNoteMessage = 'You must have a note when status is Unable to Submit.';

    const updateRequiredNoteValidity = (textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        if (!textarea.required) {
            textarea.setCustomValidity('');
            return;
        }

        textarea.setCustomValidity(textarea.value.trim() === '' ? requiredNoteMessage : '');
    };

    const updateCitationFieldsByStatus = (mode) => {
        if (mode === 'add') {
            const status = addCitationStatusInput ? String(addCitationStatusInput.value || '').trim() : 'not_started';
            const showProof = status === 'pending_submission';
            const showNap = status === 'live';
            const showUrl = status === 'live';
            const requireNotes = status === 'unable_to_submit';

            setFieldVisibility(addSubmissionProofRow, showProof);
            setFieldVisibility(addNapStatusRow, showNap);
            setFieldVisibility(addCitationUrlRow, showUrl);

            if (addSubmissionProofInput) {
                addSubmissionProofInput.required = status === 'pending_submission';
            }
            if (addCitationUrlInput) {
                addCitationUrlInput.required = status === 'live';
            }
            if (addSubmissionProofHint) {
                addSubmissionProofHint.textContent = status === 'pending_submission'
                    ? 'Required when status is Pending Submission.'
                    : 'Hidden unless status is Pending Submission.';
            }
            if (addNapStatusInput) {
                addNapStatusInput.required = showNap;
            }
            if (addCitationNotesInput instanceof HTMLTextAreaElement) {
                addCitationNotesInput.required = requireNotes;
                updateRequiredNoteValidity(addCitationNotesInput);
            }
            return;
        }

        const status = editCitationStatusInput ? String(editCitationStatusInput.value || '').trim() : 'not_started';
        const hasExistingProof = editHasExistingProofInput ? editHasExistingProofInput.value === '1' : false;
        const showProof = status === 'pending_submission';
        const showNap = status === 'live';
        const showUrl = status === 'live';
        const requireNotes = status === 'unable_to_submit';

        setFieldVisibility(editSubmissionProofRow, showProof);
        setFieldVisibility(editNapStatusRow, showNap);
        setFieldVisibility(editCitationUrlRow, showUrl);

        if (editSubmissionProofInput) {
            editSubmissionProofInput.required = status === 'pending_submission' && !hasExistingProof;
        }
        if (editCitationUrlInput) {
            editCitationUrlInput.required = status === 'live';
        }
        if (editSubmissionProofHint) {
            if (status === 'pending_submission') {
                editSubmissionProofHint.textContent = hasExistingProof
                    ? 'Optional: upload a new screenshot to replace/add proof.'
                    : 'Required when status is Pending Submission and no existing proof is on record.';
            } else {
                editSubmissionProofHint.textContent = 'Hidden unless status is Pending Submission.';
            }
        }
        if (editNapStatusInput) {
            editNapStatusInput.required = showNap;
        }
        if (editCitationNotesInput) {
            editCitationNotesInput.required = requireNotes;
            updateRequiredNoteValidity(editCitationNotesInput);
        }
    };

    if (addCitationNotesInput instanceof HTMLTextAreaElement) {
        addCitationNotesInput.addEventListener('input', () => updateRequiredNoteValidity(addCitationNotesInput));
        addCitationNotesInput.addEventListener('invalid', () => updateRequiredNoteValidity(addCitationNotesInput));
    }

    if (editCitationNotesInput instanceof HTMLTextAreaElement) {
        editCitationNotesInput.addEventListener('input', () => updateRequiredNoteValidity(editCitationNotesInput));
        editCitationNotesInput.addEventListener('invalid', () => updateRequiredNoteValidity(editCitationNotesInput));
    }

    if (addCitationStatusInput) {
        addCitationStatusInput.addEventListener('change', () => updateCitationFieldsByStatus('add'));
    }
    if (addCitationUrlInput) {
        addCitationUrlInput.addEventListener('input', () => updateCitationFieldsByStatus('add'));
    }
    if (editCitationStatusInput) {
        editCitationStatusInput.addEventListener('change', () => updateCitationFieldsByStatus('edit'));
    }
    if (editCitationUrlInput) {
        editCitationUrlInput.addEventListener('input', () => updateCitationFieldsByStatus('edit'));
    }

    updateCitationFieldsByStatus('add');

    const noteTriggers = document.querySelectorAll('.note-trigger');
    const notePopover = document.getElementById('note_popover');
    const notePopoverContent = document.getElementById('note_popover_content');
    let activeTrigger = null;
    let hideTimer = null;

    const hideNotePopover = () => {
        if (!notePopover) {
            return;
        }

        notePopover.classList.remove('opacity-100', 'scale-100');
        notePopover.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
        window.setTimeout(() => {
            if (!notePopover.classList.contains('opacity-100')) {
                notePopover.classList.add('hidden');
            }
        }, 150);

        if (activeTrigger) {
            activeTrigger.setAttribute('aria-expanded', 'false');
        }
        activeTrigger = null;
    };

    const positionPopover = (trigger) => {
        if (!notePopover) {
            return;
        }

        const triggerRect = trigger.getBoundingClientRect();
        const margin = 8;
        const spacing = 10;

        notePopover.classList.remove('hidden');
        notePopover.style.left = '0px';
        notePopover.style.top = '0px';

        const popRect = notePopover.getBoundingClientRect();
        let top = triggerRect.top - popRect.height - spacing;
        let left = triggerRect.left + (triggerRect.width / 2) - (popRect.width / 2);

        if (top < margin) {
            top = triggerRect.bottom + spacing;
        }
        if (left < margin) {
            left = margin;
        }
        if (left + popRect.width > window.innerWidth - margin) {
            left = window.innerWidth - popRect.width - margin;
        }
        if (top + popRect.height > window.innerHeight - margin) {
            top = Math.max(margin, window.innerHeight - popRect.height - margin);
        }

        notePopover.style.left = `${left}px`;
        notePopover.style.top = `${top}px`;
    };

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    const linkifyNoteText = (value) => {
        const source = String(value || '').replace(/\r\n?/g, '\n').trim();
        if (source === '') {
            return '—';
        }

        const pattern = /(https?:\/\/[^\s<>"]+|www\.[^\s<>"]+|[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/gi;
        const parts = source.split(pattern);
        let html = '';

        parts.forEach((part) => {
            if (!part) {
                return;
            }

            if (/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i.test(part)) {
                const safeEmail = escapeHtml(part);
                html += `<a class="text-sky-600 dark:text-sky-300 hover:underline break-all" href="mailto:${safeEmail}">${safeEmail}</a>`;
                return;
            }

            if (/^(https?:\/\/|www\.)/i.test(part)) {
                const href = /^www\./i.test(part) ? `https://${part}` : part;
                html += `<a class="text-sky-600 dark:text-sky-300 hover:underline break-all" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${escapeHtml(part)}</a>`;
                return;
            }

            html += escapeHtml(part).replace(/\n/g, '<br>');
        });

        return html;
    };

    const showNotePopover = (trigger) => {
        if (!notePopover || !notePopoverContent) {
            return;
        }

        window.clearTimeout(hideTimer);
        const note = trigger.getAttribute('data-note') || '';
        notePopoverContent.innerHTML = linkifyNoteText(note);

        if (activeTrigger && activeTrigger !== trigger) {
            activeTrigger.setAttribute('aria-expanded', 'false');
        }

        activeTrigger = trigger;
        trigger.setAttribute('aria-expanded', 'true');

        positionPopover(trigger);
        requestAnimationFrame(() => {
            notePopover.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
            notePopover.classList.add('opacity-100', 'scale-100');
        });
    };

    noteTriggers.forEach((trigger) => {
        trigger.addEventListener('mouseenter', () => showNotePopover(trigger));
        trigger.addEventListener('focus', () => showNotePopover(trigger));

        trigger.addEventListener('mouseleave', () => {
            hideTimer = window.setTimeout(hideNotePopover, 120);
        });

        trigger.addEventListener('blur', () => {
            hideTimer = window.setTimeout(hideNotePopover, 120);
        });

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            if (activeTrigger === trigger && notePopover && !notePopover.classList.contains('hidden')) {
                hideNotePopover();
                return;
            }
            showNotePopover(trigger);
        });
    });

    if (notePopover) {
        notePopover.addEventListener('mouseenter', () => window.clearTimeout(hideTimer));
        notePopover.addEventListener('mouseleave', () => {
            hideTimer = window.setTimeout(hideNotePopover, 120);
        });
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        if (!target.closest('.note-trigger') && !target.closest('#note_popover')) {
            hideNotePopover();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeBusinessEditModal();
            hideNotePopover();
            closeProofPreviewModal();
            closeCitationEditModal();
            closeCitationsModal();
        }
    });

    document.querySelectorAll('.note-snippet-apply').forEach((button) => {
        button.addEventListener('click', () => {
            const snippetRow = button.parentElement;
            if (!snippetRow) {
                return;
            }

            const snippetSelect = snippetRow.querySelector('.note-snippet-select');
            const notesWrapper = snippetRow.parentElement;
            const noteTextarea = notesWrapper ? notesWrapper.querySelector('textarea[name="notes"]') : null;
            if (!snippetSelect || !noteTextarea) {
                return;
            }

            const snippet = snippetSelect.value.trim();
            if (snippet === '') {
                return;
            }

            const existing = noteTextarea.value.trim();
            noteTextarea.value = existing === '' ? snippet : existing + "\n" + snippet;
            snippetSelect.value = '';
            noteTextarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    window.addEventListener('resize', () => {
        if (activeTrigger) {
            positionPopover(activeTrigger);
        }
    });

    window.addEventListener('scroll', () => {
        if (activeTrigger) {
            positionPopover(activeTrigger);
        }
    }, true);

    const citationsTypeFilter = document.getElementById('citations_type_filter');
    const citationsSearch = document.getElementById('citations_search');
    const citationsSortBtn = document.getElementById('citations_sort_btn');
    const citationsExport = document.getElementById('citations_export');
    const citationMetricButtons = Array.from(document.querySelectorAll('.citation-metric-btn'));
    const citationsTbody = document.getElementById('citations_tbody');
    const noResultsRow = document.getElementById('citations_no_results');
    const bulkAssignForm = document.getElementById('bulk_assign_form');
    const bulkDeleteForm = document.getElementById('bulk_delete_form');
    const citationsSelectAll = document.getElementById('citations_select_all');
    const submitListingTriggers = Array.from(document.querySelectorAll('.submit-listing-trigger'));
    const locationManagerBaseUrl = <?php echo json_encode(app_config()['base_url']); ?>;
    let activeStatusMetric = '';
    let activeNapMetric = '';

    const statusBadgeClassMap = {
        not_started: 'bg-gray-100 text-gray-800',
        in_progress: 'bg-blue-100 text-blue-800',
        pending_submission: 'bg-orange-100 text-orange-800',
        unable_to_submit: 'bg-rose-100 text-rose-800',
        submitted: 'bg-purple-100 text-purple-800',
        live: 'bg-green-100 text-green-800'
    };

    const sortModes = [
        { key: 'updated_desc', label: 'Sort: Newest' },
        { key: 'updated_asc', label: 'Sort: Oldest' },
        { key: 'directory_asc', label: 'Sort: Directory A-Z' },
        { key: 'directory_desc', label: 'Sort: Directory Z-A' }
    ];
    let activeSortModeIndex = 0;

    const rowStatusClassMap = {
        not_started: 'bg-slate-50/60 dark:bg-slate-800/40',
        in_progress: 'bg-blue-50/60 dark:bg-blue-900/20',
        pending_submission: 'bg-orange-50/60 dark:bg-orange-900/20',
        unable_to_submit: 'bg-rose-50/60 dark:bg-rose-900/20',
        live: 'bg-emerald-50/60 dark:bg-emerald-900/20'
    };
    const rowStatusClassTokens = Object.values(rowStatusClassMap)
        .flatMap((classGroup) => classGroup.split(' '));

    const applyRowStatusClass = (row, status) => {
        if (!row) {
            return;
        }

        row.classList.remove(...rowStatusClassTokens);
        const classGroup = rowStatusClassMap[status] || rowStatusClassMap.not_started;
        classGroup.split(' ').forEach((className) => row.classList.add(className));
    };

    const getBulkCheckboxes = () => Array.from(document.querySelectorAll('.citation-bulk-checkbox'));

    const syncBulkDeleteFields = () => {
        if (!bulkDeleteForm) {
            return;
        }

        bulkDeleteForm.querySelectorAll('input[name="citation_ids[]"]').forEach((node) => node.remove());

        getBulkCheckboxes().forEach((checkbox) => {
            if (!checkbox.checked) {
                return;
            }
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'citation_ids[]';
            hidden.value = checkbox.value;
            bulkDeleteForm.appendChild(hidden);
        });
    };

    const updateSelectAllState = () => {
        if (!citationsSelectAll) {
            return;
        }

        const visibleCheckboxes = getBulkCheckboxes().filter((checkbox) => {
            const row = checkbox.closest('tr');
            return row && row.style.display !== 'none';
        });

        if (!visibleCheckboxes.length) {
            citationsSelectAll.checked = false;
            citationsSelectAll.indeterminate = false;
            return;
        }

        const checkedCount = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
        citationsSelectAll.checked = checkedCount > 0 && checkedCount === visibleCheckboxes.length;
        citationsSelectAll.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
    };

    const getCitationRows = () => {
        if (!citationsTbody) {
            return [];
        }

        return Array.from(citationsTbody.querySelectorAll('tr')).filter((row) => {
            return !row.hasAttribute('data-empty-row') && row.id !== 'citations_no_results';
        });
    };

    const toStatusLabel = (status) => {
        return String(status || '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    };

    const normalizeTimestamp = (value) => {
        const timestamp = Date.parse(String(value || '').trim());
        return Number.isNaN(timestamp) ? 0 : timestamp;
    };

    const renumberCitationRows = () => {
        getCitationRows().forEach((row, index) => {
            const indexCell = row.querySelector('td:nth-child(2)');
            if (indexCell) {
                indexCell.textContent = String(index + 1);
            }
        });
    };

    const applyCitationSort = () => {
        if (!citationsTbody) {
            return;
        }

        const rows = getCitationRows();
        if (!rows.length) {
            return;
        }

        const activeSortMode = sortModes[activeSortModeIndex] || sortModes[0];

        rows.sort((a, b) => {
            const aDirectory = String(a.getAttribute('data-directory') || '').toLowerCase();
            const bDirectory = String(b.getAttribute('data-directory') || '').toLowerCase();
            const aUpdated = normalizeTimestamp(a.getAttribute('data-updated'));
            const bUpdated = normalizeTimestamp(b.getAttribute('data-updated'));

            if (activeSortMode.key === 'updated_asc') {
                return aUpdated - bUpdated;
            }
            if (activeSortMode.key === 'directory_asc') {
                return aDirectory.localeCompare(bDirectory);
            }
            if (activeSortMode.key === 'directory_desc') {
                return bDirectory.localeCompare(aDirectory);
            }

            return bUpdated - aUpdated;
        });

        rows.forEach((row) => citationsTbody.appendChild(row));
        renumberCitationRows();
    };

    const applyStatusToRow = (row, status) => {
        if (!row) {
            return;
        }

        row.setAttribute('data-status', status);
        applyRowStatusClass(row, status);
        if (status !== 'live') {
            row.setAttribute('data-nap-status', '');
        }
        const badge = row.querySelector('td:nth-child(5) span');
        if (badge) {
            badge.className = `inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusBadgeClassMap[status] || 'bg-slate-100 text-slate-800'}`;
            badge.textContent = toStatusLabel(status);
        }
    };

    const updateCitationMetrics = () => {
        if (!citationMetricButtons.length) {
            return;
        }

        const rows = getCitationRows();
        const selectedType = citationsTypeFilter ? citationsTypeFilter.value.trim().toLowerCase() : '';
        const statusCounts = {
            not_started: 0,
            in_progress: 0,
            pending_submission: 0,
            unable_to_submit: 0,
            live: 0
        };
        const napCounts = {
            correct: 0,
            nap_error: 0,
            pending_update_edit_request: 0,
            unable_to_claim: 0
        };

        rows.forEach((row) => {
            const type = (row.getAttribute('data-citation-type') || '').toLowerCase();
            const status = (row.getAttribute('data-status') || '').trim();
            const napStatus = (row.getAttribute('data-nap-status') || '').trim();

            const typeMatch = selectedType === '' || type === selectedType;
            if (!typeMatch) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(statusCounts, status)) {
                statusCounts[status] += 1;
            }
            if (Object.prototype.hasOwnProperty.call(napCounts, napStatus)) {
                napCounts[napStatus] += 1;
            }
        });

        citationMetricButtons.forEach((button) => {
            const metricKind = String(button.getAttribute('data-metric-kind') || '').trim();
            const metricValue = String(button.getAttribute('data-metric-value') || '').trim();
            const countEl = button.querySelector('[data-metric-count]');
            if (!countEl) {
                return;
            }

            let count = 0;
            if (metricKind === 'status') {
                count = statusCounts[metricValue] || 0;
            } else if (metricKind === 'nap') {
                count = napCounts[metricValue] || 0;
            }

            countEl.textContent = String(count);
        });
    };

    const updateMetricActiveState = () => {
        if (!citationMetricButtons.length) {
            return;
        }

        citationMetricButtons.forEach((button) => {
            const metricKind = String(button.getAttribute('data-metric-kind') || '').trim();
            const metricValue = String(button.getAttribute('data-metric-value') || '').trim();
            const isActive = (metricKind === 'status' && activeStatusMetric === metricValue)
                || (metricKind === 'nap' && activeNapMetric === metricValue);

            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('ring-2', isActive);
            button.classList.toggle('ring-brand-400', isActive);
        });
    };

    const applyCitationFilters = () => {
        const rows = getCitationRows();
        if (!rows.length) {
            return;
        }

        const selectedType = citationsTypeFilter ? citationsTypeFilter.value.trim().toLowerCase() : '';
        const selectedStatus = activeStatusMetric.toLowerCase();
        const selectedNapStatus = activeNapMetric.toLowerCase();
        const query = citationsSearch ? citationsSearch.value.trim().toLowerCase() : '';
        let visibleCount = 0;

        rows.forEach((row) => {
            const type = (row.getAttribute('data-citation-type') || '').toLowerCase();
            const status = (row.getAttribute('data-status') || '').toLowerCase();
            const napStatus = (row.getAttribute('data-nap-status') || '').toLowerCase();
            const searchable = [
                row.getAttribute('data-directory') || '',
                row.getAttribute('data-citation-type') || '',
                row.getAttribute('data-status') || '',
                row.getAttribute('data-nap-status') || '',
                row.getAttribute('data-priority-level') || '',
                row.getAttribute('data-url') || '',
                row.getAttribute('data-proof-url') || '',
                row.getAttribute('data-assignee') || '',
                row.getAttribute('data-notes') || ''
            ].join(' ').toLowerCase();

            const typeMatch = selectedType === '' || type === selectedType;
            const statusMatch = selectedStatus === '' || status === selectedStatus;
            const napStatusMatch = selectedNapStatus === '' || napStatus === selectedNapStatus;
            const searchMatch = query === '' || searchable.includes(query);
            const show = typeMatch && statusMatch && napStatusMatch && searchMatch;

            row.style.display = show ? '' : 'none';
            if (!show) {
                const rowCheckbox = row.querySelector('.citation-bulk-checkbox');
                if (rowCheckbox instanceof HTMLInputElement) {
                    rowCheckbox.checked = false;
                }
            }
            if (show) {
                visibleCount += 1;
            }
        });

        if (noResultsRow) {
            noResultsRow.classList.toggle('hidden', visibleCount !== 0);
        }

        updateSelectAllState();
        updateMetricActiveState();
        updateCitationMetrics();
    };

    const csvEscape = (value) => {
        const str = String(value ?? '');
        return '"' + str.replace(/"/g, '""') + '"';
    };

    const exportFilteredCitations = () => {
        const rows = getCitationRows().filter((row) => row.style.display !== 'none');
        if (!rows.length) {
            return;
        }

        const headers = ['Directory', 'Citation Type', 'Status', 'NAP Status', 'Priority', 'Citation URL', 'Proof URL', 'Assignee', 'Updated', 'Notes'];
        const lines = [headers.map(csvEscape).join(',')];

        rows.forEach((row) => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 5) {
                return;
            }

            const directory = (row.getAttribute('data-directory') || '').trim();
            const citationType = (row.getAttribute('data-citation-type') || '').trim();
            const status = (row.getAttribute('data-status') || '').trim();
            const napStatus = (row.getAttribute('data-nap-status') || '').trim();
            const priority = (row.getAttribute('data-priority-level') || '').trim();
            const url = (row.getAttribute('data-url') || '').trim();
            const proofUrl = (row.getAttribute('data-proof-url') || '').trim();
            const assignee = (row.getAttribute('data-assignee') || '').trim();
            const updated = (row.getAttribute('data-updated') || '').trim();
            const notes = (row.getAttribute('data-notes') || '').trim();

            lines.push([directory, citationType, status, napStatus, priority, url, proofUrl, assignee, updated, notes].map(csvEscape).join(','));
        });

        const csvContent = lines.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const downloadUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const dateTag = new Date().toISOString().slice(0, 10);

        link.href = downloadUrl;
        link.download = `citations-export-${dateTag}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(downloadUrl);
    };

    if (citationsTypeFilter) {
        citationsTypeFilter.addEventListener('change', () => {
            updateCitationMetrics();
            applyCitationFilters();
        });
    }
    if (citationsSearch) {
        citationsSearch.addEventListener('input', applyCitationFilters);
    }
    if (citationsExport) {
        citationsExport.addEventListener('click', exportFilteredCitations);
    }
    if (citationsSortBtn) {
        citationsSortBtn.addEventListener('click', () => {
            activeSortModeIndex = (activeSortModeIndex + 1) % sortModes.length;
            const activeSortMode = sortModes[activeSortModeIndex] || sortModes[0];
            citationsSortBtn.textContent = activeSortMode.label;
            applyCitationSort();
            applyCitationFilters();
        });
    }

    citationMetricButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const metricKind = String(button.getAttribute('data-metric-kind') || '').trim();
            const metricValue = String(button.getAttribute('data-metric-value') || '').trim();

            if (metricKind === 'status') {
                activeStatusMetric = activeStatusMetric === metricValue ? '' : metricValue;
                activeNapMetric = '';
            } else if (metricKind === 'nap') {
                activeNapMetric = activeNapMetric === metricValue ? '' : metricValue;
                activeStatusMetric = '';
            }

            applyCitationFilters();
        });
    });

    if (citationsSelectAll) {
        citationsSelectAll.addEventListener('change', () => {
            const shouldCheck = citationsSelectAll.checked;
            getBulkCheckboxes().forEach((checkbox) => {
                const row = checkbox.closest('tr');
                if (row && row.style.display !== 'none') {
                    checkbox.checked = shouldCheck;
                }
            });
            updateSelectAllState();
        });
    }

    getBulkCheckboxes().forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            updateSelectAllState();
            syncBulkDeleteFields();
        });
    });

    if (bulkAssignForm) {
        bulkAssignForm.addEventListener('submit', () => {
            syncBulkDeleteFields();
        });
    }

    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', () => {
            syncBulkDeleteFields();
        });
    }

    submitListingTriggers.forEach((trigger) => {
        trigger.addEventListener('click', async (event) => {
            const citationId = Number(trigger.getAttribute('data-citation-id') || 0);
            const submissionUrl = String(trigger.getAttribute('data-submission-url') || trigger.getAttribute('href') || '').trim();
            const row = trigger.closest('tr');
            const currentStatus = row ? String(row.getAttribute('data-status') || '').trim() : '';

            if (!submissionUrl) {
                return;
            }

            // Already progressed/finalized statuses can open immediately.
            if (currentStatus === 'in_progress' || currentStatus === 'pending_submission' || currentStatus === 'live') {
                return;
            }

            event.preventDefault();

            try {
                const body = new URLSearchParams();
                body.set('action', 'mark_citation_in_progress');
                body.set('citation_id', String(citationId));

                const response = await fetch(`${locationManagerBaseUrl}/location_manager.php?business_id=<?php echo e((string)$businessId); ?>`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });

                const payload = await response.json();
                if (payload && payload.ok === true) {
                    if (row) {
                        applyStatusToRow(row, String(payload.status || 'in_progress'));
                    }
                    updateCitationMetrics();
                    applyCitationFilters();
                }
            } catch (_) {
                // If status update fails, still allow the user to continue submission.
            }

            window.open(submissionUrl, '_blank', 'noopener');
        });
    });

    getCitationRows().forEach((row) => {
        const status = String(row.getAttribute('data-status') || 'not_started').trim();
        applyRowStatusClass(row, status);
    });

    applyCitationSort();
    updateCitationMetrics();
    applyCitationFilters();
    syncBulkDeleteFields();
</script>
<?php render_footer(); ?>
