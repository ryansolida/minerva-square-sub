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
        add_action('wp_ajax_update_payment_method', array($this, 'handle_update_payment_ajax'));
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
            'signup_url' => '',
            'cancel_button_text' => 'Cancel Membership',
            'confirm_cancel_text' => 'Yes, Cancel',
            'keep_membership_text' => 'Keep Membership',
            'upgrade_button_text' => 'Upgrade Your Membership',
            'manage_payment_text' => 'Manage Payment Methods',
            'payment_methods_url' => '',
            'start_membership_text' => 'Start Membership',
            'reactivate_text' => 'Reactivate Membership'
        ), $atts, 'membership');
        
        // If no subscribe_url is provided, use the one from settings
        if (empty($atts['subscribe_url'])) {
            $atts['subscribe_url'] = get_membership_signup_url();
        }
        
        // Check if user is logged in
        if (!\is_user_logged_in()) {
            // Show Sign Up Now button and login link for non-logged in users
            $signup_url = !empty($atts['signup_url']) ? $atts['signup_url'] : '';
            
            if (empty($signup_url)) {
                // If no signup URL is provided, check if we have a page with the new user signup shortcode
                $signup_pages = \get_posts([
                    'post_type' => 'page',
                    'posts_per_page' => 1,
                    's' => '[mmc_new_user_signup_form',
                    'post_status' => 'publish'
                ]);
                
                if (!empty($signup_pages)) {
                    $signup_url = \get_permalink($signup_pages[0]->ID);
                }
            }
            
            $output = '<div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden p-6">';
            $output .= '<h2 class="text-2xl font-bold mb-4">Join our Exclusive Club</h2>';
            $output .= '<p class="text-gray-600 mb-6">Sign up now to access exclusive member benefits for just $' . MMC_MEMBERSHIP_PRICE . '/month.</p>';
            
            if (!empty($signup_url)) {
                $output .= '<a href="' . \esc_url($signup_url) . '" class="block w-full bg-blue-600 text-white text-center py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors mb-4">Sign Up Now</a>';
            } else {
                $output .= '<div class="bg-yellow-50 text-yellow-700 p-4 rounded-md border-l-4 border-yellow-500 mb-4">Sign up page not configured. Please contact the administrator.</div>';
            }
            
            $output .= '<div class="text-center mt-4"><p class="text-gray-600">Already have a membership? <a href="' . \wp_login_url(\get_permalink()) . '" class="text-blue-600 hover:text-blue-800">Log in</a></p></div>';
            $output .= '</div>';
            
            return $output;
        }
        
        // Get subscription data
        $subscription_data = $this->get_subscription_data();
        
        // If no subscription data exists, show the start membership form
        if (!$subscription_data) {
            return $this->render_start_membership_form($atts);
        }
        
        // If subscription exists but is not active, show reactivation option
        if ($subscription_data['status'] !== 'active') {
            return $this->render_reactivate_membership_form($atts, $subscription_data);
        }
        
        // Generate a unique ID for this instance
        $container_id = 'square-alpine-status-' . uniqid();
        
        // Set subscription variables
        $has_subscription = !empty($subscription_data);
        $is_active = $has_subscription && $subscription_data['status'] === 'active';
        $subscription = $subscription_data; // For easier access in the template
        
        // Get card information if available
        $card = null;
        if ($has_subscription && !empty($subscription_data['card_id'])) {
            // Try to get card details from user meta
            $user_id = \get_current_user_id();
            $card = [
                'brand' => \get_user_meta($user_id, 'square_card_brand', true) ?: 'Card',
                'last4' => \get_user_meta($user_id, 'square_card_last4', true) ?: '****',
                'exp_month' => \get_user_meta($user_id, 'square_card_exp_month', true) ?: '**',
                'exp_year' => \get_user_meta($user_id, 'square_card_exp_year', true) ?: '****'
            ];
        }
        
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
                    
                    <div class="flex mb-2">
                        <div class="font-medium w-32 text-gray-600 flex-shrink-0">Monthly Fee:</div>
                        <div class="text-gray-800">
                            $<?php echo MMC_MEMBERSHIP_PRICE; ?>/month
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
            <?php if ($has_subscription && $is_active): ?>
            <div class="mb-6 pb-6 border-b border-gray-200 last:border-b-0">
                <h4 class="text-lg font-medium text-gray-700 mb-3">Payment Method</h4>
                
                <!-- Current Payment Method -->
                <?php if ($card): ?>
                <div class="bg-white p-4 rounded-lg shadow-md mb-4" x-show="!showUpdatePayment">
                    <div>
                        <span class="text-green-600 font-semibold"><?php echo esc_html($card['brand']); ?></span>
                        <span>•••• <?php echo esc_html($card['last4']); ?></span>
                    </div>
                    <div class="text-gray-600 text-sm">
                        Expires <?php echo esc_html($card['exp_month']); ?>/<?php echo esc_html($card['exp_year']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Update Payment Method Button -->
                <div class="mt-4" x-show="!showUpdatePayment && !paymentLoading && !paymentMessage">
                    <button 
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200" 
                        @click="showUpdatePayment = true"
                    >
                        Update Payment Method
                    </button>
                </div>
                
                <!-- Update Payment Method Form -->
                <div 
                    class="bg-gray-50 p-4 rounded-md border border-gray-200 my-4" 
                    x-show="showUpdatePayment"
                >
                    <h5 class="font-medium mb-3">Enter New Payment Details</h5>
                    
                    <!-- Square Card Form -->
                    <div id="square-card-container" class="mb-4"></div>
                    
                    <!-- Payment Form Buttons -->
                    <div class="flex space-x-3 mt-4">
                        <button 
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200"
                            @click="updatePaymentMethod"
                            :disabled="paymentLoading"
                        >
                            <span x-show="paymentLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-4 rounded-full"></span>
                            <span x-text="paymentLoading ? 'Processing...' : 'Update Payment Method'"></span>
                        </button>
                        <button 
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-md transition-colors duration-200"
                            @click="showUpdatePayment = false"
                            :disabled="paymentLoading"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
                
                <!-- Payment Update Message -->
                <div 
                    class="p-4 rounded-md my-4"
                    :class="paymentMessageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'"
                    x-show="paymentMessage"
                    x-text="paymentMessage"
                ></div>
                
                <?php if (!empty($atts['payment_methods_url'])): ?>
                <div x-show="!showUpdatePayment && !paymentLoading">
                    <a href="<?php echo esc_url($atts['payment_methods_url']); ?>" class="inline-block px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-md transition-colors duration-200 mt-2">
                        <?php echo esc_html($atts['manage_payment_text']); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
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
                    showUpdatePayment: false,
                    paymentLoading: false,
                    paymentMessage: '',
                    paymentMessageType: '',
                    card: null,
                    cardToken: '',
                    
                    init() {
                        // Initialize Square payment form when update payment is shown
                        this.$watch('showUpdatePayment', (value) => {
                            if (value) {
                                this.initializeSquarePayment();
                            }
                        });
                    },
                    
                    initializeSquarePayment() {
                        // Get Square application ID from WordPress settings
                        const appId = '<?php echo esc_js(get_option("square_service_application_id", "sandbox-sq0idb-YOUR-SANDBOX-APP-ID")); ?>';
                        const locationId = '<?php echo esc_js(get_option("square_service_location_id", "YOUR-LOCATION-ID")); ?>';
                        
                        // Initialize Square
                        if (!window.Square) {
                            console.error('Square.js failed to load properly');
                            this.paymentMessage = 'Payment form could not be loaded. Please try again later.';
                            this.paymentMessageType = 'error';
                            return;
                        }
                        
                        const payments = window.Square.payments(appId, locationId);
                        
                        // Initialize card
                        payments.card().then(card => {
                            this.card = card;
                            card.attach('#square-card-container');
                        }).catch(e => {
                            console.error('Initializing Card failed', e);
                            this.paymentMessage = 'Payment form could not be loaded. Please try again later.';
                            this.paymentMessageType = 'error';
                        });
                    },
                    
                    async updatePaymentMethod() {
                        if (!this.card) {
                            this.paymentMessage = 'Payment form not loaded. Please refresh and try again.';
                            this.paymentMessageType = 'error';
                            return;
                        }
                        
                        this.paymentLoading = true;
                        this.paymentMessage = '';
                        
                        try {
                            // Get a payment token from the card form
                            const result = await this.card.tokenize();
                            
                            if (result.status === 'OK') {
                                // Send the token to the server to update the payment method
                                this.cardToken = result.token;
                                this.processPaymentUpdate();
                            } else {
                                this.paymentLoading = false;
                                this.paymentMessage = 'Failed to process card. Please check your card details and try again.';
                                this.paymentMessageType = 'error';
                            }
                        } catch (e) {
                            this.paymentLoading = false;
                            this.paymentMessage = 'Error processing card: ' + e.message;
                            this.paymentMessageType = 'error';
                            console.error('Error tokenizing card', e);
                        }
                    },
                    
                    processPaymentUpdate() {
                        fetch(this.ajaxUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'update_payment_method',
                                subscription_id: this.subscriptionId,
                                card_token: this.cardToken,
                                nonce: this.nonce
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            this.paymentLoading = false;
                            
                            if (result.success) {
                                this.paymentMessage = result.data.message;
                                this.paymentMessageType = 'success';
                                this.showUpdatePayment = false;
                                
                                // Reload the page after a delay
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                this.paymentMessage = result.data.message;
                                this.paymentMessageType = 'error';
                            }
                        })
                        .catch(error => {
                            this.paymentLoading = false;
                            this.paymentMessage = 'An error occurred. Please try again.';
                            this.paymentMessageType = 'error';
                            console.error('Error:', error);
                        });
                    },
                    
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
    
    /**
     * Handle AJAX payment method update requests
     */
    public function handle_update_payment_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'square-alpine-membership-nonce')) {
            wp_send_json_error(['message' => 'Invalid security token.']);
            exit;
        }
        
        // Check for required fields
        if (!isset($_POST['subscription_id']) || empty($_POST['subscription_id'])) {
            wp_send_json_error(['message' => 'Subscription ID is required.']);
            exit;
        }
        
        if (!isset($_POST['card_token']) || empty($_POST['card_token'])) {
            wp_send_json_error(['message' => 'Card token is required.']);
            exit;
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to update your payment method.']);
            exit;
        }
        
        // Verify that the subscription belongs to this user
        $user_subscription_id = get_user_meta($user_id, 'square_subscription_id', true);
        if ($user_subscription_id !== $_POST['subscription_id']) {
            wp_send_json_error(['message' => 'You do not have permission to update this subscription.']);
            exit;
        }
        
        try {
            // Get the customer ID
            $customer_id = get_user_meta($user_id, 'square_customer_id', true);
            if (empty($customer_id)) {
                wp_send_json_error(['message' => 'Customer ID not found.']);
                exit;
            }
            
            // Get Square service
            $square_service = SquareService::get_instance();
            
            // Add the new card to the customer
            $card_token = sanitize_text_field($_POST['card_token']);
            $card_result = $square_service->addCardToCustomer($customer_id, $card_token);
            
            if (!$card_result) {
                wp_send_json_error(['message' => 'Failed to add new payment method.']);
                exit;
            }
            
            // Get the card ID from the result
            $card_id = $card_result->id ?? '';
            
            if (empty($card_id)) {
                wp_send_json_error(['message' => 'Failed to get card ID from response.']);
                exit;
            }
            
            // Update the subscription with the new card
            $subscription_id = sanitize_text_field($_POST['subscription_id']);
            $update_result = $square_service->updateSubscriptionPaymentMethod($subscription_id, $card_id);
            
            if ($update_result) {
                // Update the card ID in user meta
                update_user_meta($user_id, 'square_card_id', $card_id);
                
                // Save card details for display
                $card_brand = $card_result->card_brand ?? '';
                $last_4 = $card_result->last_4 ?? '';
                $exp_month = $card_result->exp_month ?? '';
                $exp_year = $card_result->exp_year ?? '';
                
                // Save card details as user meta
                update_user_meta($user_id, 'square_card_brand', $card_brand);
                update_user_meta($user_id, 'square_card_last4', $last_4);
                update_user_meta($user_id, 'square_card_exp_month', $exp_month);
                update_user_meta($user_id, 'square_card_exp_year', $exp_year);
                
                wp_send_json_success([
                    'message' => 'Your payment method has been successfully updated.',
                    'card' => [
                        'id' => $card_id,
                        'brand' => $card_brand,
                        'last4' => $last_4,
                        'exp_month' => $exp_month,
                        'exp_year' => $exp_year
                    ]
                ]);
            } else {
                // If the subscription update failed, try to delete the card we just added
                try {
                    $square_service->deleteCustomerCard($customer_id, $card_id);
                } catch (Exception $delete_error) {
                    // Just log this error, don't stop execution
                    error_log('Failed to delete card after failed subscription update: ' . $delete_error->getMessage());
                }
                
                wp_send_json_error(['message' => 'Failed to update subscription payment method.']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * Render the start membership form for logged-in users without a subscription
     */
    private function render_start_membership_form($atts) {
        // Get Square credentials from settings
        $application_id = \get_option('square_service_application_id', '');
        $location_id = \get_option('square_service_location_id', '');
        $environment = \get_option('square_service_environment', 'sandbox');
        $plan_id = \get_option('square_service_default_plan_id', '');
        
        // Check if Square credentials are set
        if (empty($application_id) || empty($location_id) || empty($plan_id)) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">Error: Square API credentials or plan ID are not configured. Please contact the administrator.</div>';
        }
        
        // Get current user info
        $current_user = \wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_name = $current_user->display_name;
        
        // Generate a unique form ID
        $form_id = 'square-start-membership-form-' . uniqid();
        
        // Create Alpine.js app with the form
        ob_start();
        ?>
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden p-6">
            <h2 class="text-2xl font-bold mb-4">Start Your Membership</h2>
            <p class="text-gray-600 mb-6">Join our exclusive club for just $<?php echo MMC_MEMBERSHIP_PRICE; ?>/month and get access to premium content and features.</p>
            
            <div x-data="{
                cardholderName: '<?php echo \esc_js($user_name); ?>',
                cardToken: '',
                loading: false,
                errors: {},
                success: '',
                squareStatus: 'idle',
                squareErrors: [],
                squarePaymentForm: null,
                squareData: {
                    applicationId: '<?php echo \esc_js($application_id); ?>',
                    locationId: '<?php echo \esc_js($location_id); ?>',
                    planId: '<?php echo \esc_js($plan_id); ?>',
                    ajaxUrl: '<?php echo \esc_js(\admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo \esc_js(\wp_create_nonce('square-alpine-membership-nonce')); ?>',
                    email: '<?php echo \esc_js($user_email); ?>'
                },
                
                init() {
                    // Initialize Square Web Payments SDK
                    const appId = this.squareData.applicationId;
                    const locationId = this.squareData.locationId;
                    
                    // Check if Square.js is already loaded
                    if (typeof Square === 'undefined') {
                        const script = document.createElement('script');
                        script.src = '<?php echo $environment === 'sandbox' ? 'https://sandbox.web.squarecdn.com/v1/square.js' : 'https://web.squarecdn.com/v1/square.js'; ?>';
                        script.onload = () => this.initializeSquare(appId, locationId);
                        document.body.appendChild(script);
                    } else {
                        this.initializeSquare(appId, locationId);
                    }
                },
                
                initializeSquare(appId, locationId) {
                    // Initialize payments
                    const payments = Square.payments(appId, locationId);
                    
                    // Initialize card
                    payments.card().then(card => {
                        this.squarePaymentForm = card;
                        card.attach('#card-container-start');
                    }).catch(e => {
                        console.error('Square initialization error:', e);
                        this.squareErrors.push('Failed to load payment form: ' + e.message);
                    });
                },
                
                validateForm() {
                    this.errors = {};
                    
                    // Validate cardholder name
                    if (!this.cardholderName.trim()) {
                        this.errors.cardholderName = 'Cardholder name is required';
                    }
                    
                    return Object.keys(this.errors).length === 0;
                },
                
                async submitForm() {
                    if (!this.validateForm()) {
                        return;
                    }
                    
                    this.loading = true;
                    this.squareStatus = 'processing';
                    
                    try {
                        // Get a payment token from Square
                        const result = await this.squarePaymentForm.tokenize();
                        
                        if (result.status === 'OK') {
                            this.cardToken = result.token;
                            await this.processPayment(result.token);
                        } else {
                            this.squareErrors.push('Payment tokenization failed: ' + result.errors[0].message);
                            this.squareStatus = 'error';
                        }
                    } catch (e) {
                        console.error('Payment form error:', e);
                        this.squareErrors.push('Payment form error: ' + e.message);
                        this.squareStatus = 'error';
                    } finally {
                        this.loading = false;
                    }
                },
                
                async processPayment(token) {
                    try {
                        // Create form data for submission
                        const formData = new FormData();
                        formData.append('action', 'membership_subscribe');
                        formData.append('nonce', this.squareData.nonce);
                        formData.append('source_id', token);
                        formData.append('plan_id', this.squareData.planId);
                        formData.append('cardholder_name', this.cardholderName);
                        formData.append('email', this.squareData.email);
                        
                        // Submit to WordPress AJAX
                        const response = await fetch(this.squareData.ajaxUrl, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const responseData = await response.json();
                        
                        if (responseData.success) {
                            this.success = responseData.data.message;
                            this.squareStatus = 'success';
                            
                            // Refresh the page after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            this.errors.form = responseData.data.message;
                            this.squareStatus = 'error';
                        }
                    } catch (e) {
                        console.error('Payment processing error:', e);
                        this.errors.form = 'An error occurred while processing your payment. Please try again.';
                        this.squareStatus = 'error';
                    } finally {
                        this.loading = false;
                    }
                }
            }">
                <!-- Success message -->
                <div x-show="success" x-transition class="bg-green-50 text-green-700 p-4 rounded-md border-l-4 border-green-500 mb-4" x-text="success"></div>
                
                <!-- Form error message -->
                <div x-show="errors.form" x-transition class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500 mb-4" x-text="errors.form"></div>
                
                <!-- Square errors -->
                <div x-show="squareErrors.length > 0" x-transition class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500 mb-4">
                    <p class="font-bold">Payment Error:</p>
                    <ul class="list-disc list-inside">
                        <template x-for="error in squareErrors" :key="error">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                </div>
                
                <form @submit.prevent="submitForm" x-show="!success">
                    <!-- Cardholder Name field -->
                    <div class="mb-4">
                        <label for="cardholder-name" class="block text-gray-700 font-medium mb-2">Cardholder Name</label>
                        <input type="text" id="cardholder-name" x-model="cardholderName" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{'border-red-500': errors.cardholderName}">
                        <p x-show="errors.cardholderName" class="text-red-500 text-sm mt-1" x-text="errors.cardholderName"></p>
                    </div>
                    
                    <!-- Square Card Element -->
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Card Details</label>
                        <div id="card-container-start" class="p-3 border rounded-md min-h-[40px] bg-gray-50"></div>
                        <p class="text-gray-500 text-sm mt-1">Your card will be charged $8.99/month for your membership.</p>
                    </div>
                    
                    <!-- Submit button -->
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors" :disabled="loading || squareStatus === 'processing'">
                        <span x-show="!loading && squareStatus !== 'processing'"><?php echo \esc_html($atts['start_membership_text']); ?></span>
                        <span x-show="loading || squareStatus === 'processing'" class="inline-flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render the reactivate membership form for users with inactive subscriptions
     */
    private function render_reactivate_membership_form($atts, $subscription_data) {
        // Get basic subscription info to display
        $subscription_id = $subscription_data['id'];
        $plan_id = $subscription_data['plan_id'];
        
        // Get Square credentials from settings
        $application_id = \get_option('square_service_application_id', '');
        $location_id = \get_option('square_service_location_id', '');
        $environment = \get_option('square_service_environment', 'sandbox');
        
        // Check if Square credentials are set
        if (empty($application_id) || empty($location_id) || empty($plan_id)) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">Error: Square API credentials or plan ID are not configured. Please contact the administrator.</div>';
        }
        
        // Get current user info
        $current_user = \wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_name = $current_user->display_name;
        
        // Generate a unique form ID
        $form_id = 'square-reactivate-form-' . uniqid();
        
        // Create Alpine.js app with the form
        ob_start();
        ?>
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden p-6">
            <h2 class="text-2xl font-bold mb-4">Reactivate Your Membership</h2>
            <p class="text-gray-600 mb-6">Your membership is currently inactive. Reactivate now to regain access to exclusive content and features.</p>
            
            <div class="bg-yellow-50 text-yellow-700 p-4 rounded-md border-l-4 border-yellow-500 mb-4">
                <p><strong>Previous Subscription:</strong> Inactive</p>
                <p><strong>Subscription ID:</strong> <?php echo \esc_html(substr($subscription_id, 0, 8) . '...'); ?></p>
            </div>
            
            <div x-data="{
                cardholderName: '<?php echo \esc_js($user_name); ?>',
                cardToken: '',
                loading: false,
                errors: {},
                success: '',
                squareStatus: 'idle',
                squareErrors: [],
                squarePaymentForm: null,
                squareData: {
                    applicationId: '<?php echo \esc_js($application_id); ?>',
                    locationId: '<?php echo \esc_js($location_id); ?>',
                    planId: '<?php echo \esc_js($plan_id); ?>',
                    ajaxUrl: '<?php echo \esc_js(\admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo \esc_js(\wp_create_nonce('square-alpine-membership-nonce')); ?>',
                    email: '<?php echo \esc_js($user_email); ?>'
                },
                
                init() {
                    // Initialize Square Web Payments SDK
                    const appId = this.squareData.applicationId;
                    const locationId = this.squareData.locationId;
                    
                    // Check if Square.js is already loaded
                    if (typeof Square === 'undefined') {
                        const script = document.createElement('script');
                        script.src = '<?php echo $environment === 'sandbox' ? 'https://sandbox.web.squarecdn.com/v1/square.js' : 'https://web.squarecdn.com/v1/square.js'; ?>';
                        script.onload = () => this.initializeSquare(appId, locationId);
                        document.body.appendChild(script);
                    } else {
                        this.initializeSquare(appId, locationId);
                    }
                },
                
                initializeSquare(appId, locationId) {
                    // Initialize payments
                    const payments = Square.payments(appId, locationId);
                    
                    // Initialize card
                    payments.card().then(card => {
                        this.squarePaymentForm = card;
                        card.attach('#card-container-reactivate');
                    }).catch(e => {
                        console.error('Square initialization error:', e);
                        this.squareErrors.push('Failed to load payment form: ' + e.message);
                    });
                },
                
                validateForm() {
                    this.errors = {};
                    
                    // Validate cardholder name
                    if (!this.cardholderName.trim()) {
                        this.errors.cardholderName = 'Cardholder name is required';
                    }
                    
                    return Object.keys(this.errors).length === 0;
                },
                
                async submitForm() {
                    if (!this.validateForm()) {
                        return;
                    }
                    
                    this.loading = true;
                    this.squareStatus = 'processing';
                    
                    try {
                        // Get a payment token from Square
                        const result = await this.squarePaymentForm.tokenize();
                        
                        if (result.status === 'OK') {
                            this.cardToken = result.token;
                            await this.processPayment(result.token);
                        } else {
                            this.squareErrors.push('Payment tokenization failed: ' + result.errors[0].message);
                            this.squareStatus = 'error';
                        }
                    } catch (e) {
                        console.error('Payment form error:', e);
                        this.squareErrors.push('Payment form error: ' + e.message);
                        this.squareStatus = 'error';
                    } finally {
                        this.loading = false;
                    }
                },
                
                async processPayment(token) {
                    try {
                        // Create form data for submission
                        const formData = new FormData();
                        formData.append('action', 'membership_subscribe');
                        formData.append('nonce', this.squareData.nonce);
                        formData.append('source_id', token);
                        formData.append('plan_id', this.squareData.planId);
                        formData.append('cardholder_name', this.cardholderName);
                        formData.append('email', this.squareData.email);
                        
                        // Submit to WordPress AJAX
                        const response = await fetch(this.squareData.ajaxUrl, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const responseData = await response.json();
                        
                        if (responseData.success) {
                            this.success = responseData.data.message;
                            this.squareStatus = 'success';
                            
                            // Refresh the page after a short delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            this.errors.form = responseData.data.message;
                            this.squareStatus = 'error';
                        }
                    } catch (e) {
                        console.error('Payment processing error:', e);
                        this.errors.form = 'An error occurred while processing your payment. Please try again.';
                        this.squareStatus = 'error';
                    } finally {
                        this.loading = false;
                    }
                }
            }">
                <!-- Success message -->
                <div x-show="success" x-transition class="bg-green-50 text-green-700 p-4 rounded-md border-l-4 border-green-500 mb-4" x-text="success"></div>
                
                <!-- Form error message -->
                <div x-show="errors.form" x-transition class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500 mb-4" x-text="errors.form"></div>
                
                <!-- Square errors -->
                <div x-show="squareErrors.length > 0" x-transition class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500 mb-4">
                    <p class="font-bold">Payment Error:</p>
                    <ul class="list-disc list-inside">
                        <template x-for="error in squareErrors" :key="error">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                </div>
                
                <form @submit.prevent="submitForm" x-show="!success">
                    <!-- Cardholder Name field -->
                    <div class="mb-4">
                        <label for="cardholder-name-reactivate" class="block text-gray-700 font-medium mb-2">Cardholder Name</label>
                        <input type="text" id="cardholder-name-reactivate" x-model="cardholderName" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{'border-red-500': errors.cardholderName}">
                        <p x-show="errors.cardholderName" class="text-red-500 text-sm mt-1" x-text="errors.cardholderName"></p>
                    </div>
                    
                    <!-- Square Card Element -->
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Card Details</label>
                        <div id="card-container-reactivate" class="p-3 border rounded-md min-h-[40px] bg-gray-50"></div>
                        <p class="text-gray-500 text-sm mt-1">Your card will be charged $8.99/month for your membership.</p>
                    </div>
                    
                    <!-- Submit button -->
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors" :disabled="loading || squareStatus === 'processing'">
                        <span x-show="!loading && squareStatus !== 'processing'"><?php echo \esc_html($atts['reactivate_text']); ?></span>
                        <span x-show="loading || squareStatus === 'processing'" class="inline-flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the shortcode
$membership_status = MembershipStatus::get_instance();

