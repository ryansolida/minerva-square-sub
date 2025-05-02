<?php
/**
 * Square Service Settings Page
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SquareServiceSettings {
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
     * Add options page to admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'Square Service Settings', 
            'Square Service', 
            'manage_options', 
            'square-service', 
            array($this, 'display_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings group
        register_setting(
            'square_service', 
            'square_service_access_token', 
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        
        register_setting(
            'square_service', 
            'square_service_location_id', 
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        
        register_setting(
            'square_service', 
            'square_service_application_id', 
            array(
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        
        register_setting(
            'square_service', 
            'square_service_environment', 
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'sandbox'
            )
        );
        add_settings_section(
            'square_service_section', 
            'API Configuration', 
            array($this, 'section_callback'), 
            'square_service'
        );
        
        add_settings_field(
            'square_service_access_token', 
            'Access Token', 
            array($this, 'access_token_callback'), 
            'square_service', 
            'square_service_section'
        );
        
        add_settings_field(
            'square_service_application_id', 
            'Application ID', 
            array($this, 'application_id_callback'), 
            'square_service', 
            'square_service_section'
        );
        
        add_settings_field(
            'square_service_location_id', 
            'Location ID', 
            array($this, 'location_id_callback'), 
            'square_service', 
            'square_service_section'
        );
        
        add_settings_field(
            'square_service_environment', 
            'Environment', 
            array($this, 'environment_callback'), 
            'square_service', 
            'square_service_section'
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
        $value = get_option('square_service_application_id');
        echo '<input type="text" class="regular-text" name="square_service_application_id" value="' . esc_attr($value) . '">';
        echo '<p class="description">Your Square application ID (required for Web Payments SDK)</p>';
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
     * Display the settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1>Square Service Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('square_service');
                do_settings_sections('square_service');
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
function square_service_settings() {
    return SquareServiceSettings::get_instance();
}

square_service_settings();
