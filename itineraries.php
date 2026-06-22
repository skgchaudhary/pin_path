<?php
/**
 * itineraries.php — List every saved itinerary as a card.
 *
 * Reads data/*.json, shows name, location count, created/updated dates,
 * and an Open button linking back to the builder.
 */

declare(strict_types=1);
require __DIR__ . '/helpers.php';

// Gather all itinerary files (data dir may not exist yet).
$files = is_dir(DATA_DIR) ? glob(DATA_DIR . DIRECTORY_SEPARATOR . '*.json') : [];
$files = $files ?: [];

$itineraries = [];
foreach ($files as $file) {
    $data = loadJson($file);
    if (!$data) {
        continue;
    }
    $itineraries[] = [
        'id'         => $data['id'] ?? sanitizeFilename(basename($file, '.json')),
        'name'       => $data['name'] ?? basename($file, '.json'),
        'count'      => count($data['locations'] ?? []),
        'created_at' => $data['created_at'] ?? '',
        'updated_at' => $data['updated_at'] ?? '',
    ];
}

// Most recently updated first.
usort($itineraries, static fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PinPath — Itineraries</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="topnav">
    <a class="logo" href="index.php">📍 PinPath</a>
    <div class="nav-links">
        <a href="index.php">Map</a>
        <a href="itineraries.php" class="active">Itineraries</a>
    </div>
</nav>

<main class="page">
    <header class="page-head">
        <h1>Your Itineraries</h1>
        <a href="index.php" class="btn btn-primary">+ New Itinerary</a>
    </header>

    <?php if (empty($itineraries)): ?>
        <div class="empty-state">
            <p>No itineraries yet.</p>
            <p class="muted">Open the map, search a place, and add your first stop.</p>
            <a href="index.php" class="btn btn-primary">Start planning</a>
        </div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($itineraries as $it): ?>
                <article class="card">
                    <h2><?= e($it['name']) ?></h2>
                    <p class="card-count"><?= e($it['count']) ?> location<?= $it['count'] === 1 ? '' : 's' ?></p>
                    <dl class="card-meta">
                        <div><dt>Created</dt><dd><?= e($it['created_at']) ?: '—' ?></dd></div>
                        <div><dt>Updated</dt><dd><?= e($it['updated_at']) ?: '—' ?></dd></div>
                    </dl>
                    <div class="card-actions">
                        <a class="btn btn-primary card-open"
                           href="index.php?itinerary=<?= urlencode($it['id']) ?>">Open</a>
                        <form action="delete-itinerary.php" method="POST"
                              onsubmit="return confirm('Delete &quot;<?= e($it['name']) ?>&quot; and all its locations? This cannot be undone.');">
                            <input type="hidden" name="itinerary_id" value="<?= e($it['id']) ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">Developed By Suneet Chaudhary</footer>

</body>
</html>
