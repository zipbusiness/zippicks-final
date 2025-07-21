<?php
/**
 * Invalid Vibe Data Exception
 * 
 * Thrown when vibe data validation fails
 * 
 * @package ZipPicksVibes\Exceptions
 * @since 2.0.0
 */

namespace ZipPicksVibes\Exceptions;

use Exception;

class InvalidVibeDataException extends Exception {
    /**
     * Validation errors
     * 
     * @var array
     */
    private $errors = [];
    
    /**
     * Constructor
     * 
     * @param string $message Error message
     * @param array $errors Validation errors
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     */
    public function __construct($message = 'Invalid vibe data', array $errors = [], $code = 400, Exception $previous = null) {
        $this->errors = $errors;
        
        if (!empty($errors)) {
            $message .= ': ' . implode(', ', array_map(function($field, $error) {
                return sprintf('%s - %s', $field, $error);
            }, array_keys($errors), $errors));
        }
        
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }
}