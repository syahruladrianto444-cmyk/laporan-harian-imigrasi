<?php

// Vercel Entry Point - Serverless Environment Overrides
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Force output to the browser even if it crashes later
ob_start();
echo "<!-- Vercel PHP Diagnostic Start -->\n";

try {
    $apiDir = __DIR__;
    $rootDir = realpath($apiDir . '/..');
    
    echo "<!-- API Dir: $apiDir -->\n";
    echo "<!-- Root Dir: $rootDir -->\n";
    
    // 1. Check critical files
    $indexFile = $rootDir . '/public/index.php';
    $autoloadFile = $rootDir . '/vendor/autoload.php';
    
    if (!file_exists($autoloadFile)) {
        echo "<h1>FATAL: vendor/autoload.php not found!</h1>";
        echo "<p>Search path: $autoloadFile</p>";
        ob_end_flush();
        exit;
    }
    
    if (!file_exists($indexFile)) {
        echo "<h1>FATAL: public/index.php not found!</h1>";
        echo "<p>Search path: $indexFile</p>";
        ob_end_flush();
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

    // 4. Flush diagnostics before loading Laravel
    echo "<!-- Diagnostics pass. Loading Laravel... -->\n";
    ob_end_flush();
    flush();

    require $indexFile;

} catch (\Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>Vercel PHP Fatal Error</h1>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
