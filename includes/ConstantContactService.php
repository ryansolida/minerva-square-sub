<?php
namespace MMCMembership;
use Exception;

/**
 * Constant Contact Service
 * 
 * Handles integration with Constant Contact API for managing email lists
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constant Contact Service
 */
class ConstantContactService {
    
    private $api_key;
    private $access_token;
    private $base_url = 'https://api.cc.email/v3';
    
    /**
     * Constructor
     * 
     * @param string $api_key Constant Contact API key
     * @param string $access_token Constant Contact access token
     */
    public function __construct($api_key = null, $access_token = null) {
        $this->api_key = $api_key ?: \get_option('mmc_membership_cc_api_key', '');
        $this->access_token = $access_token ?: \get_option('mmc_membership_cc_access_token', '');
    }
    
    /**
     * Get all contact lists
     * 
     * @return array Array of contact lists
     */
    public function getLists() {
        $response = $this->makeRequest('GET', '/contact_lists');
        
        if (!isset($response['lists'])) {
            return [];
        }
        
        return $response['lists'];
    }
    
    /**
     * Add contact to a list
     * 
     * @param string $email Contact email address
     * @param string $first_name Contact first name
     * @param string $last_name Contact last name
     * @param string $list_id List ID to add contact to
     * @return bool True if successful, false otherwise
     */
    public function addContactToList($email, $first_name, $last_name, $list_id) {
        // First check if contact exists
        $contact = $this->getContactByEmail($email);
        
        if ($contact) {
            // Contact exists, add to list
            return $this->addExistingContactToList($contact['contact_id'], $list_id);
        } else {
            // Create new contact and add to list
            return $this->createContactAndAddToList($email, $first_name, $last_name, $list_id);
        }
    }
    
    /**
     * Remove contact from a list
     * 
     * @param string $email Contact email address
     * @param string $list_id List ID to remove contact from
     * @return bool True if successful, false otherwise
     */
    public function removeContactFromList($email, $list_id) {
        $contact = $this->getContactByEmail($email);
        
        if (!$contact) {
            // Contact doesn't exist, nothing to do
            return true;
        }
        
        return $this->removeExistingContactFromList($contact['contact_id'], $list_id);
    }
    
    /**
     * Get contact by email
     * 
     * @param string $email Contact email address
     * @return array|null Contact data or null if not found
     */
    private function getContactByEmail($email) {
        $response = $this->makeRequest('GET', '/contacts', [
            'email' => $email
        ]);
        
        if (empty($response['contacts'])) {
            return null;
        }
        
        return $response['contacts'][0];
    }
    
    /**
     * Add existing contact to a list
     * 
     * @param string $contact_id Contact ID
     * @param string $list_id List ID
     * @return bool True if successful, false otherwise
     */
    private function addExistingContactToList($contact_id, $list_id) {
        $response = $this->makeRequest('PUT', "/contacts/{$contact_id}/lists/{$list_id}");
        
        return isset($response['contact_id']);
    }
    
    /**
     * Remove existing contact from a list
     * 
     * @param string $contact_id Contact ID
     * @param string $list_id List ID
     * @return bool True if successful, false otherwise
     */
    private function removeExistingContactFromList($contact_id, $list_id) {
        $response = $this->makeRequest('DELETE', "/contacts/{$contact_id}/lists/{$list_id}");
        
        return $response === null || (isset($response['status']) && $response['status'] === 204);
    }
    
    /**
     * Create a new contact and add to list
     * 
     * @param string $email Contact email address
     * @param string $first_name Contact first name
     * @param string $last_name Contact last name
     * @param string $list_id List ID
     * @return bool True if successful, false otherwise
     */
    private function createContactAndAddToList($email, $first_name, $last_name, $list_id) {
        $data = [
            'email_address' => [
                'address' => $email,
                'permission_to_send' => 'implicit'
            ],
            'first_name' => $first_name,
            'last_name' => $last_name,
            'list_memberships' => [$list_id]
        ];
        
        $response = $this->makeRequest('POST', '/contacts', [], $data);
        
        return isset($response['contact_id']);
    }
    
    /**
     * Make a request to the Constant Contact API
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param array $data Request body data
     * @return array|null Response data or null on error
     */
    private function makeRequest($method, $endpoint, $params = [], $data = null) {
        if (empty($this->api_key) || empty($this->access_token)) {
            error_log('Constant Contact API credentials not set');
            return null;
        }
        
        $url = $this->base_url . $endpoint;
        
        // Add query parameters if any
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ];
        
        // Add request body if data is provided
        if ($data !== null) {
            $args['body'] = json_encode($data);
        }
        
        $response = \wp_remote_request($url, $args);
        
        if (\is_wp_error($response)) {
            \error_log('Constant Contact API error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = \wp_remote_retrieve_response_code($response);
        $body = \wp_remote_retrieve_body($response);
        
        // For successful DELETE requests, return null
        if ($method === 'DELETE' && $status_code === 204) {
            return ['status' => 204];
        }
        
        // For other requests, parse and return the response body
        $data = json_decode($body, true);
        
        if ($status_code >= 400) {
            error_log('Constant Contact API error: ' . $body);
            return null;
        }
        
        return $data;
    }
    
    /**
     * Test the Constant Contact API connection
     * 
     * @return array Test result with success status and message
     */
    public function testConnection() {
        try {
            $lists = $this->getLists();
            
            if (empty($lists)) {
                return [
                    'success' => false,
                    'message' => 'Connection Failed'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Connection successful! Found ' . count($lists) . ' lists.',
                'lists' => $lists
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
}
