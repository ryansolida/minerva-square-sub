<?php
namespace MMCMembership;
/**
 * User-related functions for Square Service
 * 
 * Handles user meta for tracking subscription status
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Square Service User Functions
 */
class UserFunctions {
    
    /**
     * Initialize user-related hooks
     */
    public static function init() {
        // Update subscription status meta on login
        add_action('wp_login', array(__CLASS__, 'update_membership_status_on_login'), 10, 2);
        
        // Schedule a daily check for all users with subscriptions
        add_action('init', array(__CLASS__, 'schedule_membership_status_check'));
        add_action('square_service_daily_membership_check', array(__CLASS__, 'check_all_memberships'));
    }
    
    /**
     * Schedule a daily check for all membership statuses
     */
    public static function schedule_membership_status_check() {
        if (!wp_next_scheduled('square_service_daily_membership_check')) {
            wp_schedule_event(time(), 'daily', 'square_service_daily_membership_check');
        }
    }
    
    /**
     * Update a user's membership status meta when they log in
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public static function update_membership_status_on_login($user_login, $user) {
        self::update_user_membership_status($user->ID);
    }
    
    /**
     * Check and update the membership status for a specific user
     * 
     * @param int $user_id User ID
     * @return bool True if user has active membership, false otherwise
     */
    public static function update_user_membership_status($user_id) {
        $subscription_id = get_user_meta($user_id, 'square_subscription_id', true);
        
        // If no subscription ID exists, ensure the status is false
        if (empty($subscription_id)) {
            update_user_meta($user_id, 'has_active_membership', false);
            return false;
        }
        
        // Get subscription data from user meta (most recent stored data)
        $subscription_data = get_user_meta($user_id, 'square_subscription_data', true);
        
        // Check if we need to refresh the subscription data from Square
        $refresh_data = false;
        if (empty($subscription_data)) {
            $refresh_data = true;
        } else {
            // If data is older than 24 hours, refresh it
            $last_updated = get_user_meta($user_id, 'square_subscription_data_updated', true);
            if (empty($last_updated) || (time() - $last_updated) > 86400) {
                $refresh_data = true;
            }
        }
        
        // Refresh subscription data if needed
        if ($refresh_data) {
            try {
                // Get subscription data from Square API
                $square_service = get_square_service();
                // This API call is not implemented in the current SquareService class
                // It would need to be added to fetch subscription status
                // For now, we'll just rely on the stored data
                
                // Simulating a future API call: 
                // $subscription_data = $square_service->getSubscription($subscription_id);
                // update_user_meta($user_id, 'square_subscription_data', $subscription_data);
                // update_user_meta($user_id, 'square_subscription_data_updated', time());
            } catch (Exception $e) {
                // Log error but continue with existing data
                error_log('Failed to refresh subscription data: ' . $e->getMessage());
            }
        }
        
        // Determine if subscription is active
        $is_active = false;
        if (!empty($subscription_data) && isset($subscription_data['status'])) {
            $is_active = ($subscription_data['status'] === 'ACTIVE');
        }
        
        // Update the user meta
        update_user_meta($user_id, 'has_active_membership', $is_active);
        
        return $is_active;
    }
    
    /**
     * Check all users with subscriptions and update their status
     */
    public static function check_all_memberships() {
        // Find all users with subscription IDs
        $users = get_users(array(
            'meta_key' => 'square_subscription_id',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            self::update_user_membership_status($user->ID);
        }
    }
    
    /**
     * Set a user's membership as active
     * 
     * @param int $user_id User ID
     * @param array $subscription_data Subscription data
     */
    public static function set_active_membership($user_id, $subscription_data) {
        update_user_meta($user_id, 'has_active_membership', true);
        update_user_meta($user_id, 'square_subscription_data', $subscription_data);
        update_user_meta($user_id, 'square_subscription_data_updated', time());
    }
    
    /**
     * Set a user's membership as inactive
     * 
     * @param int $user_id User ID
     */
    public static function set_inactive_membership($user_id) {
        update_user_meta($user_id, 'has_active_membership', false);
        delete_user_meta($user_id, 'square_subscription_id');
        delete_user_meta($user_id, 'square_subscription_data');
        delete_user_meta($user_id, 'square_subscription_data_updated');
    }
    
    /**
     * Check if a user has an active membership
     * 
     * @param int $user_id User ID (defaults to current user)
     * @return bool True if user has active membership, false otherwise
     */
    public static function has_active_membership($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        return (bool) get_user_meta($user_id, 'has_active_membership', true);
    }
}

// Initialize user functions
UserFunctions::init();
