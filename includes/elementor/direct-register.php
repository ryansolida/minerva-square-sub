<?php
/**
 * Direct registration of Elementor dynamic tags
 * 
 * This file directly registers the MMC Membership dynamic tags with Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register dynamic tags directly with Elementor
 * 
 * DEPRECATED: This function is no longer used as all dynamic tags have been consolidated
 * under the MMC Membership namespace in ElementorIntegration.php
 */
function mmc_membership_register_dynamic_tags() {
    // Only proceed if Elementor is active
    if (!did_action('elementor/loaded')) {
        return;
    }

    // Add action to register dynamic tags
    add_action('elementor/dynamic_tags/register', function($dynamic_tags_manager) {
        // Register tag group
        $dynamic_tags_manager->register_group('mmc-membership', [
            'title' => 'MMC Membership'
        ]);
        
        // Include tag classes
        require_once __DIR__ . '/tags/MMCHasActiveMembershipTag.php';
        require_once __DIR__ . '/tags/MMCMembershipExpirationDateTag.php';
        require_once __DIR__ . '/tags/MMCMembershipActivationDateTag.php';
        require_once __DIR__ . '/tags/MMCNextBillingDateTag.php';
        require_once __DIR__ . '/tags/MMCNextBillingPriceTag.php';
        require_once __DIR__ . '/tags/MMCPaymentCardInfoTag.php';
        require_once __DIR__ . '/tags/MMCMembershipStatusTag.php';
        
        // Register tags
        $dynamic_tags_manager->register(new \MMCHasActiveMembershipTag());
        $dynamic_tags_manager->register(new \MMCMembershipExpirationDateTag());
        $dynamic_tags_manager->register(new \MMCMembershipActivationDateTag());
        $dynamic_tags_manager->register(new \MMCMembershipNextBillingDateTag());
        $dynamic_tags_manager->register(new \MMCMembershipNextBillingPriceTag());
        $dynamic_tags_manager->register(new \MMCPaymentCardInfoTag());
        $dynamic_tags_manager->register(new \MMCMembershipStatusTag());
    });
}

// Enable direct registration
mmc_membership_register_dynamic_tags();
