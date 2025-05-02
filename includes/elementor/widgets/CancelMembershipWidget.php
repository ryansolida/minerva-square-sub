<?php
/**
 * Cancel Membership Widget for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service Cancel Membership Widget
 */
class SquareServiceCancelMembershipWidget extends \Elementor\Widget_Base {
    /**
     * Get widget name
     *
     * @return string
     */
    public function get_name() {
        return 'square-cancel-membership';
    }

    /**
     * Get widget title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Square Cancel Membership', 'square-service');
    }

    /**
     * Get widget icon
     *
     * @return string
     */
    public function get_icon() {
        return 'eicon-close-circle';
    }

    /**
     * Get widget categories
     *
     * @return array
     */
    public function get_categories() {
        return ['general'];
    }

    /**
     * Get widget keywords
     *
     * @return array
     */
    public function get_keywords() {
        return ['square', 'cancel', 'membership', 'subscription'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Cancel Membership Settings', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => esc_html__('Button Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Cancel Membership', 'square-service'),
            ]
        );

        $this->add_control(
            'confirm_text',
            [
                'label' => esc_html__('Confirmation Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('Are you sure you want to cancel your membership? This action cannot be undone.', 'square-service'),
            ]
        );

        $this->add_control(
            'success_text',
            [
                'label' => esc_html__('Success Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Your membership has been canceled successfully.', 'square-service'),
            ]
        );

        $this->add_control(
            'no_subscription_text',
            [
                'label' => esc_html__('No Subscription Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('You do not have an active membership to cancel.', 'square-service'),
            ]
        );

        $this->add_control(
            'not_logged_in_text',
            [
                'label' => esc_html__('Not Logged In Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Please log in to manage your membership.', 'square-service'),
            ]
        );

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('Widget Style', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_padding',
            [
                'label' => esc_html__('Container Padding', 'square-service'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .square-cancel-membership' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '20',
                    'right' => '20',
                    'bottom' => '20',
                    'left' => '20',
                    'unit' => 'px',
                ],
            ]
        );

        $this->add_control(
            'confirmation_background_color',
            [
                'label' => esc_html__('Confirmation Background', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} #cancel-confirmation' => 'background-color: {{VALUE}}',
                ],
                'default' => '#ffebee',
            ]
        );

        $this->add_control(
            'confirmation_border_color',
            [
                'label' => esc_html__('Confirmation Border', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} #cancel-confirmation' => 'border-color: {{VALUE}}',
                ],
                'default' => '#ef9a9a',
            ]
        );

        $this->end_controls_section();

        // Button Style
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => esc_html__('Button Style', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'label' => esc_html__('Typography', 'square-service'),
                'selector' => '{{WRAPPER}} .cancel-membership-button',
            ]
        );

        $this->start_controls_tabs('button_style_tabs');

        $this->start_controls_tab(
            'button_normal_tab',
            [
                'label' => esc_html__('Normal', 'square-service'),
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => esc_html__('Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cancel-membership-button' => 'background-color: {{VALUE}}',
                ],
                'default' => '#e53935',
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => esc_html__('Text Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cancel-membership-button' => 'color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover_tab',
            [
                'label' => esc_html__('Hover', 'square-service'),
            ]
        );

        $this->add_control(
            'button_background_hover_color',
            [
                'label' => esc_html__('Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cancel-membership-button:hover' => 'background-color: {{VALUE}}',
                ],
                'default' => '#d32f2f',
            ]
        );

        $this->add_control(
            'button_text_hover_color',
            [
                'label' => esc_html__('Text Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .cancel-membership-button:hover' => 'color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control(
            'button_border_radius',
            [
                'label' => esc_html__('Border Radius', 'square-service'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .cancel-membership-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '4',
                    'right' => '4',
                    'bottom' => '4',
                    'left' => '4',
                    'unit' => 'px',
                ],
            ]
        );

        $this->add_control(
            'button_padding',
            [
                'label' => esc_html__('Padding', 'square-service'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .cancel-membership-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '10',
                    'right' => '20',
                    'bottom' => '10',
                    'left' => '20',
                    'unit' => 'px',
                ],
            ]
        );

        $this->end_controls_section();

        // Confirm Button Style
        $this->start_controls_section(
            'confirm_button_style_section',
            [
                'label' => esc_html__('Confirm Button Style', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'confirm_button_background_color',
            [
                'label' => esc_html__('Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} #confirm-cancel-btn' => 'background-color: {{VALUE}}',
                ],
                'default' => '#e53935',
            ]
        );

        $this->add_control(
            'confirm_button_hover_background_color',
            [
                'label' => esc_html__('Hover Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} #confirm-cancel-btn:hover' => 'background-color: {{VALUE}}',
                ],
                'default' => '#d32f2f',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Build the shortcode
        $shortcode = '[square_cancel_membership';
        $shortcode .= ' button_text="' . esc_attr($settings['button_text']) . '"';
        $shortcode .= ' confirm_text="' . esc_attr($settings['confirm_text']) . '"';
        $shortcode .= ' success_text="' . esc_attr($settings['success_text']) . '"';
        $shortcode .= ' no_subscription_text="' . esc_attr($settings['no_subscription_text']) . '"';
        $shortcode .= ' not_logged_in_text="' . esc_attr($settings['not_logged_in_text']) . '"';
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
