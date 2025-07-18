/**
 * ZipPicks Master Critic Admin JavaScript
 */

(function($) {
    'use strict';

    // Cache DOM elements
    const $form = $('#master-critic-form');
    const $categorySelect = $('#business_category');
    const $generateBtn = $('#generate-btn');
    const $promptSection = $('#prompt-section');
    const $promptTextarea = $('#generated-prompt');
    const $enableEditingBtn = $('#enable-editing');
    const $resetPromptBtn = $('#reset-prompt');
    const $executePromptBtn = $('#execute-prompt');
    const $saveTemplateBtn = $('#save-template');
    const $resultsSection = $('#results-section');
    const $aiResponse = $('#ai-response');
    const $testModeCheckbox = $('#test_mode');
    const $categoryInfo = $('.category-info');
    const $categoryPillars = $('#category-pillars');

    // Store original prompt for reset functionality
    let originalPrompt = '';
    let currentGenerationId = null;
    let currentBusinesses = [];
    let currentProvider = '';
    let currentCategory = '';
    let promptGenerationId = null; // Store generation ID from prompt generation

    // Initialize
    $(document).ready(function() {
        // Check if localized data is available
        if (typeof zippicks_master_critic === 'undefined') {
            console.error('ZipPicks Master Critic: Localized data not found. Script may not be loaded correctly.');
            showNotice('JavaScript initialization error. Please refresh the page.', 'error');
            return;
        }
        
        // Set default AI provider from localized data
        if (zippicks_master_critic.default_provider) {
            $('#ai_provider').val(zippicks_master_critic.default_provider);
        }

        // Bind events
        bindEvents();
    });

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Form submission
        $form.on('submit', handleFormSubmit);

        // Category change
        $categorySelect.on('change', handleCategoryChange);

        // Prompt editing
        $enableEditingBtn.on('click', enablePromptEditing);
        $resetPromptBtn.on('click', resetPrompt);

        // Execute prompt
        $executePromptBtn.on('click', executePrompt);

        // Save template
        $saveTemplateBtn.on('click', showSaveTemplateModal);

        // Create businesses
        $('#create-businesses').on('click', createBusinesses);
        
        // Create list
        $('#create-list').on('click', createList);

        // Modal events
        $('.cancel-modal').on('click', hideModals);
        $('#save-template-form').on('submit', saveTemplate);

        // Use event delegation for dynamically loaded elements
        $(document).on('click', '.view-generation', viewGeneration);
        $(document).on('click', '.edit-template', editTemplate);
        $(document).on('click', '.delete-template', deleteTemplate);
        
        // Test API button (on settings page)
        $('#test-zipbusiness-api').on('click', testZipBusinessAPI);
    }

    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();

        // Validate form
        if (!validateForm()) {
            return;
        }

        // Show loading state
        $generateBtn.prop('disabled', true).find('.dashicons').removeClass('dashicons-admin-generic').addClass('dashicons-update spinning');

        // Prepare data
        const formData = {
            action: 'zippicks_generate_prompt',
            nonce: zippicks_master_critic.nonce,
            business_category: $('#business_category').val(),
            topic: $('#topic').val(),
            location: $('#location').val(),
            search_type: $('#search_type').val(),
            list_category: $('#list_category').val()
        };

        // Generate prompt via AJAX
        $.post(zippicks_master_critic.ajax_url, formData, function(response) {
            if (response.success) {
                // Store prompt and generation ID
                originalPrompt = response.data.prompt;
                currentCategory = $('#business_category').val();
                promptGenerationId = response.data.generation_id;

                // Display prompt
                $promptTextarea.val(originalPrompt);
                $promptSection.slideDown();

                // Check test mode
                if ($testModeCheckbox.is(':checked')) {
                    showNotice('Test mode enabled. Prompt generated but AI will not be called.', 'info');
                }

                // Scroll to prompt
                $('html, body').animate({
                    scrollTop: $promptSection.offset().top - 50
                }, 500);
            } else {
                let errorMessage = response.data.message || 'Unknown error';
                
                // Check if it's an API-related error
                if (response.data.api_required) {
                    errorMessage += ' Please check the ZipBusiness API settings.';
                    
                    // Show settings link
                    if (zippicks_master_critic.settings_url) {
                        errorMessage += ' <a href="' + zippicks_master_critic.settings_url + '">Go to Settings</a>';
                    }
                }
                
                showNotice('Error generating prompt: ' + errorMessage, 'error');
            }
        }).fail(function() {
            showNotice('Failed to generate prompt. Please try again.', 'error');
        }).always(function() {
            // Reset button state
            $generateBtn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update spinning').addClass('dashicons-admin-generic');
        });
    }

    /**
     * Handle category change
     */
    function handleCategoryChange() {
        const category = $(this).val();
        
        if (category && zippicks_master_critic.business_categories[category]) {
            const categoryData = zippicks_master_critic.business_categories[category];
            
            // Update category info widget
            let pillarsHtml = '<ol>';
            for (const [key, label] of Object.entries(categoryData.pillars)) {
                pillarsHtml += '<li>' + label + '</li>';
            }
            pillarsHtml += '</ol>';
            
            $categoryPillars.html(pillarsHtml);
            $categoryInfo.show();
        } else {
            $categoryInfo.hide();
        }
    }

    /**
     * Enable prompt editing
     */
    function enablePromptEditing() {
        $promptTextarea.prop('readonly', false).addClass('editing');
        $enableEditingBtn.hide();
        $resetPromptBtn.show();
        showNotice('Prompt editing enabled. Make your changes and execute when ready.', 'info');
    }

    /**
     * Reset prompt to original
     */
    function resetPrompt() {
        $promptTextarea.val(originalPrompt).prop('readonly', true).removeClass('editing');
        $enableEditingBtn.show();
        $resetPromptBtn.hide();
        showNotice('Prompt reset to original generated version.', 'success');
    }

    /**
     * Execute AI generation
     */
    function executePrompt() {
        // Check test mode
        if ($testModeCheckbox.is(':checked')) {
            showNotice('Test mode is enabled. Please uncheck to execute AI generation.', 'warning');
            return;
        }

        // Confirm execution
        if (!confirm(zippicks_master_critic.strings.confirm_execute)) {
            return;
        }

        // Show loading state with animation
        $executePromptBtn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update spinning"></span> ' + zippicks_master_critic.strings.executing + ' (This may take up to 2 minutes...)');
        
        // Prepare data
        const formData = {
            action: 'zippicks_execute_ai_generation',
            nonce: zippicks_master_critic.nonce,
            prompt: $promptTextarea.val(),
            ai_provider: $('#ai_provider').val(),
            business_category: $('#business_category').val(),
            topic: $('#topic').val(),
            location: $('#location').val(),
            search_type: $('#search_type').val(),
            list_category: $('#list_category').val(),
            generation_id: promptGenerationId // Pass the generation ID
        };

        currentProvider = formData.ai_provider;

        // Execute via AJAX with extended timeout
        $.ajax({
            url: zippicks_master_critic.ajax_url,
            type: 'POST',
            data: formData,
            timeout: 150000, // 2.5 minutes timeout (slightly longer than server timeout)
            success: function(response) {
            if (response.success) {
                // Store data
                currentGenerationId = response.data.generation_id;
                currentBusinesses = response.data.businesses || [];
                
                // Display response
                displayAIResponse(response.data);
                
                // Show results section
                $resultsSection.slideDown();
                
                // Scroll to results
                $('html, body').animate({
                    scrollTop: $resultsSection.offset().top - 50
                }, 500);
            } else {
                let errorMsg = 'AI Error: ' + (response.data.error || response.data.message || 'Unknown error');
                
                // Add technical details for debugging
                if (response.data.http_code) {
                    errorMsg += '<br><strong>HTTP Code:</strong> ' + response.data.http_code;
                }
                if (response.data.wp_error_code) {
                    errorMsg += '<br><strong>WP Error:</strong> ' + response.data.wp_error_code;
                }
                if (response.data.execution_time) {
                    errorMsg += '<br><strong>Execution Time:</strong> ' + response.data.execution_time + ' seconds';
                }
                
                // Check if it's an API-related error
                if (response.data.api_required) {
                    errorMsg = '<strong>ZipBusiness API Required:</strong> ' + errorMsg;
                    errorMsg += '<br><br>The Master Critic requires real restaurant data from the ZipBusiness API to ensure the highest quality recommendations.';
                    
                    if (response.data.api_error) {
                        errorMsg += '<br><br><strong>API Error:</strong> ' + response.data.api_error;
                    }
                    
                    errorMsg += '<br><br><a href="' + zippicks_master_critic.settings_url + '" class="button button-primary">Check API Settings</a>';
                }
                // Add suggestion if available
                else if (response.data.suggestion) {
                    errorMsg += '<br><br><strong>Suggestion:</strong> ' + response.data.suggestion;
                }
                
                // Add link to settings if it's a model issue
                else if (response.data.message && response.data.message.includes('model')) {
                    errorMsg += '<br><br><a href="' + zippicks_master_critic.settings_url + '" class="button button-small">Go to Settings</a>';
                }
                
                // Common troubleshooting for specific errors
                if (response.data.message && response.data.message.includes('API key')) {
                    errorMsg += '<br><br><strong>Solution:</strong> Please check your API key in <a href="' + zippicks_master_critic.settings_url + '">Settings</a>';
                } else if (response.data.http_code === 429) {
                    errorMsg += '<br><br><strong>Solution:</strong> Rate limit exceeded. Please wait a few minutes before trying again.';
                } else if (response.data.http_code >= 500) {
                    errorMsg += '<br><br><strong>Solution:</strong> The AI service is experiencing issues. Please try again later.';
                }
                
                showNotice(errorMsg, 'error');
            }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showNotice('The request timed out. This can happen with complex queries. Try reducing the number of businesses requested or using a faster model like GPT-4 Turbo.', 'error');
                } else {
                    showNotice('Failed to execute AI generation. Please check your API keys and try again. Error: ' + error, 'error');
                }
            },
            complete: function() {
                // Reset button
                $executePromptBtn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Execute AI Generation');
            }
        });
    }

    /**
     * Display AI response
     */
    function displayAIResponse(data) {
        // Update provider badge
        const providerName = data.provider === 'anthropic' ? 'Claude' : 'GPT-4';
        $('.provider-badge').text('Generated by ' + providerName).show();
        
        // Show cache badge if cached
        if (data.cached) {
            $('.cache-badge').show();
        } else {
            $('.cache-badge').hide();
        }
        
        // Display confidence score
        if (data.confidence !== undefined) {
            let confidenceClass = 'high';
            if (data.confidence < 70) confidenceClass = 'low';
            else if (data.confidence < 85) confidenceClass = 'medium';
            
            let confidenceHtml = '<div class="confidence-score ' + confidenceClass + '">';
            confidenceHtml += '<strong>Confidence Score:</strong> ' + data.confidence.toFixed(1) + '%';
            confidenceHtml += '</div>';
            
            // Add validation warnings if any
            if (data.validation_report && data.validation_report.warnings && data.validation_report.warnings.length > 0) {
                confidenceHtml += '<div class="validation-warnings">';
                confidenceHtml += '<strong>Validation Warnings:</strong>';
                confidenceHtml += '<ul>';
                data.validation_report.warnings.forEach(function(warning) {
                    confidenceHtml += '<li>' + escapeHtml(warning) + '</li>';
                });
                confidenceHtml += '</ul>';
                confidenceHtml += '</div>';
            }
            
            $aiResponse.before(confidenceHtml);
        }
        
        // Display formatted response
        if (data.businesses && data.businesses.length > 0) {
            let html = '<div class="businesses-grid">';
            
            data.businesses.forEach(function(business, index) {
                html += formatBusinessCard(business, index + 1);
            });
            
            html += '</div>';
            html += '<div class="raw-response"><h4>Raw Response:</h4><pre>' + escapeHtml(data.response) + '</pre></div>';
            
            $aiResponse.html(html);
            $('.results-actions').show();
        } else {
            // Try to parse the response manually if businesses array is empty
            let parsedBusinesses = tryParseBusinesses(data.response);
            
            if (parsedBusinesses && parsedBusinesses.length > 0) {
                // We successfully parsed it client-side
                currentBusinesses = parsedBusinesses;
                
                let html = '<div class="businesses-grid">';
                parsedBusinesses.forEach(function(business, index) {
                    html += formatBusinessCard(business, index + 1);
                });
                html += '</div>';
                html += '<div class="raw-response"><h4>Raw Response:</h4><pre>' + escapeHtml(data.response) + '</pre></div>';
                
                $aiResponse.html(html);
                $('.results-actions').show();
            } else {
                // Show raw response
                $aiResponse.html('<pre>' + escapeHtml(data.response) + '</pre>');
                
                // Check if response looks like it contains valid JSON
                if (data.response.includes('"rank"') && data.response.includes('"name"')) {
                    // Show actions anyway since it looks like valid data
                    $('.results-actions').show();
                } else {
                    $('.results-actions').hide();
                }
            }
        }
    }

    /**
     * Format business card
     */
    function formatBusinessCard(business, rank) {
        let html = '<div class="business-card">';
        html += '<div class="business-header">';
        html += '<span class="rank">#' + rank + '</span>';
        html += '<h3>' + escapeHtml(business.name) + '</h3>';
        html += '<span class="score">' + business.score + '</span>';
        html += '</div>';
        
        html += '<div class="business-meta">';
        html += '<span class="price">' + business.price_tier + '</span>';
        html += '<span class="reviews">' + business.review_count + ' reviews</span>';
        html += '</div>';
        
        html += '<p class="summary">' + escapeHtml(business.summary) + '</p>';
        
        // Top features/dishes
        const topItems = business.top_dishes || business.top_features;
        if (topItems && topItems.length > 0) {
            const label = business.top_dishes ? 'Top Dishes' : 'Top Features';
            html += '<div class="features"><strong>' + label + ':</strong> ';
            html += topItems.map(f => escapeHtml(f)).join(', ');
            html += '</div>';
        }
        
        // Vibes
        if (business.vibes && business.vibes.length > 0) {
            html += '<div class="vibes">';
            business.vibes.forEach(function(vibe) {
                html += '<span class="vibe-tag">' + escapeHtml(vibe) + '</span>';
            });
            html += '</div>';
        }
        
        // Pillar scores
        if (business.pillar_scores) {
            html += '<div class="pillar-scores">';
            html += '<h4>Scores:</h4>';
            html += '<div class="scores-grid">';
            for (const [pillar, score] of Object.entries(business.pillar_scores)) {
                const label = getPillarLabel(pillar);
                html += '<div class="score-item">';
                html += '<span class="label">' + label + ':</span>';
                html += '<span class="value">' + score + '</span>';
                html += '</div>';
            }
            html += '</div>';
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }

    /**
     * Get pillar label
     */
    function getPillarLabel(pillar) {
        const category = currentCategory;
        if (zippicks_master_critic.business_categories[category] && 
            zippicks_master_critic.business_categories[category].pillars[pillar]) {
            return zippicks_master_critic.business_categories[category].pillars[pillar];
        }
        return pillar.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    /**
     * Create businesses
     */
    function createBusinesses() {
        if (!currentBusinesses || currentBusinesses.length === 0) {
            showNotice('No businesses to create.', 'warning');
            return;
        }

        // Confirm
        if (!confirm('Create ' + currentBusinesses.length + ' business pages?')) {
            return;
        }

        // Show loading
        $(this).prop('disabled', true).text('Creating businesses...');

        // Prepare data
        const formData = {
            action: 'zippicks_create_businesses',
            nonce: zippicks_master_critic.nonce,
            generation_id: currentGenerationId,
            businesses: JSON.stringify(currentBusinesses),
            ai_provider: currentProvider,
            business_category: currentCategory
        };

        // Create via AJAX
        $.post(zippicks_master_critic.ajax_url, formData, function(response) {
            if (response.success) {
                showNotice(response.data.message, 'success');
                $('#create-businesses').text('Businesses Created').prop('disabled', true);
            } else {
                showNotice('Error: ' + (response.data.message || 'Failed to create businesses'), 'error');
            }
        }).fail(function() {
            showNotice('Failed to create businesses. Please try again.', 'error');
        }).always(function() {
            $('#create-businesses').prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Create Business Pages');
        });
    }

    /**
     * Show save template modal
     */
    function showSaveTemplateModal() {
        $('#save-template-modal').show();
        $('#template_name').focus();
    }

    /**
     * Save template
     */
    function saveTemplate(e) {
        e.preventDefault();

        const formData = {
            action: 'zippicks_save_prompt_template',
            nonce: zippicks_master_critic.nonce,
            name: $('#template_name').val(),
            business_category: currentCategory,
            prompt_template: $promptTextarea.val(),
            is_default: $('[name="is_default"]').is(':checked') ? 1 : 0
        };

        $.post(zippicks_master_critic.ajax_url, formData, function(response) {
            if (response.success) {
                showNotice(response.data.message, 'success');
                hideModals();
                $('#template_name').val('');
                $('[name="is_default"]').prop('checked', false);
            } else {
                showNotice('Error: ' + (response.data.message || 'Failed to save template'), 'error');
            }
        });
    }

    /**
     * Create list
     */
    function createList() {
        if (!currentBusinesses || currentBusinesses.length === 0) {
            showNotice('No businesses to create a list from.', 'warning');
            return;
        }

        // Confirm
        if (!confirm('Create a Top 10 list from these ' + currentBusinesses.length + ' businesses?')) {
            return;
        }

        // Show loading
        $(this).prop('disabled', true).text('Creating list...');

        // Prepare data
        const formData = {
            action: 'zippicks_create_list',
            nonce: zippicks_master_critic.nonce,
            generation_id: currentGenerationId,
            businesses: JSON.stringify(currentBusinesses),
            ai_provider: currentProvider,
            business_category: currentCategory,
            topic: $('#topic').val(),
            location: $('#location').val(),
            list_category: $('#list_category').val()
        };

        // Create via AJAX
        $.post(zippicks_master_critic.ajax_url, formData, function(response) {
            if (response.success) {
                showNotice(response.data.message, 'success');
                
                // Show edit link
                const editLink = '<a href="' + response.data.edit_url + '" target="_blank">Edit List</a>';
                const viewLink = '<a href="' + response.data.list_url + '" target="_blank">View List</a>';
                showNotice('List created! ' + editLink + ' | ' + viewLink, 'success');
                
                // Disable button
                $('#create-list').text('List Created').prop('disabled', true);
            } else {
                showNotice('Error: ' + (response.data.message || 'Failed to create list'), 'error');
            }
        }).fail(function() {
            showNotice('Failed to create list. Please try again.', 'error');
        }).always(function() {
            $('#create-list').prop('disabled', false).html('<span class="dashicons dashicons-list-view"></span> Create List Page');
        });
    }

    /**
     * View generation details
     */
    function viewGeneration(e) {
        e.preventDefault();
        const $link = $(this);
        const id = $link.data('id');
        
        // Show loading state
        $link.text('Loading...');
        
        // Fetch generation details via AJAX
        $.post(zippicks_master_critic.ajax_url, {
            action: 'zippicks_view_generation',
            nonce: zippicks_master_critic.nonce,
            generation_id: id
        }, function(response) {
            if (response.success) {
                // Create modal to display details
                const modal = createGenerationModal(response.data);
                $('body').append(modal);
                
                // Bind close button
                $('#generation-details-modal .close-modal, #generation-details-modal .cancel-modal').on('click', function() {
                    $('#generation-details-modal').remove();
                });
                
                // Allow clicking outside modal to close
                $('#generation-details-modal').on('click', function(e) {
                    if ($(e.target).hasClass('modal')) {
                        $('#generation-details-modal').remove();
                    }
                });
            } else {
                showNotice('Error loading generation details: ' + (response.data.message || 'Unknown error'), 'error');
            }
        }).fail(function() {
            showNotice('Failed to load generation details. Please try again.', 'error');
        }).always(function() {
            $link.text('View');
        });
    }
    
    /**
     * Create generation details modal
     */
    function createGenerationModal(generation) {
        let html = '<div id="generation-details-modal" class="modal" style="display:block;">';
        html += '<div class="modal-content">';
        html += '<span class="close-modal">&times;</span>';
        html += '<h2>Generation Details</h2>';
        
        // Basic info
        html += '<div class="generation-info">';
        html += '<p><strong>ID:</strong> ' + generation.id + '</p>';
        html += '<p><strong>Category:</strong> ' + escapeHtml(generation.business_category) + '</p>';
        html += '<p><strong>Topic:</strong> ' + escapeHtml(generation.topic) + '</p>';
        html += '<p><strong>Location:</strong> ' + escapeHtml(generation.location) + '</p>';
        html += '<p><strong>Provider:</strong> ' + escapeHtml(generation.ai_provider.toUpperCase()) + '</p>';
        html += '<p><strong>Status:</strong> <span class="status-' + generation.status + '">' + escapeHtml(generation.status) + '</span></p>';
        html += '<p><strong>Created:</strong> ' + escapeHtml(generation.created_at) + '</p>';
        
        if (generation.businesses_created > 0) {
            html += '<p><strong>Businesses Created:</strong> ' + generation.businesses_created + '</p>';
        }
        
        if (generation.list_id) {
            html += '<p><strong>List ID:</strong> ' + generation.list_id + '</p>';
        }
        
        html += '</div>';
        
        // Prompt
        html += '<div class="generation-prompt">';
        html += '<h3>Prompt</h3>';
        html += '<textarea readonly rows="10" style="width:100%; font-family: monospace; font-size: 13px; background: #f9f9f9; border: 1px solid #ddd; padding: 10px;">' + escapeHtml(generation.prompt) + '</textarea>';
        html += '</div>';
        
        // AI Response
        if (generation.ai_response) {
            html += '<div class="generation-response">';
            html += '<h3>AI Response</h3>';
            html += '<textarea readonly rows="15" style="width:100%; font-family: monospace; font-size: 13px; background: #f9f9f9; border: 1px solid #ddd; padding: 10px;">' + escapeHtml(generation.ai_response) + '</textarea>';
            html += '</div>';
        }
        
        html += '<div class="modal-footer">';
        html += '<button class="button cancel-modal">Close</button>';
        html += '</div>';
        
        html += '</div>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Edit template
     */
    function editTemplate(e) {
        e.preventDefault();
        const $link = $(this);
        const id = $link.data('id');
        
        // Show loading state
        $link.text('Loading...');
        
        // Fetch template details via AJAX
        $.post(zippicks_master_critic.ajax_url, {
            action: 'zippicks_get_template',
            nonce: zippicks_master_critic.nonce,
            template_id: id
        }, function(response) {
            if (response.success) {
                // Create edit modal
                const modal = createEditTemplateModal(response.data);
                $('body').append(modal);
                
                // Show modal
                $('#edit-template-modal').show();
                
                // Bind form submission
                $('#edit-template-form').on('submit', function(e) {
                    e.preventDefault();
                    updateTemplate(id);
                });
                
                // Bind close button
                $('#edit-template-modal .close-modal, #edit-template-modal .cancel-modal').on('click', function() {
                    $('#edit-template-modal').remove();
                });
                
                // Allow clicking outside modal to close
                $('#edit-template-modal').on('click', function(e) {
                    if ($(e.target).hasClass('modal')) {
                        $('#edit-template-modal').remove();
                    }
                });
            } else {
                showNotice('Error loading template: ' + (response.data.message || 'Unknown error'), 'error');
            }
        }).fail(function() {
            showNotice('Failed to load template. Please try again.', 'error');
        }).always(function() {
            $link.text('Edit');
        });
    }
    
    /**
     * Create edit template modal
     */
    function createEditTemplateModal(template) {
        let html = '<div id="edit-template-modal" class="modal" style="display:none;">';
        html += '<div class="modal-content">';
        html += '<span class="close-modal">&times;</span>';
        html += '<h2>Edit Template</h2>';
        
        html += '<form id="edit-template-form">';
        html += '<p><label>Name:<br><input type="text" id="edit_template_name" value="' + escapeHtml(template.name) + '" required style="width:100%;"></label></p>';
        html += '<p><label>Category:<br><select id="edit_template_category" required style="width:100%;">';
        
        // Add category options
        for (const [key, data] of Object.entries(zippicks_master_critic.business_categories || {})) {
            const selected = key === template.business_category ? ' selected' : '';
            html += '<option value="' + key + '"' + selected + '>' + escapeHtml(data.label || key) + '</option>';
        }
        
        html += '</select></label></p>';
        html += '<p><label>Template:<br><textarea id="edit_template_content" rows="10" required style="width:100%;">' + escapeHtml(template.prompt_template) + '</textarea></label></p>';
        html += '<p><label><input type="checkbox" id="edit_template_default"' + (template.is_default == 1 ? ' checked' : '') + '> Set as default for this category</label></p>';
        
        html += '<div class="modal-footer">';
        html += '<button type="submit" class="button button-primary">Update Template</button>';
        html += '<button type="button" class="button cancel-modal">Cancel</button>';
        html += '</div>';
        
        html += '</form>';
        html += '</div>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * Update template
     */
    function updateTemplate(id) {
        const formData = {
            action: 'zippicks_update_template',
            nonce: zippicks_master_critic.nonce,
            template_id: id,
            name: $('#edit_template_name').val(),
            business_category: $('#edit_template_category').val(),
            prompt_template: $('#edit_template_content').val(),
            is_default: $('#edit_template_default').is(':checked') ? 1 : 0
        };
        
        $.post(zippicks_master_critic.ajax_url, formData, function(response) {
            if (response.success) {
                showNotice('Template updated successfully', 'success');
                $('#edit-template-modal').remove();
                
                // Refresh page to show updated data
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                showNotice('Error updating template: ' + (response.data.message || 'Unknown error'), 'error');
            }
        }).fail(function() {
            showNotice('Failed to update template. Please try again.', 'error');
        });
    }
    
    /**
     * Delete template
     */
    function deleteTemplate(e) {
        e.preventDefault();
        const $link = $(this);
        const id = $link.data('id');
        
        if (!confirm('Are you sure you want to delete this template?')) {
            return;
        }
        
        // Show loading state
        $link.text('Deleting...');
        
        $.post(zippicks_master_critic.ajax_url, {
            action: 'zippicks_delete_template',
            nonce: zippicks_master_critic.nonce,
            template_id: id
        }, function(response) {
            if (response.success) {
                showNotice('Template deleted successfully', 'success');
                
                // Remove row from table
                $link.closest('tr').fadeOut(function() {
                    $(this).remove();
                });
            } else {
                showNotice('Error deleting template: ' + (response.data.message || 'Unknown error'), 'error');
            }
        }).fail(function() {
            showNotice('Failed to delete template. Please try again.', 'error');
        }).always(function() {
            $link.text('Delete');
        });
    }

    /**
     * Hide all modals
     */
    function hideModals() {
        $('.modal').hide();
    }

    /**
     * Validate form
     */
    function validateForm() {
        const required = ['business_category', 'topic', 'location'];
        let valid = true;

        required.forEach(function(field) {
            const $field = $('#' + field);
            if (!$field.val()) {
                $field.addClass('error');
                valid = false;
            } else {
                $field.removeClass('error');
            }
        });

        if (!valid) {
            showNotice('Please fill in all required fields.', 'error');
        }

        return valid;
    }

    /**
     * Show notice
     */
    function showNotice(message, type) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto dismiss after 15 seconds (increased from 5)
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 15000);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Try to parse businesses from response
     */
    function tryParseBusinesses(response) {
        try {
            // First try direct parsing
            let parsed = JSON.parse(response);
            if (Array.isArray(parsed)) {
                return parsed;
            }
            
            // If it's an object with businesses array
            if (parsed.businesses && Array.isArray(parsed.businesses)) {
                return parsed.businesses;
            }
        } catch (e) {
            // Try to extract JSON from response
            const jsonMatch = response.match(/\[[\s\S]*\]/);
            if (jsonMatch) {
                try {
                    const extracted = JSON.parse(jsonMatch[0]);
                    if (Array.isArray(extracted)) {
                        return extracted;
                    }
                } catch (e2) {
                    console.error('Failed to parse extracted JSON:', e2);
                }
            }
        }
        
        return null;
    }

    /**
     * Test ZipBusiness API Connection
     */
    function testZipBusinessAPI() {
        const $button = $(this);
        const $spinner = $('#test-api-spinner');
        const $statusDiv = $('#zipbusiness-api-status');
        
        // Show loading state
        $button.prop('disabled', true);
        $spinner.show().addClass('is-active');
        
        // Clear any existing status
        $statusDiv.html('<span class="dashicons dashicons-update spinning"></span> Testing connection...');
        
        // Make test request
        $.post(zippicks_master_critic.ajax_url, {
            action: 'zippicks_test_zipbusiness_api',
            nonce: zippicks_master_critic.nonce
        }, function(response) {
            if (response.success) {
                // Show success status
                let html = '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
                
                if (response.data.health_check === 'passed') {
                    html += 'Connected to ZipBusiness API';
                    
                    if (response.data.version) {
                        html += ' (' + escapeHtml(response.data.version) + ')';
                    }
                    
                    // Add API verification status
                    if (response.data.api_verification) {
                        html += '<br><small style="color: #666; margin-left: 28px;">API verification: ' + escapeHtml(response.data.api_verification) + '</small>';
                    }
                    
                    // Show restaurant data if available
                    if (response.data.test_city && response.data.restaurant_count !== undefined) {
                        html += '<br><small style="color: #666; margin-left: 28px;">Test query returned ' + response.data.restaurant_count + ' restaurants in ' + escapeHtml(response.data.test_city) + '</small>';
                    }
                    
                    // Show any error with restaurant endpoint
                    if (response.data.restaurant_error) {
                        html += '<br><small style="color: #ff9800; margin-left: 28px;">Note: ' + escapeHtml(response.data.note || 'Restaurant data endpoint is experiencing issues') + '</small>';
                    }
                    
                    // Show message
                    if (response.data.message) {
                        showNotice(response.data.message, response.data.restaurant_error ? 'warning' : 'success');
                    } else {
                        showNotice('API connection test successful!', 'success');
                    }
                } else {
                    html += response.data.message || 'API test completed';
                    showNotice(response.data.message || 'API test completed', 'info');
                }
                
                $statusDiv.html(html);
            } else {
                // Show error status
                let html = '<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ';
                html += 'Cannot connect to ZipBusiness API';
                
                if (response.data && response.data.message) {
                    html += ': ' + escapeHtml(response.data.message);
                }
                
                if (response.data && response.data.note) {
                    html += '<br><small style="color: #666; margin-left: 28px;">' + escapeHtml(response.data.note) + '</small>';
                }
                
                $statusDiv.html(html);
                showNotice('API connection test failed: ' + (response.data.message || 'Unknown error'), 'error');
            }
        }).fail(function(xhr, status, error) {
            // Show network error
            $statusDiv.html('<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> Network error: ' + escapeHtml(error));
            showNotice('Failed to test API connection. Please check your network connection and try again.', 'error');
        }).always(function() {
            // Reset button state
            $button.prop('disabled', false);
            $spinner.hide().removeClass('is-active');
        });
    }

})(jQuery);