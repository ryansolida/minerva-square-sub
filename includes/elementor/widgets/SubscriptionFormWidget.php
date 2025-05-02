<?php
/**
 * Subscription Form Widget for Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service Subscription Form Widget
 */
class SquareServiceSubscriptionFormWidget extends \Elementor\Widget_Base {
    /**
     * Get widget name
     *
     * @return string
     */
    public function get_name() {
        return 'square-subscription-form';
    }

    /**
     * Get widget title
     *
     * @return string
     */
    public function get_title() {
        return esc_html__('Square Subscription Form', 'square-service');
    }

    /**
     * Get widget icon
     *
     * @return string
     */
    public function get_icon() {
        return 'eicon-form-horizontal';
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
        return ['square', 'subscription', 'form', 'payment', 'membership'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Form Settings', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => esc_html__('Form Title', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Subscribe to our Exclusive Club', 'square-service'),
            ]
        );

        $this->add_control(
            'description',
            [
                'label' => esc_html__('Description', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('Join our exclusive club for just $8.99/month', 'square-service'),
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => esc_html__('Button Text', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Subscribe Now', 'square-service'),
            ]
        );

        $this->add_control(
            'plan_id',
            [
                'label' => esc_html__('Plan ID', 'square-service'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'description' => esc_html__('Optional Square plan ID. Leave empty to use default plan.', 'square-service'),
            ]
        );

        $this->add_control(
            'redirect_url',
            [
                'label' => esc_html__('Redirect URL', 'square-service'),
                'type' => \Elementor\Controls_Manager::URL,
                'description' => esc_html__('Optional URL to redirect after successful subscription.', 'square-service'),
                'placeholder' => esc_html__('https://your-site.com/thank-you', 'square-service'),
                'show_external' => false,
            ]
        );

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('Form Style', 'square-service'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => esc_html__('Title Typography', 'square-service'),
                'selector' => '{{WRAPPER}} .square-subscription-form h3',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'label' => esc_html__('Description Typography', 'square-service'),
                'selector' => '{{WRAPPER}} .square-subscription-form p',
            ]
        );

        $this->add_control(
            'form_padding',
            [
                'label' => esc_html__('Form Padding', 'square-service'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .square-subscription-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
            'form_background_color',
            [
                'label' => esc_html__('Background Color', 'square-service'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .square-subscription-form' => 'background-color: {{VALUE}}',
                ],
                'default' => '#f9f9f9',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'label' => esc_html__('Border', 'square-service'),
                'selector' => '{{WRAPPER}} .square-subscription-form',
            ]
        );

        $this->add_control(
            'form_border_radius',
            [
                'label' => esc_html__('Border Radius', 'square-service'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .square-subscription-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                'name' => 'form_box_shadow',
                'label' => esc_html__('Box Shadow', 'square-service'),
                'selector' => '{{WRAPPER}} .square-subscription-form',
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
                'selector' => '{{WRAPPER}} #submit-button',
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
                    '{{WRAPPER}} #submit-button' => 'background-color: {{VALUE}}',
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
                    '{{WRAPPER}} #submit-button' => 'color: {{VALUE}}',
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
                    '{{WRAPPER}} #submit-button:hover' => 'background-color: {{VALUE}}',
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
                    '{{WRAPPER}} #submit-button:hover' => 'color: {{VALUE}}',
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
                    '{{WRAPPER}} #submit-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
                    '{{WRAPPER}} #submit-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Prepare redirect URL if set
        $redirect_url = '';
        if (!empty($settings['redirect_url']['url'])) {
            $redirect_url = $settings['redirect_url']['url'];
        }

        // Build the shortcode
        $shortcode = '[square_subscription_form';
        $shortcode .= ' title="' . esc_attr($settings['title']) . '"';
        $shortcode .= ' description="' . esc_attr($settings['description']) . '"';
        $shortcode .= ' button_text="' . esc_attr($settings['button_text']) . '"';
        
        if (!empty($settings['plan_id'])) {
            $shortcode .= ' plan_id="' . esc_attr($settings['plan_id']) . '"';
        }
        
        if (!empty($redirect_url)) {
            $shortcode .= ' redirect_url="' . esc_url($redirect_url) . '"';
        }
        
        $shortcode .= ']';

        // Output the shortcode
        echo do_shortcode($shortcode);
    }
}
