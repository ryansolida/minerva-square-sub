<?php
/**
 * MMC Membership Settings Page
 */

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
            $square_service = get_square_service();
            
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
                
                // Now try to get locations
                try {
                    // Use the Square SDK client directly
                    $locations_api = $square_service->getClient()->getLocationsApi();
                    $locations_result = $locations_api->listLocations();
                    
                    if ($locations_result->isSuccess()) {
                        $locations = $locations_result->getResult()->getLocations();
                        $location_count = count($locations);
                        
                        echo 'Found ' . $location_count . ' location(s):<br>';
                        echo '<ul style="margin-left: 15px; list-style-type: disc;">';
                        
                        foreach ($locations as $location) {
                            echo '<li>' . esc_html($location->getName()) . ' (ID: ' . esc_html($location->getId()) . ')</li>';
                        }
                        echo '</ul>';
                        
                        // Location ID help
                        $current_location_id = get_option('square_service_location_id');
                        if (empty($current_location_id) && $location_count > 0) {
                            $first_location_id = $locations[0]->getId();
                            if (!empty($first_location_id)) {
                                echo '<p><strong>Tip:</strong> You can use <code>' . esc_html($first_location_id) . '</code> as your Location ID.</p>';
                            }
                        }
                    } else {
                        echo '<p>Connection works, but could not retrieve location list. Basic functionality should still work.</p>';
                    }
                } catch (Exception $e) {
                    echo '<p>Note: Connection established but unable to retrieve locations. Basic functionality should still work.</p>';
                }
                
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
        // Register settings group
        register_setting(
            'mmc-memberships', 
            'square_service_access_token', 
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        
        register_setting(
            'mmc-memberships', 
            'square_service_application_id', 
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        
        register_setting(
            'mmc-memberships', 
            'square_service_environment', 
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'sandbox'
            )
        );
        
        register_setting(
            'mmc-memberships', 
            'square_service_default_plan_id', 
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        
        // Register the signup page ID setting
        register_setting(
            'mmc-memberships',
            'square_service_signup_page_id',
            array(
                'sanitize_callback' => 'absint',
                'default' => 0
            )
        );
        
        add_settings_section(
            'square_service_section', 
            'API Configuration', 
            array($this, 'section_callback'), 
            'mmc-memberships'
        );
        
        add_settings_field(
            'square_service_access_token', 
            'Access Token', 
            array($this, 'access_token_callback'), 
            'mmc-memberships', 
            'square_service_section'
        );
        
        add_settings_field(
            'square_service_application_id', 
            'Application ID', 
            array($this, 'application_id_callback'), 
            'mmc-memberships', 
            'square_service_section'
        );
        
        add_settings_field(
            'square_service_default_plan_id', 
            'Default Subscription Plan ID', 
            array($this, 'default_plan_id_callback'), 
            'mmc-memberships', 
            'square_service_section'
        );
        
        add_settings_field(
            'square_service_location_id', 
            'Location ID', 
            array($this, 'location_id_callback'), 
            'mmc-memberships', 
            'square_service_section'
        );
        
        add_settings_field(
            'square_service_environment', 
            'Environment', 
            array($this, 'environment_callback'), 
            'mmc-memberships', 
            'square_service_section'
        );
        
        // Add membership settings section
        add_settings_section(
            'square_service_membership_section',
            'Membership Settings',
            array($this, 'membership_section_callback'),
            'mmc-memberships'
        );
        
        // Add signup page field
        add_settings_field(
            'square_service_signup_page_id',
            'Membership Signup Page',
            array($this, 'signup_page_callback'),
            'mmc-memberships',
            'square_service_membership_section'
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
        $signup_page_id = get_option('square_service_signup_page_id', 0);
        
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
        
        echo '<select name="square_service_signup_page_id">';
        echo '<option value="0">— Select a page —</option>';
        
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
                <h2>Testing Connection</h2>
                <p>To test your connection to Square API, save your settings and then click the button below:</p>
                <button id="square-test-connection" class="button button-secondary">Test Connection</button>
                <div id="square-test-result" style="margin-top: 10px; padding: 10px; display: none;"></div>
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
                                $button.prop('disabled', false).text('Test Connection');
                                $result.html(response)
                                    .removeClass('notice-error notice-success')
                                    .addClass(response.includes('successful') ? 'notice-success' : 'notice-error')
                                    .addClass('notice').show();
                            },
                            error: function() {
                                $button.prop('disabled', false).text('Test Connection');
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
$mmc_membership_setings = MMCMembershipSettings::get_instance();
