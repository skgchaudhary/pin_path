<?php
/**
 * store.php — Relational data access for PinPath (PostgreSQL).
 *
 * Replaces the old JSON-file storage. Every function returns plain PHP
 * arrays shaped exactly like the previous JSON, so the endpoints and the
 * frontend keep working unchanged.
 *
 * Timestamps are stored as timestamptz but returned as "Y-m-d H:i:s" strings
 * (via to_char) to match the format the UI already renders.
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

// Reusable SELECT fragments that format timestamps like the legacy JSON.
const TS_FMT = "'YYYY-MM-DD HH24:MI:SS'";

/** Fetch one itinerary with its locations, or null if it doesn't exist. */
function getItinerary(string $slug): ?array
{
    $pdo = db();

    $st = $pdo->prepare(
        "select id, name,
                to_char(created_at, " . TS_FMT . ") as created_at,
                to_char(updated_at, " . TS_FMT . ") as updated_at
         from itineraries where id = ?"
    );
    $st->execute([$slug]);
    $it = $st->fetch();
    if (!$it) {
        return null;
    }

    $it['locations'] = getLocations($slug);
    return $it;
}

/** Return all locations for an itinerary, ordered by when they were added. */
function getLocations(string $slug): array
{
    $st = db()->prepare(
        "select id, name, lat, lng, visited, notes,
                to_char(added_at, " . TS_FMT . ") as added_at,
                to_char(updated_at, " . TS_FMT . ") as updated_at
         from locations
         where itinerary_id = ?
         order by added_at, id"
    );
    $st->execute([$slug]);

    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['lat']     = (float) $r['lat'];
        $r['lng']     = (float) $r['lng'];
        $r['visited'] = pgBool($r['visited']);
    }
    return $rows;
}

/** Fetch a single location (formatted) or null. */
function getLocation(string $slug, string $locId): ?array
{
    $st = db()->prepare(
        "select id, name, lat, lng, visited, notes,
                to_char(added_at, " . TS_FMT . ") as added_at,
                to_char(updated_at, " . TS_FMT . ") as updated_at
         from locations where itinerary_id = ? and id = ?"
    );
    $st->execute([$slug, $locId]);
    $l = $st->fetch();
    if (!$l) {
        return null;
    }
    $l['lat']     = (float) $l['lat'];
    $l['lng']     = (float) $l['lng'];
    $l['visited'] = pgBool($l['visited']);
    return $l;
}

/** List all itineraries (summary + location count), newest update first. */
function listItineraries(): array
{
    $sql =
        "select i.id, i.name,
                to_char(i.created_at, " . TS_FMT . ") as created_at,
                to_char(i.updated_at, " . TS_FMT . ") as updated_at,
                count(l.id) as count
         from itineraries i
         left join locations l on l.itinerary_id = i.id
         group by i.id, i.name, i.created_at, i.updated_at
         order by i.updated_at desc";

    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['count'] = (int) $r['count'];
    }
    return $rows;
}

/** Create an itinerary if it doesn't already exist (keeps existing name). */
function ensureItinerary(string $slug, string $name): void
{
    $st = db()->prepare(
        "insert into itineraries (id, name) values (?, ?)
         on conflict (id) do nothing"
    );
    $st->execute([$slug, $name]);
}

/**
 * Append a location to an itinerary and bump updated_at.
 * Returns the full itinerary afterwards.
 */
function addLocation(string $slug, string $name, float $lat, float $lng): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Existing ids -> next sequential "loc_00N".
        $st = $pdo->prepare("select id from locations where itinerary_id = ?");
        $st->execute([$slug]);
        $existing = array_map(static fn($r) => ['id' => $r['id']], $st->fetchAll());
        $locId = generateLocationId($existing);

        $ins = $pdo->prepare(
            "insert into locations (id, itinerary_id, name, lat, lng, visited, notes)
             values (?, ?, ?, ?, ?, false, '')"
        );
        $ins->execute([$locId, $slug, $name, $lat, $lng]);

        $pdo->prepare("update itineraries set updated_at = now() where id = ?")
            ->execute([$slug]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return getItinerary($slug);
}

/**
 * Update a location's visited/notes fields. Returns the updated location,
 * or null if the location doesn't exist.
 */
function updateLocationFields(string $slug, string $locId, array $fields): ?array
{
    $pdo = db();

    if (getLocation($slug, $locId) === null) {
        return null;
    }

    $sets = [];
    $args = [];

    // visited is a clean boolean we control — embed it literally (no user text).
    if (array_key_exists('visited', $fields)) {
        $sets[] = 'visited = ' . ($fields['visited'] ? 'true' : 'false');
    }
    if (array_key_exists('notes', $fields)) {
        $sets[] = 'notes = ?';
        $args[] = (string) $fields['notes'];
    }
    $sets[] = 'updated_at = now()';

    $args[] = $slug;
    $args[] = $locId;

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "update locations set " . implode(', ', $sets) .
            " where itinerary_id = ? and id = ?"
        )->execute($args);

        $pdo->prepare("update itineraries set updated_at = now() where id = ?")
            ->execute([$slug]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return getLocation($slug, $locId);
}

/** Delete a location. Returns false if it didn't exist. */
function deleteLocation(string $slug, string $locId): bool
{
    $pdo = db();

    if (getLocation($slug, $locId) === null) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("delete from locations where itinerary_id = ? and id = ?")
            ->execute([$slug, $locId]);
        $pdo->prepare("update itineraries set updated_at = now() where id = ?")
            ->execute([$slug]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return true;
}

/** Count locations in an itinerary. */
function countLocations(string $slug): int
{
    $st = db()->prepare("select count(*) as c from locations where itinerary_id = ?");
    $st->execute([$slug]);
    return (int) ($st->fetch()['c'] ?? 0);
}

/** Delete an entire itinerary (locations cascade). */
function deleteItinerary(string $slug): void
{
    db()->prepare("delete from itineraries where id = ?")->execute([$slug]);
}
