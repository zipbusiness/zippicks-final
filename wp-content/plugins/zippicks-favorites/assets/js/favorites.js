/**
 * ZipPicks Favorites Frontend JavaScript
 */
(function($) {
    'use strict';

    const ZipPicksFavorites = {
        
        // Configuration
        config: window.zippicksFavorites || {},
        
        // State
        state: {
            processing: new Set(),
            favorites: new Map(),
            cities: []
        },
        
        /**
         * Initialize
         */
        init() {
            this.bindEvents();
            this.loadUserFavorites();
            this.initLocationFilter();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents() {
            // Favorite button clicks
            $(document).on('click', '.zp-favorite-btn', this.handleFavoriteClick.bind(this));
            
            // Location filter changes
            $(document).on('change', '#zp-favorites-city-filter', this.handleCityFilterChange.bind(this));
            $(document).on('submit', '#zp-favorites-location-form', this.handleLocationSearch.bind(this));
            
            // Sort and filter changes
            $(document).on('change', '#zp-favorites-sort', this.handleSortChange.bind(this));
            $(document).on('change', '#zp-favorites-vibe-filter', this.handleVibeFilterChange.bind(this));
            
            // View mode toggle
            $(document).on('click', '.zp-view-toggle button', this.handleViewToggle.bind(this));
            
            // Search within favorites
            $(document).on('input', '#zp-favorites-search', this.debounce(this.handleFavoritesSearch.bind(this), 300));
            
            // Current location button
            $(document).on('click', '#zp-use-current-location', this.handleCurrentLocation.bind(this));
        },
        
        /**
         * Handle favorite button click
         */
        async handleFavoriteClick(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const businessId = $btn.data('business-id');
            
            // Prevent double-clicks
            if (this.state.processing.has(businessId)) {
                return;
            }
            
            this.state.processing.add(businessId);
            const isFavorited = $btn.hasClass('is-favorited');
            
            // Optimistic UI update
            this.updateFavoriteButton($btn, !isFavorited, true);
            
            try {
                const response = await this.apiRequest(
                    isFavorited ? 'DELETE' : 'POST',
                    isFavorited ? `/favorites/${businessId}` : '/favorites',
                    isFavorited ? null : { business_id: businessId }
                );
                
                // Update state
                if (isFavorited) {
                    this.state.favorites.delete(businessId);
                } else {
                    this.state.favorites.set(businessId, response.data);
                }
                
                // Show confirmation
                this.showToast(
                    isFavorited ? this.config.i18n.removed : this.config.i18n.saved,
                    'success'
                );
                
                // Update any other instances of this button
                this.updateAllFavoriteButtons(businessId, !isFavorited);
                
                // Trigger custom event
                $(document).trigger('zippicks:favorite-changed', {
                    businessId,
                    isFavorited: !isFavorited
                });
                
            } catch (error) {
                // Revert optimistic update
                this.updateFavoriteButton($btn, isFavorited, false);
                this.showToast(this.config.i18n.error + ': ' + error.message, 'error');
            } finally {
                this.state.processing.delete(businessId);
            }
        },
        
        /**
         * Update favorite button UI
         */
        updateFavoriteButton($btn, isFavorited, isLoading) {
            $btn.toggleClass('is-favorited', isFavorited);
            $btn.toggleClass('is-loading', isLoading);
            
            const $icon = $btn.find('.zp-favorite-icon');
            const $text = $btn.find('.zp-favorite-text');
            
            $icon.text(isFavorited ? '♥' : '♡');
            $text.text(isFavorited ? this.config.i18n.saved : this.config.i18n.save);
            
            $btn.attr('aria-label', 
                isFavorited ? this.config.i18n.remove : this.config.i18n.save
            );
        },
        
        /**
         * Update all favorite buttons for a business
         */
        updateAllFavoriteButtons(businessId, isFavorited) {
            $(`.zp-favorite-btn[data-business-id="${businessId}"]`).each((_, btn) => {
                this.updateFavoriteButton($(btn), isFavorited, false);
            });
        },
        
        /**
         * Load user's favorites
         */
        async loadUserFavorites() {
            if (!this.config.userId) return;
            
            try {
                const response = await this.apiRequest('GET', `/users/${this.config.userId}/favorites`);
                
                // Store favorites in state
                response.data.forEach(fav => {
                    this.state.favorites.set(fav.business_id, fav);
                });
                
                // Update UI for any visible favorite buttons
                this.updateVisibleFavoriteButtons();
                
            } catch (error) {
                console.error('Failed to load favorites:', error);
            }
        },
        
        /**
         * Update visible favorite buttons based on state
         */
        updateVisibleFavoriteButtons() {
            $('.zp-favorite-btn').each((_, btn) => {
                const $btn = $(btn);
                const businessId = $btn.data('business-id');
                const isFavorited = this.state.favorites.has(businessId);
                
                this.updateFavoriteButton($btn, isFavorited, false);
            });
        },
        
        /**
         * Initialize location filter
         */
        async initLocationFilter() {
            const $cityFilter = $('#zp-favorites-city-filter');
            if (!$cityFilter.length) return;
            
            try {
                const response = await this.apiRequest('GET', `/users/${this.config.userId}/favorites/cities`);
                this.state.cities = response.data;
                
                // Populate city dropdown
                this.populateCityFilter(response.data);
                
            } catch (error) {
                console.error('Failed to load favorite cities:', error);
            }
        },
        
        /**
         * Populate city filter dropdown
         */
        populateCityFilter(cities) {
            const $select = $('#zp-favorites-city-filter');
            
            // Clear existing options except "All"
            $select.find('option:not(:first)').remove();
            
            // Add city options
            cities.forEach(city => {
                $select.append(`
                    <option value="${city.city},${city.state}">
                        ${city.display_name} (${city.count})
                    </option>
                `);
            });
        },
        
        /**
         * Handle city filter change
         */
        async handleCityFilterChange(e) {
            const value = $(e.target).val();
            const $container = $('#zp-favorites-list');
            
            $container.addClass('is-loading');
            
            try {
                let params = {};
                if (value && value !== 'all') {
                    const [city, state] = value.split(',');
                    params = { city, state };
                }
                
                const response = await this.apiRequest('GET', 
                    `/users/${this.config.userId}/favorites`, 
                    params
                );
                
                this.renderFavorites(response.data);
                
            } catch (error) {
                this.showToast('Failed to filter favorites', 'error');
            } finally {
                $container.removeClass('is-loading');
            }
        },
        
        /**
         * Handle location search (zip code)
         */
        async handleLocationSearch(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const zip = $form.find('#zp-favorites-zip').val();
            const radius = $form.find('#zp-favorites-radius').val() || 5;
            
            if (!zip) return;
            
            const $container = $('#zp-favorites-list');
            $container.addClass('is-loading');
            
            try {
                const response = await this.apiRequest('GET', 
                    `/users/${this.config.userId}/favorites`, 
                    { zip, radius }
                );
                
                this.renderFavorites(response.data);
                this.showLocationInfo(`Within ${radius} miles of ${zip}`);
                
            } catch (error) {
                this.showToast('Failed to search by location', 'error');
            } finally {
                $container.removeClass('is-loading');
            }
        },
        
        /**
         * Handle current location
         */
        async handleCurrentLocation(e) {
            e.preventDefault();
            
            if (!navigator.geolocation) {
                this.showToast('Geolocation is not supported', 'error');
                return;
            }
            
            const $btn = $(e.target);
            $btn.prop('disabled', true).text('Getting location...');
            
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    try {
                        const response = await this.apiRequest('GET', 
                            `/users/${this.config.userId}/favorites/nearby`, 
                            {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude,
                                radius: $('#zp-favorites-radius').val() || 5
                            }
                        );
                        
                        this.renderFavorites(response.data);
                        this.showLocationInfo('Near your current location');
                        
                    } catch (error) {
                        this.showToast('Failed to load nearby favorites', 'error');
                    } finally {
                        $btn.prop('disabled', false).text('Use Current Location');
                    }
                },
                (error) => {
                    this.showToast('Could not get your location', 'error');
                    $btn.prop('disabled', false).text('Use Current Location');
                }
            );
        },
        
        /**
         * Handle favorites search
         */
        async handleFavoritesSearch(e) {
            const query = $(e.target).val();
            
            if (!query) {
                this.loadUserFavorites();
                return;
            }
            
            const $container = $('#zp-favorites-list');
            $container.addClass('is-loading');
            
            try {
                const response = await this.apiRequest('GET', 
                    `/users/${this.config.userId}/favorites/search`, 
                    { q: query }
                );
                
                this.renderFavorites(response.data);
                
            } catch (error) {
                this.showToast('Search failed', 'error');
            } finally {
                $container.removeClass('is-loading');
            }
        },
        
        /**
         * Render favorites list
         */
        renderFavorites(favorites) {
            const $container = $('#zp-favorites-list');
            
            if (!favorites.length) {
                $container.html(`
                    <div class="zp-no-favorites">
                        <p>No favorites found in this location.</p>
                    </div>
                `);
                return;
            }
            
            const viewMode = $('.zp-view-toggle .active').data('view') || 'grid';
            const html = favorites.map(fav => this.renderFavoriteItem(fav, viewMode)).join('');
            
            $container.html(`<div class="zp-favorites-${viewMode}">${html}</div>`);
        },
        
        /**
         * Render single favorite item
         */
        renderFavoriteItem(favorite, viewMode) {
            const business = favorite.business;
            
            if (viewMode === 'grid') {
                return `
                    <div class="zp-favorite-card" data-favorite-id="${favorite.id}">
                        <div class="zp-favorite-image">
                            <img src="${business.image_url || '/placeholder.jpg'}" alt="${business.name}">
                            <button class="zp-favorite-remove" data-favorite-id="${favorite.id}">×</button>
                        </div>
                        <div class="zp-favorite-content">
                            <h3><a href="${business.url}">${business.name}</a></h3>
                            <p class="zp-favorite-cuisine">${business.cuisine}</p>
                            <p class="zp-favorite-location">${business.neighborhood || business.city}</p>
                            ${favorite.distance_km ? `<p class="zp-favorite-distance">${favorite.distance_mi} mi</p>` : ''}
                            ${favorite.notes ? `<p class="zp-favorite-notes">${favorite.notes}</p>` : ''}
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="zp-favorite-list-item" data-favorite-id="${favorite.id}">
                        <div class="zp-favorite-main">
                            <h3><a href="${business.url}">${business.name}</a></h3>
                            <p>${business.cuisine} • ${business.neighborhood || business.city}</p>
                        </div>
                        <div class="zp-favorite-actions">
                            ${favorite.distance_km ? `<span class="zp-distance">${favorite.distance_mi} mi</span>` : ''}
                            <button class="zp-favorite-remove" data-favorite-id="${favorite.id}">Remove</button>
                        </div>
                    </div>
                `;
            }
        },
        
        /**
         * Show location info
         */
        showLocationInfo(text) {
            $('#zp-location-info').text(text).show();
        },
        
        /**
         * API request helper
         */
        async apiRequest(method, endpoint, data = null) {
            const options = {
                method,
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                    'Content-Type': 'application/json'
                }
            };
            
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            
            const url = method === 'GET' && data 
                ? `${this.config.apiUrl}${endpoint}?${new URLSearchParams(data)}`
                : `${this.config.apiUrl}${endpoint}`;
            
            const response = await fetch(url, options);
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Request failed');
            }
            
            return response.json();
        },
        
        /**
         * Show toast notification
         */
        showToast(message, type = 'info') {
            const $toast = $(`
                <div class="zp-toast zp-toast-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append($toast);
            
            setTimeout(() => {
                $toast.addClass('show');
            }, 10);
            
            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => $toast.remove(), 300);
            }, 3000);
        },
        
        /**
         * Debounce helper
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize when document is ready
    $(document).ready(() => {
        ZipPicksFavorites.init();
    });
    
})(jQuery);