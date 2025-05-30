<?php
namespace MMCMembership;
use Exception;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MembershipSignup {
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
        // Register shortcodes
        \add_shortcode('mmc_signup_form', [$this, 'render_shortcode']);
        \add_shortcode('mmc_new_user_signup_form', [$this, 'render_new_user_signup_form']);
        \add_action('wp_ajax_mmc_process_signup', [$this, 'ajax_handle_signup']);
        \add_action('wp_ajax_nopriv_mmc_process_signup', [$this, 'ajax_handle_signup']);
        \add_action('wp_ajax_mmc_process_new_user_signup', [$this, 'ajax_handle_new_user_signup']);
        \add_action('wp_ajax_nopriv_mmc_process_new_user_signup', [$this, 'ajax_handle_new_user_signup']);
        \add_action('wp_ajax_mmc_check_email_exists', [$this, 'ajax_check_email_exists']);
        \add_action('wp_ajax_nopriv_mmc_check_email_exists', [$this, 'ajax_check_email_exists']);
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
            'title' => 'Subscribe to the ' . MMC_MEMBERSHIP_CLUB_NAME,
            'description' => 'Join the ' . strtolower(MMC_MEMBERSHIP_CLUB_NAME) . ' for just $' . MMC_MEMBERSHIP_PRICE . '/month',
            'plan_id' => get_option('square_service_default_plan_id', ''),
            'redirect_url' => ''
        ), $atts, 'membership_subscription');
        
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
                            formData.append('action', 'membership_subscribe');
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'square-alpine-membership-nonce')) {
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
            // Get API credentials
            $access_token = get_option('square_service_access_token', '');
            $environment = get_option('square_service_environment', 'sandbox');
            
            // Create service instance
            $square_service = new SquareService($access_token, $environment === 'production');
            
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
            
            // Call UserFunctions to properly set active membership status
            UserFunctions::set_active_membership($user_id, $subscription_data);
            
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
     * Generate a username from an email address
     * 
     * @param string $email
     * @return string
     */
    private function generate_username_from_email($email) {
        // Get the part before the @ symbol
        $username = strtolower(substr($email, 0, strpos($email, '@')));
        
        // Remove any non-alphanumeric characters
        $username = preg_replace('/[^a-z0-9]/', '', $username);
        
        // Check if username already exists
        $suffix = '';
        $i = 1;
        
        while (username_exists($username . $suffix)) {
            $suffix = $i++;
        }
        
        return $username . $suffix;
    }
    
    /**
     * Render the new user signup form shortcode
     */
    public function render_new_user_signup_form($atts) {
        // Get Square credentials from settings
        $application_id = \get_option('square_service_application_id', '');
        $location_id = \get_option('square_service_location_id', '');
        $environment = \get_option('square_service_environment', 'sandbox');
        
        // Get shortcode attributes
        $atts = shortcode_atts(array(
            'button_text' => 'Sign Up Now',
            'title' => 'Join the ' . MMC_MEMBERSHIP_CLUB_NAME,
            'description' => 'Create your account and start your membership for just $' . MMC_MEMBERSHIP_PRICE . '/month',
            'plan_id' => \get_option('square_service_default_plan_id', ''),
            'redirect_url' => ''
        ), $atts, 'mmc_new_user_signup_form');
        
        // Check if Square credentials are set
        if (empty($application_id) || empty($location_id)) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">Error: Square API credentials are not configured. Please set them in the plugin settings.</div>';
        }
        
        // Check if plan ID is set (either in shortcode or as default in settings)
        if (empty($atts['plan_id'])) {
            return '<div class="bg-red-50 text-red-700 p-4 rounded-md border-l-4 border-red-500">Error: No subscription plan ID found. Please either specify a plan_id in the shortcode or set a default plan ID in the Square Service settings.</div>';
        }
        
        // If user is already logged in, show the membership status shortcode
        if (\is_user_logged_in()) {
            // Get the MembershipStatus class instance
            $membership_status = \MMCMembership\MembershipStatus::get_instance();
            
            // Return the rendered membership status shortcode
            return $membership_status->render_shortcode([]);
        }
        
        // Generate a unique form ID
        $form_id = 'square-signup-form-' . uniqid();
        
        // Create Alpine.js app with the form
        ob_start();
        ?>
        <div x-data="{
            name: '',
            email: '',
            password: '',
            confirmPassword: '',
            cardholderName: '',
            cardToken: '',
            loading: false,
            checkingEmail: false,
            errors: {},
            success: '',
            squareStatus: 'idle',
            squareErrors: [],
            squarePaymentForm: null,
            emailChecked: false,
            squareData: {
                applicationId: '<?php echo esc_js($application_id); ?>',
                locationId: '<?php echo esc_js($location_id); ?>',
                planId: '<?php echo esc_js($atts['plan_id']); ?>',
                ajaxUrl: '<?php echo esc_js(\admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(\wp_create_nonce('mmc-new-user-signup-nonce')); ?>',
                redirectUrl: '<?php echo esc_js($atts['redirect_url']); ?>'
            },
            
            init() {
                this.cardholderName = this.name;
                
                // Watch for name changes and update cardholder name
                this.$watch('name', (value) => {
                    this.cardholderName = value;
                });
                
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
                    card.attach('#card-container');
                }).catch(e => {
                    console.error('Square initialization error:', e);
                    this.squareErrors.push('Failed to load payment form: ' + e.message);
                });
            },
            
            validateForm() {
                this.errors = {};
                
                // Validate name
                if (!this.name.trim()) {
                    this.errors.name = 'Name is required';
                }
                
                // Validate email
                if (!this.email.trim()) {
                    this.errors.email = 'Email is required';
                } else if (!/^\S+@\S+\.\S+$/.test(this.email)) {
                    this.errors.email = 'Please enter a valid email address';
                }
                
                // Validate password
                if (!this.password) {
                    this.errors.password = 'Password is required';
                } else if (this.password.length < 8) {
                    this.errors.password = 'Password must be at least 8 characters';
                }
                
                // Validate confirm password
                if (this.password !== this.confirmPassword) {
                    this.errors.confirmPassword = 'Passwords do not match';
                }
                
                // Validate cardholder name
                if (!this.cardholderName.trim()) {
                    this.errors.cardholderName = 'Cardholder name is required';
                }
                
                return Object.keys(this.errors).length === 0;
            },
            
            async checkEmailExists() {
                if (!this.email.trim() || !/^\S+@\S+\.\S+$/.test(this.email)) {
                    this.errors.email = 'Please enter a valid email address';
                    return false;
                }
                
                this.checkingEmail = true;
                this.errors.email = '';
                
                try {
                    // Create form data for submission
                    const formData = new FormData();
                    formData.append('action', 'mmc_check_email_exists');
                    formData.append('nonce', this.squareData.nonce);
                    formData.append('email', this.email);
                    
                    // Submit to WordPress AJAX
                    const response = await fetch(this.squareData.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const responseData = await response.json();
                    
                    if (!responseData.success) {
                        this.errors.email = responseData.data.message;
                        this.emailChecked = false;
                        return false;
                    } else {
                        this.emailChecked = true;
                        return true;
                    }
                } catch (e) {
                    console.error('Email check error:', e);
                    this.errors.email = 'Error checking email. Please try again.';
                    this.emailChecked = false;
                    return false;
                } finally {
                    this.checkingEmail = false;
                }
            },
            
            async submitForm() {
                if (!this.validateForm()) {
                    return;
                }
                
                // Check if email exists before proceeding
                if (!this.emailChecked) {
                    const emailAvailable = await this.checkEmailExists();
                    if (!emailAvailable) {
                        return;
                    }
                }
                
                this.loading = true;
                this.squareStatus = 'processing';
                
                try {
                    // Get a payment token from Square
                    const result = await this.squarePaymentForm.tokenize();
                    
                    if (result.status === 'OK') {
                        this.cardToken = result.token;
                        await this.processSignup(result.token);
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
            
            // Fill form with test data for faster testing
            fillTestData() {
                // Generate a random email to avoid duplicates
                const timestamp = new Date().getTime();
                const randomNum = Math.floor(Math.random() * 10000);
                
                // Set test values
                this.name = 'Test User';
                this.email = `test${timestamp}${randomNum}@example.com`;
                this.password = 'Password123!';
                this.confirmPassword = 'Password123!';
                this.cardholderName = 'Test User';
                
                // Trigger email check
                this.checkEmailExists();
            },
            
            async processSignup(token) {
                try {
                    // Create form data for submission
                    const formData = new FormData();
                    formData.append('action', 'mmc_process_new_user_signup');
                    formData.append('nonce', this.squareData.nonce);
                    formData.append('source_id', token);
                    formData.append('plan_id', this.squareData.planId);
                    formData.append('name', this.name);
                    formData.append('email', this.email);
                    formData.append('password', this.password);
                    formData.append('redirect_url', this.squareData.redirectUrl);
                    
                    // Submit to WordPress AJAX
                    const response = await fetch(this.squareData.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const responseData = await response.json();
                    
                    if (responseData.success) {
                        this.success = responseData.data.message;
                        this.squareStatus = 'success';
                        
                        // Redirect if URL is provided
                        if (responseData.data.redirect_url && responseData.data.redirect_url !== '') {
                            setTimeout(() => {
                                window.location.href = responseData.data.redirect_url;
                            }, 1500);
                        }
                    } else {
                        this.errors.form = responseData.data.message;
                        this.squareStatus = 'error';
                    }
                } catch (e) {
                    console.error('Signup processing error:', e);
                    this.errors.form = 'An error occurred while processing your signup. Please try again.';
                    this.squareStatus = 'error';
                } finally {
                    this.loading = false;
                }
            }
        }" class="mmc-membership-form mx-auto bg-white rounded-lg shadow-md overflow-hidden p-6">
            <h2 class="text-2xl font-bold mb-4"><?php echo esc_html($atts['title']); ?></h2>
            <p class="text-gray-600 mb-6"><?php echo esc_html($atts['description']); ?></p>
            
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
                <!-- Name field -->
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-medium mb-2">Full Name</label>
                    <input type="text" id="name" x-model="name" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{'border-red-500': errors.name}">
                    <p x-show="errors.name" class="text-red-500 text-sm mt-1" x-text="errors.name"></p>
                </div>
                
                <!-- Email field -->
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-medium mb-1">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        x-model="email" 
                        @blur="checkEmailExists()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                        required
                    >
                    <div x-show="checkingEmail" class="text-blue-600 text-sm mt-1">Checking email availability...</div>
                    <div x-show="errors.email" class="text-red-600 text-sm mt-1" x-text="errors.email"></div>
                    <div x-show="emailChecked && !errors.email" class="text-green-600 text-sm mt-1">Email is available</div>
                </div>
                
                <!-- Password field -->
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="password" x-model="password" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{'border-red-500': errors.password}">
                    <p x-show="errors.password" class="text-red-500 text-sm mt-1" x-text="errors.password"></p>
                </div>
                
                <!-- Confirm Password field -->
                <div class="mb-4">
                    <label for="confirm-password" class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                    <input type="password" id="confirm-password" x-model="confirmPassword" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{'border-red-500': errors.confirmPassword}">
                    <p x-show="errors.confirmPassword" class="text-red-500 text-sm mt-1" x-text="errors.confirmPassword"></p>
                </div>
                
                <!-- Cardholder Name field -->
                <div class="mb-4">
                    <label for="cardholder-name" class="block text-gray-700 font-medium mb-2">Cardholder Name</label>
                    <input type="text" id="cardholder-name" x-model="cardholderName" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{'border-red-500': errors.cardholderName}">
                    <p x-show="errors.cardholderName" class="text-red-500 text-sm mt-1" x-text="errors.cardholderName"></p>
                </div>
                
                <!-- Square Card Element -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Card Details</label>
                    <div id="card-container" class="min-h-[40px]"></div>
                    <p class="text-gray-500 text-sm mt-1">Your card will be charged $<?php echo MMC_MEMBERSHIP_PRICE; ?>/month for your membership.</p>
                </div>
                
                <!-- Submit button -->
                <button type="submit" class="elementor-button elementor-size-md w-full" :disabled="loading || squareStatus === 'processing'">
                    <span x-show="!loading && squareStatus !== 'processing'"><?php echo esc_html($atts['button_text']); ?></span>
                    <span x-show="loading || squareStatus === 'processing'" class="inline-flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
                
                <!-- Login link -->
                <div class="mt-4 text-center">
                    <p class="text-gray-600">Already have an account? <a href="<?php echo esc_url(\wp_login_url()); ?>" class="text-blue-600 hover:text-blue-800">Log in</a></p>
                </div>
                
                <?php if ($environment === 'sandbox'): ?>
                <!-- Test data button (only shown in sandbox mode) -->
                <div class="mt-4 text-center">
                    <button type="button" @click="fillTestData()" class="text-sm bg-gray-200 text-gray-700 py-1 px-3 rounded hover:bg-gray-300 focus:outline-none focus:ring-1 focus:ring-gray-500 transition-colors">
                        Fill with Test Data
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX new user signup requests
     */
    public function ajax_handle_new_user_signup() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !\wp_verify_nonce($_POST['nonce'], 'mmc-new-user-signup-nonce')) {
            \wp_send_json_error(['message' => 'Invalid security token.']);
            exit;
        }
        
        // Check required fields
        $required_fields = ['source_id', 'plan_id', 'name', 'email', 'password'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                \wp_send_json_error(['message' => "Missing required field: {$field}"]);
                exit;
            }
        }
        
        // Validate email
        $email = \sanitize_email($_POST['email']);
        if (!\is_email($email)) {
            \wp_send_json_error(['message' => 'Please enter a valid email address.']);
            exit;
        }
        
        // Check if email already exists
        if (\email_exists($email)) {
            \wp_send_json_error(['message' => 'This email address is already registered. Please log in instead.']);
            exit;
        }
        
        // Validate password
        $password = $_POST['password'];
        if (strlen($password) < 8) {
            \wp_send_json_error(['message' => 'Password must be at least 8 characters long.']);
            exit;
        }
        
        try {
            // Get API credentials
            $access_token = \get_option('square_service_access_token', '');
            $environment = \get_option('square_service_environment', 'sandbox');
            
            // Create service instance
            $square_service = new \MMCMembership\SquareService();
            
            // Create customer in Square
            $name = \sanitize_text_field($_POST['name']);
            $customer_data = [
                'email_address' => $email,
                'given_name' => $name
            ];
            
            $customer_response = $square_service->createCustomer($customer_data);
            if (!$customer_response || !isset($customer_response->id)) {
                throw new \Exception('Failed to create customer in Square.');
            }
            
            $customer_id = $customer_response->id;
            
            // Add the card to customer using source_id (card token)
            $source_id = \sanitize_text_field($_POST['source_id']);
            $plan_id = \sanitize_text_field($_POST['plan_id']);
            
            $card_response = $square_service->addCardToCustomer($customer_id, $source_id);
            if (!$card_response || !isset($card_response->id)) {
                // If card addition fails, delete the customer we just created
                $square_service->deleteCustomer($customer_id);
                throw new \Exception('Failed to add payment card to customer.');
            }
            
            $card_id = $card_response->id;
            
            // Create subscription
            $subscription_data = $square_service->createSubscription($customer_id, $card_id, $plan_id);
            if (!$subscription_data || !isset($subscription_data->id)) {
                // If subscription creation fails, delete the customer and card
                $square_service->deleteCustomerCard($customer_id, $card_id);
                $square_service->deleteCustomer($customer_id);
                throw new \Exception('Failed to create subscription.');
            }
            
            $subscription_id = $subscription_data->id;
            
            // Now create the WordPress user
            $username = $this->generate_username_from_email($email);
            
            $user_id = \wp_create_user($username, $password, $email);
            if (\is_wp_error($user_id)) {
                // If user creation fails, cancel the subscription and delete customer
                $square_service->cancelSubscription($subscription_id);
                $square_service->deleteCustomerCard($customer_id, $card_id);
                $square_service->deleteCustomer($customer_id);
                throw new \Exception('Failed to create user account: ' . $user_id->get_error_message());
            }
            
            // Update user meta
            \wp_update_user([
                'ID' => $user_id,
                'first_name' => $name,
                'display_name' => $name
            ]);
            
            // Add user to the mmc_member role if it exists
            $user = new \WP_User($user_id);
            if (\get_role('mmc_member')) {
                $user->add_role('mmc_member');
            }
            
            // Save Square data as user meta
            \update_user_meta($user_id, 'square_customer_id', $customer_id);
            \update_user_meta($user_id, 'square_card_id', $card_id);
            \update_user_meta($user_id, 'square_subscription_id', $subscription_id);
            \update_user_meta($user_id, 'square_subscription_plan_id', $plan_id);
            \update_user_meta($user_id, 'square_active_membership', 'yes');
            
            // Save card details for display
            if ($card_response) {
                // Extract card details - use null coalescing operator for cleaner code
                $card_brand = $card_response->card_brand ?? '';
                $last_4 = $card_response->last_4 ?? '';
                $exp_month = $card_response->exp_month ?? '';
                $exp_year = $card_response->exp_year ?? '';
                
                // Save card details as user meta
                \update_user_meta($user_id, 'square_card_brand', $card_brand);
                \update_user_meta($user_id, 'square_card_last4', $last_4);
                \update_user_meta($user_id, 'square_card_exp_month', $exp_month);
                \update_user_meta($user_id, 'square_card_exp_year', $exp_year);
            }
            
            // Call UserFunctions to properly set active membership status
            UserFunctions::set_active_membership($user_id, $subscription_data);
            
            // Log the user in
            \wp_set_auth_cookie($user_id, true);
            
            // Return success with redirect URL
            $redirect_url = '/membership';
            
            // Only use a different URL if explicitly provided in the form submission
            if (isset($_POST['redirect_url']) && !empty($_POST['redirect_url'])) {
                $redirect_url = \esc_url_raw($_POST['redirect_url']);
            }
            
            \wp_send_json_success([
                'message' => 'Account created successfully! Redirecting to your membership page...',
                'redirect_url' => $redirect_url
            ]);
            
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * AJAX handler to check if an email already exists
     */
    public function ajax_check_email_exists() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !\wp_verify_nonce($_POST['nonce'], 'mmc-new-user-signup-nonce')) {
            \wp_send_json_error(['message' => 'Invalid security token.']);
            exit;
        }
        
        // Check if email is provided
        if (!isset($_POST['email']) || empty($_POST['email'])) {
            \wp_send_json_error(['message' => 'Email address is required.']);
            exit;
        }
        
        // Validate and sanitize email
        $email = \sanitize_email($_POST['email']);
        if (!\is_email($email)) {
            \wp_send_json_error(['message' => 'Please enter a valid email address.']);
            exit;
        }
        
        // Check if email exists
        $exists = \email_exists($email);
        
        if ($exists) {
            \wp_send_json_error([
                'message' => 'This email address is already registered. Please log in instead.',
                'exists' => true
            ]);
        } else {
            \wp_send_json_success([
                'message' => 'Email address is available.',
                'exists' => false
            ]);
        }
        
        exit;
    }
}

// Initialize the shortcode
$membership_signup = MembershipSignup::get_instance();
