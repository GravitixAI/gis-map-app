<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

// Local copy: config.local.php in this folder.
// On the server: private/gisgame.php one level above public_html.
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'u455127727_gisgame',
    'user' => 'u455127727_gislab',
    'pass' => '',
];
