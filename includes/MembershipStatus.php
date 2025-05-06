<?php
/**
 * Square Membership Status with Alpine.js
 * 
 * A standalone shortcode to display subscription/membership status
 * with reactive UI powered by Alpine.js
 */

namespace MMCMembership;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MembershipStatus {
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
        add_shortcode('membership_status', array($this, 'render_shortcode'));
        add_action('wp_ajax_cancel_subscription', array($this, 'handle_cancel_ajax'));
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
            'end_date' => get_user_meta($user_id, 'square_subscription_end_date', true),
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
            $square_service = SquareService::get_instance();
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
        ), $atts, 'membership');
        
        // If no subscribe_url is provided, use the one from settings
        if (empty($atts['subscribe_url'])) {
            $atts['subscribe_url'] = get_membership_signup_url();
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="text-red-600 font-semibold">Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view membership status.</div>';
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
            class="max-w-lg mx-auto my-6 p-6 bg-white rounded-lg shadow-md" 
            x-data="membershipStatus({
                subscriptionId: '<?php echo esc_js($has_subscription ? $subscription['id'] : ''); ?>',
                isActive: <?php echo $is_active ? 'true' : 'false'; ?>,
                ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('square-alpine-membership-nonce')); ?>',
                subscribeUrl: '<?php echo esc_js($atts['subscribe_url']); ?>',
                paymentMethodsUrl: '<?php echo esc_js($atts['payment_methods_url']); ?>'
            })"
        >
            <h3 class="text-xl font-semibold text-gray-800 mb-4"><?php echo esc_html($atts['title']); ?></h3>
            
            <!-- Subscription Status -->
            <div class="mb-6 pb-6 border-b border-gray-200 last:border-b-0">
                <h4 class="text-lg font-medium text-gray-700 mb-3">Subscription Status</h4>
                
                <?php if ($has_subscription): ?>
                    <div class="flex mb-2">
                        <div class="font-medium w-32 text-gray-600 flex-shrink-0">Status:</div>
                        <div class="text-gray-800">
                            <span class="<?php echo $is_active ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!$is_active && !empty($atts['subscribe_url'])): ?>
                    <div class="mt-4">
                        <p class="mb-2 text-gray-600">Your membership is currently inactive. Would you like to reactivate it?</p>
                        <a href="<?php echo esc_url($atts['subscribe_url']); ?>" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200">
                            Sign Up Again
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($subscription['start_date'])): ?>
                    <div class="flex mb-2">
                        <div class="font-medium w-32 text-gray-600 flex-shrink-0">Start Date:</div>
                        <div class="text-gray-800">
                            <?php echo esc_html($this->format_date($subscription['start_date'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_active && !empty($subscription['end_date'])): ?>
                    <div class="flex mb-2">
                        <div class="font-medium w-32 text-gray-600 flex-shrink-0">Active Until:</div>
                        <div class="text-gray-800">
                            <?php echo esc_html($this->format_date($subscription['end_date'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex mb-2">
                        <div class="font-medium w-32 text-gray-600 flex-shrink-0">Subscription ID:</div>
                        <div class="text-gray-800">
                            <?php echo esc_html($subscription['id']); ?>
                        </div>
                    </div>
                    
                    <!-- Cancelation Actions -->
                    <div class="mt-4" x-show="isActive">
                        <button 
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-md transition-colors duration-200" 
                            @click="showCancelConfirmation = true"
                            x-show="!showCancelConfirmation && !loading && !message"
                        >
                            <?php echo esc_html($atts['cancel_button_text']); ?>
                        </button>
                        
                        <div 
                            class="bg-red-50 p-4 rounded-md border border-red-200 my-4" 
                            x-show="showCancelConfirmation"
                        >
                            <p class="mb-4 text-red-700">Are you sure you want to cancel your membership? This will end your subscription immediately.</p>
                            <div class="flex space-x-3">
                                <button 
                                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-md transition-colors duration-200"
                                    @click="cancelSubscription"
                                    :disabled="loading"
                                >
                                    <span x-show="loading" class="animate-spin h-4 w-4 border-2 border-white border-t-4 rounded-full"></span>
                                    <span x-text="loading ? 'Processing...' : '<?php echo esc_js($atts['confirm_cancel_text']); ?>'"></span>
                                </button>
                                <button 
                                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-md transition-colors duration-200"
                                    @click="showCancelConfirmation = false"
                                    :disabled="loading"
                                >
                                    <?php echo esc_html($atts['keep_membership_text']); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div 
                            class="p-4 rounded-md my-4"
                            :class="messageType"
                            x-show="message"
                            x-text="message"
                        ></div>
                    </div>
                    
                <?php else: ?>
                    <div class="flex mb-2">
                        <div class="text-gray-800">
                            <span class="text-red-600 font-semibold">No Active Subscription</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($atts['subscribe_url'])): ?>
                    <div class="mt-4">
                        <a href="<?php echo esc_url($atts['subscribe_url']); ?>" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200">
                            <?php echo esc_html($atts['upgrade_button_text']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Payment Method -->
            <?php if ($has_subscription && $card): ?>
            <div class="mb-6 pb-6 border-b border-gray-200 last:border-b-0">
                <h4 class="text-lg font-medium text-gray-700 mb-3">Payment Method</h4>
                
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div>
                        <span class="text-green-600 font-semibold"><?php echo esc_html($card['brand']); ?></span>
                        <span>•••• <?php echo esc_html($card['last4']); ?></span>
                    </div>
                    <div class="text-gray-600 text-sm">
                        Expires <?php echo esc_html($card['exp_month']); ?>/<?php echo esc_html($card['exp_year']); ?>
                    </div>
                </div>
                
                <?php if (!empty($atts['payment_methods_url'])): ?>
                <div>
                    <a href="<?php echo esc_url($atts['payment_methods_url']); ?>" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200">
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
                                action: 'cancel_subscription',
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
            $square_service = SquareService::get_instance();
            $subscription_id = sanitize_text_field($_POST['subscription_id']);
            $result = $square_service->cancelSubscription($subscription_id);
            
            if ($result) {
                // Call UserFunctions to properly set inactive membership status
                UserFunctions::set_inactive_membership($user_id);
                
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
$membership_status = MembershipStatus::get_instance();

