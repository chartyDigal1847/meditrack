<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $portalUser = json_decode((string) $request->header(config('meditrack.user_header'), '{}'), true) ?: [];
            $deorisId = $portalUser['id']
                ?? $request->header('X-DEORIS-User-Id')
                ?? $request->header('X-Portal-User-Id');

            return Limit::perMinute(120)->by($deorisId ?: $request->ip());
        });
    }

    private function repinEnvFromFile(): void
    {
        $envFile = base_path('.env');
        if (! is_readable($envFile)) { return; }
        $pin = ['APP_KEY', 'APP_ENV', 'SESSION_DRIVER', 'SESSION_COOKIE',
                'SESSION_DOMAIN', 'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE',
                'BROADCAST_CONNECTION', 'DB_CONNECTION', 'DB_DATABASE'];
        $map = [
            'APP_KEY'               => 'app.key',
            'APP_ENV'               => 'app.env',
            'SESSION_DRIVER'        => 'session.driver',
            'SESSION_COOKIE'        => 'session.cookie',
            'SESSION_SAME_SITE'     => 'session.same_site',
            'SESSION_SECURE_COOKIE' => 'session.secure',
            'BROADCAST_CONNECTION'  => 'broadcasting.default',
            'DB_DATABASE'           => 'database.connections.mysql.database',
        ];
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#') { continue; }
            $eq = strpos($line, '=');
            if ($eq === false) { continue; }
            $key = trim(substr($line, 0, $eq));
            if (! in_array($key, $pin, true)) { continue; }
            $val = trim(substr($line, $eq + 1));
            if (strlen($val) >= 2 && $val[0] === '"' && $val[-1] === '"') { $val = substr($val, 1, -1); }
            elseif (strlen($val) >= 2 && $val[0] === "'" && $val[-1] === "'") { $val = substr($val, 1, -1); }
            $_SERVER[$key] = $val;
            if (isset($map[$key])) { config([$map[$key] => $val]); }
        }
    }
}
