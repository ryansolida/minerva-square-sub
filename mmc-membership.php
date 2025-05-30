<?php
use MMCMembership\SquareService;
use MMCMembership\Elementor\Tags\MMCHasActiveMembershipTag;
use MMCMembership\Elementor\Tags\MMCMembershipExpirationDateTag;
use MMCMembership\Elementor\Tags\MMCMembershipActivationDateTag;
use MMCMembership\Elementor\Tags\MMCNextBillingDateTag;
use MMCMembership\Elementor\Tags\MMCNextBillingPriceTag;
use MMCMembership\Elementor\Tags\MMCPaymentCardInfoTag;
use MMCMembership\Elementor\Tags\MMCMembershipStatusTag;
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

// Define plugin constants
define('MMC_MEMBERSHIP_PRICE', 8.99);
define('MMC_MEMBERSHIP_CLUB_NAME', 'Minerva Motor Club');

// Load all PHP files in the includes directory (base level only)
$includes_dir = plugin_dir_path(__FILE__) . 'includes/';
$files = glob($includes_dir . '*.php');
foreach ($files as $file) {
    require_once $file;
}

// Explicitly include the MembershipShortcodes file
require_once plugin_dir_path(__FILE__) . 'includes/MembershipShortcodes.php';

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
        
        // Load direct registration for MMC Membership tags
        if (did_action('elementor/loaded')) {
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/direct-register.php';
            
            // We no longer need to load the standalone tags as they've been consolidated
            // into the MMC Membership namespace
        }
    }
    
    
    /**
     * Initialize Elementor integration
     */
    public function init_elementor_integration() {
        // Load debug file first (if needed)
        require_once plugin_dir_path(__FILE__) . 'includes/elementor/debug.php';
        
        // Load the consolidated MMC Membership Elementor integration
        require_once plugin_dir_path(__FILE__) . 'includes/elementor/ElementorIntegration.php';
        
        // Directly register the dynamic tags
        add_action('elementor/dynamic_tags/register', function($dynamic_tags_manager) {
            // Register tag group
            $dynamic_tags_manager->register_group('mmc-membership', [
                'title' => 'MMC Membership'
            ]);
            
            // Include tag classes
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/tags/MMCHasActiveMembershipTag.php';
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/tags/MMCMembershipExpirationDateTag.php';
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/tags/MMCMembershipActivationDateTag.php';
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/tags/MMCNextBillingDateTag.php';
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/tags/MMCNextBillingPriceTag.php';
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/tags/MMCPaymentCardInfoTag.php';
            require_once plugin_dir_path(__FILE__) . 'includes/elementor/tags/MMCMembershipStatusTag.php';
            
            // Register tags
            $dynamic_tags_manager->register(new \MMCMembership\Elementor\Tags\MMCHasActiveMembershipTag());
            $dynamic_tags_manager->register(new \MMCMembership\Elementor\Tags\MMCMembershipExpirationDateTag());
            $dynamic_tags_manager->register(new \MMCMembership\Elementor\Tags\MMCMembershipActivationDateTag());
            $dynamic_tags_manager->register(new \MMCMembership\Elementor\Tags\MMCNextBillingDateTag());
            $dynamic_tags_manager->register(new \MMCMembership\Elementor\Tags\MMCNextBillingPriceTag());
            $dynamic_tags_manager->register(new \MMCMembership\Elementor\Tags\MMCPaymentCardInfoTag());
            $dynamic_tags_manager->register(new \MMCMembership\Elementor\Tags\MMCMembershipStatusTag());
        });
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

// Add custom CSS for Square payment form and Elementor button styles
add_action('wp_head', 'mmc_membership_add_custom_css');
function mmc_membership_add_custom_css() {
    echo '<style>
        /* Hide empty Square card messages */
        .sq-card-message:empty {
            display: none !important;
        }
        
        /* Apply Elementor button styles to our form buttons */
        #card-container, #card-container-start, #card-container-reactivate {
            min-height: 40px;
        }
        
        /* Make our form buttons match Elementor global button styles */
        .mmc-membership-form button[type="submit"] {
            display: inline-block;
            line-height: 1;
            background-color: var(--e-global-color-accent, #61ce70);
            font-size: 15px;
            padding: 12px 24px;
            border-radius: 3px;
            color: #fff;
            fill: #fff;
            text-align: center;
            transition: all .3s;
            border: none;
            cursor: pointer;
        }
        
        .mmc-membership-form button[type="submit"]:hover {
            background-color: var(--e-global-color-accent-hover, #4baa56);
        }
        
        .mmc-membership-form button[type="submit"]:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* Make our form fields match Elementor global form field styles */
        .mmc-membership-form input[type="text"],
        .mmc-membership-form input[type="email"],
        .mmc-membership-form input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--e-global-color-border, #d5d5d5);
            border-radius: var(--e-global-border-radius, 3px);
            color: var(--e-global-color-text, #333);
            background-color: var(--e-global-color-background, #fff);
            font-size: var(--e-global-typography-text-font-size, 15px);
            font-family: var(--e-global-typography-text-font-family, sans-serif);
            line-height: var(--e-global-typography-text-line-height, 1.5);
            transition: all .3s;
        }
        
        .mmc-membership-form input[type="text"]:focus,
        .mmc-membership-form input[type="email"]:focus,
        .mmc-membership-form input[type="password"]:focus {
            border-color: var(--e-global-color-primary, #6ec1e4);
            outline: none;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        
        .mmc-membership-form label {
            display: block;
            margin-bottom: 5px;
            color: var(--e-global-color-text, #333);
            font-size: var(--e-global-typography-text-font-size, 15px);
            font-family: var(--e-global-typography-text-font-family, sans-serif);
            font-weight: 500;
            line-height: var(--e-global-typography-text-line-height, 1.5);
        }
        
        /* Error styles */
        .mmc-membership-form .text-red-500,
        .mmc-membership-form .text-red-600 {
            color: var(--e-global-color-danger, #d9534f);
        }
        
        /* Success styles */
        .mmc-membership-form .text-green-600 {
            color: var(--e-global-color-success, #5cb85c);
        }
    </style>';
}
