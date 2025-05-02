<?php
/**
 * Membership Status Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service Membership Status Tag
 */
class SquareServiceMembershipStatusTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'square-membership-status';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Membership Status', 'square-service');
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
            'active_text',
            [
                'label' => esc_html__('Active Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Your membership is active', 'square-service'),
            ]
        );

        $this->add_control(
            'inactive_text',
            [
                'label' => esc_html__('Inactive Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('You do not have an active membership', 'square-service'),
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

        // Build the shortcode
        $shortcode = '[square_membership_status';
        $shortcode .= ' active_text="' . esc_attr($active_text) . '"';
        $shortcode .= ' inactive_text="' . esc_attr($inactive_text) . '"';
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
