/**
 * Security Helper for ZipPicks Smart Search
 * Provides HTML escaping and sanitization functions
 */

window.ZipPicksSecurity = {
    
    /**
     * Escape HTML entities
     * 
     * @param {string} str String to escape
     * @return {string} Escaped string
     */
    escapeHtml: function(str) {
        if (typeof str !== 'string') {
            return '';
        }
        
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '/': '&#x2F;'
        };
        
        return str.replace(/[&<>"'\/]/g, function(char) {
            return map[char];
        });
    },
    
    /**
     * Escape HTML attribute
     * 
     * @param {string} attr Attribute value to escape
     * @return {string} Escaped attribute
     */
    escapeAttr: function(attr) {
        if (typeof attr !== 'string') {
            return '';
        }
        
        return attr.replace(/["'<>&]/g, function(char) {
            return '&#' + char.charCodeAt(0) + ';';
        });
    },
    
    /**
     * Sanitize URL
     * 
     * @param {string} url URL to sanitize
     * @return {string} Sanitized URL
     */
    sanitizeUrl: function(url) {
        if (typeof url !== 'string') {
            return '#';
        }
        
        // Remove javascript: and data: protocols
        if (url.match(/^(javascript|data):/i)) {
            return '#';
        }
        
        // Ensure protocol is http(s) or relative
        if (!url.match(/^(https?:\/\/|\/|#)/i)) {
            return '#';
        }
        
        return url;
    },
    
    /**
     * Sanitize ZPID
     * 
     * @param {string} zpid ZPID to sanitize
     * @return {string} Sanitized ZPID
     */
    sanitizeZpid: function(zpid) {
        if (typeof zpid !== 'string') {
            return '';
        }
        
        // Only allow alphanumeric and hyphens
        return zpid.replace(/[^a-zA-Z0-9\-]/g, '');
    },
    
    /**
     * Create safe text node
     * 
     * @param {string} text Text content
     * @return {Text} Text node
     */
    createTextNode: function(text) {
        return document.createTextNode(text || '');
    },
    
    /**
     * Create element with safe attributes
     * 
     * @param {string} tag HTML tag
     * @param {object} attrs Attributes
     * @param {string|Node} content Content
     * @return {HTMLElement} Safe element
     */
    createElement: function(tag, attrs = {}, content = '') {
        const element = document.createElement(tag);
        
        // Set attributes safely
        for (const [key, value] of Object.entries(attrs)) {
            if (key === 'href') {
                element.setAttribute(key, this.sanitizeUrl(value));
            } else if (key === 'class' || key === 'id') {
                element.setAttribute(key, this.escapeAttr(value));
            } else if (key.startsWith('data-')) {
                element.setAttribute(key, this.escapeAttr(value));
            }
        }
        
        // Add content
        if (typeof content === 'string') {
            element.textContent = content;
        } else if (content instanceof Node) {
            element.appendChild(content);
        }
        
        return element;
    },
    
    /**
     * Parse and sanitize HTML string with allowed tags
     * 
     * @param {string} html HTML string
     * @param {array} allowedTags Allowed HTML tags
     * @return {string} Sanitized HTML
     */
    sanitizeHtml: function(html, allowedTags = ['strong', 'em', 'span']) {
        if (typeof html !== 'string') {
            return '';
        }
        
        // Create temporary container
        const temp = document.createElement('div');
        temp.textContent = html;
        
        // Replace allowed tags
        allowedTags.forEach(tag => {
            const regex = new RegExp(`&lt;(\/?)${tag}&gt;`, 'gi');
            temp.innerHTML = temp.innerHTML.replace(regex, `<$1${tag}>`);
        });
        
        return temp.innerHTML;
    },
    
    /**
     * Validate email address
     * 
     * @param {string} email Email to validate
     * @return {boolean} Is valid
     */
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    /**
     * Validate search query
     * 
     * @param {string} query Search query
     * @return {object} Validation result
     */
    validateSearchQuery: function(query) {
        // Check length
        if (query.length > 100) {
            return {
                valid: false,
                error: 'Search query is too long (max 100 characters)'
            };
        }
        
        // Check for malicious patterns
        const dangerous = /<script|javascript:|on\w+\s*=|<iframe|<object|<embed/i;
        if (dangerous.test(query)) {
            return {
                valid: false,
                error: 'Invalid characters in search query'
            };
        }
        
        return {
            valid: true,
            sanitized: this.escapeHtml(query)
        };
    },
    
    /**
     * Safe JSON parse
     * 
     * @param {string} json JSON string
     * @param {*} defaultValue Default value on error
     * @return {*} Parsed value or default
     */
    parseJSON: function(json, defaultValue = null) {
        try {
            return JSON.parse(json);
        } catch (e) {
            console.error('JSON parse error:', e);
            return defaultValue;
        }
    },
    
    /**
     * Check rate limit response
     * 
     * @param {object} response AJAX response
     * @return {boolean} Is rate limited
     */
    isRateLimited: function(response) {
        return response && 
               (response.status === 429 || 
                (response.data && response.data.retry_after));
    },
    
    /**
     * Handle rate limit error
     * 
     * @param {object} response Rate limit response
     * @param {function} callback Retry callback
     */
    handleRateLimit: function(response, callback) {
        const retryAfter = response.data && response.data.retry_after || 60;
        const message = response.data && response.data.message || 
                       'Too many requests. Please try again later.';
        
        // Show user message
        this.showError(message);
        
        // Optionally retry after delay
        if (callback && typeof callback === 'function') {
            setTimeout(callback, retryAfter * 1000);
        }
    },
    
    /**
     * Show error message (override this in your implementation)
     * 
     * @param {string} message Error message
     */
    showError: function(message) {
        console.error('Security Error:', message);
        // Override this method to show errors in your UI
    }
};