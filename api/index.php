<?php

// Vercel Entry Point - Serverless Environment Overrides
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<!-- Vercel PHP Diagnostic Start -->\n";

try {
    $rootDir = dirname(__DIR__);
    echo "<!-- Root: $rootDir -->\n";
    
    // 1. Check vendor
    if (!file_exists($rootDir . '/vendor/autoload.php')) {
        echo "<h1>FATAL: vendor/autoload.php not found!</h1>";
        echo "<p>This means 'composer install' failed during the Vercel build process.</p>";
        $files = scandir($rootDir);
        echo "<pre>Files in root: " . implode(", ", $files) . "</pre>";
        exit;
    }

    // 2. Setup /tmp directories for read-only filesystem
    $tmpAppDir = '/tmp/app';
    $tmpDirs = [
        $tmpAppDir . '/framework/cache/data',
        $tmpAppDir . '/framework/sessions',
        $tmpAppDir . '/framework/views',
        $tmpAppDir . '/logs',
    ];

    foreach ($tmpDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    // 3. Environment Overrides
    $_SERVER['VERCEL'] = '1';
    $_ENV['VERCEL'] = '1';
    
    putenv('APP_CONFIG_CACHE=' . $tmpAppDir . '/config.php');
    putenv('APP_PACKAGES_CACHE=' . $tmpAppDir . '/packages.php');
    putenv('APP_SERVICES_CACHE=' . $tmpAppDir . '/services.php');
    putenv('VIEW_COMPILED_PATH=' . $tmpAppDir . '/framework/views');
    putenv('LOG_CHANNEL=stderr');
    putenv('CACHE_DRIVER=array');
    putenv('SESSION_DRIVER=cookie');

    // 4. Load Laravel
    require $rootDir . '/public/index.php';

} catch (\Throwable $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>Vercel PHP Fatal Error</h1>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
