<?php
/**
 * MMC Membership Settings Page
 */

use MMCMembership\SquareService;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MMCMembershipSettings {
    private static $instance = null;
    
    // Singleton pattern
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        // Register AJAX handler for testing connection
        add_action('wp_ajax_square_service_test_connection', array($this, 'ajax_test_connection'));
        // Register AJAX handler for creating a subscription plan
        add_action('wp_ajax_mmc_membership_create_plan', array($this, 'ajax_create_subscription_plan'));
        // Register AJAX handler for testing Constant Contact connection
        add_action('wp_ajax_mmc_membership_test_cc_connection', array($this, 'ajax_test_cc_connection'));
    }
    
    /**
     * AJAX handler for testing connection to Square API
     */
    public function ajax_test_connection() {
        // Verify that user has permission
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        try {
            // Test by creating a dummy customer - this will throw an exception if connection fails
            // We'll use a method that we know is public in the SquareService class
            $square_service = SquareService::get_instance();
            
            // Create a test customer with a unique email to avoid duplicates
            $test_email = 'test_' . time() . '@example.com';
            $customer_data = array(
                'email_address' => $test_email,
                'note' => 'Test customer for API connection verification. Safe to delete.'
            );
            
            // This call will fail if the API connection doesn't work
            $result = $square_service->createCustomer($customer_data);
            
            // Handle both return formats - could be object or array
            $customer_id = null;
            if (is_object($result) && isset($result->id)) {
                $customer_id = $result->id;
            } elseif (is_array($result) && isset($result['customer']['id'])) {
                $customer_id = $result['customer']['id'];
            }
            
            if ($customer_id) {
                // Success - connection works!
                echo '<strong>Connection successful!</strong> Created a test customer with ID: ' . esc_html($customer_id) . '<br>';
                
                // Cleanup: Delete the test customer now that we've confirmed connectivity
                if ($square_service->deleteCustomer($customer_id)) {
                    echo '<p><em>Test customer has been automatically deleted.</em></p>';
                }
            } else {
                // API call worked but didn't return expected data
                echo '<strong>Connection appears to work</strong>, but could not create a test customer. Check your access token permissions.';
            }
        } catch (Exception $e) {
            // Connection failed
            echo '<strong>Connection failed:</strong> ' . esc_html($e->getMessage());
        }
        
        wp_die(); // Required to terminate AJAX request properly
    }
    
    /**
     * AJAX handler for creating a subscription plan in Square
     */
    public function ajax_create_subscription_plan() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mmc-membership-create-plan-nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        try {
            // Initialize Square service
            require_once(plugin_dir_path(dirname(__FILE__)) . 'includes/!SquareService.php');
            $square_service = new \MMCMembership\SquareService();
            
            // Create the subscription plan
            $result = $square_service->ensureMonthlyMembershipPlanExists();
            
            if ($result && isset($result['plan_variation_id'])) {
                // Update the option with the new plan variation ID
                update_option('square_service_default_plan_id', $result['plan_variation_id']);
                
                // Determine message based on status
                $message = ($result['status'] === 'exists') 
                    ? 'Existing subscription plan found and selected!' 
                    : 'Subscription plan created successfully!';
                
                wp_send_json_success(array(
                    'message' => $message,
                    'plan_id' => $result['plan_variation_id']
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to create subscription plan. Please check Square API settings.'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Add MMC Memberships menu and settings page
     */
    public function add_admin_menu() {
        // Add top-level admin menu
        add_menu_page(
            'MMC Memberships', // Page title
            'MMC Memberships', // Menu title
            'manage_options', // Capability
            'mmc-memberships', // Menu slug
            null, // Function - we don't need a default page callback
            'dashicons-groups', // Icon
            30 // Position
        );
        
        // Add settings as submenu
        add_submenu_page(
            'mmc-memberships', // Parent slug
            'Settings', // Page title
            'Settings', // Menu title
            'manage_options', // Capability
            'mmc-memberships', // Menu slug - same as parent to make it the default page
            array($this, 'display_settings_page') // Callback function
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('mmc-memberships', 'mmc_membership_access_token');
        register_setting('mmc-memberships', 'mmc_membership_application_id');
        register_setting('mmc-memberships', 'mmc_membership_location_id');
        register_setting('mmc-memberships', 'mmc_membership_default_plan_id');
        register_setting('mmc-memberships', 'mmc_membership_environment');
        
        // Register membership settings
        register_setting('mmc-memberships', 'mmc_membership_signup_page_id');
        register_setting('mmc-memberships', 'mmc_membership_club_name');
        register_setting('mmc-memberships', 'mmc_membership_price');
        register_setting('mmc-memberships', 'mmc_membership_login_page_id', array($this, 'validate_page_id'));
        register_setting('mmc-memberships', 'mmc_membership_account_page_id', array($this, 'validate_page_id'));
        
        // Register Constant Contact settings
        register_setting('mmc-memberships', 'mmc_membership_cc_api_key');
        register_setting('mmc-memberships', 'mmc_membership_cc_access_token');
        register_setting('mmc-memberships', 'mmc_membership_cc_list_id');
        
        // Add Square API settings section
        add_settings_section(
            'mmc-memberships-api',
            'Square API Settings',
            array($this, 'section_callback'),
            'mmc-memberships'
        );
        
        // Add membership settings section
        add_settings_section(
            'mmc-memberships-general',
            'Membership Settings',
            array($this, 'membership_section_callback'),
            'mmc-memberships'
        );
        
        // Add Constant Contact settings section
        add_settings_section(
            'mmc-memberships-cc',
            'Constant Contact Settings',
            array($this, 'constant_contact_section_callback'),
            'mmc-memberships'
        );
        
        // Add settings fields
        add_settings_field(
            'mmc_membership_access_token',
            'Access Token',
            array($this, 'access_token_callback'),
            'mmc-memberships',
            'mmc-memberships-api'
        );
        
        add_settings_field(
            'mmc_membership_application_id',
            'Application ID',
            array($this, 'application_id_callback'),
            'mmc-memberships',
            'mmc-memberships-api'
        );
        
        add_settings_field(
            'mmc_membership_location_id',
            'Location ID',
            array($this, 'location_id_callback'),
            'mmc-memberships',
            'mmc-memberships-api'
        );
        
        add_settings_field(
            'mmc_membership_default_plan_id',
            'Default Plan ID',
            array($this, 'default_plan_id_callback'),
            'mmc-memberships',
            'mmc-memberships-api'
        );
        
        add_settings_field(
            'mmc_membership_environment',
            'Environment',
            array($this, 'environment_callback'),
            'mmc-memberships',
            'mmc-memberships-api'
        );
        
        add_settings_field(
            'mmc_membership_signup_page_id',
            'Signup Page',
            array($this, 'signup_page_callback'),
            'mmc-memberships',
            'mmc-memberships-general'
        );
        
        add_settings_field(
            'mmc_membership_club_name',
            'Club Name',
            array($this, 'club_name_callback'),
            'mmc-memberships',
            'mmc-memberships-general'
        );
        
        add_settings_field(
            'mmc_membership_price',
            'Membership Price',
            array($this, 'membership_price_callback'),
            'mmc-memberships',
            'mmc-memberships-general'
        );
        
        add_settings_field(
            'mmc_membership_login_page_id',
            'Login Page',
            array($this, 'login_page_callback'),
            'mmc-memberships',
            'mmc-memberships-general'
        );
        
        add_settings_field(
            'mmc_membership_account_page_id',
            'Account Page',
            array($this, 'account_page_callback'),
            'mmc-memberships',
            'mmc-memberships-general'
        );
        
        // Add Constant Contact settings fields
        add_settings_field(
            'mmc_membership_cc_api_key',
            'API Key',
            array($this, 'cc_api_key_callback'),
            'mmc-memberships',
            'mmc-memberships-cc'
        );
        
        add_settings_field(
            'mmc_membership_cc_access_token',
            'Access Token',
            array($this, 'cc_access_token_callback'),
            'mmc-memberships',
            'mmc-memberships-cc'
        );
        
        add_settings_field(
            'mmc_membership_cc_list_id',
            'Member Exclusive List',
            array($this, 'cc_list_id_callback'),
            'mmc-memberships',
            'mmc-memberships-cc'
        );
    }
    
    /**
     * Section introduction
     */
    public function section_callback() {
        echo '<p>Configure your Square API credentials</p>';
    }
    
    /**
     * Access token field
     */
    public function access_token_callback() {
        $value = get_option('square_service_access_token');
        echo '<input type="password" class="regular-text" name="square_service_access_token" value="' . esc_attr($value) . '">';
        echo '<p class="description">Your Square API access token</p>';
    }
    
    /**
     * Application ID field
     */
    public function application_id_callback() {
        $application_id = get_option('square_service_application_id');
        echo '<input type="text" id="square_service_application_id" name="square_service_application_id" value="' . esc_attr($application_id) . '" class="regular-text">';
        echo '<p class="description">Your Square Application ID. Required for the Square Web Payments SDK.</p>';
    }
    
    /**
     * Default Plan ID field
     */
    public function default_plan_id_callback() {
        $plan_id = get_option('square_service_default_plan_id');
        echo '<input type="text" id="square_service_default_plan_id" name="square_service_default_plan_id" value="' . esc_attr($plan_id) . '" class="regular-text">';
        echo '<p class="description">Your default Square subscription plan ID. Will be used when no plan_id is specified in the shortcode.</p>';
        
        // Add button to create plan if it doesn't exist
        echo '<div style="margin-top: 10px;">';
        echo '<button type="button" id="create-square-plan" class="button button-secondary">Create Subscription Plan in Square</button>';
        echo '<span id="create-plan-status" style="margin-left: 10px; display: none;"></span>';
        echo '</div>';
        
        // Add JavaScript to handle the button click
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#create-square-plan').on('click', function() {
                // Show loading status
                $('#create-plan-status').html('<span style="color: #666;"><img src="<?php echo admin_url('images/spinner.gif'); ?>" style="vertical-align: middle;"> Creating plan...</span>').show();
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mmc_membership_create_plan',
                        nonce: '<?php echo wp_create_nonce('mmc-membership-create-plan-nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update the plan ID field with the new ID
                            $('#square_service_default_plan_id').val(response.data.plan_id);
                            $('#create-plan-status').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                        } else {
                            $('#create-plan-status').html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $('#create-plan-status').html('<span style="color: red;">✗ Error connecting to server</span>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Location ID field
     */
    public function location_id_callback() {
        $value = get_option('square_service_location_id');
        echo '<input type="text" class="regular-text" name="square_service_location_id" value="' . esc_attr($value) . '">';
        echo '<p class="description">Your Square location ID (required for subscriptions)</p>';
    }
    
    /**
     * Environment selection
     */
    public function environment_callback() {
        $value = get_option('square_service_environment', 'sandbox');
        ?>
        <select name="square_service_environment">
            <option value="sandbox" <?php selected($value, 'sandbox'); ?>>Sandbox</option>
            <option value="production" <?php selected($value, 'production'); ?>>Production</option>
        </select>
        <p class="description">Select the Square API environment</p>
        <?php
    }
    
    /**
     * Membership section introduction
     */
    public function membership_section_callback() {
        echo '<p>Configure membership settings for your site</p>';
    }
    
    /**
     * Signup page selection
     */
    public function signup_page_callback() {
        $signup_page_id = get_option('mmc_membership_signup_page_id', 0);
        
        // Get all published pages
        $pages = get_pages(array(
            'post_status' => 'publish',
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ));
        
        if (empty($pages)) {
            echo '<p>No pages found. <a href="' . admin_url('post-new.php?post_type=page') . '">Create a page</a> first.</p>';
            return;
        }
        
        echo '<select name="mmc_membership_signup_page_id">';
        echo '<option value="">-- Select a Page --</option>';
        
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr($page->ID) . '" ' . selected($signup_page_id, $page->ID, false) . '>';
            echo esc_html($page->post_title);
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">Select the page where you have placed the membership signup form shortcode.</p>';
        echo '<p class="description">This page will be used for "Sign Up" links throughout the site.</p>';
    }
    
    /**
     * Club name field
     */
    public function club_name_callback() {
        $club_name = get_option('mmc_membership_club_name', 'Minerva Motor Club');
        echo '<input type="text" id="mmc_membership_club_name" name="mmc_membership_club_name" value="' . esc_attr($club_name) . '" class="regular-text">';
        echo '<p class="description">The name of your club that will be displayed throughout the membership forms.</p>';
    }
    
    /**
     * Membership price field
     */
    public function membership_price_callback() {
        $price = get_option('mmc_membership_price', '8.99');
        echo '<div class="input-group">';
        echo '<span class="input-group-addon">$</span>';
        echo '<input type="number" id="mmc_membership_price" name="mmc_membership_price" value="' . esc_attr($price) . '" class="regular-text" step="0.01" min="0.01">';
        echo '</div>';
        echo '<p class="description">The monthly price for membership (e.g., 8.99).</p>';
    }
    
    /**
     * Login page field
     */
    public function login_page_callback() {
        $page_id = get_option('mmc_membership_login_page_id');
        
        // Get all pages
        $pages = get_pages();
        
        echo '<select id="mmc_membership_login_page_id" name="mmc_membership_login_page_id">';
        echo '<option value="">-- Select a page --</option>';
        
        // Add option to create a new page
        echo '<option value="create_new" ' . selected($page_id, 'create_new', false) . '>Create new page</option>';
        
        // List all existing pages
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr($page->ID) . '" ' . selected($page_id, $page->ID, false) . '>';
            echo esc_html($page->post_title);
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">Select the page where users will log in. This page should contain the <code>[mmc_login_form]</code> shortcode.</p>';
        
        // If a page is selected but doesn't have the shortcode, show a warning
        if ($page_id && $page_id !== 'create_new' && is_numeric($page_id)) {
            $page_content = get_post_field('post_content', $page_id);
            if (strpos($page_content, '[mmc_login_form') === false) {
                echo '<p class="description" style="color: #d63638;">Warning: The selected page does not contain the <code>[mmc_login_form]</code> shortcode.</p>';
            }
        }
    }
    
    /**
     * Account page field
     */
    public function account_page_callback() {
        $page_id = get_option('mmc_membership_account_page_id');
        
        // Get all pages
        $pages = get_pages();
        
        echo '<select id="mmc_membership_account_page_id" name="mmc_membership_account_page_id">';
        echo '<option value="">-- Select a page --</option>';
        
        // Add option to create a new page
        echo '<option value="create_new" ' . selected($page_id, 'create_new', false) . '>Create new page</option>';
        
        // List all existing pages
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr($page->ID) . '" ' . selected($page_id, $page->ID, false) . '>';
            echo esc_html($page->post_title);
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">Select the page where users will manage their account. This page should contain the <code>[mmc_my_account]</code> shortcode.</p>';
        
        // If a page is selected but doesn't have the shortcode, show a warning
        if ($page_id && $page_id !== 'create_new' && is_numeric($page_id)) {
            $page_content = get_post_field('post_content', $page_id);
            if (strpos($page_content, '[mmc_my_account') === false) {
                echo '<p class="description" style="color: #d63638;">Warning: The selected page does not contain the <code>[mmc_my_account]</code> shortcode.</p>';
            }
        }
    }
    
    /**
     * Validate page ID and create new pages if needed
     * 
     * @param string $value The value to validate
     * @return string|int The validated value
     */
    public function validate_page_id($value) {
        // If the value is 'create_new', create a new page
        if ($value === 'create_new') {
            // Determine which page we're creating based on the option name
            $option_name = current_filter();
            
            if ($option_name === 'sanitize_option_mmc_membership_login_page_id') {
                // Create login page
                $page_title = 'Login';  
                $page_content = '[mmc_login_form]';
            } elseif ($option_name === 'sanitize_option_mmc_membership_account_page_id') {
                // Create account page
                $page_title = 'My Account';
                $page_content = '[mmc_my_account]';
            } else {
                // Unknown option, return empty
                return '';
            }
            
            // Create the page
            $club_name = get_option('mmc_membership_club_name', 'Minerva Motor Club');
            $page_data = array(
                'post_title'    => $club_name . ' ' . $page_title,
                'post_content'  => $page_content,
                'post_status'   => 'publish',
                'post_type'     => 'page',
            );
            
            $page_id = wp_insert_post($page_data);
            
            if ($page_id && !is_wp_error($page_id)) {
                // Return the new page ID
                add_settings_error(
                    'mmc-memberships',
                    'page-created',
                    sprintf('New %s page created successfully.', $page_title),
                    'success'
                );
                return $page_id;
            } else {
                // Failed to create page
                add_settings_error(
                    'mmc-memberships',
                    'page-create-error',
                    sprintf('Failed to create %s page. Please try again or create it manually.', $page_title),
                    'error'
                );
                return '';
            }
        }
        
        // Otherwise, just return the value
        return $value;
    }
    
    /**
     * Constant Contact section introduction
     */
    public function constant_contact_section_callback() {
        echo '<p>Configure Constant Contact integration for your membership system.</p>';
        echo '<p>This will allow you to automatically add members to your "Member Exclusive" list when their membership is activated, and remove them when their membership is deactivated.</p>';
    }
    
    /**
     * Constant Contact API Key field
     */
    public function cc_api_key_callback() {
        $api_key = get_option('mmc_membership_cc_api_key', '');
        echo '<input type="text" name="mmc_membership_cc_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">Enter your Constant Contact API Key. You can find this in your Constant Contact account under "My Settings" > "API Keys".</p>';
    }
    
    /**
     * Constant Contact Access Token field
     */
    public function cc_access_token_callback() {
        $access_token = get_option('mmc_membership_cc_access_token', '');
        echo '<input type="text" name="mmc_membership_cc_access_token" value="' . esc_attr($access_token) . '" class="regular-text" />';
        echo '<p class="description">Enter your Constant Contact Access Token.</p>';
    }
    
    /**
     * Constant Contact List ID field
     */
    public function cc_list_id_callback() {
        $list_id = get_option('mmc_membership_cc_list_id', '');
        $api_key = get_option('mmc_membership_cc_api_key', '');
        $access_token = get_option('mmc_membership_cc_access_token', '');
        
        // Check if API credentials are set
        if (empty($api_key) || empty($access_token)) {
            echo '<p class="description">Please enter your Constant Contact API Key and Access Token first, then save settings to see available lists.</p>';
            echo '<input type="hidden" name="mmc_membership_cc_list_id" value="' . esc_attr($list_id) . '" />';
            return;
        }
        
        // Try to get lists from Constant Contact
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ConstantContactService.php';
        $cc_service = new \MMCMembership\ConstantContactService($api_key, $access_token);
        
        try {
            $lists = $cc_service->getLists();
            
            if (empty($lists)) {
                echo '<p class="description">No lists found in your Constant Contact account. Please create a list first.</p>';
                echo '<input type="hidden" name="mmc_membership_cc_list_id" value="' . esc_attr($list_id) . '" />';
                return;
            }
            
            // Display dropdown of lists
            echo '<select name="mmc_membership_cc_list_id" class="regular-text">';
            echo '<option value="">— Select a list —</option>';
            
            foreach ($lists as $list) {
                $selected = ($list_id === $list['list_id']) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($list['list_id']) . '" ' . $selected . '>';
                echo esc_html($list['name']);
                echo '</option>';
            }
            
            echo '</select>';
            echo '<p class="description">Select your "Member Exclusive" list from Constant Contact.</p>';
            
        } catch (\Exception $e) {
            echo '<p class="description">Error retrieving lists from Constant Contact: ' . esc_html($e->getMessage()) . '</p>';
            echo '<input type="hidden" name="mmc_membership_cc_list_id" value="' . esc_attr($list_id) . '" />';
        }
    }
    
    /**
     * AJAX handler for testing connection to Constant Contact API
     */
    public function ajax_test_cc_connection() {
        // Verify that user has permission
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        try {
            // Create a new instance of the ConstantContactService
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ConstantContactService.php';
            $cc_service = new \MMCMembership\ConstantContactService();
            
            // Test the connection
            $result = $cc_service->testConnection();
            
            // Return the result as JSON
            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error testing connection: ' . $e->getMessage()
            ]);
        }
        
        wp_die();
    }
    
    /**
     * Display the settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>MMC Memberships Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mmc-memberships');
                do_settings_sections('mmc-memberships');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2>Testing Square Connection</h2>
                <p>To test your connection to Square API, save your settings and then click the button below:</p>
                <button id="square-test-connection" class="button button-secondary">Test Square Connection</button>
                <div id="square-test-result" style="margin-top: 10px; padding: 10px; display: none;"></div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Testing Constant Contact Connection</h2>
                <p>To test your connection to Constant Contact API, save your settings and then click the button below:</p>
                <button id="cc-test-connection" class="button button-secondary">Test Constant Contact</button>
                <div id="cc-test-result" style="margin-top: 10px; padding: 10px; display: none;"></div>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('#square-test-connection').click(function(e) {
                        e.preventDefault();
                        
                        var $button = $(this);
                        var $result = $('#square-test-result');
                        
                        $button.prop('disabled', true).text('Testing...');
                        $result.hide();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'square_service_test_connection'
                            },
                            success: function(response) {
                                $button.prop('disabled', false).text('Test Square Connection');
                                $result.html(response)
                                    .removeClass('notice-error notice-success')
                                    .addClass(response.includes('successful') ? 'notice-success' : 'notice-error')
                                    .addClass('notice').show();
                            },
                            error: function() {
                                $button.prop('disabled', false).text('Test Square Connection');
                                $result.html('Error testing connection. Please check your PHP error logs.')
                                    .removeClass('notice-success')
                                    .addClass('notice notice-error').show();
                            }
                        });
                    });
                    
                    $('#cc-test-connection').click(function(e) {
                        e.preventDefault();
                        
                        var $button = $(this);
                        var $result = $('#cc-test-result');
                        
                        $button.prop('disabled', true).text('Testing...');
                        $result.hide();
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'mmc_membership_test_cc_connection'
                            },
                            success: function(response) {
                                $button.prop('disabled', false).text('Test Constant Contact');
                                try {
                                    var data = JSON.parse(response);
                                    var html = '<p>' + data.message + '</p>';
                                    
                                    if (data.success && data.lists && data.lists.length > 0) {
                                        html += '<p>Available lists:</p><ul style="margin-left: 15px; list-style-type: disc;">';
                                        data.lists.forEach(function(list) {
                                            html += '<li>' + list.name + ' (ID: ' + list.list_id + ')</li>';
                                        });
                                        html += '</ul>';
                                        html += '<p><strong>Tip:</strong> You can use one of these list IDs in the "Member Exclusive List" field.</p>';
                                    }
                                    
                                    $result.html(html)
                                        .removeClass('notice-error notice-success')
                                        .addClass(data.success ? 'notice-success' : 'notice-error')
                                        .addClass('notice').show();
                                } catch (e) {
                                    $result.html(response)
                                        .removeClass('notice-success')
                                        .addClass('notice notice-error').show();
                                }
                            },
                            error: function() {
                                $button.prop('disabled', false).text('Test Constant Contact');
                                $result.html('Error testing connection. Please check your PHP error logs.')
                                    .removeClass('notice-success')
                                    .addClass('notice notice-error').show();
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
}

// Initialize the settings page
$mmc_membership_settings = MMCMembershipSettings::get_instance();
