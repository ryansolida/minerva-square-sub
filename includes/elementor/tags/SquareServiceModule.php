<?php
/**
 * Dynamic Tags Module for Square Service
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service Module for Elementor Dynamic Tags
 */
class SquareServiceModule extends \Elementor\Core\DynamicTags\Module {
    /**
     * Get module name
     *
     * @return string
     */
    public function get_name() {
        return 'square-service-tags';
    }

    /**
     * Get dynamic tags
     *
     * @return array
     */
    public function get_tags() {
        return [
            'SquareServiceMembershipStatusTag',
            'SquareServiceNextBillingDateTag',
            'SquareServiceNextPaymentAmountTag',
            'SquareServicePaymentCardInfoTag',
        ];
    }
}
