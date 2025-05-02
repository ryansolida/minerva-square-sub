<?php
/**
 * Payment Card Info Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service Payment Card Info Tag
 */
class SquareServicePaymentCardInfoTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'square-payment-card-info';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Payment Card Info', 'square-service');
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
            'prefix',
            [
                'label' => esc_html__('Prefix', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Payment method: ', 'square-service'),
            ]
        );

        $this->add_control(
            'not_found_text',
            [
                'label' => esc_html__('Not Found Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('No payment method on file', 'square-service'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $prefix = $this->get_settings('prefix');
        $not_found_text = $this->get_settings('not_found_text');

        // Build the shortcode
        $shortcode = '[square_payment_card_info';
        $shortcode .= ' prefix="' . esc_attr($prefix) . '"';
        $shortcode .= ' not_found_text="' . esc_attr($not_found_text) . '"';
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
