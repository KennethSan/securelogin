<?php

// First, let's try to find where Laravel Pail is registered
$bootstrapAppFile = file_get_contents(__DIR__ . '/bootstrap/app.php');
file_put_contents(__DIR__ . '/bootstrap/app.php.backup', $bootstrapAppFile);

// Check if Laravel Pail is registered in bootstrap/app.php
if (strpos($bootstrapAppFile, 'Laravel\\Pail\\PailServiceProvider') !== false) {
    $modifiedContent = str_replace('Laravel\\Pail\\PailServiceProvider', '// Laravel\\Pail\\PailServiceProvider', $bootstrapAppFile);
    file_put_contents(__DIR__ . '/bootstrap/app.php', $modifiedContent);
    echo "Modified bootstrap/app.php to comment out Laravel Pail\n";
} else {
    echo "Laravel Pail not found in bootstrap/app.php\n";
}

// Next let's reinstall Laravel Pail properly
echo "Reinstalling Laravel Pail...\n";
passthru('composer remove laravel/pail');
passthru('composer require laravel/pail');
echo "Done!\n";