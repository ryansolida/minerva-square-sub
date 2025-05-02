<?php
/**
 * Simple autoloader for Square Service WordPress plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load Guzzle dependencies first
require_once __DIR__ . '/vendor/guzzlehttp/autoload.php';

// Load Square SDK next
require_once __DIR__ . '/vendor/square/autoload.php';

// Now load our classes that depend on the above libraries
// Load the SquareService class
require_once __DIR__ . '/SquareService.php';

// Load ShortcodeHandlers class
require_once __DIR__ . '/ShortcodeHandlers.php';

// Load UserFunctions class
require_once __DIR__ . '/UserFunctions.php';
