<?php

declare(strict_types=1);

load_environment_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; img-src \'self\' data:; frame-ancestors \'self\'; base-uri \'self\'; form-action \'self\'');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function load_environment_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            continue;
        }

        $name = $matches[1];
        if (getenv($name) !== false) {
            continue;
        }

        $value = trim($matches[2]);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
    }
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/http.php';
require_once dirname(__DIR__) . '/src/routes/auth.php';
require_once dirname(__DIR__) . '/src/routes/catalog.php';
require_once dirname(__DIR__) . '/src/routes/dashboard.php';

function app_config(): array
{
    static $config;
    return $config ??= require dirname(__DIR__) . '/config/config.php';
}

function legacy_e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function legacy_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function clear_login_attempts(): void
{
    unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);
}

function legacy_redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function consume_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function database(): PDO
{
    $config = app_config()['database'];
    return Database::connection($config);
}

