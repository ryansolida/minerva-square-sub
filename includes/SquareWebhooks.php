<?php
namespace MMCMembership;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles webhook events from Square
 */
class SquareWebhooks {
    
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
        // Register webhook endpoint
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Register webhook endpoint with WordPress REST API
     */
    public function register_webhook_endpoint() {
        register_rest_route('mmc-membership/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_webhook'),
            'permission_callback' => '__return_true' // Will validate the request inside the callback
        ));
    }
    
    /**
     * Process incoming webhook from Square
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function process_webhook($request) {
        // Get request body
        $body = $request->get_body();
        $data = json_decode($body);
        
        if (empty($data)) {
            error_log('Square Webhook: Empty or invalid JSON payload received');
            return rest_ensure_response(array('status' => 'error', 'message' => 'Invalid payload'));
        }
        
        // Log webhook event for debugging
        error_log('Square Webhook Received: ' . $body);
        
        // Process different event types
        $event_type = $data->type ?? '';
        
        switch ($event_type) {
            case 'subscription.updated':
                $this->handle_subscription_updated($data);
                break;
                
            case 'subscription.created':
                $this->handle_subscription_created($data);
                break;
                
            case 'subscription.canceled':
                $this->handle_subscription_canceled($data);
                break;
                
            default:
                error_log('Square Webhook: Unhandled event type: ' . $event_type);
                break;
        }
        
        // Always return success to Square
        return rest_ensure_response(array('status' => 'success'));
    }
    
    /**
     * Handle subscription updated event
     * 
     * @param object $data Webhook event data
     */
    private function handle_subscription_updated($data) {
        $subscription_id = $data->data->object->subscription->id ?? '';
        $status = $data->data->object->subscription->status ?? '';
        
        if (empty($subscription_id)) {
            error_log('Square Webhook: Missing subscription ID in subscription.updated event');
            return;
        }
        
        // Find the user with this subscription ID
        $user_id = $this->get_user_by_subscription_id($subscription_id);
        if (!$user_id) {
            error_log("Square Webhook: Could not find user with subscription ID: $subscription_id");
            return;
        }
        
        // Update user membership status based on subscription status
        if ($status === 'ACTIVE') {
            UserFunctions::set_active_membership($user_id, $data->data->object->subscription);
            error_log("Square Webhook: Updated user #$user_id to active membership status");
        } else {
            UserFunctions::set_inactive_membership($user_id);
            error_log("Square Webhook: Updated user #$user_id to inactive membership status");
        }
    }
    
    /**
     * Handle subscription created event
     * 
     * @param object $data Webhook event data
     */
    private function handle_subscription_created($data) {
        $subscription_id = $data->data->object->subscription->id ?? '';
        
        if (empty($subscription_id)) {
            error_log('Square Webhook: Missing subscription ID in subscription.created event');
            return;
        }
        
        // For newly created subscriptions, the user_id should already be stored
        // during the signup process, so we don't need to process this event separately.
        // This is handled in MembershipSignup::handle_subscription_ajax()
        error_log("Square Webhook: Received subscription.created for subscription ID: $subscription_id");
    }
    
    /**
     * Handle subscription canceled event
     * 
     * @param object $data Webhook event data
     */
    private function handle_subscription_canceled($data) {
        $subscription_id = $data->data->object->subscription->id ?? '';
        
        if (empty($subscription_id)) {
            error_log('Square Webhook: Missing subscription ID in subscription.canceled event');
            return;
        }
        
        // Find the user with this subscription ID
        $user_id = $this->get_user_by_subscription_id($subscription_id);
        if (!$user_id) {
            error_log("Square Webhook: Could not find user with subscription ID: $subscription_id");
            return;
        }
        
        // Update user membership status to inactive
        UserFunctions::set_inactive_membership($user_id);
        error_log("Square Webhook: Set inactive membership for user #$user_id due to canceled subscription");
    }
    
    /**
     * Find a user by their subscription ID
     * 
     * @param string $subscription_id
     * @return int|false User ID or false if not found
     */
    private function get_user_by_subscription_id($subscription_id) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'square_subscription_id' AND meta_value = %s LIMIT 1",
            $subscription_id
        ));
        
        return $user_id ? intval($user_id) : false;
    }
}

// Initialize the webhook handler
SquareWebhooks::get_instance();
