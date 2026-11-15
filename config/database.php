<?php

$envFile = dirname(__DIR__) . '/.env';

// Load local environment variables.
if (is_file($envFile)) {

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {

        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (
                ($value[0] === '"' && substr($value, -1) === '"') ||
                ($value[0] === "'" && substr($value, -1) === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

// Prefer Supabase credentials, then standard DB credentials.
$host = getenv('SUPABASE_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';

$port = getenv('SUPABASE_DB_PORT') ?: getenv('DB_PORT') ?: '5432';

$dbname = getenv('SUPABASE_DB_NAME') ?: getenv('DB_NAME') ?: 'campus_hostel';

$username = getenv('SUPABASE_DB_USER') ?: getenv('DB_USER') ?: 'postgres';

$password = getenv('SUPABASE_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';

$sslmode = getenv('SUPABASE_DB_SSLMODE')
    ?: getenv('DB_SSLMODE')
    ?: (getenv('SUPABASE_DB_HOST') ? 'require' : '');

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

if ($sslmode !== '') {
    $dsn .= ";sslmode=$sslmode";
}

try {

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {

    error_log('Database connection failed: ' . $e->getMessage());

    die('Unable to connect to the database. Please check your database configuration.');
}
