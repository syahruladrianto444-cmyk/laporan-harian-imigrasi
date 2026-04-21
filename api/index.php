<?php
// Absolute minimal loader for debugging
echo "Loader Start";

try {
    // Basic /tmp setup
    if (!is_dir('/tmp/app/framework/views')) {
        mkdir('/tmp/app/framework/views', 0777, true);
    }
    
    $_SERVER['VERCEL'] = '1';
    putenv('VIEW_COMPILED_PATH=/tmp/app/framework/views');
    putenv('LOG_CHANNEL=stderr');
    
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    echo "<h1>Error</h1><pre>" . $e->getMessage() . "</pre>";
}
