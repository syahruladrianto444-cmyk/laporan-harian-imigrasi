<?php

try {
    $apiDir = __DIR__;
    $rootDir = realpath($apiDir . '/..');
    
    // 1. Setup /tmp directories for read-only filesystem
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

    // 2. Environment Overrides
    $_SERVER['VERCEL'] = '1';
    $_ENV['VERCEL'] = '1';
    
    putenv('APP_CONFIG_CACHE=' . $tmpAppDir . '/config.php');
    putenv('APP_PACKAGES_CACHE=' . $tmpAppDir . '/packages.php');
    putenv('APP_SERVICES_CACHE=' . $tmpAppDir . '/services.php');
    putenv('VIEW_COMPILED_PATH=' . $tmpAppDir . '/framework/views');
    putenv('LOG_CHANNEL=stderr');
    putenv('CACHE_DRIVER=array');
    putenv('SESSION_DRIVER=cookie');

    // 3. Load Laravel
    require $rootDir . '/public/index.php';

} catch (\Throwable $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>Vercel PHP Fatal Error</h1>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
