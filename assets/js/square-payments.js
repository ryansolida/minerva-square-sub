/**
 * Square Service Payment Handler
 * 
 * Integrates with Square's Web Payments SDK to handle payment methods
 * and subscription processing.
 */

(function($) {
    'use strict';

    // Main Square Payment object
    var SquarePayments = {
        
        // Store the payment form instance
        paymentForm: null,
        
        // Store the Square payments instance
        payments: null,
        
        // Store the card instance
        card: null,
        
        /**
         * Initialize the payment functionality
         */
        init: function() {
            // Only initialize on pages with payment forms
            if ($('.square-payment-form').length === 0) {
                return;
            }
            
            // Initialize Square Web Payments SDK
            this.initializePayments();
            
            // Setup form submission handlers
            this.setupFormHandlers();
        },
        
        /**
         * Initialize the Square Payments SDK
         */
        initializePayments: function() {
            var self = this;
            var appId = square_service_params.application_id;
            var locationId = square_service_params.location_id;
            
            // Verify we have the required configuration
            if (!appId || !locationId) {
                console.error('Square Service: Missing application ID or location ID');
                $('.square-payment-form').append('<div class="square-error">Payment configuration is incomplete. Please contact the site administrator.</div>');
                return;
            }
            
            try {
                // Create a payments instance
                self.payments = Square.payments(appId, locationId);
                
                // Create a card payment method
                self.payments.card().then(function(card) {
                    self.card = card;
                    
                    // Attach the card to each form on the page
                    $('.square-payment-form').each(function() {
                        var formId = $(this).attr('id');
                        var cardContainer = document.getElementById(formId + '-card-container');
                        
                        if (cardContainer) {
                            // Mount the card form
                            card.attach(cardContainer);
                            
                            // Show the form (hidden by default until it's ready)
                            $(this).removeClass('square-loading');
                        }
                    });
                }).catch(function(err) {
                    console.error('Square Service: Could not initialize card form', err);
                    $('.square-payment-form').append('<div class="square-error">Could not load payment form. Please try again later.</div>');
                    $('.square-payment-form').removeClass('square-loading');
                });
            } catch (e) {
                console.error('Square Service: Failed to initialize payments', e);
                $('.square-payment-form').append('<div class="square-error">Payment system is currently unavailable. Please try again later.</div>');
                $('.square-payment-form').removeClass('square-loading');
            }
        },
        
        /**
         * Setup handlers for form submission
         */
        setupFormHandlers: function() {
            var self = this;
            
            // Handle subscription form submission
            $(document).on('submit', '.square-subscription-form', function(e) {
                e.preventDefault();
                self.handleSubscriptionFormSubmit($(this));
            });
            
            // Handle payment method update form submission
            $(document).on('submit', '.square-payment-methods-form', function(e) {
                e.preventDefault();
                self.handlePaymentMethodFormSubmit($(this));
            });
        },
        
        /**
         * Handle subscription form submission
         * 
         * @param {jQuery} $form The form element
         */
        handleSubscriptionFormSubmit: function($form) {
            var self = this;
            var formId = $form.attr('id');
            var $submitButton = $form.find('button[type="submit"]');
            var $errorContainer = $form.find('.square-form-errors');
            
            // Clear previous errors
            $errorContainer.empty().hide();
            
            // Disable the submit button to prevent multiple submissions
            $submitButton.prop('disabled', true).addClass('square-loading');
            
            // Tokenize the card
            self.card.tokenize().then(function(result) {
                if (result.status === 'OK') {
                    // Get the token and add it to the form
                    var sourceId = result.token;
                    
                    // Add the payment token as a hidden field
                    $form.append($('<input type="hidden" name="square_source_id">').val(sourceId));
                    
                    // Submit the form data via AJAX
                    $.ajax({
                        url: square_service_params.ajax_url,
                        type: 'POST',
                        data: $form.serialize() + '&action=square_create_subscription&security=' + square_service_params.nonce,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Show success message
                                $form.html('<div class="square-success">' + response.data.message + '</div>');
                                
                                // Redirect if a URL was provided
                                if (response.data.redirect_url) {
                                    window.location.href = response.data.redirect_url;
                                } else {
                                    // Reload the page after 2 seconds to show updated state
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                }
                            } else {
                                // Show error message
                                $errorContainer.html(response.data.message).show();
                                $submitButton.prop('disabled', false).removeClass('square-loading');
                            }
                        },
                        error: function() {
                            $errorContainer.html('An error occurred while processing your payment. Please try again.').show();
                            $submitButton.prop('disabled', false).removeClass('square-loading');
                        }
                    });
                } else {
                    // Show tokenization errors
                    $errorContainer.html(result.errors.map(function(error) {
                        return error.message;
                    }).join('<br>')).show();
                    $submitButton.prop('disabled', false).removeClass('square-loading');
                }
            }).catch(function(err) {
                $errorContainer.html('Could not process card information. Please check your card details and try again.').show();
                $submitButton.prop('disabled', false).removeClass('square-loading');
                console.error('Square Service: Tokenization failed', err);
            });
        },
        
        /**
         * Handle payment method update form submission
         * 
         * @param {jQuery} $form The form element
         */
        handlePaymentMethodFormSubmit: function($form) {
            var self = this;
            var $submitButton = $form.find('button[type="submit"]');
            var $errorContainer = $form.find('.square-form-errors');
            
            // Clear previous errors
            $errorContainer.empty().hide();
            
            // Disable the submit button to prevent multiple submissions
            $submitButton.prop('disabled', true).addClass('square-loading');
            
            // Tokenize the card
            self.card.tokenize().then(function(result) {
                if (result.status === 'OK') {
                    // Get the token and add it to the form
                    var sourceId = result.token;
                    
                    // Submit the form data via AJAX
                    $.ajax({
                        url: square_service_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'square_update_payment_method',
                            square_source_id: sourceId,
                            security: square_service_params.nonce,
                            subscription_id: $form.find('input[name="subscription_id"]').val()
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Show success message
                                $form.html('<div class="square-success">' + response.data.message + '</div>');
                                
                                // Reload the page after 2 seconds to show updated state
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                // Show error message
                                $errorContainer.html(response.data.message).show();
                                $submitButton.prop('disabled', false).removeClass('square-loading');
                            }
                        },
                        error: function() {
                            $errorContainer.html('An error occurred while updating your payment method. Please try again.').show();
                            $submitButton.prop('disabled', false).removeClass('square-loading');
                        }
                    });
                } else {
                    // Show tokenization errors
                    $errorContainer.html(result.errors.map(function(error) {
                        return error.message;
                    }).join('<br>')).show();
                    $submitButton.prop('disabled', false).removeClass('square-loading');
                }
            }).catch(function(err) {
                $errorContainer.html('Could not process card information. Please check your card details and try again.').show();
                $submitButton.prop('disabled', false).removeClass('square-loading');
                console.error('Square Service: Tokenization failed', err);
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SquarePayments.init();
    });

})(jQuery);
