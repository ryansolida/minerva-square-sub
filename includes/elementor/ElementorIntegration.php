<?php
/**
 * Elementor Integration for Square Service
 *
 * Provides dynamic tags and widgets for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SquareServiceElementor {
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
        require_once __DIR__ . '/tags/SquareServiceModule.php';
        
        // Register the module
        $dynamic_tags_manager->register_tag_group('square-service', [
            'title' => 'Square Service'
        ]);
    }
    
    /**
     * Register individual dynamic tags
     * 
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager
     */
    public function register_tags($dynamic_tags_manager) {
        // Include tag files
        require_once __DIR__ . '/tags/MembershipStatusTag.php';
        require_once __DIR__ . '/tags/NextBillingDateTag.php';
        require_once __DIR__ . '/tags/NextPaymentAmountTag.php';
        require_once __DIR__ . '/tags/PaymentCardInfoTag.php';
        
        // Register the tags
        $dynamic_tags_manager->register_tag('SquareServiceMembershipStatusTag');
        $dynamic_tags_manager->register_tag('SquareServiceNextBillingDateTag');
        $dynamic_tags_manager->register_tag('SquareServiceNextPaymentAmountTag');
        $dynamic_tags_manager->register_tag('SquareServicePaymentCardInfoTag');
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
        $widgets_manager->register(new SquareServiceSubscriptionFormWidget());
        $widgets_manager->register(new SquareServicePaymentMethodsWidget());
        $widgets_manager->register(new SquareServiceCancelMembershipWidget());
    }
}

/**
 * Initialize Elementor integration
 */
function square_service_elementor() {
    return SquareServiceElementor::get_instance();
}
