/**
 * Security Helper for ZipPicks Smart Search
 * Provides HTML escaping and sanitization functions
 */

window.ZipPicksSecurity = {
    
    /**
     * Get CSP nonce from localized data
     * 
     * @return {string|null} CSP nonce or null if not available
     */
    getCspNonce: function() {
        if (typeof zippicks_search !== 'undefined' && 
            zippicks_search.security && 
            zippicks_search.security.csp_nonce) {
            return zippicks_search.security.csp_nonce;
        }
        return null;
    },
    
    /**
     * Create script element with CSP nonce
     * 
     * @param {string} content Script content
     * @return {HTMLScriptElement} Script element with nonce
     */
    createScriptElement: function(content) {
        const script = document.createElement('script');
        const nonce = this.getCspNonce();
        if (nonce) {
            script.setAttribute('nonce', nonce);
        }
        script.textContent = content;
        return script;
    },
    
    /**
     * Create style element with CSP nonce
     * 
     * @param {string} content Style content
     * @return {HTMLStyleElement} Style element with nonce
     */
    createStyleElement: function(content) {
        const style = document.createElement('style');
        const nonce = this.getCspNonce();
        if (nonce) {
            style.setAttribute('nonce', nonce);
        }
        style.textContent = content;
        return style;
    },
    
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
            "'": '&#39;'
        };
        
        return str.replace(/[&<>"']/g, function(char) {
            return map[char];
        });
    },
    
    /**
     * Escape HTML attribute with comprehensive character set
     * 
     * @param {string} attr Attribute value to escape
     * @return {string} Escaped attribute
     */
    escapeAttr: function(attr) {
        if (typeof attr !== 'string') {
            return '';
        }
        
        // Comprehensive set of characters that can be problematic in HTML attributes
        // Including: quotes, angle brackets, ampersand, equals, backticks, 
        // control characters, and other special characters
        return attr.replace(/["'<>&=`\x00-\x1F\x7F-\x9F]/g, function(char) {
            return '&#' + char.charCodeAt(0) + ';';
        });
    },
    
    /**
     * Sanitize URL with comprehensive security checks
     * 
     * @param {string} url URL to sanitize
     * @return {string} Sanitized URL or '#' for unsafe URLs
     */
    sanitizeUrl: function(url) {
        if (typeof url !== 'string') {
            return '#';
        }
        
        // Trim whitespace
        url = url.trim();
        
        // Decode URL to catch encoded malicious protocols
        // Decode multiple times to handle double/triple encoding
        let decodedUrl = url;
        let previousUrl = '';
        let decodeCount = 0;
        
        while (decodedUrl !== previousUrl && decodeCount < 3) {
            previousUrl = decodedUrl;
            try {
                decodedUrl = decodeURIComponent(decodedUrl);
            } catch (e) {
                // If decoding fails, work with what we have
                break;
            }
            decodeCount++;
        }
        
        // Check decoded URL for dangerous protocols
        // Expanded list includes vbscript, file, and other dangerous schemes
        const dangerousProtocols = /^(javascript|data|vbscript|file|about|chrome|ms-cxh|ms-cxh-full|ms-word):/i;
        if (dangerousProtocols.test(decodedUrl)) {
            return '#';
        }
        
        // Handle protocol-relative URLs (//)
        if (url.startsWith('//')) {
            // Only allow protocol-relative URLs for known safe domains
            // In production, you might want to check against a whitelist
            // For now, we'll allow them but you can add domain validation here
            return url;
        }
        
        // Handle query-only URLs (?)
        if (url.startsWith('?')) {
            // Query-only URLs are safe as they're relative to current page
            return url;
        }
        
        // Handle fragment-only URLs (#)
        if (url.startsWith('#')) {
            return url;
        }
        
        // Check for valid URL patterns
        // Allow: http(s)://, /, relative paths
        const validUrlPattern = /^(https?:\/\/|\/(?!\/)|\.{0,2}\/|[a-zA-Z0-9])/i;
        if (!validUrlPattern.test(url)) {
            return '#';
        }
        
        // Additional check for sneaky protocol injections after URL manipulation
        // Check for colon that might indicate a protocol after the start
        const suspiciousColon = /^[^:/?#]+:[^/?#]/;
        if (suspiciousColon.test(url) && !url.match(/^https?:/i)) {
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
     * Parse and sanitize HTML string with allowed tags using DOMParser
     * 
     * @param {string} html HTML string
     * @param {array} allowedTags Allowed HTML tags
     * @param {array} allowedAttributes Allowed attributes (optional)
     * @return {string} Sanitized HTML
     */
    sanitizeHtml: function(html, allowedTags = ['strong', 'em', 'span'], allowedAttributes = []) {
        if (typeof html !== 'string') {
            return '';
        }
        
        // Use DOMParser for safe parsing
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Store reference to this for use in nested function
        const self = this;
        
        // Define dangerous attributes that should always be removed
        const dangerousAttributes = [
            'onclick', 'onload', 'onerror', 'onmouseover', 'onfocus', 'onblur',
            'onchange', 'onsubmit', 'onkeydown', 'onkeyup', 'onkeypress',
            'ondblclick', 'onmousedown', 'onmouseup', 'onmousemove', 'onmouseout',
            'oncontextmenu', 'ondrag', 'ondrop', 'oncopy', 'oncut', 'onpaste',
            'oninput', 'oninvalid', 'onreset', 'onscroll', 'onsearch', 'onselect',
            'ontouchstart', 'ontouchend', 'ontouchmove', 'ontouchcancel'
        ];
        
        // Recursive function to sanitize nodes
        const sanitizeNode = (node) => {
            // Handle text nodes - always safe
            if (node.nodeType === Node.TEXT_NODE) {
                return document.createTextNode(node.textContent);
            }
            
            // Handle element nodes
            if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.tagName.toLowerCase();
                
                // Check if tag is allowed
                if (!allowedTags.includes(tagName)) {
                    // Replace disallowed tag with its children
                    const fragment = document.createDocumentFragment();
                    for (let child of node.childNodes) {
                        const sanitizedChild = sanitizeNode(child);
                        if (sanitizedChild) {
                            fragment.appendChild(sanitizedChild);
                        }
                    }
                    return fragment;
                }
                
                // Create new clean element
                const cleanElement = document.createElement(tagName);
                
                // Process attributes
                for (let i = node.attributes.length - 1; i >= 0; i--) {
                    const attr = node.attributes[i];
                    const attrName = attr.name.toLowerCase();
                    
                    // Skip dangerous attributes
                    if (dangerousAttributes.includes(attrName)) {
                        continue;
                    }
                    
                    // Skip attributes starting with 'on' (catch-all for event handlers)
                    if (attrName.startsWith('on')) {
                        continue;
                    }
                    
                    // Skip href/src unless explicitly allowed
                    if ((attrName === 'href' || attrName === 'src') && !allowedAttributes.includes(attrName)) {
                        continue;
                    }
                    
                    // Check if attribute is in allowed list (if list is provided)
                    if (allowedAttributes.length > 0 && !allowedAttributes.includes(attrName)) {
                        continue;
                    }
                    
                    // Sanitize attribute value
                    let attrValue = attr.value;
                    
                    // Special handling for href/src
                    if (attrName === 'href' || attrName === 'src') {
                        attrValue = self.sanitizeUrl(attrValue);
                    }
                    
                    // Set the sanitized attribute
                    cleanElement.setAttribute(attrName, attrValue);
                }
                
                // Process child nodes
                for (let child of node.childNodes) {
                    const sanitizedChild = sanitizeNode(child);
                    if (sanitizedChild) {
                        cleanElement.appendChild(sanitizedChild);
                    }
                }
                
                return cleanElement;
            }
            
            // Ignore other node types (comments, etc.)
            return null;
        };
        
        // Create container for sanitized content
        const container = document.createElement('div');
        
        // Sanitize all child nodes of the body
        for (let child of doc.body.childNodes) {
            const sanitizedChild = sanitizeNode(child);
            if (sanitizedChild) {
                container.appendChild(sanitizedChild);
            }
        }
        
        // Return the sanitized HTML
        return container.innerHTML;
    },
    
    /**
     * Validate email address with enterprise-grade validation
     * 
     * @param {string} email Email to validate
     * @return {object} Validation result with detailed feedback
     */
    validateEmail: function(email) {
        // Type and basic checks
        if (typeof email !== 'string') {
            return { valid: false, error: 'Email must be a string' };
        }
        
        const trimmedEmail = email.trim();
        
        // Length checks
        if (trimmedEmail.length === 0) {
            return { valid: false, error: 'Email is required' };
        }
        
        if (trimmedEmail.length > 254) {
            return { valid: false, error: 'Email is too long (max 254 characters)' };
        }
        
        // Basic format validation (RFC 5322 simplified)
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        
        if (!emailRegex.test(trimmedEmail)) {
            return { valid: false, error: 'Invalid email format' };
        }
        
        // Check for security threats
        const dangerous = /<script|javascript:|on\w+\s*=|<iframe|<object|<embed/i;
        if (dangerous.test(trimmedEmail)) {
            return { valid: false, error: 'Email contains invalid characters' };
        }
        
        // Split local and domain parts
        const [localPart, domainPart] = trimmedEmail.split('@');
        
        // Local part validation
        if (localPart.length > 64) {
            return { valid: false, error: 'Email local part is too long (max 64 characters)' };
        }
        
        // Domain part validation
        if (domainPart.length > 253) {
            return { valid: false, error: 'Email domain is too long (max 253 characters)' };
        }
        
        // Check for valid TLD (at least 2 characters)
        const domainParts = domainPart.split('.');
        const tld = domainParts[domainParts.length - 1];
        if (tld.length < 2) {
            return { valid: false, error: 'Invalid domain extension' };
        }
        
        return { 
            valid: true, 
            email: trimmedEmail,
            localPart: localPart,
            domainPart: domainPart
        };
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