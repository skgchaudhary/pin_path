<?php
/**
 * index.php — PinPath itinerary builder.
 *
 * Supports:
 *   index.php                         -> empty builder
 *   index.php?itinerary=pune-food-trip -> loads that itinerary's saved pins
 *
 * The page renders the shell; all map/search/save logic lives in app.js.
 * The current itinerary (if any) is handed to JS as a JSON blob.
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

$slug      = (string) ($_GET['itinerary'] ?? '');
$path      = itineraryPath($slug);
$itinerary = ($path !== null && is_file($path)) ? loadJson($path) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PinPath — <?= $itinerary ? e($itinerary['name'] ?? 'Itinerary') : 'Build your itinerary' ?></title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- Top navigation -->
<nav class="topnav">
    <a class="logo" href="index.php">📍 PinPath</a>
    <div class="nav-links">
        <a href="index.php" class="active">Map</a>
        <a href="itineraries.php">Itineraries</a>
    </div>
</nav>

<!-- Builder controls -->
<section class="controls" id="controls">
    <button type="button" id="controlsClose" class="controls-close" aria-label="Hide panel" title="Hide panel">✕</button>
    <div class="controls-inner">
        <?php if ($itinerary): ?>
            <div class="trip-banner">
                <span class="trip-label">Editing</span>
                <strong><?= e($itinerary['name'] ?? '') ?></strong>
                <span class="trip-count"><?= count($itinerary['locations'] ?? []) ?> stops</span>
            </div>
        <?php endif; ?>

        <div class="search-wrap">
            <input type="text" id="search" autocomplete="off"
                   placeholder="🔍 Search a place (e.g. Koregaon Park Pune)">
            <ul id="searchResults" class="search-results" hidden></ul>
        </div>

        <div class="fields">
            <label class="field field-grow">
                <span>Place Name</span>
                <input type="text" id="placeName" placeholder="Selected place name">
            </label>
            <label class="field">
                <span>Latitude</span>
                <input type="text" id="lat" readonly placeholder="—">
            </label>
            <label class="field">
                <span>Longitude</span>
                <input type="text" id="lng" readonly placeholder="—">
            </label>
            <button id="addBtn" class="btn btn-primary" disabled>Add To Itinerary</button>
        </div>

        <p id="status" class="status" role="status" aria-live="polite"></p>
    </div>
</section>

<!-- Map -->
<div id="map"></div>

<!-- Floating button to reopen the panel after it's been closed -->
<button type="button" id="controlsOpen" class="controls-open" hidden
        aria-label="Show panel" title="Show panel">📍 Add</button>

<!-- Footer -->
<footer class="site-footer">Developed By Suneet Chaudhary</footer>

<!-- Hand server state to the client -->
<script>
    window.PINPATH = {
        itineraryId: <?= json_encode($itinerary['id'] ?? null) ?>,
        itinerary:   <?= json_encode($itinerary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        defaultCenter: [22.9734, 78.6569],
        defaultZoom: 5
    };
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="assets/app.js"></script>
</body>
</html>
