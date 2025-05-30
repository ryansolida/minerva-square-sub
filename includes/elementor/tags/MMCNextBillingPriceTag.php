<?php
/**
 * MMC Membership Next Billing Price Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MMC Membership Next Billing Price Tag
 */
class MMCMembershipNextBillingPriceTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'mmc-membership-next-billing-price';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('MMC Next Billing Price', 'mmc-membership');
    }

    /**
     * Get tag group
     *
     * @return string
     */
    public function get_group() {
        return 'square-service';
    }

    /**
     * Get tag categories
     *
     * @return array
     */
    public function get_categories() {
        return ['text'];
    }

    /**
     * Register controls
     */
    protected function register_controls() {
        $this->add_control(
            'currency_symbol',
            [
                'label' => esc_html__('Currency Symbol', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '$',
            ]
        );

        $this->add_control(
            'prefix',
            [
                'label' => esc_html__('Prefix', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $this->add_control(
            'suffix',
            [
                'label' => esc_html__('Suffix', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $this->add_control(
            'no_price_text',
            [
                'label' => esc_html__('Not Found Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('No price available', 'mmc-membership'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $currency_symbol = $this->get_settings('currency_symbol');
        $prefix = $this->get_settings('prefix');
        $suffix = $this->get_settings('suffix');
        $no_price_text = $this->get_settings('no_price_text');

        // Build the shortcode
        $shortcode = '[mmc_membership_next_billing_price';
        $shortcode .= ' currency_symbol="' . esc_attr($currency_symbol) . '"';
        $shortcode .= ' prefix="' . esc_attr($prefix) . '"';
        $shortcode .= ' suffix="' . esc_attr($suffix) . '"';
        $shortcode .= ' no_price_text="' . esc_attr($no_price_text) . '"';
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
