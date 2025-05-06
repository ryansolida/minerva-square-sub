<?php

namespace MMCMembership;
use Exception;
/**
 * Square Service for WordPress
 * 
 * Provides functionality for interacting with the Square API without Laravel dependencies
 */
class SquareService
{
    protected $client;
    protected $baseUrl = 'https://connect.squareupsandbox.com/v2';
    protected $squareVersion = '2023-09-25';
    
    // Subscription plan configuration
    protected $subscriptionPlanName = 'Exclusive Club Plan';
    protected $subscriptionPlanId = 'ExclusiveClubPlan';
    protected $subscriptionVariationName = 'Exclusive Club';
    protected $subscriptionPrice = 8.99;
    protected $subscriptionCurrency = 'USD';
    
    /**
     * Initialize the service with Square API credentials
     * 
     * @param string $accessToken Square API access token
     * @param bool $production Whether to use production environment
     */
    public function __construct()
    {
        // If no access token provided, try to get from WordPress options
        $accessToken = get_option('square_service_access_token');
        
        if (empty($accessToken)) {
            throw new Exception('Square API access token is required');
        }

        $production = get_option('square_service_environment', 'sandbox') === 'production';
        
        // Set production environment if requested
        if ($production) {
            $this->baseUrl = 'https://connect.squareup.com/v2';
        }
    }

    /**
     * Get the singleton instance of the SquareService
     *
     * @param string $accessToken Square API access token
     * @param bool $production Whether to use production environment
     * @return SquareService
     */
    public static function get_instance()
    {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new self();
        }
        
