<?php
/**
 * get-itinerary.php — Return one itinerary as JSON.
 *
 * Usage:  get-itinerary.php?itinerary=pune-food-trip
 * Output: application/json
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$slug = sanitizeFilename((string) ($_GET['itinerary'] ?? ''));

if ($slug === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing itinerary id.'], JSON_PRETTY_PRINT);
    exit;
}

try {
    $itinerary = getItinerary($slug);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], JSON_PRETTY_PRINT);
    exit;
}

if ($itinerary === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Itinerary not found.'], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode($itinerary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
