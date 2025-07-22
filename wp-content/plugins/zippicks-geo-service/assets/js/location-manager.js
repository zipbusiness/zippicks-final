/**
 * ZipPicks Location Manager
 * 
 * Client-side location detection and management
 */
class ZipPicksLocationManager {
    constructor() {
        this.endpoint = zippicks_geo.api_endpoint;
        this.nonce = zippicks_geo.nonce;
        this.userId = zippicks_geo.user_id;
        this.sessionId = zippicks_geo.session_id;
        this.cachedLocation = null;
        this.permissionGranted = null;
        this.watchId = null;
        this.listeners = new Map();
        
        // Initialize
        this.init();
    }
    
    /**
     * Initialize location manager
     */
    init() {
        // Check for geolocation support
        if (!('geolocation' in navigator)) {
            console.warn('Geolocation is not supported by this browser');
        }
        
        // Load cached location from session storage
        this.loadCachedLocation();
        
        // Set up visibility change handler
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.refreshLocation();
            }
        });
    }
    
    /**
     * Get current location using cascade strategy
     */
    async getCurrentLocation(options = {}) {
        const defaults = {
            enableHighAccuracy: false,
            timeout: 5000,
            maximumAge: 300000, // 5 minutes
            forceRefresh: false
        };
        
        options = { ...defaults, ...options };
        
        // Check cache first (unless force refresh)
        if (!options.forceRefresh && this.cachedLocation) {
            const age = Date.now() - this.cachedLocation.timestamp;
            if (age < options.maximumAge) {
                return this.cachedLocation.location;
            }
        }
        
        // Try GPS if available and permitted
        if ('geolocation' in navigator && (this.permissionGranted || this.permissionGranted === null)) {
            try {
                const position = await this.getGPSLocation(options);
                const location = this.processGPSLocation(position);
                
                // Cache and send to server
                this.cacheLocation(location);
                this.updateServerLocation(location);
                
                return location;
            } catch (error) {
                console.log('GPS location failed:', error.message);
                this.handleLocationError(error);
            }
        }
        
        // Fall back to server-side detection
        return this.getServerLocation();
    }
    
    /**
     * Get GPS location
     */
    getGPSLocation(options) {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                resolve,
                reject,
                {
                    enableHighAccuracy: options.enableHighAccuracy,
                    timeout: options.timeout,
                    maximumAge: options.maximumAge
                }
            );
        });
    }
    
    /**
     * Process GPS position to location format
     */
    processGPSLocation(position) {
        return {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: this.getAccuracyLevel(position.coords.accuracy),
            accuracy_meters: Math.round(position.coords.accuracy),
            source: 'gps',
            timestamp: position.timestamp
        };
    }
    
    /**
     * Get accuracy level from meters
     */
    getAccuracyLevel(meters) {
        if (meters <= 50) return 'precise';
        if (meters <= 1000) return 'street';
        if (meters <= 5000) return 'neighborhood';
        return 'city';
    }
    
    /**
     * Get location from server
     */
    async getServerLocation() {
        try {
            const response = await fetch(this.endpoint + 'detect', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.nonce,
                    'X-Session-ID': this.sessionId
                }
            });
            
            if (!response.ok) {
                throw new Error(`Server error: ${response.status}`);
            }
            
            const location = await response.json();
            
            // Cache if not already cached
            if (!location.cached) {
                this.cacheLocation(location);
            }
            
            return location;
        } catch (error) {
            console.error('Failed to get server location:', error);
            return this.getDefaultLocation();
        }
    }
    
    /**
     * Update location on server
     */
    async updateServerLocation(location) {
        try {
            const response = await fetch(this.endpoint + 'update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                    'X-Session-ID': this.sessionId
                },
                body: JSON.stringify(location)
            });
            
            if (!response.ok) {
                console.error('Failed to update server location:', response.status);
            }
        } catch (error) {
            console.error('Error updating server location:', error);
        }
    }
    
    /**
     * Watch location changes
     */
    watchLocation(callback, options = {}) {
        if (!('geolocation' in navigator)) {
            console.error('Geolocation not supported');
            return null;
        }
        
        const watchOptions = {
            enableHighAccuracy: options.enableHighAccuracy || false,
            timeout: options.timeout || 10000,
            maximumAge: options.maximumAge || 30000
        };
        
        const watchId = navigator.geolocation.watchPosition(
            (position) => {
                const location = this.processGPSLocation(position);
                this.cacheLocation(location);
                this.updateServerLocation(location);
                callback(location);
            },
            (error) => {
                this.handleLocationError(error);
                callback(null, error);
            },
            watchOptions
        );
        
        this.watchId = watchId;
        return watchId;
    }
    
    /**
     * Stop watching location
     */
    stopWatching() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
    }
    
    /**
     * Calculate distance between two points
     */
    async calculateDistance(from, to, unit = 'miles') {
        try {
            const response = await fetch(this.endpoint + 'distance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({ from, to, unit })
            });
            
            if (!response.ok) {
                throw new Error(`Server error: ${response.status}`);
            }
            
            const result = await response.json();
            return result.distance;
        } catch (error) {
            console.error('Failed to calculate distance:', error);
            return null;
        }
    }
    
    /**
     * Find nearby locations
     */
    async findNearby(options = {}) {
        const defaults = {
            radius: 5,
            limit: 20,
            type: 'restaurants'
        };
        
        options = { ...defaults, ...options };
        
        // Get current location if not provided
        if (!options.latitude || !options.longitude) {
            const location = await this.getCurrentLocation();
            options.latitude = location.latitude;
            options.longitude = location.longitude;
        }
        
        try {
            const response = await fetch(this.endpoint + 'nearby', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify(options)
            });
            
            if (!response.ok) {
                throw new Error(`Server error: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Failed to find nearby locations:', error);
            return { results: [], total: 0 };
        }
    }
    
    /**
     * Request location permission
     */
    async requestPermission() {
        if (!('geolocation' in navigator)) {
            return false;
        }
        
        // Check if permissions API is available
        if ('permissions' in navigator) {
            try {
                const result = await navigator.permissions.query({ name: 'geolocation' });
                this.permissionGranted = result.state === 'granted';
                
                // Listen for permission changes
                result.addEventListener('change', () => {
                    this.permissionGranted = result.state === 'granted';
                    this.emit('permissionchange', result.state);
                });
                
                return this.permissionGranted;
            } catch (error) {
                console.log('Permissions API not fully supported');
            }
        }
        
        // Fallback: try to get location to trigger permission prompt
        try {
            await this.getGPSLocation({ timeout: 5000 });
            this.permissionGranted = true;
            return true;
        } catch (error) {
            if (error.code === 1) { // PERMISSION_DENIED
                this.permissionGranted = false;
            }
            return false;
        }
    }
    
    /**
     * Cache location in session storage
     */
    cacheLocation(location) {
        this.cachedLocation = {
            location: location,
            timestamp: Date.now()
        };
        
        try {
            sessionStorage.setItem('zippicks_location', JSON.stringify(this.cachedLocation));
        } catch (error) {
            console.error('Failed to cache location:', error);
        }
        
        // Emit location update event
        this.emit('locationupdate', location);
    }
    
    /**
     * Load cached location from session storage
     */
    loadCachedLocation() {
        try {
            const cached = sessionStorage.getItem('zippicks_location');
            if (cached) {
                this.cachedLocation = JSON.parse(cached);
            }
        } catch (error) {
            console.error('Failed to load cached location:', error);
        }
    }
    
    /**
     * Clear cached location
     */
    clearCache() {
        this.cachedLocation = null;
        try {
            sessionStorage.removeItem('zippicks_location');
        } catch (error) {
            console.error('Failed to clear location cache:', error);
        }
    }
    
    /**
     * Refresh location (force update)
     */
    async refreshLocation() {
        return this.getCurrentLocation({ forceRefresh: true });
    }
    
    /**
     * Get default location
     */
    getDefaultLocation() {
        return {
            latitude: 34.0522,
            longitude: -118.2437,
            city: 'Los Angeles',
            state: 'CA',
            country: 'US',
            accuracy: 'default',
            accuracy_meters: 50000,
            source: 'default',
            cached: false,
            timestamp: Date.now()
        };
    }
    
    /**
     * Handle location errors
     */
    handleLocationError(error) {
        let message = 'Unknown error';
        
        switch (error.code) {
            case 1: // PERMISSION_DENIED
                message = 'Location permission denied';
                this.permissionGranted = false;
                break;
            case 2: // POSITION_UNAVAILABLE
                message = 'Location information unavailable';
                break;
            case 3: // TIMEOUT
                message = 'Location request timed out';
                break;
        }
        
        this.emit('locationerror', { code: error.code, message });
    }
    
    /**
     * Event emitter methods
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }
    
    off(event, callback) {
        if (!this.listeners.has(event)) return;
        
        const callbacks = this.listeners.get(event);
        const index = callbacks.indexOf(callback);
        if (index > -1) {
            callbacks.splice(index, 1);
        }
    }
    
    emit(event, data) {
        if (!this.listeners.has(event)) return;
        
        this.listeners.get(event).forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error(`Error in ${event} listener:`, error);
            }
        });
    }
}

// Initialize on DOM ready
jQuery(document).ready(function($) {
    // Create global instance
    window.zippicksLocation = new ZipPicksLocationManager();
    
    // Auto-detect location on page load (if enabled)
    if (zippicks_geo.auto_detect) {
        window.zippicksLocation.getCurrentLocation().then(location => {
            console.log('Location detected:', location);
        });
    }
    
    // Add location button handler
    $(document).on('click', '.zippicks-detect-location', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text('Detecting...').prop('disabled', true);
        
        window.zippicksLocation.requestPermission().then(granted => {
            if (granted) {
                return window.zippicksLocation.getCurrentLocation({ enableHighAccuracy: true });
            } else {
                throw new Error('Permission denied');
            }
        }).then(location => {
            $button.text('Location detected!');
            setTimeout(() => {
                $button.text(originalText).prop('disabled', false);
            }, 2000);
            
            // Trigger custom event
            $(document).trigger('zippicks:location:detected', [location]);
        }).catch(error => {
            $button.text('Detection failed').prop('disabled', false);
            setTimeout(() => {
                $button.text(originalText);
            }, 2000);
            
            console.error('Location detection failed:', error);
        });
    });
    
    // Add "use my location" to search forms
    $('.zippicks-search-form').each(function() {
        const $form = $(this);
        const $locationField = $form.find('input[name="location"]');
        
        if ($locationField.length) {
            const $useLocationBtn = $('<button type="button" class="zippicks-use-location">📍 Use my location</button>');
            $locationField.after($useLocationBtn);
            
            $useLocationBtn.on('click', function() {
                window.zippicksLocation.getCurrentLocation().then(location => {
                    if (location.city && location.state) {
                        $locationField.val(`${location.city}, ${location.state}`);
                    } else {
                        $locationField.val('Current location');
                    }
                    $locationField.data('coordinates', {
                        lat: location.latitude,
                        lng: location.longitude
                    });
                });
            });
        }
    });
});