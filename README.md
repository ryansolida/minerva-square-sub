# MMC Membership Plugin for WordPress

A comprehensive WordPress membership plugin that integrates with Square for payment processing. This plugin is designed to be used as a Must-Use Plugin (mu-plugin) and allows you to create a complete membership system with user registration, subscription management, and members-only content protection.

## Features

- **User Registration & Login**: Allow users to register and create accounts with integrated payment processing
- **Subscription Management**: Create and manage Square-based subscriptions for your members
- **Account Management**: Users can update their profile information, change passwords, and manage their membership
- **Members-Only Content**: Restrict content to active members only
- **Elementor Integration**: Dynamic tags for displaying membership information in Elementor templates
- **Square Payment Processing**: Secure payment processing using Square's Web Payments SDK
- **Log Out Button**: Convenient log out option on the My Account page

## Installation

This plugin is designed to be used as a Must-Use Plugin (mu-plugin) for WordPress:

1. Download or clone this repository
2. Upload the entire `mmc-membership` directory to the `/wp-content/mu-plugins/` directory
3. If the `mu-plugins` directory doesn't exist, create it
4. Create a loader file in your `mu-plugins` directory (if not already present):

```php
<?php
// mu-plugins loader
require_once __DIR__ . '/mmc-membership/mmc-membership.php';
```

5. Configure your settings in the MMC Memberships admin menu

**Note**: As a Must-Use Plugin, it will be automatically activated and cannot be deactivated through the WordPress admin interface. This ensures the membership functionality is always available.

## Configuration

After installation, go to MMC Memberships in your WordPress admin area to configure:

1. Square API Access Token
2. Square Application ID
3. Square Location ID
4. Default Plan ID
5. Environment (Sandbox or Production)
6. Membership pages (Signup, Login, Account)
7. Club name and membership price

### Setting Up Square Credentials

To use this plugin, you'll need to create a Square Developer account and set up an application. Follow these steps:

#### 1. Create a Square Developer Account

1. Go to the [Square Developer Portal](https://developer.squareup.com/)
2. Click "Sign Up" and create an account (or sign in if you already have one)
3. Accept the developer terms of service

#### 2. Create a New Application

1. From the Square Developer Dashboard, click "Create Your First Application"
2. Enter a name for your application (e.g., "My Website Membership")
3. Select the "Production" or "Sandbox" environment
   - **Sandbox**: Use for testing (doesn't process real payments)
   - **Production**: Use for live payments
4. Click "Create Application"

#### 3. Get Your Credentials

1. Once your application is created, you'll be taken to the application dashboard
2. In the left sidebar, click on "Credentials"
3. You'll find the following credentials:
   - **Application ID**: Used to identify your application
   - **Access Token**: Used to authenticate API requests
   - **Location ID**: Needed for subscription processing

#### 4. Set Up Web Payments

1. In the left sidebar, click on "Web Payments"
2. Click "Add Web Location"
3. Enter your website domain (e.g., `yourdomain.com`)
4. Click "Save"

#### 5. Configure the Plugin

1. In your WordPress admin, go to "MMC Memberships" settings
2. Enter the credentials you obtained from Square:
   - Application ID
   - Access Token
   - Location ID
3. Enter your membership details:
   - Club name
   - Membership price
   - Billing frequency
4. Select the appropriate environment (Sandbox or Production)
5. Save your settings

**Note**: After saving your settings, use the "Create Subscription Plan" button in the plugin settings to generate the subscription plan in Square based on the name and price you've configured. This eliminates the need to manually create a plan in the Square Dashboard.

## Usage

### Shortcodes

The plugin provides several shortcodes to display membership-related content:

- `[mmc_membership_page]`: Displays different content based on user's login and membership status
- `[mmc_my_account]`: Displays the user account management page with profile management and log out options
- `[mmc_login_form]`: Displays a custom login form
- `[mmc_signup_form]`: Displays a membership signup form for logged-in users
- `[mmc_new_user_signup_form]`: Displays a registration form with integrated payment for new users
- `[members_only]`: Restricts content to active members only
- `[mmc_has_active_membership]`: Conditional content based on membership status
- `[mmc_membership_expiration_date]`: Displays the user's membership expiration date
- `[mmc_membership_activation_date]`: Displays the user's membership activation date
- `[mmc_membership_next_billing_date]`: Displays the user's next billing date
- `[mmc_membership_next_billing_price]`: Displays the user's next billing amount

### Members-Only Content

You can restrict content to members only in two ways:

1. Using the shortcode: `[members_only]Your exclusive content here[/members_only]`
2. Making a whole page members-only by checking the "Members Only" box in the page editor sidebar. This restricts the entire page to members only.

### Elementor Integration

The plugin includes several dynamic tags for Elementor under the "MMC Membership" group:

- **MMC Membership Status**: Displays the current membership status
- **MMC Next Billing Date**: Shows when the next payment will be processed
- **MMC Next Billing Price**: Shows the amount of the next payment
- **MMC Payment Card Info**: Displays the payment card details
- **MMC Has Active Membership**: Conditional tag for membership status
- **MMC Membership Expiration Date**: Shows when the membership expires
- **MMC Membership Activation Date**: Shows when the membership was activated

These tags can be used in Elementor templates to dynamically display membership information.

### PHP API

For developers, the plugin provides a comprehensive API to interact with the membership system:

```php
// Get the Square service instance
$square_service = new \MMCMembership\SquareService();

// Check if user has active membership
$has_membership = \MMCMembership\UserFunctions::has_active_membership();

// Get membership signup URL
$signup_url = get_membership_signup_url();

// Get subscription data
$subscription_id = get_user_meta(get_current_user_id(), 'square_subscription_id', true);
$subscription = $square_service->getSubscription($subscription_id);
```

## Implementation Example

Here's an example of how to create a complete member account page:

1. Create a new page in WordPress
2. Add the following shortcode to display the account management interface:
   ```
   [mmc_my_account]
   ```

3. For a members-only page, create another page and add:
   ```
   [members_only]
   This content is only visible to active members.
   [/members_only]
   ```

4. To create a signup page for new users:
   ```
   [mmc_new_user_signup_form]
   ```

## Customization

The plugin is designed to be customizable through WordPress actions and filters. You can also extend the core classes to add custom functionality.

### CSS Customization

The plugin includes CSS for styling the account page, membership forms, and restricted content messages. You can override these styles in your theme's CSS or use the WordPress Customizer.

### Template Customization

The plugin uses output buffering to generate HTML, making it easy to modify the output using WordPress filters. For advanced customization, you can copy the plugin files to your theme and modify them directly.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Square Developer Account
- SSL Certificate (required for Square payments)
- Elementor (optional, for dynamic tags)

## Dependencies

This package includes bundled versions of:

- Square PHP SDK
- Guzzle HTTP Client

No Composer or additional dependencies are required.