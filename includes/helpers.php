<?php
/**
 * Helper functions for MMC Memberhip plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dump and die - a useful debugging function
 * 
 * @param mixed $data The data to dump
 * @param bool $pretty Whether to pretty print JSON (default: true)
 * @return void
 */
function dd($data, $pretty = true) {
    // Clean any previous output
    if (ob_get_length()) {
        ob_clean();
    }
    
    echo '<pre style="
        background-color: #f5f5f5;
        color: #333;
        padding: 15px;
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: monospace;
        font-size: 14px;
        line-height: 1.5;
        overflow: auto;
        max-height: 500px;
    ">';

    if (is_array($data) || is_object($data)) {
        if ($pretty) {
            echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            print_r($data);
        }
    } elseif (is_bool($data)) {
        echo $data ? 'true' : 'false';
    } elseif (is_null($data)) {
        echo 'NULL';
    } else {
        echo htmlspecialchars((string)$data);
    }

    echo '</pre>';
    
    // Add stack trace
    echo '<div style="
        background-color: #f0f8ff;
        color: #333;
        padding: 15px;
        margin: 10px 0;
        border: 1px solid #add8e6;
        border-radius: 4px;
        font-family: monospace;
        font-size: 14px;
        line-height: 1.5;
        overflow: auto;
    ">';
    echo '<strong>Debug backtrace:</strong><br>';
    
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
    
    foreach ($trace as $i => $step) {
        if ($i === 0) continue; // Skip current function
        
        $file = isset($step['file']) ? basename($step['file']) : 'unknown';
        $line = isset($step['line']) ? $step['line'] : 'unknown';
        $function = isset($step['function']) ? $step['function'] : 'unknown';
        $class = isset($step['class']) ? $step['class'] : '';
        $type = isset($step['type']) ? $step['type'] : '';
        
        echo "#{$i} {$file}:{$line} - {$class}{$type}{$function}()<br>";
    }
    
    echo '</div>';
    
    die(1);
}

/**
 * Variant that dumps without dying - useful when you want to continue execution
 * 
 * @param mixed $data The data to dump
 * @param bool $pretty Whether to pretty print JSON (default: true)
 * @return void
 */
function dump($data, $pretty = true) {
    echo '<pre style="
        background-color: #f5f5f5;
        color: #333;
        padding: 15px;
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: monospace;
        font-size: 14px;
        line-height: 1.5;
        overflow: auto;
        max-height: 500px;
    ">';

    if (is_array($data) || is_object($data)) {
        if ($pretty) {
            echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            print_r($data);
        }
    } elseif (is_bool($data)) {
        echo $data ? 'true' : 'false';
    } elseif (is_null($data)) {
        echo 'NULL';
    } else {
        echo htmlspecialchars((string)$data);
    }

    echo '</pre>';
}

/**
 * Get the URL of the membership signup page
 * 
 * @return string The URL of the signup page or empty string if not configured
 */
function get_membership_signup_url() {
    $signup_page_id = get_option('square_service_signup_page_id', 0);
    
    if (empty($signup_page_id)) {
        return '';
    }
    
    return get_permalink($signup_page_id);
}
