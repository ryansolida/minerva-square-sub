<?php
/**
 * Plugin Name: Square Service
 * Description: A service for interacting with the Square API
 * Version: 1.0.0
 * Author: Codeium
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the autoloader
require_once plugin_dir_path(__FILE__) . 'includes/autoload.php';

// Load helper functions (including dd() for debugging)
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/SquareService.php';

require_once plugin_dir_path(__FILE__) . 'includes/square-subscription-alpine.php';
require_once plugin_dir_path(__FILE__) . 'includes/square-membership-status-alpine.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings.php';

/**
 * Enqueue Tailwind CSS from CDN
 */
function square_service_enqueue_tailwind() {
    wp_enqueue_style(
        'tailwindcss',
        'https://cdn.tailwindcss.com',
        array(),
        null
    );
}
add_action('wp_enqueue_scripts', 'square_service_enqueue_tailwind');

/**
 * Check user's Square subscription status on login and sync with WordPress
 *
 * @param string $user_login The user's login name
 * @param WP_User $user The user object
 */
function square_service_check_subscription_on_login($user_login, $user) {
    // Get user's subscription ID
    $subscription_id = get_user_meta($user->ID, 'square_subscription_id', true);
    
    // Only proceed if user has a subscription ID stored
    if (empty($subscription_id)) {
        return;
    }
    
    try {
        // Get Square service
        $square_service = new SquareService();
        
        // Get subscription status from Square
        $subscription = $square_service->getSubscription($subscription_id);
        
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
add_action('wp_login', 'square_service_check_subscription_on_login', 10, 2);


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
        
        // Add settings page
        if (is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'admin/settings.php';
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
        square_service_elementor();
    }
    
   
    /**
     * Get an instance of the SquareService
     * 
     * @param string $access_token Square API access token
     * @return SquareService
     */
    public function get_square_service($access_token = null) {
        // Get token from WordPress options if not provided
        if (empty($access_token)) {
            $access_token = get_option('square_service_access_token');
        }
        
        return new SquareService($access_token);
    }
}

// Initialize the plugin
function square_service() {
    return SquareServicePlugin::get_instance();
}

// Global function to get the SquareService
function get_square_service($access_token = null) {
    return square_service()->get_square_service($access_token);
}

// Initialize the plugin
square_service();
