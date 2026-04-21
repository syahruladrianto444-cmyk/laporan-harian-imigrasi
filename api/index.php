<?php

// Vercel Entry Point - Serverless Environment Overrides
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    // Ensure the required temp directories exist for Laravel
    $tmpAppDir = '/tmp/app';
    $tmpDirs = [
        $tmpAppDir . '/framework/cache/data',
        $tmpAppDir . '/framework/sessions',
        $tmpAppDir . '/framework/testing',
        $tmpAppDir . '/framework/views',
        $tmpAppDir . '/logs',
        $tmpAppDir . '/public'
    ];

    foreach ($tmpDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    // Set Vercel environment flags
    $_SERVER['VERCEL'] = '1';
    $_ENV['VERCEL'] = '1';

    // Route storage and cache to /tmp
    putenv('APP_CONFIG_CACHE=' . $tmpAppDir . '/config.php');
    putenv('APP_EVENTS_CACHE=' . $tmpAppDir . '/events.php');
    putenv('APP_PACKAGES_CACHE=' . $tmpAppDir . '/packages.php');
    putenv('APP_ROUTES_CACHE=' . $tmpAppDir . '/routes.php');
    putenv('APP_SERVICES_CACHE=' . $tmpAppDir . '/services.php');
    putenv('VIEW_COMPILED_PATH=' . $tmpAppDir . '/framework/views');
    
    // Set standard Laravel environment variables for Vercel
    putenv('LOG_CHANNEL=stderr');
    putenv('CACHE_DRIVER=array');
    putenv('SESSION_DRIVER=cookie'); 

    // Load the application
    require __DIR__ . '/../public/index.php';

} catch (\Throwable $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>Vercel PHP Fatal Error</h1>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<h3>Stack Trace:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
