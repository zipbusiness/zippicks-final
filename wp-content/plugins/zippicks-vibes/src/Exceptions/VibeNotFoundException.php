<?php
/**
 * Vibe Not Found Exception
 * 
 * Thrown when a requested vibe cannot be found
 * 
 * @package ZipPicksVibes\Exceptions
 * @since 2.0.0
 */

namespace ZipPicksVibes\Exceptions;

use Exception;

class VibeNotFoundException extends Exception {
    /**
     * Constructor
     * 
     * @param string $identifier The vibe identifier (ID or slug)
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     */
    public function __construct($identifier = '', $code = 404, Exception $previous = null) {
        $message = sprintf('Vibe not found: %s', $identifier);
        parent::__construct($message, $code, $previous);
    }
}