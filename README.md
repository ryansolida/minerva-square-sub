# Square Service for WordPress

A standalone WordPress plugin that provides Square API integration without requiring Composer. This package includes bundled versions of the Square SDK and Guzzle HTTP client.

## Installation

To use this as a standard WordPress plugin:

1. Download or clone this repository
2. Upload the entire `square-service-wp` directory to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your Square API credentials in Settings > Square Service

To use as a Must-Use Plugin (mu-plugin):

1. Download or clone this repository
2. Copy the entire `square-service-wp` directory to the `/wp-content/mu-plugins/` directory
3. Create a loader file in your mu-plugins directory (if not already present):

```php
<?php
// mu-plugins loader
require_once __DIR__ . '/square-service-wp/square-service.php';
```

4. Configure your Square API credentials in Settings > Square Service

## Configuration

After installation, go to Settings > Square Service in your WordPress admin area to configure:

1. Square API Access Token
2. Square Location ID (required for subscriptions)
3. Environment (Sandbox or Production)

## Usage

### Basic Usage

The plugin provides global functions to access the Square Service:

```php
// Get an instance of the SquareService with default credentials
$squareService = get_square_service();

// Get an instance with custom credentials
$squareService = get_square_service('your-access-token');

// Create a customer
$customer = $squareService->createCustomer([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Get the customer ID
$customerId = $customer->id;
```

### Credit Card Management

```php
// Add a card to a customer (with a card token from Square.js)
$card = $squareService->addCardToCustomer($customerId, $cardToken);

// Get all customer cards
$cards = $squareService->getCustomerCards($customerId);

// Delete a card
$squareService->deleteCustomerCard($customerId, $cardId);
```

### Subscription Management

```php
// Ensure the monthly membership plan exists
$plan = $squareService->ensureMonthlyMembershipPlanExists();

// Create a subscription
$subscription = $squareService->createSubscription(
    $customerId, 
    $cardId, 
    $plan['plan_variation_id']
);

// Cancel a subscription
$squareService->cancelSubscription($subscriptionId);
```

## Customization

The SquareService class includes configurable properties for the subscription plan:

```php
protected $subscriptionPlanName = 'Exclusive Club Plan';
protected $subscriptionPlanId = 'ExclusiveClubPlan';
protected $subscriptionVariationName = 'Exclusive Club';
protected $subscriptionPrice = 8.99;
protected $subscriptionCurrency = 'USD';
```

To customize these, extend the SquareService class and override these properties.

## Dependencies

This package includes bundled versions of:

- Square PHP SDK
- Guzzle HTTP Client
- PSR-7 HTTP Message Interface
- PSR-18 HTTP Client Interface

No Composer or additional dependencies are required.

## Shortcodes

The plugin provides several shortcodes for displaying membership information and processing subscriptions on your WordPress site.

### Subscription Form

```
[square_subscription_form button_text="Subscribe Now" title="Subscribe to our Exclusive Club" description="Join our exclusive club for just $8.99/month" plan_id="" redirect_url=""]
```

Parameters:
- `button_text`: Customize the submit button text (default: "Subscribe Now")
- `title`: The form title (default: "Subscribe to our Exclusive Club")
- `description`: Description text displayed below the title (default: "Join our exclusive club for just $8.99/month")
- `plan_id`: Optional plan ID if you have multiple subscription plans (default: uses the main plan configured in settings)
- `redirect_url`: URL to redirect to after successful subscription (default: current page)

### Membership Status

```
[square_membership_status active_text="You have an active membership" inactive_text="You do not have an active membership"]
```

Parameters:
- `active_text`: Text to display for active members (default: "You have an active membership")
- `inactive_text`: Text to display for non-members (default: "You do not have an active membership")

### Next Billing Date

```
[square_next_billing_date format="F j, Y" prefix="Next payment: " not_found="No active subscription found"]
```

Parameters:
- `format`: PHP date format string (default: "F j, Y")
- `prefix`: Text to display before the date (default: "Next payment: ")
- `not_found`: Message to display when no subscription is found (default: "No active subscription found")

### Next Payment Amount

```
[square_next_payment_amount prefix="Amount due: " not_found="No active subscription found"]
```

Parameters:
- `prefix`: Text to display before the amount (default: "Amount due: ")
- `not_found`: Message to display when no subscription is found (default: "No active subscription found")

### Payment Card Information

```
[square_payment_card_info prefix="Current payment method: " not_found="No payment method on file"]
```

Parameters:
- `prefix`: Text to display before the card info (default: "Current payment method: ")
- `not_found`: Message to display when no payment method is found (default: "No payment method on file")

### Payment Methods Management

```
[square_payment_methods button_text="Update Payment Method" title="Manage Your Payment Methods"]
```

Parameters:
- `button_text`: Text for the update button (default: "Update Payment Method")
- `title`: Title of the payment methods section (default: "Manage Your Payment Methods")

### Cancel Membership

```
[square_cancel_membership button_text="Cancel Membership" confirmation_text="Are you sure you want to cancel your membership?" title="Cancel Your Membership"]
```

