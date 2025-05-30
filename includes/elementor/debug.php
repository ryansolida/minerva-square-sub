<?php
/**
 * Debug file for Elementor integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Function to log debug information
 */
function mmc_membership_debug_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

// Add a hook to check if Elementor is loaded
add_action('plugins_loaded', function() {
    mmc_membership_debug_log('MMC Membership: plugins_loaded action fired');
    
    if (did_action('elementor/loaded')) {
        mmc_membership_debug_log('MMC Membership: Elementor is loaded');
    } else {
        mmc_membership_debug_log('MMC Membership: Elementor is NOT loaded');
    }
});

// Hook into Elementor's dynamic tags registration
add_action('elementor/dynamic_tags/register', function($dynamic_tags_manager) {
    mmc_membership_debug_log('MMC Membership: elementor/dynamic_tags/register action fired');
});

// Hook into Elementor's tags registration
add_action('elementor/dynamic_tags/register_tags', function($dynamic_tags_manager) {
    mmc_membership_debug_log('MMC Membership: elementor/dynamic_tags/register_tags action fired');
    
    // Log registered tags
    $tags = [
        'SquareServiceMembershipStatusTag',
        'SquareServiceNextBillingDateTag',
        'SquareServiceNextPaymentAmountTag',
        'SquareServicePaymentCardInfoTag',
        'SquareServiceHasActiveMembershipTag',
        'SquareServiceMembershipExpirationDateTag',
        'SquareServiceMembershipActivationDateTag',
    ];
    
    foreach ($tags as $tag) {
        if (class_exists($tag)) {
            mmc_membership_debug_log("MMC Membership: Tag class $tag exists");
        } else {
            mmc_membership_debug_log("MMC Membership: Tag class $tag does NOT exist");
        }
    }
});
