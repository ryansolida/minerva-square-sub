/**
 * Square Service JavaScript
 * 
 * Handles client-side functionality for Square payments, subscriptions,
 * and account management
 */

(function($) {
    'use strict';

    // Store Square payment objects
    let squarePayments = null;
    let squareCard = null;
    let newSquareCard = null;

    // Get application ID from localized script data
    const applicationId = square_service_params.application_id;
    const isProduction = square_service_params.is_production === '1';
    
    // Set environment based on settings
    const squareEnvironment = isProduction ? 'production' : 'sandbox';

    /**
     * Initialize Square payments when DOM is ready
     */
    $(document).ready(function() {
        // Initialize subscription form if present
        if ($('.square-subscription-form').length > 0) {
            initSubscriptionForm();
        }
        
        // Initialize payment methods management if present
        if ($('.square-payment-methods-form').length > 0) {
            initPaymentMethods();
        }
        
        // Initialize membership cancellation if present
        if ($('.square-cancel-membership').length > 0) {
            initCancellationFlow();
        }
    });

    /**
     * Initialize the subscription form
     */
    function initSubscriptionForm() {
        if (!window.Square) {
            console.error('Square.js failed to load properly');
            $('.square-form-result').html('<div class="error">Failed to load payment system. Please try again later.</div>');
            return;
        }

        try {
            // Initialize all forms with the square-payment-form class
            $('.square-payment-form').each(function() {
                const formContainer = $(this);
                const formId = formContainer.attr('id');
                const cardContainerId = '#' + formId + '-card-container';
                
                // Remove loading class
                formContainer.removeClass('square-loading');
                
                // Initialize Square payments
                squarePayments = window.Square.payments(applicationId, squareEnvironment);
                
                // Create the card payment
                squareCard = squarePayments.card();
                
                // Initialize the card element
                squareCard.attach(cardContainerId);
                
                // Handle form submission
                formContainer.find('form').on('submit', handleSubscriptionSubmit);
            });
        } catch (error) {
            console.error('Error initializing Square payments:', error);
            $('.square-form-result').html('<div class="error">Failed to initialize payment system: ' + error.message + '</div>');
        }
    }

    /**
     * Handle subscription form submission
     */
    async function handleSubscriptionSubmit(event) {
        event.preventDefault();
        
        const form = $(event.target);
        const formContainer = form.closest('.square-payment-form');
        const formId = formContainer.attr('id');
        
        // Disable submit button to prevent double submission
        const submitButton = form.find('button[type="submit"]');
        const originalButtonText = submitButton.text();
        submitButton.prop('disabled', true).text('Processing...');
        
        // Clear any previous errors
        formContainer.find('.square-form-errors').hide().empty();
        formContainer.find('.square-form-result').hide().empty();
        
        try {
            const result = await squareCard.tokenize();
            if (result.status === 'OK') {
                // Send tokenized card info to server
                submitTokenToServer(result.token, form, formContainer);
            } else {
                // Display error
                showFormError(formContainer, result.errors[0].message);
                submitButton.prop('disabled', false).text(originalButtonText);
            }
        } catch (error) {
            console.error('Error tokenizing card:', error);
            showFormError(formContainer, 'An unexpected error occurred. Please try again.');
            submitButton.prop('disabled', false).text(originalButtonText);
        }
    }

    /**
     * Show error message in the form
     */
    function showFormError(formContainer, message) {
        const errorContainer = formContainer.find('.square-form-errors');
        errorContainer.html('<div class="square-error">' + message + '</div>').show();
    }

    /**
     * Submit the tokenized card to the server to create subscription
     */
    function submitTokenToServer(token, form, formContainer) {
        const submitButton = form.find('button[type="submit"]');
        const originalButtonText = submitButton.text();
        const resultContainer = formContainer.find('.square-form-result');
        
        $.ajax({
            url: square_service_params.ajax_url,
            type: 'POST',
            data: {
                action: 'square_subscribe',
                sourceId: token,
                nonce: $('#square_nonce', form).val(),
                plan_id: $('input[name="plan_id"]', form).val(),
                redirect_url: $('input[name="redirect_url"]', form).val(),
                name: $('input[name="card_name"]', form).val(),
                email: $('input[name="card_email"]', form).val(),
                form_id: $('input[name="form_id"]', form).val()
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    resultContainer.html('<div class="square-success">' + response.data.message + '</div>').show();
                    
                    // Disable form elements
                    form.find('input, button').prop('disabled', true);
                    
                    // Redirect if URL provided
                    if (response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 2000);
                    }
                } else {
                    // Show error message
                    resultContainer.html('<div class="square-error">' + response.data.message + '</div>').show();
                    submitButton.prop('disabled', false).text(originalButtonText);
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr);
                resultContainer.html('<div class="square-error">An error occurred. Please try again later.</div>').show();
                submitButton.prop('disabled', false).text(originalButtonText);
            }
        });
    }

    /**
     * Initialize payment methods management
     */
    function initPaymentMethods() {
        // Handle "Add New Card" button
        $('#add-card-button').on('click', function() {
            initCardForm();
            $('#add-card-form').show();
            $(this).hide();
        });
        
        // Handle cancel button for adding card
        $('#add-card-cancel').on('click', function() {
            $('#add-card-form').hide();
            $('#add-card-button').show();
        });
        
        // Handle delete card buttons
        $('.delete-card-btn').on('click', function() {
            const cardId = $(this).data('card-id');
            deleteCard(cardId);
        });
    }

    /**
     * Initialize the new card form
     */
    function initCardForm() {
        if (!window.Square) {
            console.error('Square.js failed to load properly');
            $('#payment-methods-result').html('<div class="error">Failed to load payment system. Please try again later.</div>');
            return;
        }

        try {
            if (!squarePayments) {
                // Initialize Square payments if not already done
                squarePayments = window.Square.payments(applicationId, squareEnvironment);
            }
            
            // Create a new card instance for the add card form
            newSquareCard = squarePayments.card();
            
            // Initialize the new card element
            newSquareCard.attach('#new-card-container');
            
            // Handle form submission
            $('#square-add-card-form').on('submit', handleAddCardSubmit);
        } catch (error) {
            console.error('Error initializing Square card form:', error);
            $('#new-card-errors').text('Failed to initialize payment form: ' + error.message);
        }
    }

    /**
     * Handle add card form submission
     */
    async function handleAddCardSubmit(event) {
        event.preventDefault();
        
        // Disable submit button to prevent double submission
        const submitButton = $('#add-card-submit');
        submitButton.prop('disabled', true).text('Processing...');
        
        // Clear any previous errors
        $('#new-card-errors').text('');
        $('#payment-methods-result').html('');
        
        try {
            const result = await newSquareCard.tokenize();
            if (result.status === 'OK') {
                // Send tokenized card info to server
                addCardToServer(result.token);
            } else {
                // Display error
                $('#new-card-errors').text(result.errors[0].message);
                submitButton.prop('disabled', false).text('Save Card');
            }
        } catch (error) {
            console.error('Error tokenizing card:', error);
            $('#new-card-errors').text('An unexpected error occurred. Please try again.');
            submitButton.prop('disabled', false).text('Save Card');
        }
    }

    /**
     * Add card to server via AJAX
     */
    function addCardToServer(token) {
        $.ajax({
            url: square_service_params.ajax_url,
            type: 'POST',
            data: {
                action: 'square_update_payment',
                card_action: 'add',
                sourceId: token,
                nonce: square_service_params.nonce,
                card_name: $('#new-card-name').val()
            },
            success: function(response) {
                if (response.success) {
                    // Refresh the page to show updated card list
                    location.reload();
                } else {
                    $('#payment-methods-result').html('<div class="error">' + response.data.message + '</div>');
                    $('#add-card-submit').prop('disabled', false).text('Save Card');
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr);
                $('#payment-methods-result').html('<div class="error">An error occurred. Please try again later.</div>');
                $('#add-card-submit').prop('disabled', false).text('Save Card');
            }
        });
    }

    /**
     * Delete card via AJAX
     */
    function deleteCard(cardId) {
        if (!confirm('Are you sure you want to remove this payment method?')) {
            return;
        }
        
        $.ajax({
            url: square_service_params.ajax_url,
            type: 'POST',
            data: {
                action: 'square_update_payment',
                card_action: 'delete',
                card_id: cardId,
                nonce: square_service_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove the card from the list
                    $('li.card-item[data-card-id="' + cardId + '"]').fadeOut(300, function() {
                        $(this).remove();
                        
                        // If no cards left, show the no cards message
                        if ($('.card-list li').length === 0) {
                            $('.card-list').replaceWith('<p class="no-cards-message">You have no payment methods on file</p>');
                        }
                    });
                    
                    $('#payment-methods-result').html('<div class="success">' + response.data.message + '</div>');
                } else {
                    $('#payment-methods-result').html('<div class="error">' + response.data.message + '</div>');
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr);
                $('#payment-methods-result').html('<div class="error">An error occurred. Please try again later.</div>');
            }
        });
    }

    /**
     * Initialize membership cancellation flow
     */
    function initCancellationFlow() {
        // Show confirmation when cancel button is clicked
        $('#cancel-membership-btn').on('click', function() {
            $(this).hide();
            $('#cancel-confirmation').show();
        });
        
        // Handle cancel cancellation
        $('#cancel-cancel-btn').on('click', function() {
            $('#cancel-confirmation').hide();
            $('#cancel-membership-btn').show();
        });
        
        // Handle confirm cancellation
        $('#confirm-cancel-btn').on('click', function() {
            const subscriptionId = $(this).data('subscription-id');
            cancelSubscription(subscriptionId);
        });
    }

    /**
     * Cancel subscription via AJAX
     */
    function cancelSubscription(subscriptionId) {
        // Disable confirm button to prevent double submission
        const confirmButton = $('#confirm-cancel-btn');
        confirmButton.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: square_service_params.ajax_url,
            type: 'POST',
            data: {
                action: 'square_cancel_subscription',
                subscription_id: subscriptionId,
                nonce: square_service_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Hide confirmation and show success
                    $('#cancel-confirmation').hide();
                    $('#cancel-result').html('<div class="success">' + response.data.message + '</div>');
                    
                    // Refresh the page after a delay
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#cancel-result').html('<div class="error">' + response.data.message + '</div>');
                    confirmButton.prop('disabled', false).text('Yes, Cancel Membership');
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr);
                $('#cancel-result').html('<div class="error">An error occurred. Please try again later.</div>');
                confirmButton.prop('disabled', false).text('Yes, Cancel Membership');
            }
        });
    }

})(jQuery);
