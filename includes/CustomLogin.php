<?php

namespace MMCMembership;

/**
 * Custom Login functionality for MMC Membership
 */
class CustomLogin {
    
    /**
     * Initialize the custom login functionality
     */
    public function __construct() {
        // Register shortcode for login form
        \add_shortcode('mmc_login_form', array($this, 'render_login_form'));
        
        // Handle login form submission
        \add_action('init', array($this, 'process_login'));
        
        // Redirect non-admin users away from wp-admin
        \add_action('admin_init', array($this, 'redirect_non_admins'));
        
        // Remove admin bar for non-admin users
        \add_action('after_setup_theme', array($this, 'remove_admin_bar'));
        
        // Filter login URL to use our custom page
        \add_filter('login_url', array($this, 'custom_login_url'), 10, 3);
        
        // Redirect wp-login.php to our custom login page
        \add_action('init', array($this, 'redirect_wp_login'));
    }
    
    /**
     * Render the login form
     */
    public function render_login_form($atts) {
        // If user is already logged in, show a message or redirect
        if (\is_user_logged_in()) {
            $account_page_id = \get_option('mmc_membership_account_page_id');
            $account_url = $account_page_id ? \get_permalink($account_page_id) : \home_url();
            
            return '<div class="mmc-login-message">You are already logged in. <a href="' . \esc_url($account_url) . '">Go to your account</a> or <a href="' . \wp_logout_url(\home_url()) . '">log out</a>.</div>';
        }
        
        // Parse shortcode attributes
        $atts = \shortcode_atts(array(
            'redirect' => '',
            'form_title' => 'Log In to ' . \mmc_membership_get_club_name(),
        ), $atts, 'mmc_login_form');
        
        // Get any error messages
        $error = isset($_GET['login']) ? $_GET['login'] : '';
        $error_message = '';
        
        if ($error === 'failed') {
            $error_message = '<div class="mmc-login-error">Invalid username or password. Please try again.</div>';
        } elseif ($error === 'empty') {
            $error_message = '<div class="mmc-login-error">Please enter both username and password.</div>';
        }
        
        // Get the redirect URL
        $redirect = !empty($atts['redirect']) ? $atts['redirect'] : '';
        if (empty($redirect)) {
            $account_page_id = \get_option('mmc_membership_account_page_id');
            $redirect = $account_page_id ? \get_permalink($account_page_id) : \home_url();
        }
        
        // Build the login form
        $output = '<div class="mmc-login-form-container">';
        $output .= '<h2>' . \esc_html($atts['form_title']) . '</h2>';
        $output .= $error_message;
        
        $output .= '<form class="mmc-login-form" action="" method="post">';
        $output .= '<div class="form-group">';
        $output .= '<label for="mmc_user_login">Username or Email</label>';
        $output .= '<input type="text" name="mmc_user_login" id="mmc_user_login" class="form-control" required>';
        $output .= '</div>';
        
        $output .= '<div class="form-group">';
        $output .= '<label for="mmc_user_pass">Password</label>';
        $output .= '<input type="password" name="mmc_user_pass" id="mmc_user_pass" class="form-control" required>';
        $output .= '</div>';
        
        $output .= '<div class="form-group">';
        $output .= '<label><input type="checkbox" name="mmc_rememberme" value="forever"> Remember Me</label>';
        $output .= '</div>';
        
        $output .= '<input type="hidden" name="mmc_login_nonce" value="' . \wp_create_nonce('mmc-login-nonce') . '">';
        $output .= '<input type="hidden" name="mmc_login_redirect" value="' . \esc_url($redirect) . '">';
        
        $output .= '<div class="form-group">';
        $output .= '<button type="submit" name="mmc_login_submit" class="mmc-login-button">Log In</button>';
        $output .= '</div>';
        
        // Add lost password link
        $output .= '<div class="mmc-login-links">';
        $output .= '<a href="' . \wp_lostpassword_url() . '">Forgot your password?</a>';
        $output .= '</div>';
        
        $output .= '</form>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Process login form submission
     */
    public function process_login() {
        // Check if the form was submitted
        if (!isset($_POST['mmc_login_submit'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['mmc_login_nonce']) || !\wp_verify_nonce($_POST['mmc_login_nonce'], 'mmc-login-nonce')) {
            return;
        }
        
        // Get form data
        $user_login = isset($_POST['mmc_user_login']) ? \sanitize_text_field($_POST['mmc_user_login']) : '';
        $user_pass = isset($_POST['mmc_user_pass']) ? $_POST['mmc_user_pass'] : '';
        $remember = isset($_POST['mmc_rememberme']) ? true : false;
        $redirect_to = isset($_POST['mmc_login_redirect']) ? \esc_url_raw($_POST['mmc_login_redirect']) : \home_url();
        
        // Validate inputs
        if (empty($user_login) || empty($user_pass)) {
            $login_page_id = \get_option('mmc_membership_login_page_id');
            $login_url = $login_page_id ? \get_permalink($login_page_id) : \home_url();
            \wp_redirect(\add_query_arg('login', 'empty', $login_url));
            exit;
        }
        
        // Attempt to log the user in
        $credentials = array(
            'user_login' => $user_login,
            'user_password' => $user_pass,
            'remember' => $remember
        );
        
        $user = \wp_signon($credentials, false);
        
        if (\is_wp_error($user)) {
            $login_page_id = \get_option('mmc_membership_login_page_id');
            $login_url = $login_page_id ? \get_permalink($login_page_id) : \home_url();
            \wp_redirect(\add_query_arg('login', 'failed', $login_url));
            exit;
        }
        
        // Successful login, redirect to the specified URL
        \wp_redirect($redirect_to);
        exit;
    }
    
    /**
     * Redirect non-admin users away from wp-admin
     */
    public function redirect_non_admins() {
        if (!\is_user_logged_in()) {
            return;
        }
        
        if (\current_user_can('manage_options')) {
            return;
        }
        
        // If this is an AJAX request, don't redirect
        if (\wp_doing_ajax()) {
            return;
        }
        
        // Get the account page URL
        $account_page_id = \get_option('mmc_membership_account_page_id');
        $redirect_url = $account_page_id ? \get_permalink($account_page_id) : \home_url();
        
        \wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Remove admin bar for non-admin users
     */
    public function remove_admin_bar() {
        if (!\is_user_logged_in()) {
            return;
        }
        
        if (!\current_user_can('manage_options')) {
            \show_admin_bar(false);
        }
    }
    
    /**
     * Filter the login URL to use our custom page
     */
    public function custom_login_url($login_url, $redirect, $force_reauth) {
        $login_page_id = \get_option('mmc_membership_login_page_id');
        
        if (!$login_page_id) {
            return $login_url;
        }
        
        $login_url = \get_permalink($login_page_id);
        
        if (!empty($redirect)) {
            $login_url = \add_query_arg('redirect_to', \urlencode($redirect), $login_url);
        }
        
        if ($force_reauth) {
            $login_url = \add_query_arg('reauth', '1', $login_url);
        }
        
        return $login_url;
    }
    
    /**
     * Redirect wp-login.php to our custom login page
     */
    public function redirect_wp_login() {
        $login_page_id = \get_option('mmc_membership_login_page_id');
        
        if (!$login_page_id) {
            return;
        }
        
        $page_viewed = basename($_SERVER['REQUEST_URI']);
        
        if ($page_viewed == 'wp-login.php' && !isset($_GET['action']) && !isset($_POST['log'])) {
            $login_url = \get_permalink($login_page_id);
            \wp_redirect($login_url);
            exit;
        }
    }
}
