<?php
/**
 * Payment Methods Widget for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service Payment Methods Widget
 */
class SquareServicePaymentMethodsWidget extends \Elementor\Widget_Base {
    /**
     * Get widget name
     *
     * @return string
     */
    public function get_name() {
        return 'square-payment-methods';
    }

    /**
     * Get widget title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Square Payment Methods', 'square-service');
    }

    /**
     * Get widget icon
     *
     * @return string
     */
    public function get_icon() {
        return 'eicon-credit-card';
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
        return ['square', 'payment', 'credit card', 'membership'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Payment Methods Settings', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => esc_html__('Section Title', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Your Payment Methods', 'square-service'),
            ]
        );

        $this->add_control(
            'add_button_text',
            [
                'label' => esc_html__('Add Button Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Add New Card', 'square-service'),
            ]
        );

        $this->add_control(
            'delete_button_text',
            [
                'label' => esc_html__('Delete Button Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Delete', 'square-service'),
            ]
        );

        $this->add_control(
            'not_logged_in_text',
            [
                'label' => esc_html__('Not Logged In Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('Please log in to manage payment methods', 'square-service'),
            ]
        );

        $this->add_control(
            'no_methods_text',
            [
                'label' => esc_html__('No Methods Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('You have no payment methods on file', 'square-service'),
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

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => esc_html__('Title Typography', 'square-service'),
                'selector' => '{{WRAPPER}} .square-payment-methods h3',
            ]
        );

        $this->add_control(
            'container_padding',
            [
                'label' => esc_html__('Container Padding', 'square-service'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .square-payment-methods' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
            'container_background_color',
            [
                'label' => esc_html__('Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .square-payment-methods' => 'background-color: {{VALUE}}',
                ],
                'default' => '#f9f9f9',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'label' => esc_html__('Border', 'square-service'),
                'selector' => '{{WRAPPER}} .square-payment-methods',
            ]
        );

        $this->add_control(
            'container_border_radius',
            [
                'label' => esc_html__('Border Radius', 'square-service'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .square-payment-methods' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '5',
                    'right' => '5',
                    'bottom' => '5',
                    'left' => '5',
                    'unit' => 'px',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'label' => esc_html__('Box Shadow', 'square-service'),
                'selector' => '{{WRAPPER}} .square-payment-methods',
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
                'selector' => '{{WRAPPER}} .add-card-btn',
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
                    '{{WRAPPER}} .add-card-btn' => 'background-color: {{VALUE}}',
                ],
                'default' => '#1e88e5',
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => esc_html__('Text Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .add-card-btn' => 'color: {{VALUE}}',
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
                    '{{WRAPPER}} .add-card-btn:hover' => 'background-color: {{VALUE}}',
                ],
                'default' => '#1976d2',
            ]
        );

        $this->add_control(
            'button_text_hover_color',
            [
                'label' => esc_html__('Text Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .add-card-btn:hover' => 'color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        // Delete Button Style
        $this->start_controls_section(
            'delete_button_style_section',
            [
                'label' => esc_html__('Delete Button Style', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'delete_button_typography',
                'label' => esc_html__('Typography', 'square-service'),
                'selector' => '{{WRAPPER}} .delete-card-btn',
            ]
        );

        $this->start_controls_tabs('delete_button_style_tabs');

        $this->start_controls_tab(
            'delete_button_normal_tab',
            [
                'label' => esc_html__('Normal', 'square-service'),
            ]
        );

        $this->add_control(
            'delete_button_background_color',
            [
                'label' => esc_html__('Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .delete-card-btn' => 'background-color: {{VALUE}}',
                ],
                'default' => '#f44336',
            ]
        );

        $this->add_control(
            'delete_button_text_color',
            [
                'label' => esc_html__('Text Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .delete-card-btn' => 'color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'delete_button_hover_tab',
            [
                'label' => esc_html__('Hover', 'square-service'),
            ]
        );

        $this->add_control(
            'delete_button_background_hover_color',
            [
                'label' => esc_html__('Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .delete-card-btn:hover' => 'background-color: {{VALUE}}',
                ],
                'default' => '#e53935',
            ]
        );

        $this->add_control(
            'delete_button_text_hover_color',
            [
                'label' => esc_html__('Text Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .delete-card-btn:hover' => 'color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Build the shortcode
        $shortcode = '[square_payment_methods';
        $shortcode .= ' title="' . esc_attr($settings['title']) . '"';
        $shortcode .= ' add_button_text="' . esc_attr($settings['add_button_text']) . '"';
        $shortcode .= ' delete_button_text="' . esc_attr($settings['delete_button_text']) . '"';
        $shortcode .= ' not_logged_in_text="' . esc_attr($settings['not_logged_in_text']) . '"';
        $shortcode .= ' no_methods_text="' . esc_attr($settings['no_methods_text']) . '"';
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
