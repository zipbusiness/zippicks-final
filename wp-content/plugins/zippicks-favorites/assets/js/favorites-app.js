/**
 * ZipPicks Favorites React App
 */

const { useState, useEffect, useContext, createContext, useCallback, useMemo } = wp.element;
const { Button, Spinner, TextControl, SelectControl, ToggleControl, Popover, Modal } = wp.components;
const apiFetch = wp.apiFetch;
const { __ } = wp.i18n;

// Configure API
apiFetch.use(apiFetch.createNonceMiddleware(zipPicksFavorites.nonce));
apiFetch.use(apiFetch.createRootURLMiddleware(zipPicksFavorites.apiUrl));

// Favorites Context
const FavoritesContext = createContext();

// Favorites Provider
const FavoritesProvider = ({ children }) => {
    const [favorites, setFavorites] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [filters, setFilters] = useState({
        city: '',
        search: '',
        sort: 'date'
    });

    const fetchFavorites = useCallback(async (params = {}) => {
        setLoading(true);
        setError(null);
        
        try {
            const response = await apiFetch({
                path: '/favorites?' + new URLSearchParams({ ...filters, ...params }).toString()
            });
            setFavorites(response.data || []);
        } catch (err) {
            setError(err.message);
            console.error('Error fetching favorites:', err);
        } finally {
            setLoading(false);
        }
    }, [filters]);

    const saveFavorite = async (businessId, notes = '') => {
        try {
            const response = await apiFetch({
                path: '/favorites',
                method: 'POST',
                data: { business_id: businessId, notes }
            });
            
            // Refresh favorites list
            fetchFavorites();
            
            return response;
        } catch (err) {
            console.error('Error saving favorite:', err);
            throw err;
        }
    };

    const removeFavorite = async (favoriteId) => {
        try {
            await apiFetch({
                path: `/favorites/${favoriteId}`,
                method: 'DELETE'
            });
            
            // Remove from local state
            setFavorites(prev => prev.filter(f => f.id !== favoriteId));
        } catch (err) {
            console.error('Error removing favorite:', err);
            throw err;
        }
    };

    const value = {
        favorites,
        loading,
        error,
        filters,
        setFilters,
        fetchFavorites,
        saveFavorite,
        removeFavorite
    };

    return (
        <FavoritesContext.Provider value={value}>
            {children}
        </FavoritesContext.Provider>
    );
};

// Custom hook
const useFavorites = () => {
    const context = useContext(FavoritesContext);
    if (!context) {
        throw new Error('useFavorites must be used within FavoritesProvider');
    }
    return context;
};

// Heart Icon Component
const HeartIcon = ({ filled = false, className = '' }) => (
    <svg 
        className={`zippicks-heart-icon ${className}`}
        width="24" 
        height="24" 
        viewBox="0 0 24 24" 
        fill={filled ? "currentColor" : "none"}
        stroke="currentColor"
        strokeWidth="2"
    >
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
    </svg>
);

// Favorite Button Component
const FavoriteButton = ({ businessId, initialState = false, onToggle }) => {
    const [isFavorited, setIsFavorited] = useState(initialState);
    const [loading, setLoading] = useState(false);
    const { saveFavorite, removeFavorite } = useFavorites();

    const handleToggle = async () => {
        setLoading(true);
        
        try {
            if (isFavorited) {
                // Find favorite ID - in real implementation, this would be passed as prop
                await removeFavorite(businessId);
                setIsFavorited(false);
            } else {
                await saveFavorite(businessId);
                setIsFavorited(true);
            }
            
            if (onToggle) {
                onToggle(!isFavorited);
            }
        } catch (err) {
            console.error('Error toggling favorite:', err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <button
            className={`zippicks-favorite-button ${isFavorited ? 'is-favorited' : ''}`}
            onClick={handleToggle}
            disabled={loading}
            aria-label={isFavorited ? __('Remove from favorites', 'zippicks-favorites') : __('Add to favorites', 'zippicks-favorites')}
        >
            {loading ? <Spinner /> : <HeartIcon filled={isFavorited} />}
        </button>
    );
};

// Location Filter Component
const LocationFilter = ({ cities, selectedCity, onChange }) => {
    const [useCurrentLocation, setUseCurrentLocation] = useState(false);
    const [currentLocation, setCurrentLocation] = useState(null);
    const [loadingLocation, setLoadingLocation] = useState(false);

    const getCurrentLocation = () => {
        if (!navigator.geolocation) {
            alert(__('Geolocation is not supported by your browser', 'zippicks-favorites'));
            return;
        }

        setLoadingLocation(true);
        
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setCurrentLocation({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                });
                setUseCurrentLocation(true);
                setLoadingLocation(false);
                
                // Trigger location-based search
                onChange('near-me', position.coords);
            },
            (error) => {
                console.error('Error getting location:', error);
                alert(__('Unable to get your location', 'zippicks-favorites'));
                setLoadingLocation(false);
            }
        );
    };

    return (
        <div className="zippicks-location-filter">
            <SelectControl
                label={__('Filter by Location', 'zippicks-favorites')}
                value={selectedCity}
                onChange={(value) => {
                    setUseCurrentLocation(false);
                    onChange(value);
                }}
                options={[
                    { label: __('All Cities', 'zippicks-favorites'), value: '' },
                    ...cities.map(city => ({
                        label: `${city.display_name} (${city.count})`,
                        value: city.city
                    }))
                ]}
            />
            
            <Button
                variant="secondary"
                onClick={getCurrentLocation}
                disabled={loadingLocation}
                className="zippicks-near-me-button"
            >
                {loadingLocation ? <Spinner /> : __('Near Me', 'zippicks-favorites')}
            </Button>
        </div>
    );
};

