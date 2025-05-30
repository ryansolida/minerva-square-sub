<?php
/**
 * MMC Membership Module for Elementor Dynamic Tags
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MMC Membership Module
 */
class MMCMembershipModule extends \Elementor\Core\DynamicTags\Module {
    /**
     * Get module name
     *
     * @return string
     */
    public function get_name() {
        return 'mmc-membership';
    }

    /**
     * Get module group
     *
     * @return string
     */
    public function get_group() {
        return 'mmc-membership';
    }

    /**
     * Get module widgets
     *
     * @return array
     */
    public function get_widgets() {
        return [
            'mmc-membership-status',
            'mmc-has-active-membership',
            'mmc-membership-expiration-date',
            'mmc-membership-activation-date',
            'mmc-next-billing-date',
            'mmc-next-billing-price',
            'mmc-payment-card-info'
        ];
    }
}
