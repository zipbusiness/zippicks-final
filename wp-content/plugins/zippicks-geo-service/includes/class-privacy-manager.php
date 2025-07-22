<?php
/**
 * Privacy Manager Class
 * 
 * Handles user privacy settings and location preferences
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class Privacy_Manager {
    
    /**
     * Privacy levels
     */
    const PRIVACY_PRECISE = 'precise';
    const PRIVACY_CITY = 'city';
    const PRIVACY_STATE = 'state';
    
    /**
     * Default preferences
     */
    private $default_preferences = [
        'zip_code' => '',
        'allow_gps' => false,
        'privacy_level' => self::PRIVACY_CITY,
        'default_radius_miles' => 5,
        'share_location' => true,
        'store_history' => false,
        'last_known_location' => null,
    ];
    
    /**
     * Get user preferences
     * 
     * @param int $user_id
     * @return array
     */
    public function get_preferences($user_id) {
        $preferences = get_user_meta($user_id, 'zippicks_location_preferences', true);
        
        if (!is_array($preferences)) {
            $preferences = [];
        }
        
        // Merge with defaults
        return wp_parse_args($preferences, $this->default_preferences);
    }
    
    /**
     * Save user preferences
     * 
     * @param int $user_id
     * @param array $preferences
     * @return bool
     */
    public function save_preferences($user_id, $preferences) {
        // Validate preferences
        $validated = $this->validate_preferences($preferences);
        
        // Merge with existing
        $existing = $this->get_preferences($user_id);
        $updated = wp_parse_args($validated, $existing);
        
        return update_user_meta($user_id, 'zippicks_location_preferences', $updated);
    }
    
    /**
     * Validate preferences
     * 
     * @param array $preferences
     * @return array
     */
    private function validate_preferences($preferences) {
        $validated = [];
        
        // Validate ZIP code
        if (isset($preferences['zip_code'])) {
            $zip = preg_replace('/[^0-9-]/', '', $preferences['zip_code']);
            if (strlen($zip) >= 5) {
                $validated['zip_code'] = substr($zip, 0, 10);
            }
        }
        
        // Validate boolean fields
        $bool_fields = ['allow_gps', 'share_location', 'store_history'];
        foreach ($bool_fields as $field) {
            if (isset($preferences[$field])) {
                $validated[$field] = (bool) $preferences[$field];
            }
        }
        
        // Validate privacy level
        if (isset($preferences['privacy_level'])) {
            $valid_levels = [self::PRIVACY_PRECISE, self::PRIVACY_CITY, self::PRIVACY_STATE];
            if (in_array($preferences['privacy_level'], $valid_levels)) {
                $validated['privacy_level'] = $preferences['privacy_level'];
            }
        }
        
        // Validate radius
        if (isset($preferences['default_radius_miles'])) {
            $radius = (float) $preferences['default_radius_miles'];
            if ($radius >= 1 && $radius <= 100) {
                $validated['default_radius_miles'] = $radius;
            }
        }
        
        return $validated;
    }
    
    /**
     * Check if user allows GPS
     * 
     * @param int $user_id
     * @return bool
     */
    public function allows_gps($user_id) {
        $preferences = $this->get_preferences($user_id);
        return $preferences['allow_gps'];
    }
    
    /**
     * Get privacy level
     * 
     * @param int $user_id
     * @return string
     */
    public function get_privacy_level($user_id) {
        $preferences = $this->get_preferences($user_id);
        return $preferences['privacy_level'];
    }
    
    /**
     * Apply privacy filter to location
     * 
     * @param array $location
     * @param int $user_id
     * @return array
     */
    public function apply_privacy_filter($location, $user_id) {
        $privacy_level = $this->get_privacy_level($user_id);
        
        switch ($privacy_level) {
            case self::PRIVACY_STATE:
                // Only return state-level accuracy
                $location['latitude'] = round($location['latitude'], 0);
                $location['longitude'] = round($location['longitude'], 0);
                $location['accuracy'] = 'state';
                $location['accuracy_meters'] = 100000;
                unset($location['city']);
                unset($location['zip_code']);
                break;
                
            case self::PRIVACY_CITY:
                // Round to city-level accuracy
                $location['latitude'] = round($location['latitude'], 1);
                $location['longitude'] = round($location['longitude'], 1);
                $location['accuracy'] = 'city';
                $location['accuracy_meters'] = 10000;
                unset($location['zip_code']);
                break;
                
            case self::PRIVACY_PRECISE:
                // Return full precision
                break;
        }
        
        return $location;
    }
    
    /**
     * Render user profile fields
     * 
     * @param \WP_User $user
     */
    public function render_user_fields($user) {
        $preferences = $this->get_preferences($user->ID);
        ?>
        <h3><?php _e('Location Preferences', 'zippicks-geo'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="zippicks_zip_code"><?php _e('Default ZIP Code', 'zippicks-geo'); ?></label></th>
                <td>
                    <input type="text" 
                           id="zippicks_zip_code"
                           name="zippicks_location[zip_code]"
                           value="<?php echo esc_attr($preferences['zip_code']); ?>"
                           class="regular-text"
                           placeholder="90210" />
                    <p class="description">
                        <?php _e('Your default location when GPS is not available', 'zippicks-geo'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><?php _e('Location Permissions', 'zippicks-geo'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="zippicks_location[allow_gps]" 
                                   value="1"
                                   <?php checked($preferences['allow_gps']); ?> />
                            <?php _e('Allow GPS location detection', 'zippicks-geo'); ?>
                        </label>
                        <br>
                        
                        <label>
                            <input type="checkbox" 
                                   name="zippicks_location[share_location]" 
                                   value="1"
                                   <?php checked($preferences['share_location']); ?> />
                            <?php _e('Share my location with businesses (when messaging)', 'zippicks-geo'); ?>
                        </label>
                        <br>
                        
                        <label>
                            <input type="checkbox" 
                                   name="zippicks_location[store_history]" 
                                   value="1"
                                   <?php checked($preferences['store_history']); ?> />
                            <?php _e('Store location history (for personalized recommendations)', 'zippicks-geo'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th><label for="zippicks_privacy_level"><?php _e('Privacy Level', 'zippicks-geo'); ?></label></th>
                <td>
                    <select id="zippicks_privacy_level" name="zippicks_location[privacy_level]">
                        <option value="precise" <?php selected($preferences['privacy_level'], 'precise'); ?>>
                            <?php _e('Precise location (exact coordinates)', 'zippicks-geo'); ?>
                        </option>
                        <option value="city" <?php selected($preferences['privacy_level'], 'city'); ?>>
                            <?php _e('City level only (rounded coordinates)', 'zippicks-geo'); ?>
                        </option>
                        <option value="state" <?php selected($preferences['privacy_level'], 'state'); ?>>
                            <?php _e('State level only (very approximate)', 'zippicks-geo'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Controls how much location detail is shared', 'zippicks-geo'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="zippicks_default_radius"><?php _e('Default Search Radius', 'zippicks-geo'); ?></label></th>
                <td>
                    <input type="number" 
                           id="zippicks_default_radius"
                           name="zippicks_location[default_radius_miles]"
                           value="<?php echo esc_attr($preferences['default_radius_miles']); ?>"
                           min="1"
                           max="100"
                           step="1"
                           class="small-text" />
                    <span><?php _e('miles', 'zippicks-geo'); ?></span>
                    <p class="description">
                        <?php _e('Default radius for "near me" searches', 'zippicks-geo'); ?>
                    </p>
                </td>
            </tr>
            
            <?php if (!empty($preferences['last_known_location'])) : ?>
            <tr>
                <th><?php _e('Last Known Location', 'zippicks-geo'); ?></th>
                <td>
                    <?php
                    $last = $preferences['last_known_location'];
                    $time_ago = human_time_diff($last['timestamp'], current_time('timestamp'));
                    printf(
                        __('Located %s ago at coordinates %s, %s', 'zippicks-geo'),
                        $time_ago,
                        round($last['lat'], 4),
                        round($last['lng'], 4)
                    );
                    ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php wp_nonce_field('zippicks_location_preferences', 'zippicks_location_nonce'); ?>
        <?php
    }
    
    /**
     * Save user profile fields
     * 
     * @param int $user_id
     */
    public function save_user_fields($user_id) {
        // Check nonce
        if (!isset($_POST['zippicks_location_nonce']) || 
            !wp_verify_nonce($_POST['zippicks_location_nonce'], 'zippicks_location_preferences')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Save preferences
        if (isset($_POST['zippicks_location'])) {
            $this->save_preferences($user_id, $_POST['zippicks_location']);
        }
    }
    
    /**
     * Delete user location data (for GDPR compliance)
     * 
     * @param int $user_id
     * @return bool
     */
    public function delete_user_data($user_id) {
        global $wpdb;
        
        // Delete preferences
        delete_user_meta($user_id, 'zippicks_location_preferences');
        
        // Delete location history
        $table = $wpdb->prefix . 'user_locations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->delete($table, ['wp_user_id' => $user_id]);
        }
        
        // Clear cache
        $cache = new Geo_Cache();
        $cache->clear_user_location('user_' . $user_id);
        
        return true;
    }
    
    /**
     * Export user location data (for GDPR compliance)
     * 
     * @param int $user_id
     * @return array
     */
    public function export_user_data($user_id) {
        global $wpdb;
        
        $data = [
            'preferences' => $this->get_preferences($user_id),
            'location_history' => [],
        ];
        
        // Get location history
        $table = $wpdb->prefix . 'user_locations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE wp_user_id = %d ORDER BY created_at DESC",
                $user_id
            ), ARRAY_A);
            
            $data['location_history'] = $history;
        }
        
        return $data;
    }
}