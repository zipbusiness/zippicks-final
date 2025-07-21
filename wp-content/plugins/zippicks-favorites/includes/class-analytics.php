<?php
namespace ZipPicks\Favorites;

if (!defined('ABSPATH')) {
    exit;
}

class Analytics {
    
    public function get_analytics_data() {
        global $wpdb;
        $favorites_table = Database::get_favorites_table();
        
        $data = [
            'total_favorites' => $this->get_total_favorites(),
            'favorites_change_7d' => $this->get_favorites_change(7),
            'active_users' => $this->get_active_users_count(),
            'user_percentage' => $this->get_user_percentage(),
            'avg_per_user' => $this->get_average_favorites_per_user(),
            'top_cities' => $this->get_top_cities(10),
            'top_businesses' => $this->get_top_businesses(10),
            'location_patterns' => $this->get_location_patterns(),
            'timeline_data' => $this->get_timeline_data(30),
            'city_distribution' => $this->get_city_distribution()
        ];
        
        return $data;
    }
    
    public function get_dashboard_summary() {
        return [
            'total_favorites' => $this->get_total_favorites(),
            'favorites_this_week' => $this->get_favorites_count_since(7),
            'top_city' => $this->get_top_city_name()
        ];
    }
    
    private function get_total_favorites() {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return intval($wpdb->get_var("SELECT COUNT(*) FROM $table"));
    }
    
