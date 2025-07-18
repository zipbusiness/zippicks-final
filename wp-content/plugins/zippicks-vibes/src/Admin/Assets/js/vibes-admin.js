/**
 * ZipPicks Vibes Admin JavaScript - Hardened & Accessible
 * 
 * WordPress standards compliant admin interface with enhanced security
 * Comprehensive CRUD operations, accessibility features, and error handling
 * 
 * @package ZipPicksVibes
 * @version 2.0.0
 */

(function($, window, document) {
    'use strict';
    
    // INITIAL LOAD CONFIRMATION
    console.log('🚀 ZipPicks Vibes Admin JS: Starting initialization...');
    
    // Enhanced dependency and security checks
    if (typeof $ === 'undefined') {
        console.error('❌ ZipPicks Vibes: jQuery not loaded');
        return;
    }
    console.log('✅ ZipPicks Vibes: jQuery loaded successfully');
    
    if (typeof window.zippicksVibesAdmin === 'undefined') {
        console.error('❌ ZipPicks Vibes: Localized data not available');
        console.log('🔍 Available window properties:', Object.keys(window).filter(key => key.includes('zippicks') || key.includes('admin')));
        return;
    }
    console.log('✅ ZipPicks Vibes: Localized data available:', window.zippicksVibesAdmin);
    
    // Content Security Policy compliance check
    if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
        console.warn('ZipPicks Vibes: HTTPS recommended for security');
    }
    
    // Verify we're in WordPress admin
    if (!document.body.classList.contains('wp-admin')) {
        console.error('ZipPicks Vibes: Not in WordPress admin context');
        return;
    }

    /**
     * Security Module - Handles authentication, nonces, and validation
     */
    const SecurityModule = {
        
        validateNonce() {
            const nonce = window.zippicksVibesAdmin?.nonce;
            if (!nonce || nonce.length < 10) {
                console.error('Invalid nonce detected');
                return false;
            }
            return true;
        },
        
        checkRateLimit() {
            const now = Date.now();
            const lastCall = SecurityModule.lastAjaxCall || 0;
            const delay = parseInt(window.zippicksVibesAdmin?.security?.rateLimitDelay) || 1000;
            
            if (now - lastCall < delay) {
                return false;
            }
            
            SecurityModule.lastAjaxCall = now;
            return true;
        },
        
        validateRequest(data = {}) {
            if (!this.validateNonce()) {
                return false;
            }
            
            if (!this.checkRateLimit()) {
                UIModule.showError(window.zippicksVibesAdmin?.strings?.tooManyRequests || 'Rate limit exceeded');
                return false;
            }
            
            return true;
        },
        
        getSecureAjaxData(extraData = {}) {
            return {
                security: window.zippicksVibesAdmin?.nonce,
                ...extraData
            };
        }
    };

    /**
     * UI Module - Handles user interface interactions and feedback
     */
    const UIModule = {
        
        showLoading(element) {
            if (element && element.length) {
                element.addClass('is-loading').prop('disabled', true);
                element.attr('aria-busy', 'true');
            }
        },
        
        hideLoading(element) {
            if (element && element.length) {
                element.removeClass('is-loading').prop('disabled', false);
                element.removeAttr('aria-busy');
            }
        },
        
        showSuccess(message, timeout = 3000) {
            this.showNotice(message, 'success', timeout);
        },
        
        showError(message, timeout = 5000) {
            this.showNotice(message, 'error', timeout);
        },
        
        showNotice(message, type = 'info', timeout = 3000) {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible zp-notice" role="alert">
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `);
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss
            if (timeout > 0) {
                setTimeout(() => {
                    notice.fadeOut(() => notice.remove());
                }, timeout);
            }
            
            // Screen reader announcement
            this.announceToScreenReader(message);
        },
        
        announceToScreenReader(message) {
            const announcement = $('<div>')
                .attr('aria-live', 'polite')
                .attr('aria-atomic', 'true')
                .addClass('sr-only')
                .text(message);
                
            $('body').append(announcement);
            
            setTimeout(() => announcement.remove(), 1000);
        },
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        confirmAction(message) {
            return confirm(message);
        }
    };

    /**
     * AJAX Module - Handles all AJAX communications
     */
    const AjaxModule = {
        
        pendingRequests: new Set(),
        
        makeRequest(action, data = {}, options = {}) {
            return new Promise((resolve, reject) => {
                console.log(`🌐 ZipPicks Vibes: Making AJAX request for action: ${action}`);
                console.log('📊 ZipPicks Vibes: Request data:', data);
                
                if (!SecurityModule.validateRequest(data)) {
                    console.error('❌ ZipPicks Vibes: Security validation failed');
                    reject(new Error('Security validation failed'));
                    return;
                }
                
                const ajaxData = {
                    action: `zippicks_vibes_${action}`,
                    ...SecurityModule.getSecureAjaxData(data)
                };
                
                console.log('📤 ZipPicks Vibes: Final AJAX data:', ajaxData);
                console.log('🎯 ZipPicks Vibes: AJAX URL:', window.zippicksVibesAdmin?.ajaxUrl || ajaxurl);
                
                const settings = {
                    url: window.zippicksVibesAdmin?.ajaxUrl || ajaxurl,
                    type: 'POST',
                    data: ajaxData,
                    timeout: parseInt(window.zippicksVibesAdmin?.security?.ajaxTimeout) || 30000,
                    beforeSend: () => {
                        if (options.loadingElement) {
                            UIModule.showLoading(options.loadingElement);
                        }
                    },
                    complete: () => {
                        if (options.loadingElement) {
                            UIModule.hideLoading(options.loadingElement);
                        }
                        this.pendingRequests.delete(settings);
                    },
                    success: (response) => {
                        console.log('📥 ZipPicks Vibes: AJAX response received:', response);
                        if (response.success) {
                            console.log('✅ ZipPicks Vibes: AJAX request successful');
                            resolve(response.data);
                        } else {
                            console.error('❌ ZipPicks Vibes: AJAX request failed:', response);
                            reject(new Error(response.data?.message || 'Request failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('💥 ZipPicks Vibes: AJAX error:', {
                            xhr: xhr,
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        
                        let message = 'Network error occurred';
                        
                        // Try to parse JSON error response
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.data && response.data.message) {
                                message = response.data.message;
                            }
                        } catch (e) {
                            // If not JSON, check for specific error patterns
                            if (xhr.responseText && xhr.responseText.includes('Fatal error')) {
                                message = 'Server error: Please check the console for details';
                            } else if (status === 'timeout') {
                                message = 'Request timed out';
                            } else if (xhr.status === 403) {
                                message = window.zippicksVibesAdmin?.strings?.unauthorized || 'Unauthorized';
                            } else if (xhr.status >= 500) {
                                message = 'Server error occurred';
                            } else if (xhr.status === 0) {
                                message = 'Network connection lost or request was blocked';
                            }
                        }
                        
                        reject(new Error(message));
                    }
                };
                
                this.pendingRequests.add(settings);
                $.ajax(settings);
            });
        },
        
        saveVibe(vibeData, vibeId = 0) {
            const data = {
                vibe_id: vibeId,
                ...vibeData
            };
            
            return this.makeRequest('save', data);
        },
        
        deleteVibe(vibeId) {
            console.log('🗑️ ZipPicks Vibes: Sending delete request for vibe ID:', vibeId);
            return this.makeRequest('delete', { vibe_id: vibeId });
        },
        
        getVibe(vibeId) {
            return this.makeRequest('get', { vibe_id: vibeId });
        },
        
        toggleStatus(vibeId, isActive) {
            return this.makeRequest('toggle_status', { 
                vibe_id: vibeId, 
                is_active: isActive 
            });
        },
        
        reorderVibes(order) {
            return this.makeRequest('reorder', { order: order });
        },
        
        bulkAction(action, vibeIds) {
            return this.makeRequest('bulk', {
                action_type: action,
                vibe_ids: vibeIds
            });
        },
        
        saveCategory(categoryData, categoryId = 0) {
            const data = {
                category_id: categoryId,
                ...categoryData
            };
            
            return this.makeRequest('save_category', data);
        },
        
        deleteCategory(categoryId) {
            return this.makeRequest('delete_category', { category_id: categoryId });
        },
        
        getCategories() {
            return this.makeRequest('categories', {});
        }
    };

    /**
     * Form Module - Handles form validation and submissions
     */
    const FormModule = {
        
        validateForm(form) {
            const errors = [];
            
            // Required fields validation
            form.find('[required]').each(function() {
                const field = $(this);
                const value = field.val().trim();
                
                if (!value) {
                    errors.push(`${field.attr('name')} is required`);
                    field.addClass('error');
                } else {
                    field.removeClass('error');
                }
            });
            
            // Color field validation
            form.find('input[type="color"]').each(function() {
                const field = $(this);
                const value = field.val();
                
                if (value && !/^#[0-9A-F]{6}$/i.test(value)) {
                    errors.push('Please enter a valid color');
                    field.addClass('error');
                } else {
                    field.removeClass('error');
                }
            });
            
            return errors;
        },
        
        serializeFormData(form) {
            const data = {};
            const formData = form.serializeArray();
            
            formData.forEach(field => {
                if (data[field.name]) {
                    if (!Array.isArray(data[field.name])) {
                        data[field.name] = [data[field.name]];
                    }
                    data[field.name].push(field.value);
                } else {
                    data[field.name] = field.value;
                }
            });
            
            // Handle checkboxes (but not array checkboxes like vibe_categories[])
            form.find('input[type="checkbox"]').each(function() {
                const checkbox = $(this);
                const name = checkbox.attr('name');
                // Skip array checkboxes - they're already handled by serializeArray
                if (!name.endsWith('[]') && !checkbox.prop('checked')) {
                    data[name] = '';
                }
            });
            
            // Ensure icon field is included
            const iconSelect = form.find('#vibe_icon');
            if (iconSelect.length) {
                const iconValue = iconSelect.val();
                data['vibe_icon'] = iconValue || 'default';
                console.log('🎨 ZipPicks Vibes: Icon field value:', iconValue);
            }
            
            return data;
        },
        
        resetForm(form) {
            form[0].reset();
            form.find('.error').removeClass('error');
        }
    };

    /**
     * Modal Module - Handles modal dialogs
     */
    const ModalModule = {
        
        currentModal: null,
        
        show(modalId, options = {}) {
            const modal = $(`#${modalId}`);
            if (!modal.length) return;
            
            this.currentModal = modal;
            modal.show();
            
            // Focus management
            const firstFocusable = modal.find('input, button, select, textarea').first();
            if (firstFocusable.length) {
                firstFocusable.focus();
            }
            
            // Trap focus
            this.trapFocus(modal);
            
            // ESC key handler
            $(document).on('keydown.modal', (e) => {
                if (e.keyCode === 27) { // ESC
                    this.hide();
                }
            });
        },
        
        hide() {
            if (this.currentModal) {
                this.currentModal.hide();
                this.currentModal = null;
            }
            $(document).off('keydown.modal');
        },
        
        trapFocus(modal) {
            const focusableElements = modal.find('input, button, select, textarea, [tabindex]:not([tabindex="-1"])');
            const firstElement = focusableElements.first();
            const lastElement = focusableElements.last();
            
            modal.on('keydown', (e) => {
                if (e.keyCode === 9) { // TAB
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement[0]) {
                            lastElement.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastElement[0]) {
                            firstElement.focus();
                            e.preventDefault();
                        }
                    }
                }
            });
        }
    };

    /**
     * Icon Selector Module - Handles SVG icon selection and preview
     */
    const IconSelector = {
        
        init() {
            console.log('🎨 ZipPicks Vibes: Initializing icon selector...');
            this.loadIcons();
            this.bindEvents();
        },
        
        loadIcons() {
            const select = $('#vibe_icon');
            if (!select.length) return;
            
            console.log('📥 ZipPicks Vibes: Loading available icons...');
            
            $.ajax({
                url: window.zippicksVibesAdmin?.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_get_icons',
                    nonce: window.zippicksVibesAdmin?.nonce
                },
                success: (response) => {
                    console.log('✅ ZipPicks Vibes: Icons loaded:', response);
                    if (response.success) {
                        this.populateDropdown(response.data);
                    } else {
                        console.error('❌ ZipPicks Vibes: Failed to load icons:', response);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('💥 ZipPicks Vibes: Icon loading error:', error);
                }
            });
        },
        
        populateDropdown(icons) {
            const select = $('#vibe_icon');
            const currentValue = select.data('current') || 'default';
            
            console.log('🎯 ZipPicks Vibes: Populating dropdown with icons:', icons);
            console.log('🔍 ZipPicks Vibes: Current value:', currentValue);
            
            // Clear existing options except the first one
            select.find('option:not(:first)').remove();
            
            icons.forEach(icon => {
                const option = $('<option></option>')
                    .attr('value', icon.name)
                    .text(icon.name)
                    .prop('selected', icon.name === currentValue);
                select.append(option);
            });
            
            // Update preview with current value
            this.updatePreview(currentValue);
        },
        
        updatePreview(iconName) {
            const preview = $('#vibe_icon_preview');
            if (!preview.length || !iconName) return;
            
            console.log('🖼️ ZipPicks Vibes: Updating icon preview for:', iconName);
            
            $.ajax({
                url: window.zippicksVibesAdmin?.ajaxUrl || ajaxurl,
                type: 'POST',
                data: {
                    action: 'zippicks_get_icons',
                    nonce: window.zippicksVibesAdmin?.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const icon = response.data.find(i => i.name === iconName);
                        if (icon && icon.svg) {
                            preview.html(icon.svg);
                            console.log('✅ ZipPicks Vibes: Icon preview updated');
                        } else {
                            preview.html('<span class="vibe-icon-text">' + iconName + '</span>');
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('💥 ZipPicks Vibes: Preview update error:', error);
                    preview.html('<span class="vibe-icon-text">' + iconName + '</span>');
                }
            });
        },
        
        bindEvents() {
            $(document).on('change', '#vibe_icon', (e) => {
                const selectedIcon = $(e.target).val();
                console.log('🔄 ZipPicks Vibes: Icon selection changed to:', selectedIcon);
                this.updatePreview(selectedIcon);
            });
        }
    };

    /**
     * Main Admin Controller
     */
    const ZipPicksVibesAdmin = {
        
        init() {
            console.log('🎯 ZipPicks Vibes: Initializing admin controller...');
            this.initEventHandlers();
            this.initSortable();
            this.initAccessibility();
            this.startHeartbeat();
            
            // Initialize icon selector if on add/edit page
            if ($('#vibe_icon').length) {
                IconSelector.init();
            }
            
            console.log('✅ ZipPicks Vibes: Admin controller initialized successfully');
        },
        
        initEventHandlers() {
            console.log('🔧 ZipPicks Vibes: Binding event handlers...');
            
            // Form submissions - Direct binding for vibe form
            const vibeForm = $('#vibe-form');
            if (vibeForm.length) {
                console.log('✅ ZipPicks Vibes: #vibe-form found on page', vibeForm);
                
                // Direct binding to ensure it catches the submit event
                vibeForm.on('submit', (e) => {
                    console.log('📝 ZipPicks Vibes: Form submit triggered!', e.target);
                    e.preventDefault();
                    this.handleFormSubmit($(e.target));
                });
            } else {
                console.log('⚠️ ZipPicks Vibes: #vibe-form not found on current page');
            }
            
            $(document).on('submit', '#category-form', (e) => {
                e.preventDefault();
                this.handleCategoryFormSubmit($(e.target));
            });
            
            // Category buttons
            $(document).on('click', '#add-category-btn, #create-first-category', (e) => {
                e.preventDefault();
                this.handleAddCategory();
            });
            
            $(document).on('click', '.edit-category', (e) => {
                e.preventDefault();
                this.handleEditCategory($(e.target));
            });
            
            $(document).on('click', '.delete-category', (e) => {
                e.preventDefault();
                this.handleDeleteCategory($(e.target));
            });
            
            // Modal controls
            $(document).on('click', '#close-modal, #cancel-category', (e) => {
                e.preventDefault();
                ModalModule.hide();
            });
            
            // Delete buttons
            $(document).on('click', '.delete-vibe', (e) => {
                e.preventDefault();
                this.handleDelete($(e.target));
            });
            
            // Edit buttons
            $(document).on('click', '.edit-vibe', (e) => {
                e.preventDefault();
                this.handleEdit($(e.target));
            });
            
            // Status toggles
            $(document).on('change', '.status-toggle', (e) => {
                this.handleStatusToggle($(e.target));
            });
            
            // Bulk actions
            $(document).on('click', '#bulk-action-apply', (e) => {
                e.preventDefault();
                this.handleBulkAction();
            });
            
            // Close modals
            $(document).on('click', '.modal-close, .modal-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    ModalModule.hide();
                }
            });
            
            // Form validation on input
            $(document).on('input', 'form input, form textarea', (e) => {
                const field = $(e.target);
                if (field.hasClass('error')) {
                    field.removeClass('error');
                }
            });
        },
        
        initSortable() {
            const sortableList = $('#sortable-vibes');
            if (sortableList.length) {
                sortableList.sortable({
                    handle: '.drag-handle',
                    placeholder: 'ui-sortable-placeholder',
                    tolerance: 'pointer',
                    update: () => {
                        this.handleSortUpdate(sortableList);
                    },
                    start: (event, ui) => {
                        ui.placeholder.height(ui.item.height());
                        ui.item.addClass('dragging');
                    },
                    stop: (event, ui) => {
                        ui.item.removeClass('dragging');
                    }
                });
            }
        },
        
        initAccessibility() {
            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                // Ctrl+S or Cmd+S to save
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                    e.preventDefault();
                    const form = $('form:visible').first();
                    if (form.length) {
                        this.handleFormSubmit(form);
                    }
                }
            });
            
            // Screen reader announcements for dynamic content
            this.setupAriaLiveRegions();
        },
        
        setupAriaLiveRegions() {
            if (!$('#zp-announcements').length) {
                $('body').append('<div id="zp-announcements" aria-live="polite" aria-atomic="true" class="sr-only"></div>');
            }
        },
        
        startHeartbeat() {
            setInterval(() => {
                this.checkSession();
            }, 60000); // Check every minute
        },
        
        checkSession() {
            $.post(ajaxurl, {
                action: 'heartbeat',
                _wpnonce: window.zippicksVibesAdmin?.nonce
            }).fail(() => {
                UIModule.showError(window.zippicksVibesAdmin?.strings?.sessionExpired || 'Session expired');
            });
        },
        
        handleFormSubmit(form) {
            console.log('🎯 ZipPicks Vibes: handleFormSubmit called with form:', form);
            
            const errors = FormModule.validateForm(form);
            console.log('🔍 ZipPicks Vibes: Form validation result:', errors);
            
            if (errors.length > 0) {
                console.error('❌ ZipPicks Vibes: Form validation failed:', errors);
                UIModule.showError(errors.join(', '));
                return;
            }
            
            const formData = FormModule.serializeFormData(form);
            const vibeId = form.find('input[name="vibe_id"]').val() || 0;
            
            console.log('📋 ZipPicks Vibes: Form data:', formData);
            console.log('🆔 ZipPicks Vibes: Vibe ID:', vibeId);
            
            // Log icon value specifically
            console.log('🎨 ZipPicks Vibes: Icon value:', formData.vibe_icon || 'not set');
            
            console.log('🚀 ZipPicks Vibes: Starting AJAX save...');
            
            AjaxModule.saveVibe(formData, vibeId)
                .then((response) => {
                    console.log('✅ ZipPicks Vibes: AJAX save successful:', response);
                    UIModule.showSuccess(response.message || 'Vibe saved successfully');
                    if (response.redirect_url) {
                        console.log('🔄 ZipPicks Vibes: Redirecting to:', response.redirect_url);
                        setTimeout(() => {
                            window.location.href = response.redirect_url;
                        }, 1000);
                    }
                })
                .catch((error) => {
                    console.error('❌ ZipPicks Vibes: AJAX save failed:', error);
                    UIModule.showError(error.message || 'Network error occurred');
                });
        },
        
        handleDelete(button) {
            const vibeId = button.data('vibe-id') || button.attr('data-vibe-id');
            const vibeName = button.data('vibe-name') || button.attr('data-vibe-name') || 'this vibe';
            
            console.log('🗑️ ZipPicks Vibes: Delete button clicked', {
                vibeId: vibeId,
                vibeName: vibeName,
                button: button
            });
            
            if (!vibeId) {
                UIModule.showError('No vibe ID found');
                return;
            }
            
            if (!UIModule.confirmAction(`Are you sure you want to delete "${vibeName}"?`)) {
                return;
            }
            
            UIModule.showLoading(button);
            
            AjaxModule.deleteVibe(vibeId)
                .then(() => {
                    UIModule.showSuccess('Vibe deleted successfully');
                    button.closest('tr').fadeOut(() => {
                        button.closest('tr').remove();
                        // Update vibe count in stats
                        const totalVibesElement = $('.stat-number').first();
                        const currentTotal = parseInt(totalVibesElement.text()) || 0;
                        if (currentTotal > 0) {
                            totalVibesElement.text(currentTotal - 1);
                        }
                    });
                })
                .catch((error) => {
                    UIModule.showError(error.message);
                })
                .finally(() => {
                    UIModule.hideLoading(button);
                });
        },
        
        handleEdit(button) {
            const vibeId = button.data('vibe-id');
            
            AjaxModule.getVibe(vibeId)
                .then((data) => {
                    this.populateEditForm(data);
                    ModalModule.show('edit-vibe-modal');
                })
                .catch((error) => {
                    UIModule.showError(error.message);
                });
        },
        
        handleStatusToggle(toggle) {
            const vibeId = toggle.data('vibe-id');
            const isActive = toggle.prop('checked');
            
            AjaxModule.toggleStatus(vibeId, isActive)
                .then(() => {
                    UIModule.showSuccess('Status updated successfully');
                })
                .catch((error) => {
                    UIModule.showError(error.message);
                    // Revert toggle state
                    toggle.prop('checked', !isActive);
                });
        },
        
        handleSortUpdate(sortableList) {
            const order = sortableList.sortable('toArray', { attribute: 'data-vibe-id' });
            
            AjaxModule.reorderVibes(order)
                .then(() => {
                    UIModule.showSuccess('Order updated successfully');
                })
                .catch((error) => {
                    UIModule.showError(error.message);
                    // Revert sort order
                    sortableList.sortable('cancel');
                });
        },
        
        handleBulkAction() {
            const action = $('#bulk-action-selector').val();
            const selectedIds = [];
            
            $('.bulk-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (!action || selectedIds.length === 0) {
                UIModule.showError(window.zippicksVibesAdmin?.strings?.selectActionAndItems || 'Please select an action and at least one item');
                return;
            }
            
            const confirmMessage = window.zippicksVibesAdmin?.strings?.confirmBulkAction || 
                `Are you sure you want to ${action} ${selectedIds.length} item(s)?`;
            if (!UIModule.confirmAction(confirmMessage)) {
                return;
            }
            
            AjaxModule.bulkAction(action, selectedIds)
                .then(() => {
                    UIModule.showSuccess(window.zippicksVibesAdmin?.strings?.bulkActionCompleted || 'Bulk action completed successfully');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                })
                .catch((error) => {
                    UIModule.showError(error.message);
                });
        },
        
        handleAddCategory() {
            this.resetCategoryForm();
            $('#modal-title').text('Add Category');
            // Re-enable all parent options
            $('#category_parent option').prop('disabled', false);
            ModalModule.show('category-modal');
        },
        
        handleEditCategory(button) {
            const categoryId = button.data('category-id');
            const categoryName = button.data('category-name');
            const categoryDescription = button.data('category-description');
            const categorySlug = button.data('category-slug');
            const categoryParent = button.data('category-parent') || 0;
            const categoryOrder = button.data('category-order') || 0;
            
            $('#category_id').val(categoryId);
            $('#category_name').val(categoryName);
            $('#category_slug').val(categorySlug);
            $('#category_description').val(categoryDescription);
            $('#category_parent').val(categoryParent);
            $('#category_order').val(categoryOrder);
            $('#modal-title').text('Edit Category');
            
            // Disable selecting self as parent when editing
            $('#category_parent option[value="' + categoryId + '"]').prop('disabled', true);
            
            ModalModule.show('category-modal');
        },
        
        handleDeleteCategory(button) {
            const categoryId = button.data('category-id');
            const categoryName = button.closest('tr').find('.column-name strong').text() || 'this category';
            
            if (!UIModule.confirmAction(`Are you sure you want to delete "${categoryName}"?`)) {
                return;
            }
            
            AjaxModule.deleteCategory(categoryId)
                .then(() => {
                    UIModule.showSuccess('Category deleted successfully');
                    button.closest('tr').fadeOut(() => {
                        button.closest('tr').remove();
                    });
                })
                .catch((error) => {
                    UIModule.showError(error.message);
                });
        },
        
        handleCategoryFormSubmit(form) {
            const errors = FormModule.validateForm(form);
            
            if (errors.length > 0) {
                UIModule.showError(errors.join(', '));
                return;
            }
            
            const formData = FormModule.serializeFormData(form);
            const categoryId = form.find('input[name="category_id"]').val() || 0;
            
            AjaxModule.saveCategory(formData, categoryId)
                .then((response) => {
                    UIModule.showSuccess(response.message || 'Category saved successfully');
                    ModalModule.hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                })
                .catch((error) => {
                    UIModule.showError(error.message);
                });
        },
        
        resetCategoryForm() {
            const form = $('#category-form');
            FormModule.resetForm(form);
            $('#category_id').val(0);
            $('#category_slug').val('');
            $('#category_parent').val(0);
            $('#category_order').val(0);
            // Re-enable all parent options
            $('#category_parent option').prop('disabled', false);
        },
        
        populateEditForm(data) {
            const form = $('#edit-vibe-form');
            
            Object.keys(data).forEach(key => {
                const field = form.find(`[name="${key}"]`);
                if (field.length) {
                    if (field.attr('type') === 'checkbox') {
                        field.prop('checked', Boolean(data[key]));
                    } else {
                        field.val(data[key]);
                    }
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(() => {
        console.log('📋 ZipPicks Vibes: DOM ready, starting initialization...');
        
        // Final check for required elements
        const vibeForm = $('#vibe-form');
        if (vibeForm.length) {
            console.log('✅ ZipPicks Vibes: #vibe-form found during init:', vibeForm[0]);
        } else {
            console.log('ℹ️ ZipPicks Vibes: #vibe-form not found on this page (may not be needed)');
        }
        
        // Check for essential admin elements
        console.log('🔍 ZipPicks Vibes: Available forms on page:', $('form').map(function() { return this.id || this.className; }).get());
        
        ZipPicksVibesAdmin.init();
        
        // Additional debugging after init
        setTimeout(() => {
            console.log('🔍 ZipPicks Vibes: Post-init form check...');
            const formAfterInit = $('#vibe-form');
            if (formAfterInit.length) {
                console.log('📋 ZipPicks Vibes: Form events bound:', $._data(formAfterInit[0], 'events'));
            }
        }, 2000);
    });
    
    // Always expose to global scope for debugging in admin
    window.ZipPicksVibesAdmin = ZipPicksVibesAdmin;
    window.ZipPicksVibesModules = {
        Security: SecurityModule,
        UI: UIModule,
        Ajax: AjaxModule,
        Form: FormModule,
        Modal: ModalModule
    };
    
    console.log('🔧 ZipPicks Vibes: Debug objects exposed to window.ZipPicksVibesAdmin and window.ZipPicksVibesModules');
    
    // Log final status
    console.log('🎉 ZipPicks Vibes Admin JS: Initialization complete!');

})(jQuery, window, document);