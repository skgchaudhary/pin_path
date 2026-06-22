<?php
/**
 * save-itinerary.php — POST endpoint that saves a location into an itinerary.
 *
 * Inputs (POST):
 *   itinerary_id    optional slug of an existing itinerary
 *   itinerary_name  required when creating a new itinerary
 *   location_name   required
 *   lat, lng        required, numeric
 *
 * Behavior:
 *   - No itinerary_id  -> create data/<slug-of-name>.json
 *   - Existing id      -> append the location, update updated_at
 *
 * Always responds with JSON.
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

// --- Gather + trim input ----------------------------------------------------
$itineraryId   = sanitizeFilename(trim((string) ($_POST['itinerary_id'] ?? '')));
$itineraryName = trim((string) ($_POST['itinerary_name'] ?? ''));
$locationName  = trim((string) ($_POST['location_name'] ?? ''));
$latRaw        = trim((string) ($_POST['lat'] ?? ''));
$lngRaw        = trim((string) ($_POST['lng'] ?? ''));

// --- Validation -------------------------------------------------------------
if ($locationName === '') {
    respond(['success' => false, 'error' => 'Location name is required.'], 422);
}
if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
    respond(['success' => false, 'error' => 'Valid coordinates are required.'], 422);
}

$lat = (float) $latRaw;
$lng = (float) $lngRaw;
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    respond(['success' => false, 'error' => 'Coordinates out of range.'], 422);
}

// --- Resolve target itinerary ----------------------------------------------
$isNew = ($itineraryId === '');

if ($isNew) {
    // Creating a new itinerary: a name is required to derive the slug.
    if ($itineraryName === '') {
        respond(['success' => false, 'error' => 'Itinerary name is required.'], 422);
    }
    $itineraryId = slugify($itineraryName);
    if ($itineraryId === '') {
        respond(['success' => false, 'error' => 'Itinerary name produced an invalid slug.'], 422);
    }
}

$path = itineraryPath($itineraryId);
if ($path === null) {
    respond(['success' => false, 'error' => 'Invalid itinerary id.'], 422);
}

$now = date('Y-m-d H:i:s');

// --- Load existing or scaffold a new itinerary ------------------------------
if (is_file($path)) {
    $itinerary = loadJson($path);
    // Make sure required keys exist even if the file was hand-edited.
    $itinerary['id']         = $itinerary['id']         ?? $itineraryId;
    $itinerary['name']       = $itinerary['name']       ?? ($itineraryName ?: $itineraryId);
    $itinerary['created_at'] = $itinerary['created_at'] ?? $now;
    $itinerary['locations']  = $itinerary['locations']  ?? [];
} else {
    $itinerary = [
        'id'         => $itineraryId,
        'name'       => $itineraryName !== '' ? $itineraryName : $itineraryId,
        'created_at' => $now,
        'updated_at' => $now,
        'locations'  => [],
    ];
}

// --- Append the new location ------------------------------------------------
$itinerary['locations'][] = [
    'id'         => generateLocationId($itinerary['locations']),
    'name'       => $locationName,
    'lat'        => $lat,
    'lng'        => $lng,
    'added_at'   => $now,
    'visited'    => false,
    'notes'      => '',
    'updated_at' => $now,
];
$itinerary['updated_at'] = $now;

if (!saveJson($path, $itinerary)) {
    respond(['success' => false, 'error' => 'Failed to write itinerary file.'], 500);
}

respond([
    'success'       => true,
    'itinerary_id'  => $itinerary['id'],
    'itinerary'     => $itinerary,
    'location_count' => count($itinerary['locations']),
]);
