<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

try {
    // Ensure the required temp directories exist for Laravel
    $tmpDirs = [
        '/tmp/framework/cache/data',
        '/tmp/framework/sessions',
        '/tmp/framework/testing',
        '/tmp/framework/views',
        '/tmp/logs',
        '/tmp/app/public',
        '/tmp/app/temp_docs'
    ];

    foreach ($tmpDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    $_SERVER['VERCEL'] = '1';
    $_ENV['VERCEL'] = '1';

    putenv('APP_CONFIG_CACHE=/tmp/config.php');
    putenv('APP_EVENTS_CACHE=/tmp/events.php');
    putenv('APP_PACKAGES_CACHE=/tmp/packages.php');
    putenv('APP_ROUTES_CACHE=/tmp/routes.php');
    putenv('APP_SERVICES_CACHE=/tmp/services.php');
    putenv('VIEW_COMPILED_PATH=/tmp/framework/views');
    $_ENV['VIEW_COMPILED_PATH'] = '/tmp/framework/views';

    putenv('LOG_CHANNEL=stderr');
    putenv('CACHE_DRIVER=array');
    putenv('SESSION_DRIVER=cookie'); 

    require __DIR__ . '/../public/index.php';

} catch (\Throwable $e) {
    echo "<h1>Vercel PHP Fatal Error:</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine();
}
