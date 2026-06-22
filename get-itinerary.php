<?php
/**
 * get-itinerary.php — Return the JSON content of one itinerary.
 *
 * Usage:  get-itinerary.php?itinerary=pune-food-trip
 * Output: application/json
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$slug = (string) ($_GET['itinerary'] ?? '');
$path = itineraryPath($slug);

if ($path === null || !is_file($path)) {
    http_response_code(404);
    echo json_encode(
        ['success' => false, 'error' => 'Itinerary not found.'],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
    exit;
}

// Stream the stored JSON straight back (already pretty-printed on save).
echo json_encode(
    loadJson($path),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
