<?php
/**
 * delete-itinerary.php — Delete an entire itinerary file.
 *
 * POST: itinerary_id=<slug>
 *
 * Validates the slug as a safe filename (blocks ../ traversal), deletes
 * data/<slug>.json if it exists, then redirects back to the list.
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: itineraries.php');
    exit;
}

$slug = sanitizeFilename(trim((string) ($_POST['itinerary_id'] ?? '')));
$path = $slug !== '' ? itineraryPath($slug) : null;

// Only delete a real file inside the data directory.
if ($path !== null && is_file($path)) {
    unlink($path);
}

header('Location: itineraries.php');
exit;