    private function get_favorites_count_since($days) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE saved_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        )));
    }
    
    private function get_favorites_change($days) {
        $current_period = $this->get_favorites_count_since($days);
        $previous_period = $this->get_favorites_count_between($days * 2, $days);
        
        if ($previous_period == 0) {
            return 100; // 100% increase if no previous data
        }
        
        return round((($current_period - $previous_period) / $previous_period) * 100, 1);
    }
    
    private function get_favorites_count_between($days_ago, $days_until) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE saved_date >= DATE_SUB(NOW(), INTERVAL %d DAY) 
             AND saved_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_ago,
            $days_until
        )));
    }
    
    private function get_active_users_count() {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM $table"
        ));
    }
    
    private function get_user_percentage() {
        $active_users = $this->get_active_users_count();
        $total_users = count_users()['total_users'];
        
        if ($total_users == 0) {
            return 0;
        }
        
        return round(($active_users / $total_users) * 100, 1);
    }
    
    private function get_average_favorites_per_user() {
        $total_favorites = $this->get_total_favorites();
        $active_users = $this->get_active_users_count();
        
        if ($active_users == 0) {
            return 0;
        }
        
        return round($total_favorites / $active_users, 1);
    }
    
    private function get_top_cities($limit = 10) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT city, state, COUNT(*) as count
            FROM $table
            WHERE city IS NOT NULL AND state IS NOT NULL
            GROUP BY city, state
            ORDER BY count DESC
            LIMIT %d
        ", $limit));
        
        return array_map(function($row) {
            return [
                'city' => $row->city,
                'state' => $row->state,
                'count' => intval($row->count),
                'display_name' => $row->city . ', ' . $row->state
            ];
        }, $results);
    }
    
    private function get_top_city_name() {
        $top_cities = $this->get_top_cities(1);
        return !empty($top_cities) ? $top_cities[0]['display_name'] : __('N/A', 'zippicks-favorites');
    }
    
    private function get_top_businesses($limit = 10) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                f.business_id,
                p.post_title as business_name,
                COUNT(*) as favorite_count,
                MAX(f.city) as city,
                MAX(f.state) as state,
                GROUP_CONCAT(DISTINCT t.name) as category
            FROM $table f
            LEFT JOIN {$wpdb->posts} p ON f.business_id = p.ID
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND tt.taxonomy = 'business_category'
            WHERE p.post_status = 'publish'
            GROUP BY f.business_id, p.post_title
            ORDER BY favorite_count DESC
            LIMIT %d
        ", $limit));
    }
    
    private function get_location_patterns() {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return $wpdb->get_results("
            SELECT 
                city,
                state,
                COUNT(*) as total_favorites,
                COUNT(DISTINCT user_id) as unique_users
            FROM $table
            WHERE city IS NOT NULL AND state IS NOT NULL
            GROUP BY city, state
            ORDER BY total_favorites DESC
            LIMIT 20
        ");
    }
    
    private function get_timeline_data($days = 30) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(saved_date) as date,
                COUNT(*) as count
            FROM $table
            WHERE saved_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(saved_date)
            ORDER BY date ASC
        ", $days));
        
        // Fill in missing dates
        $data = [];
        $current_date = new \DateTime("-{$days} days");
        $end_date = new \DateTime();
        
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            $count = 0;
            
            foreach ($results as $row) {
                if ($row->date === $date_str) {
                    $count = intval($row->count);
                    break;
                }
            }
            
            $data[] = [
                'date' => $date_str,
                'count' => $count
            ];
            
            $current_date->modify('+1 day');
        }
        
        return $data;
    }
    
    private function get_city_distribution() {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $results = $wpdb->get_results("
            SELECT 
                city,
                COUNT(*) as count
            FROM $table
            WHERE city IS NOT NULL
            GROUP BY city
            ORDER BY count DESC
            LIMIT 5
        ");
        
        return array_map(function($row) {
            return [
                'city' => $row->city,
                'count' => intval($row->count)
            ];
        }, $results);
    }
    
    /**
     * Get heatmap data for geographical visualization
     */
    public function get_heatmap_data() {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return $wpdb->get_results("
            SELECT 
                latitude,
                longitude,
                COUNT(*) as intensity,
                city,
                state
            FROM $table
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY ROUND(latitude, 2), ROUND(longitude, 2), city, state
            HAVING intensity > 1
            ORDER BY intensity DESC
            LIMIT 500
        ");
    }
    
    /**
     * Get user engagement metrics
     */
    public function get_user_engagement_metrics() {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        return [
            'power_users' => $wpdb->get_results("
                SELECT 
                    user_id,
                    COUNT(*) as favorite_count,
                    COUNT(DISTINCT city) as cities_count,
                    MIN(saved_date) as first_favorite,
                    MAX(saved_date) as last_favorite
                FROM $table
                GROUP BY user_id
                HAVING favorite_count >= 10
                ORDER BY favorite_count DESC
                LIMIT 20
            "),
            'retention_30d' => $this->calculate_retention(30),
            'avg_time_to_second_favorite' => $this->get_avg_time_to_second_favorite()
        ];
    }
    
    private function calculate_retention($days) {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        // Users who saved a favorite X days ago
        $cohort_users = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM $table
            WHERE DATE(saved_date) = DATE_SUB(CURDATE(), INTERVAL %d DAY)
        ", $days));
        
        if ($cohort_users == 0) {
            return 0;
        }
        
        // Of those users, how many saved another favorite since then
        $retained_users = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT f1.user_id)
            FROM $table f1
            INNER JOIN $table f2 ON f1.user_id = f2.user_id
            WHERE DATE(f1.saved_date) = DATE_SUB(CURDATE(), INTERVAL %d DAY)
            AND f2.saved_date > f1.saved_date
        ", $days));
        
        return round(($retained_users / $cohort_users) * 100, 1);
    }
    
    private function get_avg_time_to_second_favorite() {
        global $wpdb;
        $table = Database::get_favorites_table();
        
        $result = $wpdb->get_var("
            SELECT AVG(TIMESTAMPDIFF(HOUR, first_save, second_save)) as avg_hours
            FROM (
                SELECT 
                    user_id,
                    MIN(saved_date) as first_save,
                    (
                        SELECT MIN(saved_date) 
                        FROM $table f2 
                        WHERE f2.user_id = f1.user_id 
                        AND f2.saved_date > f1.saved_date
                    ) as second_save
                FROM $table f1
                GROUP BY user_id
                HAVING second_save IS NOT NULL
            ) as user_saves
        ");
        
        return $result ? round($result, 1) : 0;
    }
}