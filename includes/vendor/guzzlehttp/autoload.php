<?php
/**
 * Autoloader for Guzzle HTTP library
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Map of class prefixes to directories
$prefixes = array(
    'GuzzleHttp\\' => __DIR__ . '/guzzlehttp/',
    'Psr\\Http\\Message\\' => __DIR__ . '/psr/http-message/src/',
    'Psr\\Http\\Client\\' => __DIR__ . '/psr/http-client/src/'
);

// Register the autoloader
spl_autoload_register(function ($class) use ($prefixes) {
    foreach ($prefixes as $prefix => $baseDir) {
        // Does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // No, move to the next registered prefix
            continue;
        }
        
        // Get the relative class name
        $relativeClass = substr($class, $len);
        
        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators and append '.php'
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }
    
    return false;
});
