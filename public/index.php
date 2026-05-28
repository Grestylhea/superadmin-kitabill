<?php
// DEBUG LOGGING
if (strpos($_SERVER['REQUEST_URI'] ?? '', 'import-mikrotik') !== false) {
    file_put_contents('/var/isp-managerjs/www/storage/logs/laravel.log', 
        "[" . date('Y-m-d H:i:s') . "] local.INFO: ???? PUBLIC INDEX HIT: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown URI') . " Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Unknown') . "\n", 
        FILE_APPEND
    );
}

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
