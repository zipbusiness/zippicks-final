<?php
/**
 * Quality Testing Framework for Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Quality_Tester {
    
    /**
     * Known good test cases for major cities
     */
    private static $test_cases = array(
        'new york' => array(
            'pizza' => array(
                'expected' => ['Prince Street Pizza', 'Joe\'s', 'Lucali', 'Patsy\'s', 'Di Fara', 'Roberta\'s'],
                'minimum_matches' => 3
            ),
            'bagel' => array(
                'expected' => ['Russ & Daughters', 'H&H', 'Black Seed', 'Ess-a-Bagel', 'Absolute Bagels', 'Murray\'s'],
                'minimum_matches' => 3
            ),
            'steakhouse' => array(
                'expected' => ['Peter Luger', 'Keens', 'The Grill', 'Cote', 'St. Anselm', 'Wolfgang\'s'],
                'minimum_matches' => 3
            )
        ),
        'austin' => array(
            'bbq' => array(
                'expected' => ['Franklin', 'la Barbecue', 'Micklethwait', 'Kerlin', 'Terry Black\'s', 'Salt Lick'],
                'minimum_matches' => 3
            ),
            'mexican' => array(
                'expected' => ['Suerte', 'Veracruz', 'El Alma', 'Matt\'s El Rancho', 'Gabriela\'s', 'ATX Cocina'],
                'minimum_matches' => 3
            ),
            'tacos' => array(
                'expected' => ['Veracruz All Natural', 'Torchy\'s', 'Tacodeli', 'El Primo', 'Papalote', 'Valentina\'s'],
                'minimum_matches' => 3
            )
        ),
        'chicago' => array(
            'pizza' => array(
                'expected' => ['Pequod\'s', 'Lou Malnati\'s', 'Art of Pizza', 'Piece', 'Spacca Napoli', 'Burt\'s Place'],
                'minimum_matches' => 3
            ),
            'steakhouse' => array(
                'expected' => ['Gibsons', 'RPM Steak', 'Bavette\'s', 'Swift & Sons', 'Chicago Cut', 'Maple & Ash'],
                'minimum_matches' => 3
            )
        ),
        'los angeles' => array(
            'korean' => array(
                'expected' => ['Park\'s BBQ', 'Kang Ho-dong Baekjeong', 'Sun Nong Dan', 'Quarters', 'Soowon Galbi'],
                'minimum_matches' => 3
            ),
            'taco' => array(
                'expected' => ['Mariscos Jalisco', 'Leo\'s Tacos', 'Guisados', 'Guelaguetza', 'Teddy\'s Red Tacos'],
                'minimum_matches' => 3
            )
        ),
        'san francisco' => array(
            'seafood' => array(
                'expected' => ['Swan Oyster Depot', 'Waterbar', 'Hog Island', 'Farallon', 'Anchor Oyster Bar'],
                'minimum_matches' => 2
            ),
            'chinese' => array(
                'expected' => ['Z&Y Bistro', 'Mister Jiu\'s', 'Good Luck Dim Sum', 'Dragon Beaux', 'China Live'],
                'minimum_matches' => 2
            )
        )
    );
    
    /**
     * Run quality tests for a specific city and category
     *
     * @param string $city
     * @param string $category
     * @param array $businesses Generated businesses to test
     * @return array Test results
     */
    public static function test_recommendations($city, $category, $businesses) {
        $city_lower = strtolower($city);
        $category_lower = strtolower($category);
        
        $results = array(
            'city' => $city,
            'category' => $category,
            'total_businesses' => count($businesses),
            'accuracy' => 0,
            'precision' => 0,
            'relevance' => 0,
            'matches' => array(),
            'missing' => array(),
            'unexpected' => array(),
            'passed' => false
        );
        
        // Check if we have test data for this city/category
        if (!isset(self::$test_cases[$city_lower][$category_lower])) {
            $results['message'] = 'No test data available for ' . $city . ' ' . $category;
            return $results;
        }
        
        $test_data = self::$test_cases[$city_lower][$category_lower];
        $expected = $test_data['expected'];
        $minimum_matches = $test_data['minimum_matches'];
        
        // Extract business names from results
        $business_names = array_map(function($b) {
            return $b['name'];
        }, $businesses);
        
        // Check for matches
        $matches = 0;
        foreach ($expected as $expected_name) {
            $found = false;
            foreach ($business_names as $actual_name) {
                if (self::fuzzy_match($expected_name, $actual_name)) {
                    $results['matches'][] = $expected_name;
                    $matches++;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $results['missing'][] = $expected_name;
            }
        }
        
        // Find unexpected businesses
        foreach ($business_names as $actual_name) {
            $is_expected = false;
            foreach ($expected as $expected_name) {
                if (self::fuzzy_match($expected_name, $actual_name)) {
                    $is_expected = true;
                    break;
                }
            }
            if (!$is_expected) {
                $results['unexpected'][] = $actual_name;
            }
        }
        
        // Calculate metrics
        $results['accuracy'] = ($matches / count($expected)) * 100;
        $results['precision'] = count($businesses) > 0 ? ($matches / count($businesses)) * 100 : 0;
        $results['relevance'] = min(100, ($matches / $minimum_matches) * 100);
        $results['passed'] = $matches >= $minimum_matches;
        
        return $results;
    }
    
    /**
     * Fuzzy match business names
     *
     * @param string $expected
     * @param string $actual
     * @return bool
     */
    private static function fuzzy_match($expected, $actual) {
        // Normalize strings
        $expected = strtolower(trim($expected));
        $actual = strtolower(trim($actual));
        
        // Exact match
        if ($expected === $actual) {
            return true;
        }
        
        // Check if expected is contained in actual
        if (strpos($actual, $expected) !== false) {
            return true;
        }
        
        // Check if main part matches (before common suffixes)
        $suffixes = array(' restaurant', ' bbq', ' barbecue', ' steakhouse', ' pizza', ' tacos', ' taqueria');
        foreach ($suffixes as $suffix) {
            $expected_base = str_replace($suffix, '', $expected);
            $actual_base = str_replace($suffix, '', $actual);
            if ($expected_base === $actual_base) {
                return true;
            }
        }
        
        // Check similarity
        similar_text($expected, $actual, $percent);
        return $percent > 80;
    }
    
    /**
     * Run comprehensive test suite
     *
     * @return array Test results for all cities/categories
     */
    public static function run_test_suite() {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service.php';
        $ai_service = new ZipPicks_Master_Critic_AI_Service();
        
        $all_results = array();
        
        foreach (self::$test_cases as $city => $categories) {
            foreach ($categories as $category => $test_data) {
                // Generate recommendations
                $params = array(
                    'location' => ucfirst($city),
                    'business_category' => 'restaurant', // Assuming restaurant for all food categories
                    'topic' => $category
                );
                
                $result = $ai_service->execute_enhanced_generation($params);
                
                if ($result['success'] && isset($result['businesses'])) {
                    $test_result = self::test_recommendations($city, $category, $result['businesses']);
                    $test_result['confidence'] = $result['confidence'] ?? 0;
                    $all_results[] = $test_result;
                } else {
                    $all_results[] = array(
                        'city' => $city,
                        'category' => $category,
                        'error' => $result['error'] ?? 'Unknown error',
                        'passed' => false
                    );
                }
                
                // Sleep to respect rate limits
                sleep(2);
            }
        }
        
        return $all_results;
    }
    
    /**
     * Generate quality report
     *
     * @param array $test_results
     * @return string HTML report
     */
    public static function generate_report($test_results) {
        $total_tests = count($test_results);
        $passed_tests = count(array_filter($test_results, function($r) { return $r['passed']; }));
        $overall_accuracy = array_sum(array_column($test_results, 'accuracy')) / $total_tests;
        
        $html = '<div class="zippicks-quality-report">';
        $html .= '<h2>Master Critic Quality Test Report</h2>';
        $html .= '<div class="summary">';
        $html .= '<p><strong>Overall Results:</strong></p>';
        $html .= '<ul>';
        $html .= '<li>Tests Passed: ' . $passed_tests . '/' . $total_tests . '</li>';
        $html .= '<li>Overall Accuracy: ' . number_format($overall_accuracy, 1) . '%</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        $html .= '<h3>Detailed Results:</h3>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>City</th><th>Category</th><th>Status</th><th>Accuracy</th><th>Matches</th><th>Confidence</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($test_results as $result) {
            $status = $result['passed'] ? '<span style="color:green;">✓ Passed</span>' : '<span style="color:red;">✗ Failed</span>';
            $html .= '<tr>';
            $html .= '<td>' . ucfirst($result['city']) . '</td>';
            $html .= '<td>' . ucfirst($result['category']) . '</td>';
            $html .= '<td>' . $status . '</td>';
            $html .= '<td>' . number_format($result['accuracy'] ?? 0, 1) . '%</td>';
            $html .= '<td>' . count($result['matches'] ?? []) . '</td>';
            $html .= '<td>' . number_format($result['confidence'] ?? 0, 1) . '%</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        return $html;
    }
}