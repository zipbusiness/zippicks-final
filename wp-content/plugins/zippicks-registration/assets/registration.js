/**
 * ZipPicks Registration JavaScript
 * File: assets/registration.js
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle form submission
        $('#zippicks-registration-form').on('submit', function(e) {
            e.preventDefault();
            
            // Clear previous errors
            clearErrors();
            
            // Validate form
            var validation = validateForm();
            if (!validation.valid) {
                displayErrors(validation.errors);
                return;
            }
            
            // Show loading state
            setLoadingState(true);
            
            // Prepare form data
            var formData = new FormData(this);
            formData.append('action', 'zippicks_register');
            
            // Submit form
            $.ajax({
                url: zippicks_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    setLoadingState(false);
                    
                    if (response.success) {
                        displayMessage(response.data.message, 'success');
                        
                        // CHECK FOR REDIRECT - THIS WAS MISSING!
                        if (response.data.redirect) {
                            // Show success message briefly, then redirect
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500); // 1.5 second delay to show success message
                        } else {
                            // If no redirect, reset form (fallback)
                            $('#zippicks-registration-form')[0].reset();
                            
                            // Reset reCAPTCHA
                            if (typeof grecaptcha !== 'undefined') {
                                grecaptcha.reset();
                            }
                        }
                    } else {
                        displayMessage(response.data.message, 'error');
                        if (response.data.errors) {
                            displayErrors(response.data.errors);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    setLoadingState(false);
                    displayMessage('An error occurred. Please try again.', 'error');
                    console.error('Registration error:', error);
                }
            });
        });
        
        // Real-time validation
        $('#zp_email').on('blur', function() {
            validateEmail($(this).val());
        });
        
        $('#zp_username').on('blur', function() {
            validateUsername($(this).val());
        });
        
        $('#zp_zip').on('blur', function() {
            validateZip($(this).val());
        });
        
        $('#zp_confirm_password').on('blur keyup', function() {
            validatePasswordMatch();
        });
        
        $('#zp_password').on('keyup', function() {
            validatePasswordStrength($(this).val());
            validatePasswordMatch();
        });
    });
    
    // Check for verification messages
    $(window).on('load', function() {
        var urlParams = new URLSearchParams(window.location.search);
        var message = urlParams.get('zp_message');
        var type = urlParams.get('zp_type');
        
        if (message && type) {
            displayMessage(decodeURIComponent(message), type);
            
            // Clean URL
            var cleanUrl = window.location.pathname + window.location.hash;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    });
    
    function validateForm() {
        var errors = {};
        var valid = true;
        
        // First name
        var firstName = $('#zp_first_name').val().trim();
        if (!firstName) {
            errors.first_name = 'First name is required';
            valid = false;
        } else if (firstName.length > 100) {
            errors.first_name = 'First name must be less than 100 characters';
            valid = false;
        }
        
        // Last name
        var lastName = $('#zp_last_name').val().trim();
        if (!lastName) {
            errors.last_name = 'Last name is required';
            valid = false;
        } else if (lastName.length > 100) {
            errors.last_name = 'Last name must be less than 100 characters';
            valid = false;
        }
        
        // Username
        var username = $('#zp_username').val().trim();
        if (!username) {
            errors.username = 'Username is required';
            valid = false;
        } else if (username.length < 3) {
            errors.username = 'Username must be at least 3 characters';
            valid = false;
        } else if (username.length > 20) {
            errors.username = 'Username must be less than 20 characters';
            valid = false;
        } else if (!/^[a-z0-9_]+$/.test(username)) {
            errors.username = 'Username can only contain lowercase letters, numbers, and underscores';
            valid = false;
        }
        
        // Email
        var email = $('#zp_email').val().trim();
        if (!email) {
            errors.email = 'Email address is required';
            valid = false;
        } else if (!isValidEmail(email)) {
            errors.email = 'Please enter a valid email address';
            valid = false;
        }
        
        // ZIP code
        var zip = $('#zp_zip').val().trim();
        if (!zip) {
            errors.zip = 'ZIP code is required';
            valid = false;
        } else if (!/^\d{5}(-\d{4})?$/.test(zip)) {
            errors.zip = 'Please enter a valid ZIP code (e.g., 12345 or 12345-6789)';
            valid = false;
        }
        
        // Password
        var password = $('#zp_password').val();
        var confirmPassword = $('#zp_confirm_password').val();
        
        if (!password) {
            errors.password = 'Password is required';
            valid = false;
        } else if (password.length < 8) {
            errors.password = 'Password must be at least 8 characters long';
            valid = false;
        } else if (!isStrongPassword(password)) {
            errors.password = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
            valid = false;
        }
        
        if (password !== confirmPassword) {
            errors.confirm_password = 'Passwords do not match';
            valid = false;
        }
        
        // User Type
        var userType = $('#zp_user_type').val();
        if (!userType) {
            errors.user_type = 'Please select your user type';
            valid = false;
        }
        
        // reCAPTCHA
        if (typeof grecaptcha !== 'undefined') {
            var recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse) {
                errors.recaptcha = 'Please complete the reCAPTCHA verification';
                valid = false;
            }
        }
        
        return { valid: valid, errors: errors };
    }
    
    function validateEmail(email) {
        var errorElement = $('#zp_email_error');
        if (!email) {
            errorElement.text('Email address is required');
            return false;
        } else if (!isValidEmail(email)) {
            errorElement.text('Please enter a valid email address');
            return false;
        } else {
            errorElement.text('');
            return true;
        }
    }
    
    function validateUsername(username) {
        var errorElement = $('#zp_username_error');
        if (!username) {
            errorElement.text('Username is required');
            return false;
        } else if (username.length < 3) {
            errorElement.text('Username must be at least 3 characters');
            return false;
        } else if (username.length > 20) {
            errorElement.text('Username must be less than 20 characters');
            return false;
        } else if (!/^[a-z0-9_]+$/.test(username)) {
            errorElement.text('Username can only contain lowercase letters, numbers, and underscores');
            return false;
        } else {
            errorElement.text('');
            return true;
        }
    }
    
    function validateZip(zip) {
        var errorElement = $('#zp_zip_error');
        if (!zip) {
            errorElement.text('ZIP code is required');
            return false;
        } else if (!/^\d{5}(-\d{4})?$/.test(zip)) {
            errorElement.text('Please enter a valid ZIP code (e.g., 12345 or 12345-6789)');
            return false;
        } else {
            errorElement.text('');
            return true;
        }
    }
    
    function validatePasswordStrength(password) {
        var errorElement = $('#zp_password_error');
        if (!password) {
            errorElement.text('Password is required');
            return false;
        } else if (password.length < 8) {
            errorElement.text('Password must be at least 8 characters long');
            return false;
        } else if (!isStrongPassword(password)) {
            errorElement.text('Password must contain at least one uppercase letter, one lowercase letter, and one number');
            return false;
        } else {
            errorElement.text('');
            return true;
        }
    }
    
    function validatePasswordMatch() {
        var password = $('#zp_password').val();
        var confirmPassword = $('#zp_confirm_password').val();
        var errorElement = $('#zp_confirm_password_error');
        
        if (confirmPassword && password !== confirmPassword) {
            errorElement.text('Passwords do not match');
            return false;
        } else {
            errorElement.text('');
            return true;
        }
    }
    
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function isStrongPassword(password) {
        // Check for at least one uppercase, one lowercase, and one number
        var hasUpper = /[A-Z]/.test(password);
        var hasLower = /[a-z]/.test(password);
        var hasNumber = /\d/.test(password);
        return hasUpper && hasLower && hasNumber;
    }
    
    function clearErrors() {
        $('.zip-error').text('');
        $('#zippicks-messages').empty();
    }
    
    function displayErrors(errors) {
        $.each(errors, function(field, message) {
            $('#zp_' + field + '_error').text(message);
        });
    }
    
    function displayMessage(message, type) {
        var messageHtml = '<div class="zip-message ' + type + '">' + message + '</div>';
        $('#zippicks-messages').html(messageHtml);
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $('#zippicks-messages').offset().top - 20
        }, 500);
        
        // Auto-hide success messages after 5 seconds (but only if no redirect)
        if (type === 'success') {
            setTimeout(function() {
                $('#zippicks-messages').fadeOut();
            }, 5000);
        }
    }
    
    function setLoadingState(loading) {
        var submitBtn = $('#zp-submit-btn');
        var btnText = submitBtn.find('.zip-btn-text');
        var spinner = submitBtn.find('.zip-spinner');
        
        if (loading) {
            submitBtn.prop('disabled', true);
            btnText.hide();
            spinner.show();
        } else {
            submitBtn.prop('disabled', false);
            btnText.show();
            spinner.hide();
        }
    }
    
})(jQuery);