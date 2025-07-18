<?php
/**
 * ZipPicks Business API Controller
 * 
 * Handles business-related API endpoints
 *
 * @package ZipPicks\Foundation\Api\Controllers\V1
 */

namespace ZipPicks\Foundation\Api\Controllers\V1;

use ZipPicks\Foundation\Api\Controllers\ApiController;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;

class BusinessController extends ApiController
{
    /**
     * List businesses
     * GET /api/v1/businesses
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            // Get query parameters
            $page = (int) $request->query->get('page', 1);
            $perPage = min((int) $request->query->get('per_page', 20), 100);
            $zip = $request->query->get('zip');
            $vibes = $request->query->get('vibes', []);
            
            // Build query
            $args = [
                'post_type' => 'zippicks_business',
                'posts_per_page' => $perPage,
                'paged' => $page,
                'post_status' => 'publish'
            ];
            
            // Add location filter if ZIP provided
            if ($zip) {
                $args['meta_query'] = [
                    [
                        'key' => 'zip_code',
                        'value' => $zip,
                        'compare' => '='
                    ]
                ];
            }
            
            // Add vibe filter if provided
            if (!empty($vibes)) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'zippicks_vibe',
                        'field' => 'slug',
                        'terms' => $vibes,
                        'operator' => 'IN'
                    ]
                ];
            }
            
            // Execute query
            $query = new \WP_Query($args);
            
            // Format results
            $businesses = [];
            foreach ($query->posts as $post) {
                $businesses[] = $this->formatBusiness($post);
            }
            
            // Return paginated response
            return $this->paginated($businesses, $query->found_posts, $request);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get single business
     * GET /api/v1/businesses/{id}
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->attributes->get('id');
            
            // Get business
            $post = get_post($id);
            
            if (!$post || $post->post_type !== 'zippicks_business') {
                return $this->error('Business not found', 404);
            }
            
            // Format and return
            return $this->success($this->formatBusiness($post));
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Create new business
     * POST /api/v1/businesses
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        try {
            // Check permission
            $this->authorize($request, 'edit_posts');
            
            // Get validated data
            $data = $this->validated($request);
            
            // Create business post
            $postData = [
                'post_title' => $data['name'],
                'post_type' => 'zippicks_business',
                'post_status' => 'publish',
                'meta_input' => [
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'zip_code' => $data['zip'],
                    'phone' => $data['phone'] ?? '',
                    'website' => $data['website'] ?? ''
                ]
            ];
            
            $postId = wp_insert_post($postData);
            
            if (is_wp_error($postId)) {
                return $this->error('Failed to create business', 500);
            }
            
            // Assign vibes
            if (!empty($data['vibes'])) {
                wp_set_object_terms($postId, $data['vibes'], 'zippicks_vibe');
            }
            
            // Return created business
            return $this->success(
                $this->formatBusiness(get_post($postId)),
                [],
                201
            );
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update business
     * PUT /api/v1/businesses/{id}
     *
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        try {
            // Check permission
            $this->authorize($request, 'edit_posts');
            
            $id = $request->attributes->get('id');
            
            // Get business
            $post = get_post($id);
            
            if (!$post || $post->post_type !== 'zippicks_business') {
                return $this->error('Business not found', 404);
            }
            
            // Get validated data
            $data = $this->validated($request);
            
            // Update post
            $postData = [
                'ID' => $id,
                'post_title' => $data['name'] ?? $post->post_title
            ];
            
            wp_update_post($postData);
            
            // Update meta
            foreach (['address', 'city', 'state', 'zip_code', 'phone', 'website'] as $field) {
                if (isset($data[$field])) {
                    update_post_meta($id, $field, $data[$field]);
                }
            }
            
            // Update vibes
            if (isset($data['vibes'])) {
                wp_set_object_terms($id, $data['vibes'], 'zippicks_vibe');
            }
            
            // Return updated business
            return $this->success($this->formatBusiness(get_post($id)));
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete business
     * DELETE /api/v1/businesses/{id}
     *
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request): Response
    {
        try {
            // Check permission
            $this->authorize($request, 'delete_posts');
            
            $id = $request->attributes->get('id');
            
            // Get business
            $post = get_post($id);
            
            if (!$post || $post->post_type !== 'zippicks_business') {
                return $this->error('Business not found', 404);
            }
            
            // Delete post
            wp_delete_post($id, true);
            
            // Return success with no content
            return new Response('', 204);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Format business for API response
     *
     * @param \WP_Post $post
     * @return array
     */
    protected function formatBusiness(\WP_Post $post): array
    {
        // Get meta data
        $meta = get_post_meta($post->ID);
        
        // Get vibes
        $vibes = wp_get_object_terms($post->ID, 'zippicks_vibe', ['fields' => 'all']);
        $vibeData = array_map(function($term) {
            return [
                'id' => $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name
            ];
        }, $vibes);
        
        // Get Master Critic score
        $masterCriticScore = get_post_meta($post->ID, 'master_critic_score', true) ?: 0;
        
        // Get scoring pillars
        $pillars = get_post_meta($post->ID, 'scoring_pillars', true) ?: [];
        
        return [
            'id' => $post->ID,
            'name' => $post->post_title,
            'slug' => $post->post_name,
            'address' => $meta['address'][0] ?? '',
            'city' => $meta['city'][0] ?? '',
            'state' => $meta['state'][0] ?? '',
            'zip' => $meta['zip_code'][0] ?? '',
            'phone' => $meta['phone'][0] ?? null,
            'website' => $meta['website'][0] ?? null,
            'vibes' => $vibeData,
            'master_critic_score' => (float) $masterCriticScore,
            'pillars' => $pillars,
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'large'),
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified
        ];
    }
}