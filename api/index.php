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

    // 2. Vercel Environment Overrides
    $_SERVER['VERCEL'] = '1';
    $_ENV['VERCEL'] = '1';
    
    // Path overrides for serverless
    putenv('APP_CONFIG_CACHE=' . $tmpAppDir . '/config.php');
    putenv('APP_PACKAGES_CACHE=' . $tmpAppDir . '/packages.php');
    putenv('APP_SERVICES_CACHE=' . $tmpAppDir . '/services.php');
    putenv('VIEW_COMPILED_PATH=' . $tmpAppDir . '/framework/views');
    
    // Enforce serverless drivers (avoid read-only storage)
    $_ENV['LOG_CHANNEL'] = $_SERVER['LOG_CHANNEL'] = 'stderr';
    $_ENV['CACHE_DRIVER'] = $_SERVER['CACHE_DRIVER'] = 'array';
    $_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'cookie';
    
    // Map Vercel environment variables to Laravel expected keys
    // This allows using standard Laravel env names in vercel dashboard
    $toOverride = [
        'DB_CONNECTION' => 'mysql',
        'DB_PORT' => '4000',
        'APP_DEBUG' => 'false',
        'DB_SSL_MODE' => 'REQUIRED',
        'MYSQL_ATTR_SSL_CA' => '/etc/pki/tls/certs/ca-bundle.crt',
    ];

    foreach ($toOverride as $key => $val) {
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            $_ENV[$key] = $_SERVER[$key] = $val;
            putenv("$key=$val");
        }
    }

    // 3. Load Laravel
    require $rootDir . '/public/index.php';

} catch (\Throwable $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>Vercel PHP Fatal Error</h1>";
    echo "<strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
