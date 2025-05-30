<?php
/**
 * MMC Membership Status Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MMC Membership Status Tag
 */
class MMCMembershipStatusTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'mmc-membership-status';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('MMC Membership Status', 'mmc-membership');
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
            'active_text',
            [
                'label' => esc_html__('Active Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Active', 'mmc-membership'),
            ]
        );

        $this->add_control(
            'inactive_text',
            [
                'label' => esc_html__('Inactive Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Inactive', 'mmc-membership'),
            ]
        );

        $this->add_control(
            'not_logged_in_text',
            [
                'label' => esc_html__('Not Logged In Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Please log in to see membership status', 'mmc-membership'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $active_text = $this->get_settings('active_text');
        $inactive_text = $this->get_settings('inactive_text');
        $not_logged_in_text = $this->get_settings('not_logged_in_text');

        // Build the shortcode
        $shortcode = '[mmc_has_active_membership';
        $shortcode .= ' yes_text="' . esc_attr($active_text) . '"';
        $shortcode .= ' no_text="' . esc_attr($inactive_text) . '"';
        $shortcode .= ']';

        // Check if user is logged in
        if (is_user_logged_in()) {
            // Output the shortcode
            echo do_shortcode($shortcode);
        } else {
            echo esc_html($not_logged_in_text);
        }
    }
}