Parameters:
- `button_text`: Text for the cancel button (default: "Cancel Membership")
- `confirmation_text`: Confirmation message shown before cancellation (default: "Are you sure you want to cancel your membership?")
- `title`: Title for the cancellation section (default: "Cancel Your Membership")

## Elementor Integration

The plugin provides Elementor widgets and dynamic tags that correspond to all the shortcodes above:

### Widgets
- Subscription Form Widget
- Payment Methods Widget
- Cancel Membership Widget

### Dynamic Tags
- Membership Status Tag
- Next Billing Date Tag
- Next Payment Amount Tag
- Payment Card Info Tag

These can be used in your Elementor templates by looking in the "Square Service" section of the Elementor editor.

## Example Template

The plugin includes an example template with all shortcodes demonstrated. To use this template:

1. Create a new page in WordPress
2. In the Page Attributes section, set the Template to "Square Service - All Features Demo"
3. Publish the page

This template provides a comprehensive demonstration of all shortcodes with examples of various configurations.
```

**Attributes:**
- `button_text`: Text for the submit button
- `title`: Form title
- `description`: Form description text
- `plan_id`: (Optional) The Square plan ID to subscribe to. If not provided, uses the default plan.
- `redirect_url`: (Optional) URL to redirect after successful subscription

### Membership Status

```
[square_membership_status active_text="Your membership is active" inactive_text="You do not have an active membership"]
```

**Attributes:**
- `active_text`: Text to display when membership is active
- `inactive_text`: Text to display when there's no active membership

### Next Billing Date

```
[square_next_billing_date prefix="Next billing date: " format="F j, Y" not_found_text="No active subscription"]
```

**Attributes:**
- `prefix`: Text to display before the date
- `format`: PHP date format string
- `not_found_text`: Text to display when no subscription is found

### Next Payment Amount

```
[square_next_payment_amount prefix="Next payment: " not_found_text="No active subscription"]
```

**Attributes:**
- `prefix`: Text to display before the amount
- `not_found_text`: Text to display when no subscription is found

### Payment Card Info

```
[square_payment_card_info prefix="Payment method: " not_found_text="No payment method on file"]
```

**Attributes:**
- `prefix`: Text to display before the card info
- `not_found_text`: Text to display when no card is found

### Payment Methods Management

```
[square_payment_methods title="Your Payment Methods" add_button_text="Add New Card" delete_button_text="Delete" not_logged_in_text="Please log in to manage payment methods" no_methods_text="You have no payment methods on file"]
```

**Attributes:**
- `title`: Section title
- `add_button_text`: Text for the add card button
- `delete_button_text`: Text for the delete card button
- `not_logged_in_text`: Text shown to users who aren't logged in
- `no_methods_text`: Text shown when user has no saved payment methods

### Cancel Membership

```
[square_cancel_membership button_text="Cancel Membership" confirm_text="Are you sure you want to cancel your membership? This action cannot be undone." success_text="Your membership has been canceled successfully." no_subscription_text="You do not have an active membership to cancel." not_logged_in_text="Please log in to manage your membership."]
```

**Attributes:**
- `button_text`: Text for the cancel button
- `confirm_text`: Confirmation message text
- `success_text`: Success message after cancellation
- `no_subscription_text`: Text shown when no active subscription exists
- `not_logged_in_text`: Text shown to users who aren't logged in

## Implementation Example

Here's an example of how to create a complete member account page with all shortcodes:

```
<h1>Your Membership</h1>

[square_membership_status]

<h2>Billing Information</h2>
[square_next_billing_date]
[square_next_payment_amount]
[square_payment_card_info]

<h2>Manage Your Payment Methods</h2>
[square_payment_methods]

<h2>Cancel Membership</h2>
[square_cancel_membership]
```

## Elementor Integration

This plugin includes full Elementor integration, providing both dynamic tags and widgets for a more visual design experience.

### Elementor Dynamic Tags

The following dynamic tags are available in the "Square Service" section of Elementor's dynamic tags:

- **Membership Status**: Displays the current membership status
- **Next Billing Date**: Shows the next billing date for the subscription
- **Next Payment Amount**: Displays the amount of the next payment
- **Payment Card Info**: Shows the payment card details (brand and last 4 digits)

To use these tags:
1. Edit any text element in Elementor
2. Click on the Dynamic Tags icon (database icon)
3. Select the desired tag from the "Square Service" category
4. Configure the tag settings as needed

### Elementor Widgets

The plugin also includes fully customizable widgets for more complex functionality:

- **Square Subscription Form**: A complete subscription form with Square payment processing
- **Square Payment Methods**: Interface for managing payment methods
- **Square Cancel Membership**: Option for members to cancel their subscription

All widgets include extensive styling options through the Elementor interface, allowing you to match them to your site's design without custom CSS.

## Notes

- The bundled libraries are simplified versions of the original packages
- For production use with high transaction volume, consider using Composer-managed dependencies
- This package is designed for WordPress environments where Composer isn't available
