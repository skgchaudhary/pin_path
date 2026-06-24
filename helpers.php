<?php
/**
 * helpers.php — Shared utilities + bootstrap for PinPath.
 *
 * Storage now lives in PostgreSQL (see db.php / store.php). This file keeps
 * the small pure helpers and pulls in the data-access layer so endpoints can
 * just `require helpers.php`.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';

/**
 * Convert arbitrary text into a URL-safe slug.
 * "Pune Food Trip" -> "pune-food-trip"
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

/**
 * Validate a slug so it only contains safe characters (a-z, 0-9, hyphen).
 * Also strips any path-traversal attempt. Returns '' if nothing safe remains.
 */
function sanitizeFilename(string $slug): string
{
    $slug = basename($slug);                                 // kill ../, slashes
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
    return trim($slug, '-');
}

/**
 * Generate the next sequential location id, e.g. "loc_001".
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
