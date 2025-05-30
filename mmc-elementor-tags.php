<?php
/**
 * Plugin Name: MMC Membership - Elementor Tags
 * Description: Adds membership-related dynamic tags to Elementor
 * Version: 1.0.0
 * Author: Codeium
 * Depends: Elementor
 * 
 * DEPRECATED: This file is no longer used as all dynamic tags have been consolidated
 * under the MMC Membership namespace in ElementorIntegration.php
 */

use MMCMembership\Elementor\Tags\MMCHasActiveMembershipTag;
use MMCMembership\Elementor\Tags\MMCMembershipExpirationDateTag;
use MMCMembership\Elementor\Tags\MMCMembershipActivationDateTag;
use MMCMembership\Elementor\Tags\MMCNextBillingDateTag;
use MMCMembership\Elementor\Tags\MMCNextBillingPriceTag;
use MMCMembership\Elementor\Tags\MMCPaymentCardInfoTag;
use MMCMembership\Elementor\Tags\MMCMembershipStatusTag;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register dynamic tags with Elementor
 * 
 * DEPRECATED: This class is no longer used as all dynamic tags have been consolidated
 * under the MMC Membership namespace in ElementorIntegration.php
 */
class MMC_Elementor_Tags {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register tags when Elementor is loaded
        add_action('elementor/dynamic_tags/register', array($this, 'register_tags'));
    }
    
    /**
     * Register dynamic tags with Elementor
     * 
     * DEPRECATED: This method is no longer used as all dynamic tags have been consolidated
     * under the MMC Membership namespace in ElementorIntegration.php
     */
    public function register_tags($dynamic_tags_manager) {
        // Register tag group
        $dynamic_tags_manager->register_group('mmc-membership', [
            'title' => 'MMC Membership'
        ]);
        
        // Include tag classes
        require_once __DIR__ . '/includes/elementor/tags/MMCHasActiveMembershipTag.php';
        require_once __DIR__ . '/includes/elementor/tags/MMCMembershipExpirationDateTag.php';
        require_once __DIR__ . '/includes/elementor/tags/MMCMembershipActivationDateTag.php';
        require_once __DIR__ . '/includes/elementor/tags/MMCNextBillingDateTag.php';
        require_once __DIR__ . '/includes/elementor/tags/MMCNextBillingPriceTag.php';
        require_once __DIR__ . '/includes/elementor/tags/MMCPaymentCardInfoTag.php';
        require_once __DIR__ . '/includes/elementor/tags/MMCMembershipStatusTag.php';
        
        // Register tags
        $dynamic_tags_manager->register(new MMCHasActiveMembershipTag());
        $dynamic_tags_manager->register(new MMCMembershipExpirationDateTag());
        $dynamic_tags_manager->register(new MMCMembershipActivationDateTag());
        $dynamic_tags_manager->register(new MMCNextBillingDateTag());
        $dynamic_tags_manager->register(new MMCNextBillingPriceTag());
        $dynamic_tags_manager->register(new MMCPaymentCardInfoTag());
        $dynamic_tags_manager->register(new MMCMembershipStatusTag());
    }
}

// Initialize
add_action('elementor/loaded', function() {
    MMC_Elementor_Tags::get_instance();
});
