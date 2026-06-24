<?php
/**
 * update-location.php — Update or delete a single location.
 *
 * POST JSON body:
 *   Update: { "itinerary_id":"...", "location_id":"loc_001",
 *             "visited":true, "notes":"...", "action":"update" }
 *   Delete: { "itinerary_id":"...", "location_id":"loc_001", "action":"delete" }
 *
 * Responds with JSON. Storage is PostgreSQL (see store.php).
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

// --- Parse JSON body (tolerate form-encoded) -------------------------------
$raw = file_get_contents('php://input');
$in  = json_decode((string) $raw, true);
if (!is_array($in)) {
    $in = $_POST;
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

try {
    if ($action === 'delete') {
        // --- Remove the location --------------------------------------------
        if (!deleteLocation($itineraryId, $locationId)) {
            respond(['success' => false, 'error' => 'Location not found.'], 404);
        }
        respond([
            'success'        => true,
            'action'         => 'delete',
            'location_id'    => $locationId,
            'location_count' => countLocations($itineraryId),
        ]);
    }

    // --- Default: update visited / notes -----------------------------------
    $fields = [];
    if (array_key_exists('visited', $in)) {
        $fields['visited'] = filter_var($in['visited'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
    if (array_key_exists('notes', $in)) {
        $fields['notes'] = trim((string) $in['notes']);
    }

    $location = updateLocationFields($itineraryId, $locationId, $fields);
    if ($location === null) {
        respond(['success' => false, 'error' => 'Location not found.'], 404);
    }

    respond([
        'success'  => true,
        'action'   => 'update',
        'location' => $location,
    ]);
} catch (Throwable $e) {
    respond(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
