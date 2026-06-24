<?php
/**
 * delete-itinerary.php — Delete an entire itinerary (and its locations).
 *
 * POST: itinerary_id=<slug>
 * Redirects back to the list afterwards.
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: itineraries.php');
    exit;
}

$slug = sanitizeFilename(trim((string) ($_POST['itinerary_id'] ?? '')));

if ($slug !== '') {
    try {
        deleteItinerary($slug); // locations cascade-delete via FK
    } catch (Throwable $e) {
        // Best-effort: fall through to the list even if the delete failed.
    }
}

header('Location: itineraries.php');
exit;
