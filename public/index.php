<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
// ── XAMPP cross-vhost isolation ───────────────────────────────────────────────
// Apache reuses PHP worker processes across vhosts. Force-reload critical env
// values from this module's own .env before Laravel bootstraps, preventing
// contamination from other vhosts (wrong APP_KEY, SESSION_DRIVER, etc.).
(static function (): void {
    $envFile = __DIR__ . '/../.env';
    if (! is_readable($envFile)) { return; }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $pin   = ['APP_KEY', 'APP_ENV', 'SESSION_DRIVER', 'SESSION_COOKIE',
              'SESSION_DOMAIN', 'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE',
              'BROADCAST_CONNECTION', 'DB_CONNECTION', 'DB_DATABASE'];
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') { continue; }
        $eq = strpos($line, '=');
        if ($eq === false) { continue; }
        $key = trim(substr($line, 0, $eq));
        if (! in_array($key, $pin, true)) { continue; }
        $val = trim(substr($line, $eq + 1));
        if (strlen($val) >= 2 && $val[0] === '"'  && $val[-1] === '"')  { $val = substr($val, 1, -1); }
        if (strlen($val) >= 2 && $val[0] === "'"  && $val[-1] === "'")  { $val = substr($val, 1, -1); }
        // Do NOT call putenv() — it writes to the process-level environment
        // which persists across all threads in mod_php + mpm_winnt, causing
        // cross-vhost contamination. $_SERVER is per-request (thread-local).
        $_SERVER[$key] = $val;
    }
    
})();


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
