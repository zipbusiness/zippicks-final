/**
 * ZipPicks Geo Service Admin JavaScript
 */
jQuery(document).ready(function($) {
    
    // Test location detection
    $('#test-location').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Testing...');
        
        $.post(zippicks_geo_admin.ajax_url, {
            action: 'zippicks_geo_test_location',
            nonce: zippicks_geo_admin.nonce
        })
        .done(function(response) {
            if (response.success) {
                const location = response.data;
                alert(`Location detected:\n\nLatitude: ${location.latitude}\nLongitude: ${location.longitude}\nCity: ${location.city || 'Unknown'}\nState: ${location.state || 'Unknown'}\nSource: ${location.source}\nAccuracy: ${location.accuracy}`);
            } else {
                alert('Location detection failed');
            }
        })
        .fail(function() {
            alert('Request failed');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Clear cache
    $('#clear-cache').on('click', function() {
        if (!confirm('Clear all location cache?')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Clearing...');
        
        $.post(zippicks_geo_admin.ajax_url, {
            action: 'zippicks_geo_clear_cache',
            nonce: zippicks_geo_admin.nonce
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Failed to clear cache');
            }
        })
        .fail(function() {
            alert('Request failed');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Update MaxMind database
    $('#update-maxmind').on('click', function() {
        if (!confirm('Update MaxMind GeoLite2 database?')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Updating...');
        
        $.post(zippicks_geo_admin.ajax_url, {
            action: 'zippicks_geo_update_maxmind',
            nonce: zippicks_geo_admin.nonce
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data.message || 'Update failed');
            }
        })
        .fail(function() {
            alert('Request failed');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    });
    
    // Distance calculator
    $('#calculate-distance').on('click', function() {
        const fromLat = parseFloat($('#from-lat').val());
        const fromLng = parseFloat($('#from-lng').val());
        const toLat = parseFloat($('#to-lat').val());
        const toLng = parseFloat($('#to-lng').val());
        
        if (isNaN(fromLat) || isNaN(fromLng) || isNaN(toLat) || isNaN(toLng)) {
            alert('Please enter valid coordinates');
            return;
        }
        
        // Calculate using Haversine formula
        const R = 3959; // Earth radius in miles
        const dLat = toRad(toLat - fromLat);
        const dLng = toRad(toLng - fromLng);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(toRad(fromLat)) * Math.cos(toRad(toLat)) *
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const distance = R * c;
        
        $('#distance-result').html(` = <strong>${distance.toFixed(2)} miles</strong> (${(distance * 1.60934).toFixed(2)} km)`);
    });
    
    // Geohash encoder
    $('#encode-geohash').on('click', function() {
        const lat = parseFloat($('#geo-lat').val());
        const lng = parseFloat($('#geo-lng').val());
        const precision = parseInt($('#geo-precision').val()) || 8;
        
        if (isNaN(lat) || isNaN(lng)) {
            alert('Please enter valid coordinates');
            return;
        }
        
        // Simple geohash encoding (for demonstration)
        const base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
        let geohash = '';
        let latRange = [-90, 90];
        let lngRange = [-180, 180];
        let bits = 0;
        let ch = 0;
        
        while (geohash.length < precision) {
            if (bits % 2 === 0) {
                // Even bit: longitude
                const mid = (lngRange[0] + lngRange[1]) / 2;
                if (lng > mid) {
                    ch |= (1 << (4 - (bits % 5)));
                    lngRange[0] = mid;
                } else {
                    lngRange[1] = mid;
                }
            } else {
                // Odd bit: latitude
                const mid = (latRange[0] + latRange[1]) / 2;
                if (lat > mid) {
                    ch |= (1 << (4 - (bits % 5)));
                    latRange[0] = mid;
                } else {
                    latRange[1] = mid;
                }
            }
            
            bits++;
            
            if (bits % 5 === 0) {
                geohash += base32[ch];
                ch = 0;
            }
        }
        
        $('#geohash-result').html(` = <strong>${geohash}</strong>`);
    });
    
    // Helper function to convert degrees to radians
    function toRad(deg) {
        return deg * (Math.PI / 180);
    }
});