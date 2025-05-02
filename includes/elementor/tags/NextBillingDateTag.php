<?php
/**
 * Next Billing Date Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service Next Billing Date Tag
 */
class SquareServiceNextBillingDateTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'square-next-billing-date';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Next Billing Date', 'square-service');
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
                'default' => esc_html__('Next billing date: ', 'square-service'),
            ]
        );

        $this->add_control(
            'format',
            [
                'label' => esc_html__('Date Format', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'F j, Y',
                'description' => esc_html__('PHP date format. See php.net/manual/datetime.format.php', 'square-service'),
            ]
        );

        $this->add_control(
            'not_found_text',
            [
                'label' => esc_html__('Not Found Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('No active subscription', 'square-service'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $prefix = $this->get_settings('prefix');
        $format = $this->get_settings('format');
        $not_found_text = $this->get_settings('not_found_text');

        // Build the shortcode
        $shortcode = '[square_next_billing_date';
        $shortcode .= ' prefix="' . esc_attr($prefix) . '"';
        $shortcode .= ' format="' . esc_attr($format) . '"';
        $shortcode .= ' not_found_text="' . esc_attr($not_found_text) . '"';
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
