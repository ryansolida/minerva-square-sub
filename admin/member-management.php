<?php
/**
 * MMC Membership Management Admin Page
 * 
 * This page allows admins to view and manage memberships.
 */

namespace MMCMembership;

use Exception;
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MemberManagement {
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
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle AJAX actions
        add_action('wp_ajax_mmc_cancel_membership', array($this, 'handle_cancel_membership'));
        add_action('wp_ajax_mmc_refresh_memberships', array($this, 'handle_refresh_memberships'));
    }
    
    /**
     * Add the membership management page to the admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'mmc-memberships', // Parent slug (MMC Memberships main menu)
            'Manage Memberships', // Page title
            'Manage Memberships', // Menu title
            'manage_options', // Capability required
            'mmc-membership-management', // Menu slug
            array($this, 'render_admin_page') // Callback function
        );
    }
    
    /**
     * Get all users with membership data
     * 
     * @return array Array of users with membership data
     */
    private function get_membership_users() {
        $users = get_users(array(
            'meta_key' => 'square_subscription_id',
            'meta_compare' => 'EXISTS'
        ));
        
        $membership_users = array();
        $square_service = get_mmc_membership();
        
        foreach ($users as $user) {
            $subscription_id = get_user_meta($user->ID, 'square_subscription_id', true);
            $status = get_user_meta($user->ID, 'has_active_membership', true) ? 'active' : 'inactive';
            $start_date = get_user_meta($user->ID, 'square_subscription_start_date', true);
            $end_date = get_user_meta($user->ID, 'square_subscription_end_date', true);
            $customer_id = get_user_meta($user->ID, 'square_customer_id', true);
            
            // Get auto-renewal status from Square API if subscription is active
            $auto_renewal = 'No';
            if ($status === 'active' && !empty($subscription_id)) {
                try {
                    $subscription_data = $square_service->getSubscription($subscription_id);
                    // In Square, a subscription with no cancel_date and status ACTIVE is set to auto-renew
                    if (empty($subscription_data->canceled_date) && $subscription_data->status === 'ACTIVE') {
                        $auto_renewal = 'Yes';
                    }
                } catch (Exception $e) {
                    // If we can't get subscription data, assume no auto-renewal
                    error_log('Error getting subscription data for auto-renewal check: ' . $e->getMessage());
                }
            }
            
            $membership_users[] = array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'subscription_id' => $subscription_id,
                'customer_id' => $customer_id,
                'status' => $status,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'auto_renewal' => $auto_renewal
            );
        }
        
        return $membership_users;
    }
    
    /**
     * Format date for display
     */
    private function format_date($date) {
        if (empty($date)) {
            return 'N/A';
        }
        
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return $date;
        }
        
        return date('F j, Y', $timestamp);
    }
    
    /**
     * Refresh all membership data
     * This function updates the membership status for all users with a Square subscription
     */
    private function refresh_all_memberships() {
        // Get all users with a Square subscription
        $users = get_users(array(
            'meta_key' => 'square_subscription_id',
            'meta_compare' => 'EXISTS'
        ));
        
        // Loop through each user and update their membership status
        foreach ($users as $user) {
            // Force refresh the user's membership status
            UserFunctions::update_user_membership_status($user->ID, true);
        }
        
        return true;
    }
    
    /**
     * Render the admin page
     */
    public function render_admin_page() {
        // Generate nonce for AJAX actions
        $nonce = wp_create_nonce('mmc_admin_action_nonce');
        
        // Get all users with membership data
        $members = $this->get_membership_users();
        
        // Generate nonce for AJAX actions
        $nonce = wp_create_nonce('mmc_admin_action_nonce');
        
        ?>
        <div class="wrap">
            <h1>MMC Membership Management</h1>
            
            <?php
            // Show success/error messages if any
            if (isset($_GET['message']) && $_GET['message'] === 'canceled') {
                echo '<div class="notice notice-success is-dismissible"><p>Membership canceled successfully.</p></div>';
            } elseif (isset($_GET['message']) && $_GET['message'] === 'error') {
                echo '<div class="notice notice-error is-dismissible"><p>Error processing your request. Please try again.</p></div>';
            }
            
            // Add a div for AJAX messages
            echo '<div id="refresh-message" class="notice notice-success is-dismissible" style="display:none;"></div>';
            ?>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button id="refresh-memberships" class="button" data-nonce="<?php echo $nonce; ?>">Refresh All Membership Data</button>
                </div>
                <br class="clear">
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>Active Until</th>
                        <th>Auto-Renewal</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="7">No memberships found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td>
                                    <strong><a href="<?php echo admin_url('user-edit.php?user_id=' . $member['user_id']); ?>"><?php echo esc_html($member['display_name']); ?></a></strong>
                                    <div class="row-actions">
                                        <span class="id">User ID: <?php echo esc_html($member['user_id']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html($member['email']); ?></td>
                                <td>
                                    <?php if ($member['status'] === 'active'): ?>
                                        <span class="mmc-status-active">Active</span>
                                    <?php else: ?>
                                        <span class="mmc-status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($this->format_date($member['start_date'])); ?></td>
                                <td><?php echo esc_html($this->format_date($member['end_date'])); ?></td>
                                <td>
                                    <?php if ($member['status'] === 'active'): ?>
                                        <?php if ($member['auto_renewal'] === 'Yes'): ?>
                                            <span class="mmc-renewal-active">Yes</span>
                                        <?php else: ?>
                                            <span class="mmc-renewal-inactive">No</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="mmc-renewal-inactive">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($member['status'] === 'active'): ?>
                                        <button 
                                            class="button button-secondary cancel-membership"
                                            data-user-id="<?php echo esc_attr($member['user_id']); ?>"
                                            data-subscription-id="<?php echo esc_attr($member['subscription_id']); ?>"
                                            data-nonce="<?php echo esc_attr($nonce); ?>"
                                        >
                                            Cancel Membership
                                        </button>
                                    <?php else: ?>
                                        <span class="description">No actions available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div id="cancel-membership-modal" class="mmc-modal" style="display: none;">
            <div class="mmc-modal-content">
                <span class="mmc-modal-close">&times;</span>
                <h3>Confirm Membership Cancellation</h3>
                <p>Are you sure you want to cancel this membership? This action cannot be undone.</p>
                <p>User: <span id="modal-user-name"></span></p>
                <div class="mmc-modal-actions">
                    <button id="confirm-cancel" class="button button-primary">Yes, Cancel Membership</button>
                    <button id="cancel-modal" class="button button-secondary">No, Keep Membership</button>
                </div>
                <div id="modal-response" style="display: none;"></div>
            </div>
        </div>
        
        <style>
            .mmc-status-active {
                color: #46b450;
                font-weight: bold;
            }
            .mmc-status-inactive {
                color: #dc3232;
                font-weight: bold;
            }
            .mmc-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
            }
            .mmc-modal-content {
                background-color: #fefefe;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #ddd;
                width: 50%;
                max-width: 500px;
                border-radius: 4px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .mmc-modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .mmc-modal-close:hover {
                color: black;
            }
            .mmc-modal-actions {
                margin-top: 20px;
                text-align: right;
            }
            .mmc-modal-actions button {
                margin-left: 10px;
            }
            #modal-response {
                margin-top: 15px;
                padding: 10px;
                background-color: #f8f8f8;
                border-left: 4px solid #dc3232;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                // Process refresh if requested
                <?php if (isset($_GET['refresh']) && $_GET['refresh'] === 'true'): ?>
                    $(document).ready(function() {
                        refreshAllMemberships();
                    });
                <?php endif; ?>
                
                // Handle cancel membership button
                $('.cancel-membership').on('click', function() {
                    var userId = $(this).data('user-id');
                    var subscriptionId = $(this).data('subscription-id');
                    var userName = $(this).closest('tr').find('td:first-child strong a').text();
                    
                    // Set modal content
                    $('#modal-user-name').text(userName);
                    
                    // Store data for use when confirming
                    $('#confirm-cancel').data('user-id', userId);
                    $('#confirm-cancel').data('subscription-id', subscriptionId);
                    
                    // Show modal
                    $('#cancel-membership-modal').show();
                });
                
                // Handle modal close
                $('.mmc-modal-close, #cancel-modal').on('click', function() {
                    $('#cancel-membership-modal').hide();
                    $('#modal-response').hide();
                });
                
                // Handle confirm cancel
                $('#confirm-cancel').on('click', function() {
                    var userId = $(this).data('user-id');
                    var subscriptionId = $(this).data('subscription-id');
                    var nonce = $('.cancel-membership').data('nonce');
                    
                    // Disable buttons
                    $('#confirm-cancel, #cancel-modal').prop('disabled', true);
                    $('#confirm-cancel').text('Processing...');
                    
                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mmc_cancel_membership',
                            user_id: userId,
                            subscription_id: subscriptionId,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page with success message
                                window.location.href = '<?php echo admin_url('admin.php?page=mmc-membership-management&message=canceled'); ?>';
                            } else {
                                // Show error message
                                $('#modal-response').html('<p>' + response.data.message + '</p>').show();
                                $('#confirm-cancel, #cancel-modal').prop('disabled', false);
                                $('#confirm-cancel').text('Yes, Cancel Membership');
                            }
                        },
                        error: function() {
                            // Show error message
                            $('#modal-response').html('<p>An error occurred. Please try again.</p>').show();
                            $('#confirm-cancel, #cancel-modal').prop('disabled', false);
                            $('#confirm-cancel').text('Yes, Cancel Membership');
                        }
                    });
                });
                
                // Handle refresh button click
                $('#refresh-memberships').on('click', function() {
                    var nonce = $(this).data('nonce');
                    
                    // Create loading overlay
                    $('body').append('<div id="mmc-loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 10000; display: flex; justify-content: center; align-items: center;"><div style="text-align: center;"><span class="spinner is-active" style="float:none; width:60px; height:60px; margin:0 0 10px 0;"></span><p>Refreshing all membership data...</p></div></div>');
                    
                    // Send AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mmc_refresh_memberships',
                            nonce: nonce
                        },
                        success: function(response) {
                            // Remove loading overlay
                            $('#mmc-loading-overlay').remove();
                            
                            if (response.success) {
                                // Show success message
                                $('#refresh-message').html('<p>' + response.data.message + '</p>').show();
                                // Reload the table data
                                window.location.reload();
                            } else {
                                // Show error message
                                $('#refresh-message').removeClass('notice-success').addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
                            }
                        },
                        error: function() {
                            // Remove loading overlay
                            $('#mmc-loading-overlay').remove();
                            // Show error message
                            $('#refresh-message').removeClass('notice-success').addClass('notice-error').html('<p>An error occurred while refreshing membership data. Please try again.</p>').show();
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX membership cancellation
     */
    public function handle_cancel_membership() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mmc_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            exit;
        }
        
        // Check required fields
        if (!isset($_POST['user_id']) || !isset($_POST['subscription_id'])) {
            wp_send_json_error(array('message' => 'Missing required data.'));
            exit;
        }
        
        $user_id = intval($_POST['user_id']);
        $subscription_id = sanitize_text_field($_POST['subscription_id']);
        
        // Verify that the user has this subscription
        $user_subscription_id = get_user_meta($user_id, 'square_subscription_id', true);
        if ($user_subscription_id !== $subscription_id) {
            wp_send_json_error(array('message' => 'Subscription ID mismatch.'));
            exit;
        }
        
        try {
            // Get the Square Service
            $square_service = SquareService::get_instance();
            
            // Use the new method that handles already-cancelled subscriptions
            $result = $square_service->cancelSubscription($subscription_id);
            
            if ($result) {
                // Set the user's membership to inactive
                UserFunctions::set_inactive_membership($user_id);
                
                // Send success response
                wp_send_json_success(array('message' => 'Membership canceled successfully.'));
            } else {
                wp_send_json_error(array('message' => 'Failed to cancel subscription in Square.'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
        
        exit;
    }
    
    /**
     * Handle AJAX refresh all memberships
     */
    public function handle_refresh_memberships() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mmc_admin_action_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
            exit;
        }
        
        try {
            // Refresh all memberships
            $result = $this->refresh_all_memberships();
            
            if ($result) {
                wp_send_json_success(array('message' => 'All membership data has been refreshed successfully.'));
            } else {
                wp_send_json_error(array('message' => 'Failed to refresh membership data.'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
        
        exit;
    }
}

// Initialize the member management page
$member_management = MemberManagement::get_instance();