        return $instance;
    }
    
    /**
     * Make an API request to Square
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path
     * @param array $data Request data (body for POST, query params for GET)
     * @param array $options Additional request options
     * @return array Response data
     * @throws Exception
     */
    private function makeApiRequest(string $method, string $endpoint, array $data = [], array $options = [])
    {
        try {
            $headers = [
                'Square-Version' => $this->squareVersion,
                'Authorization' => 'Bearer ' . (get_option('square_service_access_token') ?: ''),
                'Content-Type' => 'application/json'
            ];
            
            $args = [
                'headers' => $headers,
                'timeout' => 30,
                'method' => $method
            ];
            
            // For GET requests, use query params in the URL
            if ($method === 'GET' && !empty($data)) {
                $endpoint .= '?' . http_build_query($data);
            } 
            // For other requests, add data as JSON body
            else if (!empty($data)) {
                $args['body'] = json_encode($data);
            }
            
            // Merge any additional options
            $args = array_merge($args, $options);
            
            // Make the request using WordPress HTTP API
            $response = wp_remote_request($this->baseUrl . $endpoint, $args);
            
            // Check for errors
            if (is_wp_error($response)) {
                throw new Exception('WordPress HTTP Error: ' . $response->get_error_message());
            }
            
            // Get the response code
            $response_code = wp_remote_retrieve_response_code($response);
            
            // Check if response is successful
            if ($response_code >= 400) {
                throw new Exception('Square API Error: endpoint: ' . $endpoint.' - '. wp_remote_retrieve_response_message($response) . ' (Code: ' . $response_code . ')');
            }
            
            // Get and decode the body
            $body = wp_remote_retrieve_body($response);
            return json_decode($body, true);
        } catch (Exception $e) {
            // Log any exceptions
            error_log('Square API Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a customer in Square
     *
     * @param array $customerData Customer data (name, email, etc.)
     * @return object|null
     * @throws Exception
     */
    public function createCustomer(array $customerData)
    {
        $data = [];
        
        // Handle different parameter naming conventions
        if (isset($customerData['name'])) {
            $data['given_name'] = $customerData['name'];
        }
        
        // Email could be passed either as 'email' or 'email_address'
        if (isset($customerData['email'])) {
            $data['email_address'] = $customerData['email'];
        } elseif (isset($customerData['email_address'])) {
            $data['email_address'] = $customerData['email_address'];
        }
        
        // Add optional fields if provided
        if (!empty($customerData['family_name'])) {
            $data['family_name'] = $customerData['family_name'];
        }
        
        if (!empty($customerData['phone'])) {
            $data['phone_number'] = $customerData['phone'];
        }
        
        // Handle notes
        if (!empty($customerData['note'])) {
            $data['note'] = $customerData['note'];
        }
        
        $result = $this->makeApiRequest('POST', '/customers', $data);
        
        if (isset($result['customer'])) {
            return (object) $result['customer'];
        } else {
            $this->log_error('Failed to create Square customer: ' . json_encode($result));
            throw new Exception('Failed to create Square customer: ' . json_encode($result));
        }
    }
    
    /**
     * Delete a customer from Square
     *
     * @param string $customerId Square customer ID to delete
     * @return bool True if successful, false otherwise
     */
    public function deleteCustomer(string $customerId)
    {
        try {
            $result = $this->makeApiRequest('DELETE', "/customers/{$customerId}");
            return true;
        } catch (Exception $e) {
            $this->log_error('Failed to delete Square customer: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get customer cards from Square
     *
     * @param string $customerId
     * @return array
     * @throws Exception
     */
    public function getCustomerCards(string $customerId)
    {
        try {
            $data = [
                'customer_id' => $customerId
            ];
            
            $result = $this->makeApiRequest('GET', '/cards', $data);
                
            if (isset($result['cards'])) {
                // Convert each card array to a class that has the expected methods
                return array_map(function ($card) {
                    // Create a card wrapper class with the same method names
                    // that the original Square SDK would have
                    return new class($card) {
                        private $card;
                        
                        public function __construct($card) {
                            $this->card = $card;
                        }
                        
                        public function getCardBrand() {
                            return $this->card['card_brand'] ?? 'Unknown';
                        }
                        
                        public function getLast4() {
                            return $this->card['last_4'] ?? '****';
                        }
                        
                        public function getExpMonth() {
                            return $this->card['exp_month'] ?? '**';
                        }
                        
                        public function getExpYear() {
                            return $this->card['exp_year'] ?? '****';
                        }
                        
                        public function getId() {
                            return $this->card['id'] ?? '';
                        }
                    };
                }, $result['cards'] ?? []);
            } else {
                return [];
            }
        } catch (Exception $e) {
            // Just return an empty array if there's an error - likely the customer has no cards yet
            $this->log_error('Error getting customer cards: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add a payment card to a customer
     *
     * @param string $customerId
     * @param string $cardToken
     * @return object|null
     * @throws Exception
     */
    public function addCardToCustomer(string $customerId, string $cardToken)
    {
        $data = [
            'idempotency_key' => uniqid('card-'),
            'source_id' => $cardToken,
            'card' => [
                'customer_id' => $customerId,
            ]
        ];
        
        $result = $this->makeApiRequest('POST', '/cards', $data);
        
        if (isset($result['card'])) {
            // Create object with all properties
            $cardObj = (object) $result['card'];
            // Add helper getter methods to match Square SDK methods
            $cardObj->getCardBrand = function() use ($result) {
                return $result['card']['card_brand'] ?? 'Unknown';
            };
            $cardObj->getLast4 = function() use ($result) {
                return $result['card']['last_4'] ?? '****';
            };
            $cardObj->getExpMonth = function() use ($result) {
                return $result['card']['exp_month'] ?? '**';
            };
            $cardObj->getExpYear = function() use ($result) {
                return $result['card']['exp_year'] ?? '****';
            };
            $cardObj->getId = function() use ($result) {
                return $result['card']['id'] ?? '';
            };
            return $cardObj;
        } else {
            $this->log_error('Failed to add card to customer: ' . json_encode($result));
            throw new Exception('Failed to add card to customer: ' . json_encode($result));
        }
    }
    
    /**
     * Delete a customer's card
     *
     * @param string $customerId
     * @param string $cardId
     * @return bool
     * @throws Exception
     */
    public function deleteCustomerCard(string $customerId, string $cardId)
    {
        $result = $this->makeApiRequest('POST', "/cards/{$cardId}/disable");
        
        if (isset($result['card'])) {
            return true;
        } else {
            $this->log_error('Failed to delete card: ' . json_encode($result));
            throw new Exception('Failed to delete card: ' . json_encode($result));
        }
    }
    
    /**
     * Create a subscription for a customer
     *
     * @param string $customerId
     * @param string $cardId
     * @param string $planId
     * @return object|null
     * @throws Exception
     */
    public function createSubscription(string $customerId, string $cardId, string $planId)
    {
        $data = [
            'location_id' => get_option('square_service_location_id'),
            'plan_variation_id' => $planId,
            'customer_id' => $customerId,
            'card_id' => $cardId,
            'start_date' => date('Y-m-d'),
        ];
        
        $result = $this->makeApiRequest('POST', '/subscriptions', $data);

        if (isset($result['subscription'])) {
            return (object) $result['subscription'];
        } else {
            $this->log_error('Failed to create subscription: ' . json_encode($result));
            throw new Exception('Failed to create subscription: ' . json_encode($result));
        }
    }
    
    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId
     * @return bool
     * @throws Exception
     */
    public function cancelSubscription(string $subscriptionId)
    {
        $result = $this->makeApiRequest('POST', "/subscriptions/{$subscriptionId}/cancel");
        
        if (isset($result['subscription'])) {
            return true;
        } else {
            $this->log_error('Failed to cancel subscription: ' . json_encode($result));
            throw new Exception('Failed to cancel subscription: ' . json_encode($result));
        }
    }
    
    /**
     * Get subscription details and status
     *
     * @param string $subscriptionId
     * @return object|null
     * @throws Exception
     */
    public function getSubscription(string $subscriptionId)
    {
        $result = $this->makeApiRequest('GET', "/subscriptions/{$subscriptionId}");
        
        if (isset($result['subscription'])) {
            return (object) $result['subscription'];
        } else {
            $this->log_error('Failed to get subscription: ' . json_encode($result));
            throw new Exception('Failed to get subscription: ' . json_encode($result));
        }
    }
    
    /**
     * Ensure that the monthly membership plan exists in Square
     * Creates the plan if it doesn't exist
     *
     * @param float|null $price Price in dollars (e.g., 8.99), null to use default
     * @param string|null $currencyCode Currency code, null to use default
     * @return array Plan details including variation ID and phase ID
     * @throws Exception
     */
    public function ensureMonthlyMembershipPlanExists(float $price = null, string $currencyCode = null)
    {
        // First check if we can find the "Exclusive Club" plan variation by listing catalog items
        try {
            $result = $this->makeApiRequest('GET', '/catalog/list', [
                'types' => 'SUBSCRIPTION_PLAN_VARIATION'
            ]);
            
            // Search for our specific plan
            if (isset($result['objects']) && is_array($result['objects'])) {
                foreach ($result['objects'] as $object) {
                    if ($object['type'] === 'SUBSCRIPTION_PLAN_VARIATION') {
                        $variationData = $object['subscription_plan_variation_data'] ?? [];
                        $name = $variationData['name'] ?? '';
                        
                        if (stripos($name, $this->subscriptionVariationName) !== false) {
                            // We found our plan, return its details
                            $planVariationId = $object['id'];
                            $phases = $variationData['phases'] ?? [];
                            $phaseId = $phases[0]['uid'] ?? null;
                            
                            if ($planVariationId && $phaseId) {
                                return [
                                    'plan_variation_id' => $planVariationId,
                                    'phase_id' => $phaseId,
                                    'status' => 'exists'
                                ];
                            }
                        }
                    }
                }
            }
            
            // If we reach here, the plan doesn't exist, so create it
            return $this->createMonthlyMembershipPlan($price, $currencyCode);
            
        } catch (Exception $e) {
            // If we can't check or something fails, try to create the plan
            $this->log_error('Error checking for existing plan: ' . $e->getMessage());
            return $this->createMonthlyMembershipPlan($price, $currencyCode);
        }
    }
    
    /**
     * Create a new Monthly Membership subscription plan
     *
     * @param float|null $price Price in dollars (e.g., 8.99), null to use default
     * @param string|null $currencyCode Currency code, null to use default
     * @return array Plan details including variation ID and phase ID
     * @throws Exception
     */
    private function createMonthlyMembershipPlan(float $price = null, string $currencyCode = null)
    {
        // Use default values if not provided
        $price = $price ?? $this->subscriptionPrice;
        $currencyCode = $currencyCode ?? $this->subscriptionCurrency;
        
        // Convert dollars to cents for Square API
        $priceInCents = (int)($price * 100);
        
        try {
            // First, create a subscription plan
            $createPlanBody = [
                'idempotency_key' => uniqid('create-plan-'),
                'object' => [
                    'type' => 'SUBSCRIPTION_PLAN',
                    'id' => "#{$this->subscriptionPlanId}",
                    'present_at_all_locations' => true,
                    'subscription_plan_data' => [
                        'name' => $this->subscriptionPlanName
                    ]
                ]
            ];
            
            // Create the subscription plan
            $planResult = $this->makeApiRequest('POST', '/catalog/object', $createPlanBody);
            $planId = $planResult['catalog_object']['id'] ?? null;

            if (!$planId) {
                throw new Exception('Failed to create subscription plan: ' . json_encode($planResult));
            }
            
            // Now create a subscription plan variation
            $createVariationBody = [
                'idempotency_key' => uniqid('create-variation-'),
                'object' => [
                    'type' => 'SUBSCRIPTION_PLAN_VARIATION',
                    'id' => '#'.uniqid(strtolower(str_replace(' ', '-', $this->subscriptionVariationName)).'-'),
                    'present_at_all_locations' => true,
                    'subscription_plan_variation_data' => [
                        'name' => $this->subscriptionVariationName,
                        'subscription_plan_id' => $planId,
                        'phases' => [
                            [
                                'uid' => uniqid('phase-'),
                                'cadence' => 'MONTHLY',
                                'pricing' => [
                                    'type' => 'STATIC', // Using STATIC instead of RELATIVE for simplicity
                                    'price_money' => [
                                        'amount' => $priceInCents,
                                        'currency' => $currencyCode
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            
            // Log the request for debugging
            $this->log_info('Creating Square plan variation', [
                'planId' => $planId,
                'createBody' => $createVariationBody
            ]);
            
            // Create the subscription plan variation
            $variationResult = $this->makeApiRequest('POST', '/catalog/object', $createVariationBody);
            
            // Extract the variation ID and phase ID from the result
            $variationObject = $variationResult['catalog_object'] ?? [];
            $planVariationId = $variationObject['id'] ?? null;
            $phases = $variationObject['subscription_plan_variation_data']['phases'] ?? [];
            $phaseId = $phases[0]['uid'] ?? null;
            
            if (!$planVariationId || !$phaseId) {
                throw new Exception('Failed to extract plan variation or phase IDs: ' . json_encode($variationResult));
            }
            
            return [
                'plan_variation_id' => $planVariationId,
                'phase_id' => $phaseId,
                'status' => 'created'
            ];
        } catch (Exception $e) {
            $this->log_error('Failed to create monthly membership plan: ' . $e->getMessage());
            throw new Exception('Failed to create subscription plan: ' . $e->getMessage());
        }
    }
    
    /**
     * Log an error message
     *
     * @param string $message
     * @param array $context
     */
    private function log_error($message, $context = [])
    {
        if (function_exists('error_log')) {
            error_log('SquareService Error: ' . $message . (empty($context) ? '' : ' ' . json_encode($context)));
        }
    }
    
    /**
     * Log an info message
     *
     * @param string $message
     * @param array $context
     */
    private function log_info($message, $context = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('SquareService Info: ' . $message . (empty($context) ? '' : ' ' . json_encode($context)));
        }
    }
    
    /**
     * Update a subscription's payment method
     *
     * @param string $subscriptionId The subscription ID to update
     * @param string $cardId The new card ID to use for payment
     * @return bool True if successful, false otherwise
     * @throws Exception
     */
    public function updateSubscriptionPaymentMethod(string $subscriptionId, string $cardId)
    {
        try {
            $data = [
                'subscription' => [
                    'card_id' => $cardId
                ]
            ];
            
            $result = $this->makeApiRequest('PUT', "/subscriptions/{$subscriptionId}", $data);
            
            if (isset($result['subscription'])) {
                return true;
            } else {
                $this->log_error('Failed to update subscription payment method: ' . json_encode($result));
                throw new Exception('Failed to update subscription payment method: ' . json_encode($result));
            }
        } catch (Exception $e) {
            $this->log_error('Error updating subscription payment method: ' . $e->getMessage());
            throw $e;
        }
    }
}
