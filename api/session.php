<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

function gis_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('GISMAP');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (empty($_SESSION['gis_token']) || !is_string($_SESSION['gis_token'])) {
        $_SESSION['gis_token'] = bin2hex(random_bytes(16));
    }
}

function gis_token(): string
{
    gis_session();
    return $_SESSION['gis_token'];
}

function gis_token_ok(): bool
{
    gis_session();
    $sent = $_SERVER['HTTP_X_GIS_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['gis_token'], $sent);
}
