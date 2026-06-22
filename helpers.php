<?php
/**
 * helpers.php — Shared utilities for PinPath.
 *
 * Pure file-based persistence: every itinerary lives in data/<slug>.json.
 * No database, no Composer, no frameworks.
 */

declare(strict_types=1);

// Absolute path to the data directory, regardless of which script includes this.
define('DATA_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'data');

/**
 * Convert arbitrary text into a URL-safe slug.
 * "Pune Food Trip" -> "pune-food-trip"
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    // Replace anything that isn't a-z or 0-9 with a hyphen.
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    // Collapse + trim stray hyphens.
    return trim($text, '-');
}

/**
 * Validate a slug so it can only ever map to data/<slug>.json.
 * Strips any path-traversal attempt; returns '' if nothing safe remains.
 */
function sanitizeFilename(string $slug): string
{
    // Keep only the basename (kills ../, slashes, backslashes).
    $slug = basename($slug);
    // Whitelist: lowercase letters, digits, hyphen.
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
    return trim($slug, '-');
}

/**
 * Resolve a slug to its absolute JSON file path, safely.
 * Returns null if the slug is invalid.
 */
function itineraryPath(string $slug): ?string
{
    $safe = sanitizeFilename($slug);
    if ($safe === '') {
        return null;
    }
    return DATA_DIR . DIRECTORY_SEPARATOR . $safe . '.json';
}

/**
 * Load and decode a JSON file into an array. Empty array on any failure.
 */
function loadJson(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Encode and write data as pretty, unescaped-unicode JSON (atomic via LOCK_EX).
 */
function saveJson(string $file, array $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

/**
 * Generate the next sequential location id, e.g. "loc_001".
 * Looks at existing locations to avoid collisions.
 */
function generateLocationId(array $locations): string
{
    $max = 0;
    foreach ($locations as $loc) {
        if (isset($loc['id']) && preg_match('/(\d+)$/', (string) $loc['id'], $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    return 'loc_' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

/**
 * Escape a value for safe HTML output (XSS protection).
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
