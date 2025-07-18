<?php
/**
 * Validation Configuration
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Rules
    |--------------------------------------------------------------------------
    */
    'custom_rules' => [
        // Add custom rule classes here
        // 'slug' => \ZipPicks\Foundation\Validation\Rules\SlugRule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Error Messages
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'required' => 'The :attribute field is required.',
        'email' => 'The :attribute must be a valid email address.',
        'min_length' => 'The :attribute must be at least :min characters.',
        'max_length' => 'The :attribute may not be greater than :max characters.',
        'numeric' => 'The :attribute must be a number.',
        'integer' => 'The :attribute must be an integer.',
        'boolean' => 'The :attribute field must be true or false.',
        'array' => 'The :attribute must be an array.',
        'string' => 'The :attribute must be a string.',
        'url' => 'The :attribute format is invalid.',
        'in' => 'The selected :attribute is invalid.',
        'not_in' => 'The selected :attribute is invalid.',
        'confirmed' => 'The :attribute confirmation does not match.',
        'unique' => 'The :attribute has already been taken.',
        'exists' => 'The selected :attribute is invalid.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Attribute Names
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'email' => 'email address',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'username' => 'username',
        'first_name' => 'first name',
        'last_name' => 'last name',
        'phone' => 'phone number',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stop On First Failure
    |--------------------------------------------------------------------------
    */
    'stop_on_first_failure' => false,

    /*
    |--------------------------------------------------------------------------
    | Bail On First Failure Per Field
    |--------------------------------------------------------------------------
    */
    'bail' => true,
];