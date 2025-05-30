<?php

namespace MMCMembership;

/**
 * My Account functionality for MMC Membership
 */
class MyAccount {
    
    /**
     * Initialize the My Account functionality
     */
    public function __construct() {
        // Register shortcode for account page
        \add_shortcode('mmc_my_account', array($this, 'render_my_account'));
        
        // Hook into WordPress init to process account actions
        \add_action('init', array($this, 'process_account_actions'));
        
        // Enqueue scripts and styles
        \add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts and styles for the account page
     */
    public function enqueue_scripts() {
        // Check if the shortcode is being used on the current page
        global $post;
        $has_shortcode = false;
        
        if (\is_a($post, 'WP_Post') && \has_shortcode($post->post_content, 'mmc_my_account')) {
            $has_shortcode = true;
        }
        
        // Also check the account page setting
        $account_page_id = \get_option('mmc_membership_account_page_id');
        $is_account_page = $account_page_id && \is_page($account_page_id);
        
        // Enqueue if either condition is true
        if ($has_shortcode || $is_account_page) {
            // Enqueue CSS with a version parameter to prevent caching
            \wp_enqueue_style(
                'mmc-account-styles',
                \plugins_url('/assets/css/mmc-account.css', \dirname(__FILE__, 1)),
                array(),
                time() // Use current time to force cache refresh
            );
            
            // Enqueue JavaScript (depends on jQuery)
            \wp_enqueue_script(
                'mmc-account-scripts',
                \plugins_url('/assets/js/mmc-account.js', \dirname(__FILE__, 1)),
                array('jquery'),
                time(), // Use current time to force cache refresh
                true
            );
        }
    }
    
    /**
     * Render the My Account page
     */
    public function render_my_account($atts) {
        // If user is not logged in, redirect to login page
        if (!\is_user_logged_in()) {
            $login_page_id = \get_option('mmc_membership_login_page_id');
            $login_url = $login_page_id ? \get_permalink($login_page_id) : \wp_login_url();
            $redirect_url = \add_query_arg('redirect_to', \urlencode(\get_permalink()), $login_url);
            
            return '<div class="mmc-account-message">Please <a href="' . \esc_url($redirect_url) . '">log in</a> to view your account.</div>';
        }
        
        // Parse shortcode attributes
        $atts = \shortcode_atts(array(
            'page_title' => 'My Account',
        ), $atts, 'mmc_my_account');
        
        // Get current user
        $current_user = \wp_get_current_user();
        
        // Initialize Square Service to get membership info
        $square_service = new \MMCMembership\SquareService();
        
        // Get user's membership status
        $customer_id = \get_user_meta($current_user->ID, 'square_customer_id', true);
        $subscription_id = \get_user_meta($current_user->ID, 'square_subscription_id', true);
        
        $has_active_membership = false;
        $subscription_details = null;
        
        if ($subscription_id) {
            try {
                $subscription_details = $square_service->getSubscription($subscription_id);
                $has_active_membership = isset($subscription_details->status) && $subscription_details->status === 'ACTIVE';
            } catch (\Exception $e) {
                // Handle error gracefully
            }
        }
        
        // Build the account page with inline CSS
        $output = '<style>
            .mmc-account-container {
                max-width: 800px;
                margin: 0 auto;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            
            .mmc-account-section {
                margin-bottom: 40px;
                padding: 25px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
                transition: box-shadow 0.3s ease;
            }
            
            .mmc-account-section:hover {
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            }
            
            .mmc-account-section h3 {
                margin-top: 0;
                margin-bottom: 20px;
                font-size: 20px;
                color: #333;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            
            .mmc-notice {
                padding: 10px 15px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            
            .mmc-notice-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .mmc-notice-error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            .mmc-form-group {
                margin-bottom: 20px;
            }
            
            .mmc-form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #333;
            }
            
            .mmc-form-group input[type="text"],
            .mmc-form-group input[type="email"],
            .mmc-form-group input[type="password"] {
                width: 100%;
                padding: 12px 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
                transition: border-color 0.2s ease;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            
            .mmc-form-group input[type="text"]:focus,
            .mmc-form-group input[type="email"]:focus,
            .mmc-form-group input[type="password"]:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 1px 3px rgba(0, 115, 170, 0.2);
            }
            
            .mmc-button {
                display: inline-block;
                background-color: #0073aa;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
                font-weight: 500;
                text-decoration: none;
                transition: background-color 0.2s ease;
                margin-top: 15px;
            }
            
            .mmc-button:hover {
                background-color: #005177;
            }
            
            .mmc-button:focus {
                outline: none;
                box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.5);
            }
            
            .mmc-password-strength {
                margin-top: 8px;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 14px;
            }
            
            .mmc-password-strength.weak {
                background-color: #f8d7da;
                color: #721c24;
            }
            
            .mmc-password-strength.medium {
                background-color: #fff3cd;
                color: #856404;
            }
            
            .mmc-password-strength.strong {
                background-color: #d4edda;
                color: #155724;
            }
        </style>';
        $output .= '<div class="mmc-account-container">';
        $output .= '<h2>' . \esc_html($atts['page_title']) . '</h2>';
        
        // User info section
        $output .= '<div class="mmc-account-section mmc-account-user-info">';
        $output .= '<h3>Account Information</h3>';
        
        // Check for update messages
        if (isset($_GET['profile_updated']) && $_GET['profile_updated'] === 'success') {
            $output .= '<div class="mmc-notice mmc-notice-success">Your profile has been updated successfully.</div>';
        } elseif (isset($_GET['profile_updated']) && $_GET['profile_updated'] === 'error') {
            $output .= '<div class="mmc-notice mmc-notice-error">There was an error updating your profile. Please try again.</div>';
        } elseif (isset($_GET['password_updated']) && $_GET['password_updated'] === 'success') {
            $output .= '<div class="mmc-notice mmc-notice-success">Your password has been updated successfully.</div>';
        } elseif (isset($_GET['password_updated']) && $_GET['password_updated'] === 'error') {
            $output .= '<div class="mmc-notice mmc-notice-error">There was an error updating your password. Please try again.</div>';
        } elseif (isset($_GET['password_mismatch'])) {
            $output .= '<div class="mmc-notice mmc-notice-error">The passwords you entered do not match. Please try again.</div>';
        }
        
        // Profile edit form
        $output .= '<form method="post" class="mmc-profile-form">';
        $output .= '<div class="mmc-form-group">';
        $output .= '<label for="mmc_display_name">Name</label>';
        $output .= '<input type="text" id="mmc_display_name" name="mmc_display_name" value="' . \esc_attr($current_user->display_name) . '" required>';
        $output .= '</div>';
        
        $output .= '<div class="mmc-form-group">';
        $output .= '<label for="mmc_user_email">Email</label>';
        $output .= '<input type="email" id="mmc_user_email" name="mmc_user_email" value="' . \esc_attr($current_user->user_email) . '" required>';
        $output .= '</div>';
        
        $output .= '<div class="mmc-form-group">';
        $output .= '<input type="hidden" name="mmc_update_profile" value="1">';
        $output .= '<input type="hidden" name="mmc_profile_nonce" value="' . \wp_create_nonce('mmc-update-profile-nonce') . '">';
        $output .= '<button type="submit" class="mmc-button">Update Profile</button>';
        $output .= '</div>';
        $output .= '</form>';
        
        // Password change form
        $output .= '<h4>Change Password</h4>';
        $output .= '<form method="post" class="mmc-password-form">';
        $output .= '<div class="mmc-form-group">';
        $output .= '<label for="mmc_new_password">New Password</label>';
        $output .= '<input type="password" id="mmc_new_password" name="mmc_new_password" required>';
        $output .= '</div>';
        
        $output .= '<div class="mmc-form-group">';
        $output .= '<label for="mmc_confirm_password">Confirm New Password</label>';
        $output .= '<input type="password" id="mmc_confirm_password" name="mmc_confirm_password" required>';
        $output .= '</div>';
        
        $output .= '<div class="mmc-form-group">';
        $output .= '<input type="hidden" name="mmc_update_password" value="1">';
        $output .= '<input type="hidden" name="mmc_password_nonce" value="' . \wp_create_nonce('mmc-update-password-nonce') . '">';
        $output .= '<button type="submit" class="mmc-button">Update Password</button>';
        $output .= '</div>';
        $output .= '</form>';
        
        $output .= '</div>';
        
        // Log Out section
        $output .= '<div class="mmc-account-section mmc-account-logout">';
        $output .= '<h3>Log Out</h3>';
        $output .= '<p>Click the button below to log out of your account.</p>';
        $output .= '<a href="' . \wp_logout_url(\home_url()) . '" class="mmc-button mmc-button-logout">Log Out</a>';
        $output .= '</div>';
        
        $output .= '</div>'; // Close account container
        
        return $output;
    }
    
    /**
     * Process account actions
     * This would be hooked to init or a similar action
     */
    public function process_account_actions() {
        if (!\is_user_logged_in()) {
            return;
        }
        
        // Process profile update
        if (isset($_POST['mmc_update_profile']) && $_POST['mmc_update_profile'] == 1) {
            $this->process_update_profile();
        }
        
        // Process password update
        if (isset($_POST['mmc_update_password']) && $_POST['mmc_update_password'] == 1) {
            $this->process_update_password();
        }
    }
    
    // Membership cancellation and card deletion methods have been removed as they're no longer needed
    
    /**
     * Process profile update
     */
    private function process_update_profile() {
        // Verify nonce
        if (!isset($_POST['mmc_profile_nonce']) || !\wp_verify_nonce($_POST['mmc_profile_nonce'], 'mmc-update-profile-nonce')) {
            return;
        }
        
        $user_id = \get_current_user_id();
        $display_name = isset($_POST['mmc_display_name']) ? \sanitize_text_field($_POST['mmc_display_name']) : '';
        $user_email = isset($_POST['mmc_user_email']) ? \sanitize_email($_POST['mmc_user_email']) : '';
        
        // Validate inputs
        if (empty($display_name) || empty($user_email)) {
            $account_page_id = \get_option('mmc_membership_account_page_id');
            $redirect_url = $account_page_id ? \get_permalink($account_page_id) : \home_url();
            \wp_redirect(\add_query_arg('profile_updated', 'error', $redirect_url));
            exit;
        }
        
        // Check if email is already in use by another user
        if (\email_exists($user_email) && \email_exists($user_email) != $user_id) {
            $account_page_id = \get_option('mmc_membership_account_page_id');
            $redirect_url = $account_page_id ? \get_permalink($account_page_id) : \home_url();
            \wp_redirect(\add_query_arg('profile_updated', 'error', $redirect_url));
            exit;
        }
        
        // Update user data
        $user_data = array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $user_email
        );
        
        $result = \wp_update_user($user_data);
        
        // Redirect back to account page with success/error message
        $account_page_id = \get_option('mmc_membership_account_page_id');
        $redirect_url = $account_page_id ? \get_permalink($account_page_id) : \home_url();
        
        if (!\is_wp_error($result)) {
            \wp_redirect(\add_query_arg('profile_updated', 'success', $redirect_url));
        } else {
            \wp_redirect(\add_query_arg('profile_updated', 'error', $redirect_url));
        }
        exit;
    }
    
    /**
     * Process password update
     */
    private function process_update_password() {
        // Verify nonce
        if (!isset($_POST['mmc_password_nonce']) || !\wp_verify_nonce($_POST['mmc_password_nonce'], 'mmc-update-password-nonce')) {
            return;
        }
        
        $user_id = \get_current_user_id();
        $new_password = isset($_POST['mmc_new_password']) ? $_POST['mmc_new_password'] : '';
        $confirm_password = isset($_POST['mmc_confirm_password']) ? $_POST['mmc_confirm_password'] : '';
        
        // Get the account page URL for redirects
        $account_page_id = \get_option('mmc_membership_account_page_id');
        $redirect_url = $account_page_id ? \get_permalink($account_page_id) : \home_url();
        
        // Validate inputs
        if (empty($new_password) || empty($confirm_password)) {
            \wp_redirect(\add_query_arg('password_updated', 'error', $redirect_url));
            exit;
        }
        
        // Check if passwords match
        if ($new_password !== $confirm_password) {
            \wp_redirect(\add_query_arg('password_mismatch', '1', $redirect_url));
            exit;
        }
        
        // Update user password
        \wp_set_password($new_password, $user_id);
        
        // Log the user back in
        $user = \get_user_by('id', $user_id);
        \wp_set_current_user($user_id, $user->user_login);
        \wp_set_auth_cookie($user_id);
        
        // Redirect back to account page with success message
        \wp_redirect(\add_query_arg('password_updated', 'success', $redirect_url));
        exit;
    }
}
