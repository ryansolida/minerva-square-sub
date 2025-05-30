<?php
/**
 * Plugin Name: MMC Membership - Elementor Tags (Simple)
 * Description: Adds membership-related dynamic tags to Elementor (simplified version)
 * Version: 1.0.0
 * Author: Codeium
 * Depends: Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register dynamic tags with Elementor
 */
function mmc_register_elementor_tags() {
    // Only run if Elementor is active
    if (!did_action('elementor/loaded')) {
        return;
    }
    
    // Register tags when Elementor is ready
    add_action('elementor/dynamic_tags/register', function($dynamic_tags_manager) {
        // Include our tag files
        require_once __DIR__ . '/includes/elementor/tags/standalone/HasActiveMembershipTag.php';
        require_once __DIR__ . '/includes/elementor/tags/standalone/MembershipExpirationDateTag.php';
        require_once __DIR__ . '/includes/elementor/tags/standalone/MembershipActivationDateTag.php';
        
        // Register each tag
        $dynamic_tags_manager->register(new \MMC_HasActiveMembershipTag());
        $dynamic_tags_manager->register(new \MMC_MembershipExpirationDateTag());
        $dynamic_tags_manager->register(new \MMC_MembershipActivationDateTag());
    });
}

// Initialize
add_action('init', 'mmc_register_elementor_tags');
