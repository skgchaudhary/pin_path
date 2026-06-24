<?php
/**
 * save-itinerary.php — POST endpoint that saves a location into an itinerary.
 *
 * Inputs (POST): itinerary_id, itinerary_name, location_name, lat, lng
 *   - No itinerary_id -> create itinerary from the name's slug
 *   - Existing id     -> append the location
 *
 * Always responds with JSON. Storage is PostgreSQL (see store.php).
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

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
    if ($itineraryName === '') {
        respond(['success' => false, 'error' => 'Itinerary name is required.'], 422);
    }
    $itineraryId = slugify($itineraryName);
    if ($itineraryId === '') {
        respond(['success' => false, 'error' => 'Itinerary name produced an invalid slug.'], 422);
    }
}

// --- Persist ----------------------------------------------------------------
try {
    ensureItinerary($itineraryId, $itineraryName !== '' ? $itineraryName : $itineraryId);
    $itinerary = addLocation($itineraryId, $locationName, $lat, $lng);
} catch (Throwable $e) {
    respond(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}

respond([
    'success'        => true,
    'itinerary_id'   => $itinerary['id'],
    'itinerary'      => $itinerary,
    'location_count' => count($itinerary['locations'] ?? []),
]);
