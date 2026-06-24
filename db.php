<?php
/**
 * db.php — PostgreSQL connection for PinPath (via PDO / pdo_pgsql).
 *
 * The connection string comes from the DATABASE_URL environment variable,
 * e.g. (Supabase session pooler):
 *   postgresql://postgres.<ref>:<password>@aws-0-<region>.pooler.supabase.com:5432/postgres
 *
 * Never hardcode credentials. For local dev, put DATABASE_URL in a .env file
 * next to this script (it is git-ignored); in production set it as a real
 * environment variable (Render → Environment).
 */

declare(strict_types=1);

/** Load KEY=VALUE pairs from a local .env file into the environment (once). */
function loadEnvFile(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $file = __DIR__ . '/.env';
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        $val = trim($val, "\"'"); // strip optional surrounding quotes
        // Real environment variables take precedence over the .env file.
        if (getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

/** Coerce a Postgres boolean (returned as 't'/'f' by pdo_pgsql) to PHP bool. */
function pgBool($v): bool
{
    return $v === true || $v === 't' || $v === 'true' || $v === '1' || $v === 1;
}

/**
 * Return a shared PDO connection. Throws RuntimeException if DATABASE_URL is
 * missing/invalid, or PDOException if the connection itself fails.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    loadEnvFile();

    $url = getenv('DATABASE_URL');
    if ($url === false || trim($url) === '') {
        $url = $_ENV['DATABASE_URL'] ?? '';
    }
    if (trim((string) $url) === '') {
        throw new RuntimeException('DATABASE_URL is not set.');
    }

    $p = parse_url((string) $url);
    if ($p === false || empty($p['host'])) {
        throw new RuntimeException('DATABASE_URL is malformed.');
    }

    $host   = $p['host'];
    $port   = $p['port'] ?? 5432;
    $dbname = isset($p['path']) ? ltrim($p['path'], '/') : 'postgres';
    $user   = isset($p['user']) ? urldecode($p['user']) : '';
    $pass   = isset($p['pass']) ? urldecode($p['pass']) : '';

    // Supabase requires SSL; allow override via ?sslmode= in the URL.
    $sslmode = 'require';
    if (isset($p['query'])) {
        parse_str($p['query'], $q);
        if (!empty($q['sslmode'])) {
            $sslmode = $q['sslmode'];
        }
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $host,
        (int) $port,
        $dbname,
        $sslmode
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
