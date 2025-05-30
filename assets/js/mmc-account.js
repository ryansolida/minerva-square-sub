/**
 * MMC Membership Account JavaScript
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // Password strength meter
        if ($('#mmc_new_password').length) {
            $('#mmc_new_password').on('keyup', function() {
                var password = $(this).val();
                var strength = checkPasswordStrength(password);
                showPasswordStrength(strength);
            });
        }

        // Password confirmation match
        if ($('#mmc_confirm_password').length) {
            $('#mmc_confirm_password').on('keyup', function() {
                var password = $('#mmc_new_password').val();
                var confirm = $(this).val();
                
                if (confirm.length > 0) {
                    if (password === confirm) {
                        $(this).css('border-color', '#28a745');
                    } else {
                        $(this).css('border-color', '#dc3545');
                    }
                } else {
                    $(this).css('border-color', '');
                }
            });
        }

        // Toggle add payment method form
        $('.mmc-add-payment-method').on('click', function(e) {
            e.preventDefault();
            $('.mmc-add-payment-form').slideToggle();
        });

        // Auto-hide notices after 5 seconds
        setTimeout(function() {
            $('.mmc-notice').slideUp();
        }, 5000);
    });

    /**
     * Check password strength
     * 
     * @param {string} password The password to check
     * @return {number} Strength score (0-4)
     */
    function checkPasswordStrength(password) {
        var strength = 0;
        
        // Length check
        if (password.length >= 8) {
            strength += 1;
        }
        
        // Contains lowercase
        if (password.match(/[a-z]+/)) {
            strength += 1;
        }
        
        // Contains uppercase
        if (password.match(/[A-Z]+/)) {
            strength += 1;
        }
        
        // Contains number
        if (password.match(/[0-9]+/)) {
            strength += 1;
        }
        
        // Contains special character
        if (password.match(/[$@#&!]+/)) {
            strength += 1;
        }
        
        return strength;
    }

    /**
     * Show password strength indicator
     * 
     * @param {number} strength The password strength score (0-4)
     */
    function showPasswordStrength(strength) {
        var $meter = $('.mmc-password-strength');
        
        // Create meter if it doesn't exist
        if (!$meter.length) {
            $('#mmc_new_password').after('<div class="mmc-password-strength"></div>');
            $meter = $('.mmc-password-strength');
        }
        
        // Update meter based on strength
        var text, color;
        
        switch(strength) {
            case 0:
            case 1:
                text = 'Weak';
                color = '#dc3545';
                break;
            case 2:
            case 3:
                text = 'Medium';
                color = '#ffc107';
                break;
            case 4:
            case 5:
                text = 'Strong';
                color = '#28a745';
                break;
            default:
                text = '';
                color = '';
        }
        
        $meter.text(text).css({
            'color': color,
            'font-size': '0.8em',
            'margin-top': '5px'
        });
    }

})(jQuery);
