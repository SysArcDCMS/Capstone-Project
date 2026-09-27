<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;

/*
 * Reset the throwaway test schema once per PHPUnit process.
 *
 * Two problems make this necessary:
 *
 *   1. `migrate:fresh` drops tables, but the migrations also create Postgres
 *      ENUM types through raw `CREATE TYPE ... AS ENUM`. Nothing tracks those,
 *      so a second run dies with `type "user_role" already exists`.
 *   2. `RefreshDatabase` keeps a per-process static flag, so the wipe has to
 *      happen before the framework boots rather than inside a test.
 *
 * Tests therefore live in their own `testing` schema on the same database,
 * reached through the `pgsql_testing` connection. Application data in `public`
 * is never touched.
 *
 * Set DB_CONNECTION to anything else (or SKIP_TEST_SCHEMA_RESET=1) to bypass
 * this, e.g. when running a one-off script against a different database.
 */

if (getenv('SKIP_TEST_SCHEMA_RESET') === '1') {
    return;
}

if (getenv('DB_CONNECTION') !== 'pgsql_testing') {
    return;
}

$root = dirname(__DIR__);

Dotenv::createImmutable($root)->safeLoad();

/*
 * Dotenv writes to $_ENV and $_SERVER by default; it does not call putenv().
 * So getenv() alone comes back empty for anything defined in .env.
 */
$env = static function (string $key, ?string $default = null): ?string {
    foreach ([$_ENV, $_SERVER] as $bag) {
        if (isset($bag[$key]) && $bag[$key] !== '') {
            return (string) $bag[$key];
        }
    }

    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
};

$host     = $env('DB_HOST', '127.0.0.1');
$port     = $env('DB_PORT', '5432');
$database = $env('DB_DATABASE', 'laravel');
$username = $env('DB_USERNAME', 'root');
$password = $env('DB_PASSWORD', '');
$schema   = $env('DB_TEST_SCHEMA', 'testing');

// The name is interpolated into DDL, so refuse anything unexpected.
if (! preg_match('/^[a-z_][a-z0-9_]*$/i', $schema)) {
    fwrite(STDERR, "Refusing to reset schema with an unsafe name: {$schema}\n");
    exit(1);
}

if (strcasecmp($schema, 'public') === 0) {
    fwrite(STDERR, "Refusing to reset the public schema.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$database}",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $pdo->exec("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
    $pdo->exec("CREATE SCHEMA \"{$schema}\"");
} catch (PDOException $e) {
    fwrite(STDERR, "Could not reset test schema '{$schema}': {$e->getMessage()}\n");
    exit(1);
}
