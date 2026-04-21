<?php

// Vercel Entry Point - Serverless Environment Overrides
// Vercel's filesystem is read-only except for /tmp
putenv('APP_CONFIG_CACHE=/tmp/config.php');
putenv('APP_EVENTS_CACHE=/tmp/events.php');
putenv('APP_PACKAGES_CACHE=/tmp/packages.php');
putenv('APP_ROUTES_CACHE=/tmp/routes.php');
putenv('APP_SERVICES_CACHE=/tmp/services.php');
putenv('VIEW_COMPILED_PATH=/tmp');
$_ENV['VIEW_COMPILED_PATH'] = '/tmp';

// Set logging and caching channels that don't need persistent file storage
putenv('LOG_CHANNEL=stderr');
putenv('CACHE_DRIVER=array');
putenv('SESSION_DRIVER=cookie'); 

require __DIR__ . '/../public/index.php';