// Favorite Card Component
const FavoriteCard = ({ favorite, view = 'grid' }) => {
    const [showRemoveModal, setShowRemoveModal] = useState(false);
    const { removeFavorite } = useFavorites();
    const { business } = favorite;

    const handleRemove = async () => {
        try {
            await removeFavorite(favorite.id);
            setShowRemoveModal(false);
        } catch (err) {
            alert(__('Failed to remove favorite', 'zippicks-favorites'));
        }
    };

    return (
        <div className={`zippicks-favorite-card ${view}`}>
            {business.image_url && (
                <div className="favorite-image">
                    <img src={business.image_url} alt={business.name} />
                </div>
            )}
            
            <div className="favorite-content">
                <h3 className="favorite-name">
                    <a href={business.url}>{business.name}</a>
                </h3>
                
                <div className="favorite-meta">
                    {business.cuisine && (
                        <span className="favorite-cuisine">{business.cuisine}</span>
                    )}
                    {business.price_range && (
                        <span className="favorite-price">{business.price_range}</span>
                    )}
                    {business.rating > 0 && (
                        <span className="favorite-rating">★ {business.rating}</span>
                    )}
                </div>
                
                <div className="favorite-location">
                    {favorite.neighborhood && (
                        <span className="favorite-neighborhood">{favorite.neighborhood}</span>
                    )}
                    <span className="favorite-city">{favorite.city}, {favorite.state}</span>
                    {favorite.distance_mi && (
                        <span className="favorite-distance">{favorite.distance_mi} mi</span>
                    )}
                </div>
                
                {business.vibes && business.vibes.length > 0 && (
                    <div className="favorite-vibes">
                        {business.vibes.map(vibe => (
                            <span key={vibe} className="vibe-tag">{vibe}</span>
                        ))}
                    </div>
                )}
                
                {favorite.user_notes && (
                    <div className="favorite-notes">{favorite.user_notes}</div>
                )}
                
                <div className="favorite-actions">
                    <Button
                        variant="link"
                        onClick={() => setShowRemoveModal(true)}
                        className="remove-favorite"
                    >
                        {__('Remove', 'zippicks-favorites')}
                    </Button>
                </div>
            </div>
            
            {showRemoveModal && (
                <Modal
                    title={__('Remove Favorite', 'zippicks-favorites')}
                    onRequestClose={() => setShowRemoveModal(false)}
                >
                    <p>{__('Are you sure you want to remove this from your favorites?', 'zippicks-favorites')}</p>
                    <div className="modal-actions">
                        <Button variant="secondary" onClick={() => setShowRemoveModal(false)}>
                            {__('Cancel', 'zippicks-favorites')}
                        </Button>
                        <Button variant="primary" isDestructive onClick={handleRemove}>
                            {__('Remove', 'zippicks-favorites')}
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
};

// Favorites Dashboard Component
const FavoritesDashboard = ({ settings = {} }) => {
    const { favorites, loading, error, filters, setFilters, fetchFavorites } = useFavorites();
    const [view, setView] = useState(settings.view || 'grid');
    const [cities, setCities] = useState([]);
    const [showMap, setShowMap] = useState(settings.show_map !== 'false');
    const [mapInstance, setMapInstance] = useState(null);

    // Fetch cities on mount
    useEffect(() => {
        fetchCities();
        fetchFavorites();
    }, []);
    
    // Initialize map when showMap changes or favorites update
    useEffect(() => {
        if (showMap && favorites.length > 0 && window.ZipPicksFavoritesMap) {
            // Cleanup previous map instance
            if (mapInstance) {
                mapInstance.destroy();
            }
            
            // Create new map instance
            const map = new window.ZipPicksFavoritesMap('favorites-map');
            map.addMarkers(favorites);
            setMapInstance(map);
        }
        
        return () => {
            if (mapInstance) {
                mapInstance.destroy();
            }
        };
    }, [showMap, favorites]);

    const fetchCities = async () => {
        try {
            const response = await apiFetch({ path: '/favorites/cities' });
            setCities(response || []);
        } catch (err) {
            console.error('Error fetching cities:', err);
        }
    };

    const handleLocationChange = (city, coords = null) => {
        if (coords) {
            // Fetch favorites near location
            fetchFavoritesNearLocation(coords.lat, coords.lng);
        } else {
            setFilters({ ...filters, city });
        }
    };

    const fetchFavoritesNearLocation = async (lat, lng) => {
        try {
            const response = await apiFetch({
                path: `/favorites/near?lat=${lat}&lng=${lng}&radius=${zipPicksFavorites.settings.defaultRadius}`
            });
            // Handle response - update favorites with distance data
        } catch (err) {
            console.error('Error fetching nearby favorites:', err);
        }
    };

    const handleExport = async (format = 'json') => {
        try {
            const params = new URLSearchParams({ format, ...filters });
            window.location.href = `${zipPicksFavorites.apiUrl}/favorites/export?${params}`;
        } catch (err) {
            console.error('Error exporting favorites:', err);
        }
    };

    if (loading && favorites.length === 0) {
        return <div className="zippicks-favorites-loading"><Spinner /></div>;
    }

    return (
        <div className="zippicks-favorites-dashboard">
            <div className="favorites-header">
                <h2>{__('My Favorites', 'zippicks-favorites')}</h2>
                
                <div className="favorites-controls">
                    <div className="view-toggle">
                        <Button
                            variant={view === 'grid' ? 'primary' : 'secondary'}
                            onClick={() => setView('grid')}
                        >
                            {__('Grid', 'zippicks-favorites')}
                        </Button>
                        <Button
                            variant={view === 'list' ? 'primary' : 'secondary'}
                            onClick={() => setView('list')}
                        >
                            {__('List', 'zippicks-favorites')}
                        </Button>
                    </div>
                    
                    {zipPicksFavorites.settings.enableMap && (
                        <ToggleControl
                            label={__('Show Map', 'zippicks-favorites')}
                            checked={showMap}
                            onChange={setShowMap}
                        />
                    )}
                </div>
            </div>
            
            {settings.show_filters !== 'false' && (
                <div className="favorites-filters">
                    <LocationFilter
                        cities={cities}
                        selectedCity={filters.city}
                        onChange={handleLocationChange}
                    />
                    
                    <TextControl
                        label={__('Search', 'zippicks-favorites')}
                        value={filters.search}
                        onChange={(search) => setFilters({ ...filters, search })}
                        placeholder={__('Search favorites...', 'zippicks-favorites')}
                    />
                    
                    <SelectControl
                        label={__('Sort by', 'zippicks-favorites')}
                        value={filters.sort}
                        onChange={(sort) => setFilters({ ...filters, sort })}
                        options={[
                            { label: __('Date Added', 'zippicks-favorites'), value: 'date' },
                            { label: __('Name', 'zippicks-favorites'), value: 'name' },
                            { label: __('Distance', 'zippicks-favorites'), value: 'distance' },
                            { label: __('Rating', 'zippicks-favorites'), value: 'rating' }
                        ]}
                    />
                </div>
            )}
            
            {error && (
                <div className="favorites-error">
                    {__('An error occurred:', 'zippicks-favorites')} {error}
                </div>
            )}
            
            <div className="favorites-content">
                {showMap && <div className="favorites-map" id="favorites-map"></div>}
                
                {favorites.length === 0 ? (
                    <div className="no-favorites">
                        {__('No favorites found. Start exploring and save your favorite places!', 'zippicks-favorites')}
                    </div>
                ) : (
                    <div className={`favorites-grid ${view}`}>
                        {favorites.map(favorite => (
                            <FavoriteCard
                                key={favorite.id}
                                favorite={favorite}
                                view={view}
                            />
                        ))}
                    </div>
                )}
            </div>
            
            {zipPicksFavorites.settings.enableExport && favorites.length > 0 && (
                <div className="favorites-export">
                    <Button onClick={() => handleExport('json')}>
                        {__('Export as JSON', 'zippicks-favorites')}
                    </Button>
                    <Button onClick={() => handleExport('csv')}>
                        {__('Export as CSV', 'zippicks-favorites')}
                    </Button>
                </div>
            )}
        </div>
    );
};

// Initialize Dashboard
document.addEventListener('DOMContentLoaded', () => {
    const dashboardEl = document.getElementById('zippicks-favorites-dashboard');
    if (dashboardEl) {
        const settings = JSON.parse(dashboardEl.dataset.settings || '{}');
        
        wp.element.render(
            <FavoritesProvider>
                <FavoritesDashboard settings={settings} />
            </FavoritesProvider>,
            dashboardEl
        );
    }
    
    // Initialize favorite buttons
    document.querySelectorAll('.zippicks-favorite-button-wrapper').forEach(wrapper => {
        const businessId = parseInt(wrapper.dataset.businessId);
        const isFavorited = wrapper.dataset.favorited === 'true';
        
        wp.element.render(
            <FavoritesProvider>
                <FavoriteButton businessId={businessId} initialState={isFavorited} />
            </FavoritesProvider>,
            wrapper
        );
    });
});