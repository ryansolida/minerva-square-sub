<?php
/**
 * Membership Expiration Date Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MMC Membership Expiration Date Tag
 */
class MMC_MembershipExpirationDateTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'mmc-membership-expiration-date';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Membership Expiration Date', 'mmc-membership');
    }

    /**
     * Get tag group
     *
     * @return string
     */
    public function get_group() {
        return 'mmc-membership';
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
            'date_format',
            [
                'label' => esc_html__('Date Format', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'F j, Y',
                'options' => [
                    'F j, Y' => date('F j, Y'), // January 1, 2025
                    'Y-m-d' => date('Y-m-d'),   // 2025-01-01
                    'm/d/Y' => date('m/d/Y'),   // 01/01/2025
                    'd/m/Y' => date('d/m/Y'),   // 01/01/2025
                    'M j, Y' => date('M j, Y'), // Jan 1, 2025
                ],
            ]
        );

        $this->add_control(
            'no_date_text',
            [
                'label' => esc_html__('No Date Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('No active membership', 'mmc-membership'),
                'description' => esc_html__('Text to display when there is no expiration date', 'mmc-membership'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $date_format = $this->get_settings('date_format');
        $no_date_text = $this->get_settings('no_date_text');
        
        // Build the shortcode
        $shortcode = '[mmc_membership_expiration_date';
        $shortcode .= ' date_format="' . \esc_attr($date_format) . '"';
        $shortcode .= ' no_date_text="' . \esc_attr($no_date_text) . '"';
        $shortcode .= ']';
        
        // Output the shortcode
        echo \do_shortcode($shortcode);
    }
}
