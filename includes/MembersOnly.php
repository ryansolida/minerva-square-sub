<?php
namespace MMCMembership;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MembersOnly
 * Handles the functionality for restricting content to members only
 */
class MembersOnly {
    
    /**
     * Initialize the members only functionality
     */
    public static function init() {
        // Register the shortcode
        add_shortcode('members_only', array(__CLASS__, 'members_only_shortcode'));
        
        // Add meta boxes to posts and pages
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta_box_data'));
        
        // Filter content
        add_filter('the_content', array(__CLASS__, 'check_content'));
    }
    
    /**
     * Members only shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     */
    public static function members_only_shortcode($atts, $content = null) {
        // Check if user has active membership
        if (UserFunctions::has_active_membership()) {
            // If yes, show the content
            return do_shortcode($content);
        } else {
            // If no, show sign up message
            return self::get_restricted_content_message();
        }
    }
    
    /**
     * Add meta boxes to posts and pages
     */
    public static function add_meta_boxes() {
        $post_types = array('post', 'page'); // Add any custom post types here
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'mmc_membership_meta_box',
                'Membership Options',
                array(__CLASS__, 'meta_box_callback'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Meta box callback
     * 
     * @param WP_Post $post Post object
     */
    public static function meta_box_callback($post) {
        // Add nonce for security
        wp_nonce_field('mmc_membership_meta_box', 'mmc_membership_meta_box_nonce');
        
        // Get current value
        $members_only = get_post_meta($post->ID, '_mmc_members_only', true);
        
        // Output checkbox
        ?>
        <p>
            <input type="checkbox" id="mmc_members_only" name="mmc_members_only" <?php checked($members_only, 'yes'); ?> />
            <label for="mmc_members_only">Only visible to members</label>
        </p>
        <?php
    }
    
    /**
     * Save meta box data
     * 
     * @param int $post_id Post ID
     */
    public static function save_meta_box_data($post_id) {
        // Check if nonce is set
        if (!isset($_POST['mmc_membership_meta_box_nonce'])) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['mmc_membership_meta_box_nonce'], 'mmc_membership_meta_box')) {
            return;
        }
        
        // If this is an autosave, don't do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (isset($_POST['post_type']) && 'page' === $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }
        
        // Save data
        if (isset($_POST['mmc_members_only'])) {
            update_post_meta($post_id, '_mmc_members_only', 'yes');
        } else {
            delete_post_meta($post_id, '_mmc_members_only');
        }
    }
    
    /**
     * Check content before it's displayed
     * 
     * @param string $content Post content
     * @return string
     */
    public static function check_content($content) {
        global $post;
        
        if (!is_singular() || !isset($post->ID)) {
            return $content;
        }
        
        // Check if this post/page is members only
        $members_only = get_post_meta($post->ID, '_mmc_members_only', true);
        
        if ($members_only === 'yes') {
            // Check if user has active membership
            if (UserFunctions::has_active_membership()) {
                return $content;
            } else {
                // Generate membership required message
                return self::get_restricted_content_message();
            }
        }
        
        return $content;
    }
    
    /**
     * Get the message to display for restricted content
     * 
     * @return string
     */
    public static function get_restricted_content_message() {
        // Use the configured signup page URL or fallback to a default
        $signup_url = get_membership_signup_url();
        if (empty($signup_url)) {
            // Get the membership page ID from settings
            $membership_page_id = \get_option('mmc_membership_page_id');
            if ($membership_page_id) {
                $signup_url = \get_permalink($membership_page_id);
            } else {
                // Final fallback if no settings are configured
                $signup_url = \site_url('/membership-signup/');
            }
        }
        
        $login_url = wp_login_url(site_url($_SERVER['REQUEST_URI'])); // Current page as redirect
        $is_logged_in = is_user_logged_in();
        
        $message = '<div class="bg-gray-100 border border-gray-300 rounded-md p-6 my-5 text-center">';
        $message .= '<h3 class="text-xl font-bold mb-3">Members Only Content</h3>';
        $message .= '<p class="mb-4">This content is only available to members with an active subscription.</p>';
        
        if ($is_logged_in) {
            // User is logged in but doesn't have an active membership
            $message .= '<p><a href="' . esc_url($signup_url) . '" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded no-underline transition duration-300">Sign up Here</a></p>';
        } else {
            // User is not logged in
            $message .= '<div class="flex flex-col sm:flex-row justify-center space-y-2 sm:space-y-0 sm:space-x-4">';
            $message .= '<a href="' . esc_url($login_url) . '" class="inline-block bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded no-underline transition duration-300">Log In</a>';
            $message .= '<a href="' . esc_url($signup_url) . '" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded no-underline transition duration-300">Sign up Here</a>';
            $message .= '</div>';
        }
        
        $message .= '</div>';
        
        return $message;
    }
}

// Initialize the members only functionality
MembersOnly::init();
