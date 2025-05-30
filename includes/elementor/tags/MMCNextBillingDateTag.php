<?php
/**
 * MMC Membership Next Billing Date Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MMC Membership Next Billing Date Tag
 */
class MMCMembershipNextBillingDateTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'mmc-membership-next-billing-date';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('MMC Next Billing Date', 'mmc-membership');
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
                'label' => esc_html__('Prefix', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Next billing date: ', 'mmc-membership'),
            ]
        );

        $this->add_control(
            'date_format',
            [
                'label' => esc_html__('Date Format', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'F j, Y',
                'description' => esc_html__('PHP date format. See php.net/manual/datetime.format.php', 'mmc-membership'),
            ]
        );

        $this->add_control(
            'no_date_text',
            [
                'label' => esc_html__('Not Found Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('No billing date available', 'mmc-membership'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $prefix = $this->get_settings('prefix');
        $date_format = $this->get_settings('date_format');
        $no_date_text = $this->get_settings('no_date_text');

        // Build the shortcode
        $shortcode = '[mmc_membership_next_billing_date';
        $shortcode .= ' prefix="' . esc_attr($prefix) . '"';
        $shortcode .= ' date_format="' . esc_attr($date_format) . '"';
        $shortcode .= ' no_date_text="' . esc_attr($no_date_text) . '"';
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
