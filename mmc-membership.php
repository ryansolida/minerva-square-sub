<?php
use MMCMembership\SquareService;
/**
 * Plugin Name: MMC Membership
 * Description: A service for interacting with the Square API
 * Version: 1.0.0
 * Author: Codeium
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load all PHP files in the includes directory (base level only)
$includes_dir = plugin_dir_path(__FILE__) . 'includes/';
$files = glob($includes_dir . '*.php');
foreach ($files as $file) {
    require_once $file;
}


require_once plugin_dir_path(__FILE__) . 'admin/settings.php';

/**
 * Enqueue frontend assets (Tailwind CSS and Alpine.js)
 */
function mmc_membership_enqueue_frontend_assets() {
    // Enqueue Tailwind CSS
    wp_enqueue_style(
        'square-service-tailwindcss',
        'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
        array(),
        '2.2.19'
    );
    
    // Enqueue Alpine.js
    wp_enqueue_script(
        'square-service-alpinejs',
        'https://cdn.jsdelivr.net/npm/alpinejs@3.12.0/dist/cdn.min.js',
        array(),
        '3.12.0',
        true // Load in footer
    );
    
    // For now, always load the Square SDK on all pages
    // This ensures it's available when needed, especially during development
    $environment = get_option('mmc_membership_environment', 'sandbox');
    $sdk_url = $environment === 'production' 
        ? 'https://web.squarecdn.com/v1/square.js' 
        : 'https://sandbox.web.squarecdn.com/v1/square.js';
        
    wp_enqueue_script(
        'square-web-payments-sdk',
        $sdk_url,
        array(),
        null,
        true // Load in footer
    );
}
add_action('wp_enqueue_scripts', 'mmc_membership_enqueue_frontend_assets');

/**
 * Check user's Square subscription status on login and sync with WordPress
 *
 * @param string $user_login The user's login name
 * @param WP_User $user The user object
 */
function mmc_membership_check_subscription_on_login($user_login, $user) {
    // Get user's subscription ID
    $subscription_id = get_user_meta($user->ID, 'square_subscription_id', true);
    
    // Only proceed if user has a subscription ID stored
    if (empty($subscription_id)) {
        return;
    }
    
    try {
        // Get Square service
        $mmc_membership = new SquareService();
        
        // Get subscription status from Square
        $subscription = $mmc_membership->getSubscription($subscription_id);
        
        // Check if subscription status differs from what we have stored
        $stored_status = get_user_meta($user->ID, 'square_active_membership', true);
        $is_active = ($subscription->status === 'ACTIVE');
        
        // If status has changed, update it
        if (($is_active && $stored_status !== 'yes') || (!$is_active && $stored_status === 'yes')) {
            $new_status = $is_active ? 'yes' : 'no';
            update_user_meta($user->ID, 'square_active_membership', $new_status);
            
            // Log the status change
            error_log(sprintf('Updated user #%d subscription status from %s to %s', 
                $user->ID, 
                $stored_status, 
                $new_status
            ));
        }
    } catch (Exception $e) {
        // Log the error but don't disrupt the login process
        error_log('Error checking Square subscription on login: ' . $e->getMessage());
    }
}

// Hook into WordPress login to verify subscription status
add_action('wp_login', 'mmc_membership_check_subscription_on_login', 10, 2);


// Main plugin class
class SquareServicePlugin {
    private static $instance = null;
    
    // Singleton pattern
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize the plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        
        // Add admin pages
        if (is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
            require_once plugin_dir_path(__FILE__) . 'admin/member-management.php';
        }
        
        // Initialize Elementor integration if Elementor is active
        add_action('elementor/loaded', array($this, 'init_elementor_integration'));
    }
    
    
    /**
     * Initialize Elementor integration
     */
    public function init_elementor_integration() {
        // Load Elementor integration file
        require_once plugin_dir_path(__FILE__) . 'includes/elementor/ElementorIntegration.php';
        
        // Initialize the integration
        mmc_membership_elementor();
    }
    
   
    /**
     * Get an instance of the SquareService
     * 
     * @param string $access_token Square API access token
     * @return SquareService
     */
    public function get_mmc_membership($access_token = null) {
        // Get token from WordPress options if not provided
        if (empty($access_token)) {
            $access_token = get_option('mmc_membership_access_token');
        }
        
        return new SquareService($access_token);
    }
}

// Initialize the plugin
function mmc_membership() {
    return SquareServicePlugin::get_instance();
}

// Global function to get the SquareService
function get_mmc_membership($access_token = null) {
    return mmc_membership()->get_mmc_membership($access_token);
}

// Initialize the plugin
mmc_membership();
