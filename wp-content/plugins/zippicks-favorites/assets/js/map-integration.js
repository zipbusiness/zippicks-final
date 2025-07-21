/**
 * ZipPicks Favorites Map Integration
 */

class ZipPicksFavoritesMap {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Map container not found');
            return;
        }
        
        this.options = {
            center: [34.0522, -118.2437], // Default to LA
            zoom: 12,
            provider: zipPicksFavorites.settings.mapProvider || 'mapbox',
            ...options
        };
        
        this.map = null;
        this.markers = [];
        this.bounds = null;
        
        this.init();
    }
    
    init() {
        if (this.options.provider === 'mapbox' && window.mapboxgl) {
            this.initMapbox();
        } else if (this.options.provider === 'google' && window.google) {
            this.initGoogle();
        } else {
            // Fallback to OpenStreetMap with Leaflet
            this.loadLeaflet().then(() => this.initLeaflet());
        }
    }
    
    initMapbox() {
        mapboxgl.accessToken = zipPicksFavorites.settings.mapboxToken;
        
        this.map = new mapboxgl.Map({
            container: this.container,
            style: 'mapbox://styles/mapbox/streets-v11',
            center: this.options.center,
            zoom: this.options.zoom
        });
        
        this.map.addControl(new mapboxgl.NavigationControl());
        
        // Add geolocation control
        this.map.addControl(
            new mapboxgl.GeolocateControl({
                positionOptions: {
                    enableHighAccuracy: true
                },
                trackUserLocation: true,
                showUserHeading: true
            })
        );
    }
    
    initGoogle() {
        this.map = new google.maps.Map(this.container, {
            center: { lat: this.options.center[0], lng: this.options.center[1] },
            zoom: this.options.zoom
        });
    }
    
    async loadLeaflet() {
        if (window.L) return;
        
        // Load Leaflet CSS
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);
        
        // Load Leaflet JS
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = resolve;
            document.head.appendChild(script);
        });
    }
    
    initLeaflet() {
        this.map = L.map(this.container).setView(this.options.center, this.options.zoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);
    }
    
    addMarkers(favorites) {
        // Clear existing markers
        this.clearMarkers();
        
        // Reset bounds
        if (this.options.provider === 'mapbox') {
            this.bounds = new mapboxgl.LngLatBounds();
        } else if (this.options.provider === 'google') {
            this.bounds = new google.maps.LatLngBounds();
        } else if (window.L) {
            this.bounds = L.latLngBounds();
        }
        
        favorites.forEach((favorite) => {
            if (favorite.latitude && favorite.longitude) {
                this.addMarker(favorite);
            }
        });
        
        // Fit map to markers
        this.fitBounds();
    }
    
    addMarker(favorite) {
        const { business } = favorite;
        const lat = parseFloat(favorite.latitude);
        const lng = parseFloat(favorite.longitude);
        
        if (this.options.provider === 'mapbox') {
            // Create custom marker element
            const el = document.createElement('div');
            el.className = 'favorite-marker';
            el.innerHTML = '📍';
            el.style.width = '30px';
            el.style.height = '30px';
            el.style.fontSize = '20px';
            el.style.cursor = 'pointer';
            
            // Create popup
            const popup = new mapboxgl.Popup({ offset: 25 })
                .setHTML(this.createPopupContent(favorite));
            
            // Create marker
            const marker = new mapboxgl.Marker(el)
                .setLngLat([lng, lat])
                .setPopup(popup)
                .addTo(this.map);
            
            this.markers.push(marker);
            this.bounds.extend([lng, lat]);
            
        } else if (this.options.provider === 'google') {
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: this.map,
                title: business.name
            });
            
            const infoWindow = new google.maps.InfoWindow({
                content: this.createPopupContent(favorite)
            });
            
            marker.addListener('click', () => {
                infoWindow.open(this.map, marker);
            });
            
            this.markers.push(marker);
            this.bounds.extend({ lat, lng });
            
        } else if (window.L) {
            const marker = L.marker([lat, lng])
                .bindPopup(this.createPopupContent(favorite))
                .addTo(this.map);
            
            this.markers.push(marker);
            this.bounds.extend([lat, lng]);
        }
    }
    
    createPopupContent(favorite) {
        const { business } = favorite;
        
        return `
            <div class="map-popup">
                <h4><a href="${business.url}" target="_blank">${business.name}</a></h4>
                ${business.cuisine ? `<p class="cuisine">${business.cuisine}</p>` : ''}
                ${business.rating ? `<p class="rating">★ ${business.rating}</p>` : ''}
                <p class="address">${business.address}</p>
                ${favorite.user_notes ? `<p class="notes">${favorite.user_notes}</p>` : ''}
            </div>
        `;
    }
    
    clearMarkers() {
        if (this.options.provider === 'mapbox') {
            this.markers.forEach(marker => marker.remove());
        } else if (this.options.provider === 'google') {
            this.markers.forEach(marker => marker.setMap(null));
        } else if (window.L) {
            this.markers.forEach(marker => this.map.removeLayer(marker));
        }
        this.markers = [];
    }
    
    fitBounds() {
        if (!this.bounds || this.markers.length === 0) return;
        
        if (this.options.provider === 'mapbox') {
            this.map.fitBounds(this.bounds, { padding: 50 });
        } else if (this.options.provider === 'google') {
            this.map.fitBounds(this.bounds);
        } else if (window.L) {
            this.map.fitBounds(this.bounds, { padding: [50, 50] });
        }
    }
    
    setUserLocation(lat, lng) {
        if (this.options.provider === 'mapbox') {
            this.map.flyTo({
                center: [lng, lat],
                zoom: 14
            });
        } else if (this.options.provider === 'google') {
            this.map.setCenter({ lat, lng });
            this.map.setZoom(14);
        } else if (window.L) {
            this.map.setView([lat, lng], 14);
        }
    }
    
    destroy() {
        if (this.map) {
            if (this.options.provider === 'mapbox') {
                this.map.remove();
            } else if (this.options.provider === 'leaflet') {
                this.map.remove();
            }
            // Google Maps doesn't have a destroy method
        }
    }
}

// Export for use in React components
window.ZipPicksFavoritesMap = ZipPicksFavoritesMap;

// Auto-initialize maps
document.addEventListener('DOMContentLoaded', () => {
    // Check if map container exists
    const mapContainer = document.getElementById('favorites-map');
    if (mapContainer && zipPicksFavorites.settings.enableMap) {
        // Will be initialized by React component
        console.log('Map container ready for initialization');
    }
});

// Add custom styles for map popups
const style = document.createElement('style');
style.textContent = `
    .map-popup {
        max-width: 200px;
    }
    
    .map-popup h4 {
        margin: 0 0 5px;
        font-size: 16px;
    }
    
    .map-popup h4 a {
        color: #333;
        text-decoration: none;
    }
    
    .map-popup h4 a:hover {
        color: #007cba;
    }
    
    .map-popup p {
        margin: 3px 0;
        font-size: 14px;
    }
    
    .map-popup .cuisine {
        color: #007cba;
        font-weight: 500;
    }
    
    .map-popup .rating {
        color: #f39c12;
    }
    
    .map-popup .address {
        color: #666;
        font-size: 13px;
    }
    
    .map-popup .notes {
        font-style: italic;
        color: #666;
        font-size: 13px;
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px solid #eee;
    }
    
    .favorite-marker {
        display: flex;
        align-items: center;
        justify-content: center;
    }
`;
document.head.appendChild(style);