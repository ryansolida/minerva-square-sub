<?php
/**
 * Has Active Membership Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MMC Has Active Membership Tag
 */
class MMC_HasActiveMembershipTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'mmc-has-active-membership';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Has Active Membership', 'mmc-membership');
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
            'yes_text',
            [
                'label' => esc_html__('Yes Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Yes', 'mmc-membership'),
                'description' => esc_html__('Text to display when user has an active membership', 'mmc-membership'),
            ]
        );

        $this->add_control(
            'no_text',
            [
                'label' => esc_html__('No Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('No', 'mmc-membership'),
                'description' => esc_html__('Text to display when user does not have an active membership', 'mmc-membership'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $yes_text = $this->get_settings('yes_text');
        $no_text = $this->get_settings('no_text');
        
        // Build the shortcode
        $shortcode = '[mmc_has_active_membership';
        $shortcode .= ' yes_text="' . esc_attr($yes_text) . '"';
        $shortcode .= ' no_text="' . esc_attr($no_text) . '"';
        $shortcode .= ']';
        
        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
