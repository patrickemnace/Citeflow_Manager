<?php

declare(strict_types=1);

// Usage:
//   php deploy/update_base_url.php https://example.com
//   php deploy/update_base_url.php /
// If no argument is provided, the script prompts for the value.

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

function prompt(string $message): string
{
    fwrite(STDOUT, $message);
    return trim((string)fgets(STDIN));
}

function normalize_base_url(string $value): string
{
    $value = trim($value);

    if ($value === '' || $value === '/' || $value === '""' || $value === "''") {
        return '';
    }

    return rtrim($value, '/');
}

$configFile = __DIR__ . '/../app/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Config file not found: {$configFile}\n");
    exit(1);
}

$currentConfig = file_get_contents($configFile);
if ($currentConfig === false) {
    fwrite(STDERR, "Unable to read config file.\n");
    exit(1);
}

if (!preg_match("/'base_url'\s*=>\s*'([^']*)'/", $currentConfig, $matches)) {
    fwrite(STDERR, "Unable to locate base_url in config.php.\n");
    exit(1);
}

$currentBase = $matches[1];
fwrite(STDOUT, "Current base_url: {$currentBase}\n");

$inputBase = $argv[1] ?? prompt('Enter new base_url (use / for site root): ');
$newBase = normalize_base_url($inputBase);

$updatedConfig = preg_replace(
    "/'base_url'\s*=>\s*'[^']*'/",
    "'base_url' => '" . str_replace("'", "\\'", $newBase) . "'",
    $currentConfig,
    1
);

if (!is_string($updatedConfig) || $updatedConfig === $currentConfig) {
    fwrite(STDERR, "base_url was not updated.\n");
    exit(1);
}

if (file_put_contents($configFile, $updatedConfig) === false) {
    fwrite(STDERR, "Failed to update config.php.\n");
    exit(1);
}

fwrite(STDOUT, "base_url updated successfully in app/config.php.\n");
