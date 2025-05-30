<?php
/**
 * MMC Payment Card Info Dynamic Tag for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MMC Payment Card Info Tag
 */
class MMCPaymentCardInfoTag extends \Elementor\Core\DynamicTags\Tag {
    /**
     * Get tag name
     *
     * @return string
     */
    public function get_name() {
        return 'mmc-payment-card-info';
    }

    /**
     * Get tag title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('MMC Payment Card Info', 'mmc-membership');
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
            'format',
            [
                'label' => esc_html__('Format', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('[brand] ending in [last4] (expires [month]/[year])', 'mmc-membership'),
                'description' => esc_html__('Available placeholders: [brand], [last4], [month], [year]', 'mmc-membership'),
            ]
        );

        $this->add_control(
            'no_card_text',
            [
                'label' => esc_html__('No Card Text', 'mmc-membership'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('No payment card on file', 'mmc-membership'),
            ]
        );
    }

    /**
     * Render tag output
     */
    public function render() {
        // Get settings
        $format = $this->get_settings('format');
        $no_card_text = $this->get_settings('no_card_text');

        // Check if user is logged in
        if (!\is_user_logged_in()) {
            echo esc_html($no_card_text);
            return;
        }

        // Get user ID
        $user_id = \get_current_user_id();
        
        // Get card details from user meta
        $card_brand = \get_user_meta($user_id, 'square_card_brand', true);
        $card_last4 = \get_user_meta($user_id, 'square_card_last4', true);
        $card_exp_month = \get_user_meta($user_id, 'square_card_exp_month', true);
        $card_exp_year = \get_user_meta($user_id, 'square_card_exp_year', true);
        
        // Check if we have card details
        if (empty($card_brand) || empty($card_last4)) {
            echo esc_html($no_card_text);
            return;
        }
        
        // Replace placeholders in format
        $output = $format;
        $output = str_replace('[brand]', $card_brand, $output);
        $output = str_replace('[last4]', $card_last4, $output);
        $output = str_replace('[month]', $card_exp_month, $output);
        $output = str_replace('[year]', $card_exp_year, $output);
        
        echo esc_html($output);
    }
}
