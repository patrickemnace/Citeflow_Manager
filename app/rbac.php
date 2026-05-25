<?php

declare(strict_types=1);

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function is_employee(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'employee';
}

function role_label(?array $user): string
{
    if ($user === null) {
        return 'guest';
    }

    if ($user['role'] === 'admin') {
        return 'admin';
    }

    return 'employee';
}

function default_employee_permissions(): array
{
    return ['dashboard', 'businesses', 'location_manager', 'directories', 'notifications'];
}

function user_permissions(?array $user): array
{
    if ($user === null) {
        return [];
    }

    if (($user['role'] ?? '') === 'admin') {
        return ['*'];
    }

    $raw = (string)($user['permissions'] ?? '');
    if ($raw === '') {
        return default_employee_permissions();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return default_employee_permissions();
    }

    $permissions = array_values(array_filter(array_map('strval', $decoded)));
    if (!in_array('notifications', $permissions, true)) {
        $permissions[] = 'notifications';
    }

    return $permissions;
}

function has_permission(string $feature): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }

    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    return in_array($feature, user_permissions($user), true);
}

function require_permission(string $feature): void
{
    require_login();
    if (!has_permission($feature)) {
        set_flash('err', 'You are not authorized to access that page.');
        redirect('/dashboard.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        set_flash('err', 'Admin access required.');
        redirect('/dashboard.php');
    }
}
