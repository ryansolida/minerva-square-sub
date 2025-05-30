<?php
/**
 * Elementor Integration for MMC Membership
 *
 * Provides dynamic tags and widgets for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MMCMembershipElementor {
    private static $instance = null;
    
    // Singleton pattern
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register Elementor functionality when Elementor is loaded
        add_action('elementor/dynamic_tags/register', array($this, 'register_dynamic_tags'));
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        
        // Register Dynamic Tags Module
        add_action('elementor/dynamic_tags/register_tags', array($this, 'register_tags'));
    }
    
    /**
     * Register the dynamic tags module
     * 
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager
     */
    public function register_dynamic_tags($dynamic_tags_manager) {
        // Include the Dynamic Tags module
        require_once __DIR__ . '/tags/MMCMembershipModule.php';
        
        // Register the module
        $dynamic_tags_manager->register_tag_group('mmc-membership', [
            'title' => 'MMC Membership'
        ]);
    }
    
    /**
     * Register individual dynamic tags
     * 
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager
     */
    public function register_tags($dynamic_tags_manager) {
        // Include tag files
        require_once __DIR__ . '/tags/MMCNextBillingDateTag.php';
        require_once __DIR__ . '/tags/MMCNextBillingPriceTag.php';
        require_once __DIR__ . '/tags/MMCPaymentCardInfoTag.php';
        require_once __DIR__ . '/tags/MMCMembershipStatusTag.php';
        require_once __DIR__ . '/tags/MMCHasActiveMembershipTag.php';
        require_once __DIR__ . '/tags/MMCMembershipExpirationDateTag.php';
        require_once __DIR__ . '/tags/MMCMembershipActivationDateTag.php';
        
        // Register the tags
        $dynamic_tags_manager->register(new \MMCMembershipNextBillingDateTag());
        $dynamic_tags_manager->register(new \MMCMembershipNextBillingPriceTag());
        $dynamic_tags_manager->register(new \MMCPaymentCardInfoTag());
        $dynamic_tags_manager->register(new \MMCMembershipStatusTag());
        $dynamic_tags_manager->register(new \MMCHasActiveMembershipTag());
        $dynamic_tags_manager->register(new \MMCMembershipExpirationDateTag());
        $dynamic_tags_manager->register(new \MMCMembershipActivationDateTag());
    }
    
    /**
     * Register Elementor widgets
     * 
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets($widgets_manager) {
        // Include widget files
        require_once __DIR__ . '/widgets/SubscriptionFormWidget.php';
        require_once __DIR__ . '/widgets/PaymentMethodsWidget.php';
        require_once __DIR__ . '/widgets/CancelMembershipWidget.php';
        
        // Register widgets
        $widgets_manager->register(new \MMCMembership\Elementor\Widgets\MMCMembershipSubscriptionFormWidget());
        $widgets_manager->register(new \MMCMembership\Elementor\Widgets\MMCMembershipPaymentMethodsWidget());
        $widgets_manager->register(new \MMCMembership\Elementor\Widgets\MMCMembershipCancelMembershipWidget());
    }
}

/**
 * Initialize Elementor integration
 */
function mmc_membership_elementor() {
    return MMCMembershipElementor::get_instance();
}

// Initialize the Elementor integration
add_action('elementor/dynamic_tags/register', function($dynamic_tags_manager) {
    MMCMembershipElementor::get_instance()->register_dynamic_tags($dynamic_tags_manager);
});

// Register the dynamic tags
add_action('elementor/dynamic_tags/register_tags', function($dynamic_tags_manager) {
    MMCMembershipElementor::get_instance()->register_tags($dynamic_tags_manager);
});

// Register the widgets
add_action('elementor/widgets/register', function($widgets_manager) {
    MMCMembershipElementor::get_instance()->register_widgets($widgets_manager);
});
