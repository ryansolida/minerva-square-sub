<?php
/**
 * Square Subscription Form with Alpine.js
 * 
 * A standalone shortcode to display a Square subscription form
 * with reactive UI powered by Alpine.js
 */

 
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SquareSubscriptionAlpine {
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
        add_shortcode('membership_signup', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_square_alpine_subscribe', array($this, 'handle_subscription_ajax'));
        add_action('wp_ajax_nopriv_square_alpine_subscribe', array($this, 'handle_subscription_ajax'));
    }
    
    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with our shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'square_alpine_subscription')) {
            return;
        }
        
        // Enqueue Alpine.js
        wp_enqueue_script('alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.12.0/dist/cdn.min.js', array(), null, true);
        
        // Enqueue Square Web Payments SDK
        wp_enqueue_script('square-web-payments-sdk', 'https://sandbox.web.squarecdn.com/v1/square.js', array(), null, true);
        
        // Enqueue Tailwind CSS
        wp_enqueue_style('tailwindcss', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), null);
    }
    
    /**
     * Render the subscription form shortcode
     */
    public function render_shortcode($atts) {
        // Get Square credentials from settings
        $application_id = get_option('square_service_application_id', '');
        $location_id = get_option('square_service_location_id', '');
        $environment = get_option('square_service_environment', 'sandbox');
        
        // Get shortcode attributes
        $atts = shortcode_atts(array(
            'button_text' => 'Subscribe Now',
            'title' => 'Subscribe to our Exclusive Club',
            'description' => 'Join our exclusive club for just $8.99/month',
            'plan_id' => get_option('square_service_default_plan_id', ''),
            'redirect_url' => ''
        ), $atts, 'square_alpine_subscription');
        
        // Check if Square credentials are set
        if (empty($application_id) || empty($location_id)) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">Error: Square API credentials are not configured. Please set them in the plugin settings.</div>';
        }
        
        // Check if plan ID is set (either in shortcode or as default in settings)
        if (empty($atts['plan_id'])) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">Error: No subscription plan ID found. Please either specify a plan_id in the shortcode or set a default plan ID in the Square Service settings.</div>';
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to subscribe.</div>';
        }
        
        // Check if user already has an active subscription
        if ($this->user_has_subscription()) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">You already have an active subscription. <a href="' . get_permalink(get_option('square_service_account_page_id')) . '">Manage your subscription</a>.</div>';
        }
        
        // If user has an inactive subscription, show a message but still allow them to subscribe
        $user_id = get_current_user_id();
        $subscription_id = get_user_meta($user_id, 'square_subscription_id', true);
        $has_inactive_subscription = !empty($subscription_id) && get_user_meta($user_id, 'square_active_membership', true) !== 'yes';
        
        $subscription_message = '';
        if ($has_inactive_subscription) {
            $subscription_message = '<div class="bg-yellow-50 text-yellow-700 p-4 rounded-md border-l-4 border-yellow-500"><strong>Note:</strong> Your previous subscription has been canceled. Fill out the form below to resubscribe.</div>';
        }
        
        // Generate a unique form ID
        $form_id = 'square-alpine-form-' . uniqid();
        
        // Get current user email
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        // Create Alpine.js app with the form
        ob_start();
        ?>
        <?php if (isset($subscription_message) && !empty($subscription_message)) {
            echo $subscription_message;
        } ?>
        <div 
            id="<?php echo esc_attr($form_id); ?>" 
            class="mx-auto p-5 bg-white rounded-lg shadow-md" 
            x-data="squareSubscriptionForm({
                applicationId: '<?php echo esc_js($application_id); ?>',
                locationId: '<?php echo esc_js($location_id); ?>',
                environment: '<?php echo esc_js($environment); ?>',
                planId: '<?php echo esc_js($atts['plan_id']); ?>',
                redirectUrl: '<?php echo esc_js($atts['redirect_url']); ?>',
                ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('square-alpine-nonce')); ?>',
                formId: '<?php echo esc_js($form_id); ?>'
            })"
        >
            <h3 class="text-xl font-semibold text-gray-800 mb-4"><?php echo esc_html($atts['title']); ?></h3>
            
            <?php if (!empty($atts['description'])): ?>
            <p class="text-gray-600 mb-5"><?php echo esc_html($atts['description']); ?></p>
            <?php endif; ?>
            
            <template x-if="error">
                <div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500" x-text="error"></div>
            </template>
            
            <template x-if="success">
                <div class="bg-green-50 text-green-700 p-4 rounded-md border-l-4 border-green-500" x-text="success"></div>
            </template>
            
            <form @submit.prevent="submitForm" x-show="!success" class="space-y-4">
                <div class="mb-4">
                    <label for="<?php echo esc_attr($form_id); ?>-cardholderName" class="block text-sm font-medium text-gray-700 mb-1">Cardholder Name</label>
                    <input 
                        id="<?php echo esc_attr($form_id); ?>-cardholderName" 
                        type="text" 
                        x-model="cardholderName" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
                
                <div class="mb-4">
                    <label for="<?php echo esc_attr($form_id); ?>-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input 
                        id="<?php echo esc_attr($form_id); ?>-email" 
                        type="email" 
                        x-model="email" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Card Information</label>
                    <div id="<?php echo esc_attr($form_id); ?>-card-container"></div>
                    <div x-show="cardError" class="mt-2 bg-red-50 text-red-700 p-3 rounded-md border-l-4 border-red-500" x-text="cardError"></div>
                </div>
                
                <div class="mt-6">
                    <button 
                        type="submit" 
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md shadow transition-all duration-200 ease-in-out transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" 
                        :disabled="loading"
                    >
                        <span x-show="loading" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span x-text="loading ? 'Processing...' : '<?php echo esc_js($atts['button_text']); ?>'"></span>
                    </button>
                </div>
            </form>
        </div>
        
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('squareSubscriptionForm', (config) => ({
                    cardholderName: '',
                    email: '<?php echo esc_js($user_email); ?>',
                    loading: false,
                    error: null,
                    cardError: null,
                    success: null,
                    card: null,
                    payments: null,
                    
                    init() {
                        // Initialize Square Web Payments SDK
                        document.addEventListener('DOMContentLoaded', () => {
                            this.initializeSquare();
                        });
                    },
                    
                    async initializeSquare() {
                        if (!window.Square) {
                            this.error = 'Failed to load Square Web Payments SDK. Please refresh the page and try again.';
                            return;
                        }
                        
                        try {
                            this.payments = window.Square.payments(
                                config.applicationId, 
                                config.locationId, 
                                { 
                                    environment: config.environment 
                                }
                            );
                            
                            this.card = await this.payments.card();
                            await this.card.attach('#' + config.formId + '-card-container');
                        } catch (error) {
                            console.error('Error initializing Square Payments:', error);
                            this.error = 'Failed to initialize payment form: ' + error.message;
                        }
                    },
                    
                    async submitForm() {
                        this.loading = true;
                        this.error = null;
                        this.cardError = null;
                        
                        try {
                            if (!this.card) {
                                throw new Error('Payment form not initialized. Please refresh the page and try again.');
                            }
                            
                            // Tokenize the card
                            const result = await this.card.tokenize();
                            
                            if (result.status === 'OK') {
                                // Submit to server
                                await this.processPayment(result.token);
                            } else {
                                this.cardError = result.errors[0].message;
                                this.loading = false;
                            }
                        } catch (error) {
                            console.error('Payment error:', error);
                            this.error = 'Payment processing error: ' + error.message;
                            this.loading = false;
                        }
                    },
                    
                    async processPayment(token) {
                        try {
                            // Create form data for submission
                            const formData = new FormData();
                            formData.append('action', 'square_alpine_subscribe');
                            formData.append('nonce', config.nonce);
                            formData.append('source_id', token);
                            formData.append('plan_id', config.planId);
                            formData.append('cardholder_name', this.cardholderName);
                            formData.append('email', this.email);
                            formData.append('redirect_url', config.redirectUrl);
                            
                            // Submit to WordPress AJAX
                            const response = await fetch(config.ajaxUrl, {
                                method: 'POST',
                                body: formData
                            });
                            
                            const responseData = await response.json();
                            
                            if (responseData.success) {
                                this.success = responseData.data.message;
                                
                                // Redirect if URL is provided
                                if (responseData.data.redirect_url && responseData.data.redirect_url !== '') {
                                    setTimeout(() => {
                                        window.location.href = responseData.data.redirect_url;
                                    }, 2000);
                                }
                            } else {
                                this.error = responseData.data.message || 'Error processing payment.';
                            }
                        } catch (error) {
                            console.error('AJAX error:', error);
                            this.error = 'Server communication error: ' + error.message;
                        } finally {
                            this.loading = false;
                        }
                    }
                }));
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Check if the current user already has an active subscription
     */
    private function user_has_subscription() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        // Check active membership flag
        $has_active_membership = get_user_meta($user_id, 'square_active_membership', true);
        if ($has_active_membership !== 'yes') {
            return false;
        }
        
        // Check subscription ID
        $subscription_id = get_user_meta($user_id, 'square_subscription_id', true);
        return !empty($subscription_id);
    }
    
    /**
     * Handle AJAX subscription requests
     */
    public function handle_subscription_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'square-alpine-nonce')) {
            wp_send_json_error(['message' => 'Invalid security token.']);
            exit;
        }
        
        // Check required fields
        $required_fields = ['source_id', 'plan_id', 'cardholder_name', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                wp_send_json_error(['message' => "Missing required field: {$field}"]);
                exit;
            }
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to subscribe.']);
            exit;
        }

        try {

            
            // Get Square service
            $square_service = $this->get_square_service();
            
            // Get customer ID or create one
            $customer_id = get_user_meta($user_id, 'square_customer_id', true);
            if (!$customer_id) {
                $customer_data = [
                    'email_address' => sanitize_email($_POST['email']),
                    'given_name' => sanitize_text_field($_POST['cardholder_name'])
                ];
                
                $customer_response = $square_service->createCustomer($customer_data);
                $customer_id = $customer_response->id;
                
                // Save customer ID
                update_user_meta($user_id, 'square_customer_id', $customer_id);
            }
            
            // Add the card to the customer's account first
            $source_id = sanitize_text_field($_POST['source_id']);
            $plan_id = sanitize_text_field($_POST['plan_id']);
            
            // Add the card to customer using source_id (card token)
            $card_response = $square_service->addCardToCustomer($customer_id, $source_id);
            if (!$card_response) {
                throw new Exception('Failed to add payment card to customer.');
            }
            
            // Get the card ID directly from the card object
            // Note: We can't use the getId() method directly since it's a closure property
            $card_id = $card_response->id ?? '';
            if (empty($card_id)) {
                throw new Exception('Failed to get card ID from response.');
            }
            
            // Store card ID as user meta for future reference
            update_user_meta($user_id, 'square_card_id', $card_id);
            $subscription_data = $square_service->createSubscription($customer_id, $card_id, $plan_id);
            
            if (!$subscription_data || !isset($subscription_data->id)) {
                throw new Exception('Failed to create subscription.');
            }
            
            // Save subscription data
            update_user_meta($user_id, 'square_subscription_id', $subscription_data->id);
            update_user_meta($user_id, 'square_subscription_plan_id', $plan_id);
            update_user_meta($user_id, 'square_active_membership', 'yes');
            
            // Return success
            $redirect_url = isset($_POST['redirect_url']) ? esc_url_raw($_POST['redirect_url']) : '';
            wp_send_json_success([
                'message' => 'Subscription created successfully!',
                'redirect_url' => $redirect_url
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * Get Square service instance
     */
    private function get_square_service() {
        // Make sure SquareService class is loaded
        if (!class_exists('SquareService')) {
            require_once dirname(__FILE__) . '/SquareService.php';
        }
        
        // Get API credentials
        $access_token = get_option('square_service_access_token', '');
        $environment = get_option('square_service_environment', 'sandbox');
        
        // Create service instance
        return new SquareService($access_token, $environment === 'production');
    }
}

// Initialize the shortcode
function square_alpine_subscription_init() {
    return SquareSubscriptionAlpine::get_instance();
}
square_alpine_subscription_init();
