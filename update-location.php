<?php
/**
 * update-location.php — Update or delete a single location inside an itinerary.
 *
 * Accepts a POST JSON body:
 *
 *   Update:
 *     { "itinerary_id":"delhijuly2026", "location_id":"loc_001",
 *       "visited":true, "notes":"Visited in evening", "action":"update" }
 *
 *   Delete:
 *     { "itinerary_id":"delhijuly2026", "location_id":"loc_001", "action":"delete" }
 *
 * Responds with JSON. Mutating a location also bumps the location's
 * updated_at and the itinerary's root updated_at.
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

/** Emit a JSON response and stop. */
function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'error' => 'Method not allowed'], 405);
}

// --- Parse the JSON body (fall back to form-encoded just in case) ----------
$raw = file_get_contents('php://input');
$in  = json_decode((string) $raw, true);
if (!is_array($in)) {
    $in = $_POST; // tolerate application/x-www-form-urlencoded
}

$itineraryId = sanitizeFilename(trim((string) ($in['itinerary_id'] ?? '')));
$locationId  = trim((string) ($in['location_id'] ?? ''));
$action      = strtolower(trim((string) ($in['action'] ?? 'update')));

// --- Validate ids -----------------------------------------------------------
if ($itineraryId === '') {
    respond(['success' => false, 'error' => 'Invalid itinerary id.'], 422);
}
if ($locationId === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $locationId)) {
    respond(['success' => false, 'error' => 'Invalid location id.'], 422);
}

$path = itineraryPath($itineraryId); // null if slug is unsafe (../, etc.)
if ($path === null || !is_file($path)) {
    respond(['success' => false, 'error' => 'Itinerary not found.'], 404);
}

$itinerary = loadJson($path);
$locations = $itinerary['locations'] ?? [];

// --- Find the target location ----------------------------------------------
$index = null;
foreach ($locations as $i => $loc) {
    if (($loc['id'] ?? null) === $locationId) {
        $index = $i;
        break;
    }
}
if ($index === null) {
    respond(['success' => false, 'error' => 'Location not found.'], 404);
}

$now = date('Y-m-d H:i:s');

if ($action === 'delete') {
    // --- Remove the location ------------------------------------------------
    array_splice($locations, $index, 1);
    $itinerary['locations']  = $locations;
    $itinerary['updated_at'] = $now;

    if (!saveJson($path, $itinerary)) {
        respond(['success' => false, 'error' => 'Failed to save itinerary.'], 500);
    }
    respond([
        'success'        => true,
        'action'         => 'delete',
        'location_id'    => $locationId,
        'location_count' => count($locations),
        'updated_at'     => $now,
    ]);
}

// --- Default: update visited / notes ---------------------------------------
$loc = $locations[$index];

// Apply defaults for legacy records missing the new fields.
$loc['visited']    = $loc['visited']    ?? false;
$loc['notes']      = $loc['notes']      ?? '';
$loc['updated_at'] = $loc['updated_at'] ?? ($loc['added_at'] ?? $now);

// Only overwrite fields that were actually provided.
if (array_key_exists('visited', $in)) {
    $loc['visited'] = filter_var($in['visited'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
}
if (array_key_exists('notes', $in)) {
    $loc['notes'] = trim((string) $in['notes']);
}

$loc['updated_at']       = $now;
$locations[$index]       = $loc;
$itinerary['locations']  = $locations;
$itinerary['updated_at'] = $now;

if (!saveJson($path, $itinerary)) {
    respond(['success' => false, 'error' => 'Failed to save itinerary.'], 500);
}

respond([
    'success'     => true,
    'action'      => 'update',
    'location'    => $loc,
    'updated_at'  => $now,
]);
