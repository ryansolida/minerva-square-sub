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
        
        // Register page templates
        add_filter('theme_page_templates', array($this, 'register_page_templates'));
        add_filter('template_include', array($this, 'load_page_template'));
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        
        // Initialize Elementor integration if Elementor is active
        add_action('elementor/loaded', array($this, 'init_elementor_integration'));
    }
    
    /**
     * Register custom page templates
     *
     * @param array $templates Existing templates
     * @return array Modified templates
     */
    public function register_page_templates($templates) {
        // Add the Square Service template
        $templates[plugin_dir_path(__FILE__) . 'page-templates/square-demo.php'] = 'Square Service Demo';
        return $templates;
    }
    
    /**
     * Load the custom page template if needed
     *
     * @param string $template Current template path
     * @return string Template path to use
     */
    public function load_page_template($template) {
        global $post;
        
        if (!$post) {
            return $template;
        }
        
        // Get the template selected for this page
        $template_file = get_post_meta($post->ID, '_wp_page_template', true);
        
        // Check if the template is one of our custom templates
        if (plugin_dir_path(__FILE__) . 'page-templates/square-demo.php' === $template_file) {
            if (file_exists(plugin_dir_path(__FILE__) . 'page-templates/square-demo.php')) {
                return plugin_dir_path(__FILE__) . 'page-templates/square-demo.php';
            }
        }
        
        return $template;
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
     * Register scripts and styles for Square payment processing
     */
    public function register_scripts() {
        return;
        // Register Square Web Payments SDK from CDN
        wp_register_script(
            'square-web-payments-sdk',
            'https://sandbox.web.squarecdn.com/v1/square.js',
            array(),
            '1.0.0',
            true
        );
        
        // Register our custom JS for handling Square payments
        wp_register_script(
            'square-service-payments',
            plugin_dir_url(__FILE__) . 'assets/js/square-payments.js',
            array('jquery', 'square-web-payments-sdk'),
            '1.0.0',
            true
        );
        
        // Register CSS for payment forms
        wp_register_style(
            'square-service-forms',
            plugin_dir_url(__FILE__) . 'assets/css/square-forms.css',
            array(),
            '1.0.0'
        );
        
        // Add localized data for our JS
        $square_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'application_id' => get_option('square_service_application_id', ''),
            'location_id' => get_option('square_service_location_id', ''),
            'environment' => get_option('square_service_environment', 'sandbox'),
            'nonce' => wp_create_nonce('square_service_nonce')
        );
        
        wp_localize_script('square-service-payments', 'square_service_params', $square_data);
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
