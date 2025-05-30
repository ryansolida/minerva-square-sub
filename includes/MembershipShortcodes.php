<?php
namespace MMCMembership;

/**
 * Membership Shortcodes
 * 
 * Provides shortcodes for displaying membership information
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MembershipShortcodes {
    private static $instance = null;
    
    /**
     * Get the singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register shortcodes
        \add_shortcode('mmc_has_active_membership', array($this, 'has_active_membership_shortcode'));
        \add_shortcode('mmc_membership_expiration_date', array($this, 'membership_expiration_date_shortcode'));
        \add_shortcode('mmc_membership_activation_date', array($this, 'membership_activation_date_shortcode'));
        \add_shortcode('mmc_membership_next_billing_date', array($this, 'membership_next_billing_date_shortcode'));
        \add_shortcode('mmc_membership_next_billing_price', array($this, 'membership_next_billing_price_shortcode'));
    }
    
    /**
     * Has Active Membership shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function has_active_membership_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'yes_text' => 'Yes',
            'no_text' => 'No',
        ), $atts, 'mmc_has_active_membership');
        
        // Check if user is logged in
        if (!\is_user_logged_in()) {
            return $atts['no_text'];
        }
        
        // Get user ID
        $user_id = \get_current_user_id();
        
        // Check if user has active membership
        $has_active_membership = \get_user_meta($user_id, 'square_active_membership', true) === 'yes';
        
        // Return appropriate text
        return $has_active_membership ? $atts['yes_text'] : $atts['no_text'];
    }
    
    /**
     * Membership Expiration Date shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function membership_expiration_date_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'date_format' => 'F j, Y',
            'no_date_text' => 'No active membership',
        ), $atts, 'mmc_membership_expiration_date');
        
        // Check if user is logged in
        if (!\is_user_logged_in()) {
            return $atts['no_date_text'];
        }
        
        // Get user ID
        $user_id = \get_current_user_id();
        
        // Check if user has active membership
        $has_active_membership = \get_user_meta($user_id, 'square_active_membership', true) === 'yes';
        
        if (!$has_active_membership) {
            return $atts['no_date_text'];
        }
        
        // Get subscription data
        $subscription_id = \get_user_meta($user_id, 'square_subscription_id', true);
        
        if (empty($subscription_id)) {
            return $atts['no_date_text'];
        }
        
        try {
            // Get Square service
            $square_service = \get_mmc_membership();
            
            // Get subscription details
            $subscription = $square_service->getSubscription($subscription_id);
            
            // Check if subscription has end date
            if (!empty($subscription->end_date)) {
                $end_date = strtotime($subscription->end_date);
                return date($atts['date_format'], $end_date);
            } else {
                return $atts['no_date_text'];
            }
        } catch (\Exception $e) {
            return $atts['no_date_text'];
        }
    }
    
    /**
     * Membership Activation Date shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function membership_activation_date_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'date_format' => 'F j, Y',
            'no_date_text' => 'No membership found',
        ), $atts, 'mmc_membership_activation_date');
        
        // Check if user is logged in
        if (!\is_user_logged_in()) {
            return $atts['no_date_text'];
        }
        
        // Get user ID
        $user_id = \get_current_user_id();
        
        // Get subscription data
        $subscription_id = \get_user_meta($user_id, 'square_subscription_id', true);
        
        if (empty($subscription_id)) {
            return $atts['no_date_text'];
        }
        
        try {
            // Get Square service
            $square_service = \get_mmc_membership();
            
            // Get subscription details
            $subscription = $square_service->getSubscription($subscription_id);
            
            // Check if subscription has start date
            if (!empty($subscription->start_date)) {
                $start_date = strtotime($subscription->start_date);
                return date($atts['date_format'], $start_date);
            } else {
                return $atts['no_date_text'];
            }
        } catch (\Exception $e) {
            return $atts['no_date_text'];
        }
    }
    
    /**
     * Next Billing Date shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function membership_next_billing_date_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'date_format' => 'F j, Y',
            'prefix' => '',
            'no_date_text' => 'No billing date available',
        ), $atts, 'mmc_membership_next_billing_date');
        
        // Check if user is logged in
        if (!\is_user_logged_in()) {
            return $atts['no_date_text'];
        }
        
        // Get user ID
        $user_id = \get_current_user_id();
        
        // Get subscription data
        $subscription_id = \get_user_meta($user_id, 'square_subscription_id', true);
        
        if (empty($subscription_id)) {
            return $atts['no_date_text'];
        }
        
        try {
            // Get Square service
            $square_service = \get_mmc_membership();
            
            // Get subscription details
            $subscription = $square_service->getSubscription($subscription_id);
            
            // Check if subscription has charged_through_date (which indicates when the next payment is due)
            if (!empty($subscription->charged_through_date)) {
                $next_billing_date = strtotime($subscription->charged_through_date);
                $formatted_date = date($atts['date_format'], $next_billing_date);
                
                // Add prefix if provided
                if (!empty($atts['prefix'])) {
                    return $atts['prefix'] . ' ' . $formatted_date;
                }
                
                return $formatted_date;
            } else {
                return $atts['no_date_text'];
            }
        } catch (\Exception $e) {
            return $atts['no_date_text'];
        }
    }
    
    /**
     * Next Billing Price shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function membership_next_billing_price_shortcode($atts) {
        $atts = \shortcode_atts(array(
            'currency_symbol' => '$',
            'prefix' => '',
            'suffix' => '',
            'no_price_text' => 'No price available',
        ), $atts, 'mmc_membership_next_billing_price');
        
        // Check if user is logged in
        if (!\is_user_logged_in()) {
            return $atts['no_price_text'];
        }
        
        // Get user ID
        $user_id = \get_current_user_id();
        
        // Get subscription data
        $subscription_id = \get_user_meta($user_id, 'square_subscription_id', true);
        
        if (empty($subscription_id)) {
            return $atts['no_price_text'];
        }
        
        try {
            // Get Square service
            $square_service = \get_mmc_membership();
            
            // Get subscription details
            $subscription = $square_service->getSubscription($subscription_id);
            
            // Try to get the price from the subscription
            $price = null;
            
            // Check if price_money is available in the subscription object
            if (!empty($subscription->price_money) && !empty($subscription->price_money->amount)) {
                $price = $subscription->price_money->amount / 100; // Convert cents to dollars
            }
            
            // If we still don't have a price, use the default price
            if (is_null($price)) {
                $price = defined('MMC_MEMBERSHIP_PRICE') ? MMC_MEMBERSHIP_PRICE : 8.99;
            }
            
            // Format the price
            $formatted_price = $atts['currency_symbol'] . number_format($price, 2);
            
            // Add prefix/suffix if provided
            $output = '';
            if (!empty($atts['prefix'])) {
                $output .= $atts['prefix'] . ' ';
            }
            
            $output .= $formatted_price;
            
            if (!empty($atts['suffix'])) {
                $output .= ' ' . $atts['suffix'];
            }
            
            return $output;
        } catch (\Exception $e) {
            return $atts['no_price_text'];
        }
    }
}

// Initialize shortcodes
MembershipShortcodes::get_instance();
