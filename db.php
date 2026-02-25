<?php

function vibemap_load_local_db_config()
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $config = [];
    $configPath = __DIR__ . '/db-config.php';
    if (!is_file($configPath)) {
        return $config;
    }

    $loaded = require $configPath;
    if (!is_array($loaded)) {
        return $config;
    }

    foreach ($loaded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if (is_string($value) || is_numeric($value)) {
            $config[$key] = trim((string) $value);
        }
    }

    return $config;
}

function vibemap_env_string($key, $default = '')
{
    $values = [
        getenv($key),
        $_ENV[$key] ?? null,
        $_SERVER[$key] ?? null,
    ];

    if (function_exists('apache_getenv')) {
        $apacheValue = apache_getenv($key, true);
        if ($apacheValue !== false) {
            $values[] = $apacheValue;
        }
    }

    foreach ($values as $value) {
        if ($value === false || $value === null) {
            continue;
        }
        $stringValue = trim((string) $value);
        if ($stringValue !== '') {
            return $stringValue;
        }
    }

    $localConfig = vibemap_load_local_db_config();
    if (isset($localConfig[$key])) {
        $stringValue = trim((string) $localConfig[$key]);
        if ($stringValue !== '') {
            return $stringValue;
        }
    }

    return $default;
}

$host = vibemap_env_string('VIBEMAP_DB_HOST', 'localhost');
$database = vibemap_env_string('VIBEMAP_DB_NAME', 'vibemap');
$username = vibemap_env_string('VIBEMAP_DB_USER', 'root');
$password = vibemap_env_string('VIBEMAP_DB_PASSWORD', '');
$port = (int) vibemap_env_string('VIBEMAP_DB_PORT', '3306');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if ($conn === false) {
    http_response_code(500);
    exit('Database connection failed. Check VIBEMAP_DB_* settings.');
}

$connected = @mysqli_real_connect($conn, $host, $username, $password, $database, $port);
if (!$connected) {
    http_response_code(500);
    exit('Database connection failed. Check VIBEMAP_DB_* settings.');
}

mysqli_set_charset($conn, 'utf8mb4');
