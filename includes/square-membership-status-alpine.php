<?php
/**
 * Square Membership Status with Alpine.js
 * 
 * A standalone shortcode to display subscription/membership status
 * with reactive UI powered by Alpine.js
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SquareMembershipStatusAlpine {
    private static $instance = null;
    
    /**
     * Singleton pattern implementation
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_shortcode('square_alpine_membership', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_square_alpine_cancel_subscription', array($this, 'handle_cancel_ajax'));
    }
    
    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with our shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'square_alpine_membership')) {
            return;
        }
        
        // Enqueue Alpine.js from CDN
        wp_enqueue_script('alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js', array(), null, true);
        
        // Add inline styles
        wp_add_inline_style('wp-block-library', $this->get_inline_css());
    }
    
    /**
     * Get inline CSS for the status display
     */
    private function get_inline_css() {
        return '
            .square-alpine-status {
                max-width: 600px;
                margin: 20px 0;
                padding: 20px;
                background-color: #f9f9f9;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            
            .square-alpine-status h3 {
                margin-top: 0;
                color: #333;
                font-size: 1.5em;
                margin-bottom: 15px;
            }
            
            .square-alpine-status-section {
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            
            .square-alpine-status-section:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .square-alpine-status-section h4 {
                margin-top: 0;
                color: #555;
                font-size: 1.2em;
            }
            
            .square-alpine-status-detail {
                display: flex;
                margin-bottom: 8px;
            }
            
            .square-alpine-detail-label {
                font-weight: bold;
                width: 140px;
                color: #666;
                flex-shrink: 0;
            }
            
            .square-alpine-detail-value {
                color: #333;
            }
            
            .square-alpine-status-active {
                color: #4CAF50;
                font-weight: bold;
            }
            
            .square-alpine-status-inactive {
                color: #F44336;
                font-weight: bold;
            }
            
            .square-alpine-button {
                background-color: #4CAF50;
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.3s;
            }
            
            .square-alpine-button:hover {
                background-color: #45a049;
            }
            
            .square-alpine-button.cancel {
                background-color: #F44336;
            }
            
            .square-alpine-button.cancel:hover {
                background-color: #d32f2f;
            }
            
            .square-alpine-button:disabled {
                background-color: #cccccc;
                cursor: not-allowed;
            }
            
            .square-alpine-loading {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                border-top-color: white;
                animation: spin 1s ease-in-out infinite;
                margin-right: 10px;
                vertical-align: middle;
            }
            
            .square-alpine-message {
                margin-top: 15px;
                padding: 10px;
                border-radius: 4px;
            }
            
            .square-alpine-message.success {
                background-color: #E8F5E9;
                color: #388E3C;
                border: 1px solid #C8E6C9;
            }
            
            .square-alpine-message.error {
                background-color: #FFEBEE;
                color: #D32F2F;
                border: 1px solid #FFCDD2;
            }
            
            .square-alpine-confirmation {
                margin-top: 15px;
                padding: 15px;
                background-color: #FFF8E1;
                border: 1px solid #FFE082;
                border-radius: 4px;
            }
            
            .square-alpine-confirmation-actions {
                margin-top: 10px;
                display: flex;
                gap: 10px;
            }
            
            .square-alpine-card {
                background-color: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .square-alpine-card-brand {
                font-weight: bold;
                margin-right: 10px;
            }
            
            .square-alpine-card-expiry {
                color: #666;
                font-size: 0.9em;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        ';
    }
    
    /**
     * Check if user has active membership
     */
    private function user_has_membership() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $active = get_user_meta($user_id, 'square_active_membership', true);
        return $active === 'yes';
    }
    
    /**
     * Get subscription data
     */
    private function get_subscription_data() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }
        
        $subscription_id = get_user_meta($user_id, 'square_subscription_id', true);
        if (!$subscription_id) {
            return null;
        }
        
        $customer_id = get_user_meta($user_id, 'square_customer_id', true);
        $card_id = get_user_meta($user_id, 'square_card_id', true);
        $plan_id = get_user_meta($user_id, 'square_subscription_plan_id', true);
        
        return [
            'id' => $subscription_id,
            'customer_id' => $customer_id,
            'card_id' => $card_id,
            'plan_id' => $plan_id,
            'start_date' => get_user_meta($user_id, 'square_subscription_start_date', true),
            'status' => $this->user_has_membership() ? 'active' : 'inactive'
        ];
    }
    
    /**
     * Get card data
     */
    private function get_card_data($card_id, $customer_id) {
        if (empty($card_id) || empty($customer_id)) {
            return null;
        }
        
        try {
            $square_service = $this->get_square_service();
            $cards = $square_service->getCustomerCards($customer_id);
            
            foreach ($cards as $card) {
                if ($card->getId() == $card_id) {
                    return [
                        'id' => $card->getId(),
                        'brand' => $card->getCardBrand(),
                        'last4' => $card->getLast4(),
                        'exp_month' => $card->getExpMonth(),
                        'exp_year' => $card->getExpYear()
                    ];
                }
            }
        } catch (Exception $e) {
            return null;
        }
        
        return null;
    }
    
    /**
     * Get a Square service instance
     */
    private function get_square_service() {
        return new SquareService();
    }
    
    /**
     * Format date for display
     */
    private function format_date($date) {
        if (empty($date)) {
            return 'N/A';
        }
        
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return $date;
        }
        
        return date('F j, Y', $timestamp);
    }
    
    /**
     * Render the membership status shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Membership Status',
            'subscribe_url' => '',
            'cancel_button_text' => 'Cancel Membership',
            'confirm_cancel_text' => 'Yes, Cancel',
            'keep_membership_text' => 'Keep Membership',
            'upgrade_button_text' => 'Upgrade Your Membership',
            'manage_payment_text' => 'Manage Payment Methods',
            'payment_methods_url' => ''
        ), $atts, 'square_alpine_membership');
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="square-alpine-status-error">Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view membership status.</div>';
        }
        
        // Get subscription data
        $subscription = $this->get_subscription_data();
        $has_subscription = !empty($subscription);
        $is_active = $has_subscription && $subscription['status'] === 'active';
        
        // Get card data if available
        $card = null;
        if ($has_subscription && !empty($subscription['card_id']) && !empty($subscription['customer_id'])) {
            $card = $this->get_card_data($subscription['card_id'], $subscription['customer_id']);
        }
        
        // Generate a unique ID for this instance
        $container_id = 'square-alpine-status-' . uniqid();
        
        // Create Alpine.js app with status display
        ob_start();
        ?>
        <div 
            id="<?php echo esc_attr($container_id); ?>" 
            class="square-alpine-status" 
            x-data="membershipStatus({
                subscriptionId: '<?php echo esc_js($has_subscription ? $subscription['id'] : ''); ?>',
                isActive: <?php echo $is_active ? 'true' : 'false'; ?>,
                ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('square-alpine-membership-nonce')); ?>',
                subscribeUrl: '<?php echo esc_js($atts['subscribe_url']); ?>',
                paymentMethodsUrl: '<?php echo esc_js($atts['payment_methods_url']); ?>'
            })"
        >
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <!-- Subscription Status -->
            <div class="square-alpine-status-section">
                <h4>Subscription Status</h4>
                
                <?php if ($has_subscription): ?>
                    <div class="square-alpine-status-detail">
                        <div class="square-alpine-detail-label">Status:</div>
                        <div class="square-alpine-detail-value">
                            <span class="<?php echo $is_active ? 'square-alpine-status-active' : 'square-alpine-status-inactive'; ?>">
                                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($subscription['start_date'])): ?>
                    <div class="square-alpine-status-detail">
                        <div class="square-alpine-detail-label">Start Date:</div>
                        <div class="square-alpine-detail-value">
                            <?php echo esc_html($this->format_date($subscription['start_date'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="square-alpine-status-detail">
                        <div class="square-alpine-detail-label">Subscription ID:</div>
                        <div class="square-alpine-detail-value">
                            <?php echo esc_html($subscription['id']); ?>
                        </div>
                    </div>
                    
                    <!-- Cancelation Actions -->
                    <div style="margin-top: 15px;" x-show="isActive">
                        <button 
                            class="square-alpine-button cancel" 
                            @click="showCancelConfirmation = true"
                            x-show="!showCancelConfirmation && !loading && !message"
                        >
                            <?php echo esc_html($atts['cancel_button_text']); ?>
                        </button>
                        
                        <div 
                            class="square-alpine-confirmation" 
                            x-show="showCancelConfirmation"
                        >
                            <p>Are you sure you want to cancel your membership? This will end your subscription immediately.</p>
                            <div class="square-alpine-confirmation-actions">
                                <button 
                                    class="square-alpine-button cancel"
                                    @click="cancelSubscription"
                                    :disabled="loading"
                                >
                                    <span x-show="loading" class="square-alpine-loading"></span>
                                    <span x-text="loading ? 'Processing...' : '<?php echo esc_js($atts['confirm_cancel_text']); ?>'"></span>
                                </button>
                                <button 
                                    class="square-alpine-button"
                                    @click="showCancelConfirmation = false"
                                    :disabled="loading"
                                >
                                    <?php echo esc_html($atts['keep_membership_text']); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div 
                            class="square-alpine-message"
                            :class="messageType"
                            x-show="message"
                            x-text="message"
                        ></div>
                    </div>
                    
                <?php else: ?>
                    <div class="square-alpine-status-detail">
                        <div class="square-alpine-detail-value">
                            <span class="square-alpine-status-inactive">No Active Subscription</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($atts['subscribe_url'])): ?>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo esc_url($atts['subscribe_url']); ?>" class="square-alpine-button">
                            <?php echo esc_html($atts['upgrade_button_text']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Payment Method -->
            <?php if ($has_subscription && $card): ?>
            <div class="square-alpine-status-section">
                <h4>Payment Method</h4>
                
                <div class="square-alpine-card">
                    <div>
                        <span class="square-alpine-card-brand"><?php echo esc_html($card['brand']); ?></span>
                        <span>•••• <?php echo esc_html($card['last4']); ?></span>
                    </div>
                    <div class="square-alpine-card-expiry">
                        Expires <?php echo esc_html($card['exp_month']); ?>/<?php echo esc_html($card['exp_year']); ?>
                    </div>
                </div>
                
                <?php if (!empty($atts['payment_methods_url'])): ?>
                <div>
                    <a href="<?php echo esc_url($atts['payment_methods_url']); ?>" class="square-alpine-button">
                        <?php echo esc_html($atts['manage_payment_text']); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('membershipStatus', (config) => ({
                    subscriptionId: config.subscriptionId,
                    isActive: config.isActive,
                    ajaxUrl: config.ajaxUrl,
                    nonce: config.nonce,
                    subscribeUrl: config.subscribeUrl,
                    paymentMethodsUrl: config.paymentMethodsUrl,
                    showCancelConfirmation: false,
                    loading: false,
                    message: '',
                    messageType: '',
                    
                    cancelSubscription() {
                        this.loading = true;
                        this.message = '';
                        
                        fetch(this.ajaxUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'square_alpine_cancel_subscription',
                                subscription_id: this.subscriptionId,
                                nonce: this.nonce
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            this.loading = false;
                            this.showCancelConfirmation = false;
                            
                            if (result.success) {
                                this.message = result.data.message;
                                this.messageType = 'success';
                                this.isActive = false;
                                
                                // Reload the page after a delay
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                this.message = result.data.message;
                                this.messageType = 'error';
                            }
                        })
                        .catch(error => {
                            this.loading = false;
                            this.showCancelConfirmation = false;
                            this.message = 'An error occurred. Please try again.';
                            this.messageType = 'error';
                            console.error('Error:', error);
                        });
                    }
                }));
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle subscription cancellation AJAX requests
     */
    public function handle_cancel_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'square-alpine-membership-nonce')) {
            wp_send_json_error(['message' => 'Invalid security token.']);
            exit;
        }
        
        // Check for subscription ID
        if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
            wp_send_json_error(['message' => 'Subscription ID is required.']);
            exit;
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to cancel a subscription.']);
            exit;
        }
        
        // Verify that the subscription belongs to this user
        $user_subscription_id = get_user_meta($user_id, 'square_subscription_id', true);
        if ($user_subscription_id !== $_POST['subscription_id']) {
            wp_send_json_error(['message' => 'You do not have permission to cancel this subscription.']);
            exit;
        }
        
        try {
            // Get the card ID associated with the subscription
            $card_id = get_user_meta($user_id, 'square_card_id', true);
            $customer_id = get_user_meta($user_id, 'square_customer_id', true);
            
            // Cancel the subscription in Square
            $square_service = $this->get_square_service();
            $subscription_id = sanitize_text_field($_POST['subscription_id']);
            $result = $square_service->cancelSubscription($subscription_id);
            
            if ($result) {
                // Update user metadata
                update_user_meta($user_id, 'square_active_membership', 'no');
                
                // Delete the card on file if we have both card ID and customer ID
                if (!empty($card_id) && !empty($customer_id)) {
                    try {
                        $square_service->deleteCustomerCard($customer_id, $card_id);
                        // Clear the card ID from user meta
                        update_user_meta($user_id, 'square_card_id', '');
                    } catch (Exception $card_error) {
                        // Log the error but continue - subscription was already canceled
                        error_log('Failed to delete card: ' . $card_error->getMessage());
                    }
                }
                
                wp_send_json_success([
                    'message' => 'Your membership has been successfully canceled and your payment method removed.'
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to cancel subscription. Please try again.']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
        
        exit;
    }
}

// Initialize the shortcode
function square_membership_status_alpine() {
    return SquareMembershipStatusAlpine::get_instance();
}

square_membership_status_alpine();
