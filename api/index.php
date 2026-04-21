<?php

// Vercel Entry Point - Serverless Environment Overrides

// Ensure the required temp directories exist for Laravel
$tmpDirs = [
    '/tmp/framework/cache/data',
    '/tmp/framework/sessions',
    '/tmp/framework/testing',
    '/tmp/framework/views',
    '/tmp/logs',
    '/tmp/app/public',
    '/tmp/app/temp_docs' // explicitly creating temp docs for parser
];

foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Ensure VERCEL environment variable is set
$_SERVER['VERCEL'] = '1';
$_ENV['VERCEL'] = '1';

// Vercel's filesystem is read-only except for /tmp
putenv('APP_CONFIG_CACHE=/tmp/config.php');
putenv('APP_EVENTS_CACHE=/tmp/events.php');
putenv('APP_PACKAGES_CACHE=/tmp/packages.php');
putenv('APP_ROUTES_CACHE=/tmp/routes.php');
putenv('APP_SERVICES_CACHE=/tmp/services.php');
putenv('VIEW_COMPILED_PATH=/tmp/framework/views');
$_ENV['VIEW_COMPILED_PATH'] = '/tmp/framework/views';

// Set logging and caching channels that don't need persistent file storage
putenv('LOG_CHANNEL=stderr');
putenv('CACHE_DRIVER=array');
putenv('SESSION_DRIVER=cookie'); 

require __DIR__ . '/../public/index.php';
