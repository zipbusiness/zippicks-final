/**
 * ZipPicks Business Template Hydration
 *
 * Handles client-side rendering of business templates for anti-scraping protection.
 * Uses progressive enhancement and graceful degradation.
 */
(function($) {
    'use strict';
    
    const ZipPicksTemplates = {
        
        /**
         * Initialize template hydration
         */
        init: function() {
            this.hydrateTemplates();
            this.addFingerprints();
            this.monitorCopyAttempts();
        },
        
        /**
         * Hydrate all template placeholders
         */
        hydrateTemplates: function() {
            $('.zp-template-placeholder').each(function() {
                const $placeholder = $(this);
                const template = $placeholder.data('template');
                const dataScript = $('script.zp-template-data[data-for="' + $placeholder.attr('id') + '"]');
                
                if (dataScript.length) {
                    try {
                        const data = JSON.parse(dataScript.text());
                        ZipPicksTemplates.renderTemplate($placeholder, template, data);
                    } catch (e) {
                        console.warn('ZipPicks: Failed to parse template data', e);
                        $placeholder.hide(); // Hide broken templates
                    }
                }
            });
        },
        
        /**
         * Render specific template
         */
        renderTemplate: function($placeholder, template, data) {
            let html = '';
            
            switch (template) {
                case 'business-verification-badge':
                    html = this.renderVerificationBadge(data);
                    break;
                case 'business-vibes':
                    html = this.renderBusinessVibes(data);
                    break;
                case 'zippicks-scores':
                    html = this.renderZipPicksScores(data);
                    break;
                default:
                    console.warn('ZipPicks: Unknown template type', template);
                    return;
            }
            
            if (html) {
                $placeholder.html(html);
                $placeholder.attr('data-hydrated', 'true');
                
                // Add entry animation
                $placeholder.find('.zippicks-business-verification, .zippicks-business-vibes, .zippicks-scores')
                    .css('opacity', '0')
                    .animate({opacity: 1}, 300);
            }
        },
        
        /**
         * Render verification badge
         */
        renderVerificationBadge: function(data) {
            if (!data.is_verified || !data.zpid) {
                return '';
            }
            
            let confidenceHtml = '';
            if (data.confidence && data.confidence >= 0.8) {
                confidenceHtml = '<span class="confidence-score" title="Confidence: ' + Math.round(data.confidence * 100) + '%">' +
                    '(' + Math.round(data.confidence * 100) + '%)</span>';
            }
            
            return '<div class="zippicks-business-verification">' +
                '<span class="verified-badge">' +
                '<svg class="verification-icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">' +
                '<path d="M8 0L10.5 2.5L14 3L14.5 6.5L16 9L14.5 11.5L14 15L10.5 13.5L8 16L5.5 13.5L2 15L1.5 11.5L0 9L1.5 6.5L2 3L5.5 2.5L8 0Z" fill="#22C55E"/>' +
                '<path d="M11.5 5.5L7 10L4.5 7.5" stroke="white" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>' +
                '</svg>' +
                '<span class="verification-text">Verified by ZipBusiness</span>' +
                confidenceHtml +
                '</span>' +
                '</div>';
        },
        
        /**
         * Render business vibes
         */
        renderBusinessVibes: function(data) {
            if (!data.vibes || data.vibes.length === 0) {
                return '';
            }
            
            let titleHtml = '';
            if (data.display_mode === 'full') {
                titleHtml = '<h3 class="vibes-title">The Vibe</h3>';
            }
            
            let vibesHtml = '';
            data.vibes.forEach(function(vibe, index) {
                const confidence = vibe.confidence || 0;
                let confidenceClass = 'confidence-low';
                
                if (confidence >= 0.9) {
                    confidenceClass = 'confidence-high';
                } else if (confidence >= 0.75) {
                    confidenceClass = 'confidence-medium';
                }
                
                let confidenceIndicator = '';
                if (data.display_mode === 'full' && confidence >= 0.8) {
                    confidenceIndicator = '<span class="confidence-indicator">' + Math.round(confidence * 100) + '%</span>';
                }
                
                vibesHtml += '<span class="vibe-tag ' + confidenceClass + '" ' +
                    'data-confidence="' + confidence + '" ' +
                    'title="' + vibe.display_name + ' - ' + Math.round(confidence * 100) + '% confidence" ' +
                    'style="animation-delay: ' + (index * 0.1) + 's">' +
                    vibe.display_name + confidenceIndicator +
                    '</span>';
            });
            
            let noteHtml = '';
            if (data.display_mode === 'full' && data.vibes.length === 5) {
                noteHtml = '<small class="vibes-note">Showing top vibes based on AI analysis</small>';
            }
            
            return '<div class="zippicks-business-vibes vibes-' + data.display_mode + '">' +
                titleHtml +
                '<div class="vibe-tags">' + vibesHtml + '</div>' +
                noteHtml +
                '</div>';
        },
        
        /**
         * Render ZipPicks scores
         */
        renderZipPicksScores: function(data) {
            if (!data.pillar_scores || Object.keys(data.pillar_scores).length === 0) {
                return '';
            }
            
            const pillarLabels = {
                'taste': 'Taste',
                'service': 'Service',
                'speed': 'Speed',
                'value': 'Value',
                'overall': 'Overall'
            };
            
            // Calculate overall if not present
            if (!data.pillar_scores.overall && Object.keys(data.pillar_scores).length > 0) {
                const scores = Object.values(data.pillar_scores);
                data.pillar_scores.overall = scores.reduce((a, b) => a + b, 0) / scores.length;
            }
            
            let titleHtml = '';
            if (data.display_mode === 'full') {
                titleHtml = '<h3 class="scores-title">ZipPicks Score</h3>';
            }
            
            if (data.display_mode === 'summary' && data.pillar_scores.overall) {
                // Summary mode: just overall score
                const scoreClass = this.getScoreClass(data.pillar_scores.overall);
                return '<div class="zippicks-scores scores-summary">' +
                    '<div class="overall-score ' + scoreClass + '">' +
                    '<span class="score-value">' + data.pillar_scores.overall.toFixed(1) + '</span>' +
                    '<span class="score-label">ZipPicks Score</span>' +
                    '</div>' +
                    '</div>';
            }
            
            // Full/compact mode
            let scoresHtml = '';
            let index = 0;
            
            // Show overall first if in full mode
            if (data.display_mode === 'full' && data.pillar_scores.overall) {
                const scoreClass = this.getScoreClass(data.pillar_scores.overall);
                scoresHtml += '<div class="score-item overall ' + scoreClass + '" style="animation-delay: ' + (index * 0.1) + 's">' +
                    '<span class="pillar-name">Overall</span>' +
                    '<span class="score-value">' + data.pillar_scores.overall.toFixed(1) + '</span>' +
                    '</div>';
                index++;
            }
            
            // Other pillars
            Object.entries(data.pillar_scores).forEach(([pillar, score]) => {
                if (pillar === 'overall' && data.display_mode === 'full') return; // Already shown
                if (!pillarLabels[pillar]) return; // Skip unknown pillars
                if (score <= 0) return; // Skip zero scores
                
                const scoreClass = this.getScoreClass(score);
                scoresHtml += '<div class="score-item ' + pillar + ' ' + scoreClass + '" style="animation-delay: ' + (index * 0.1) + 's">' +
                    '<span class="pillar-name">' + pillarLabels[pillar] + '</span>' +
                    '<span class="score-value">' + parseFloat(score).toFixed(1) + '</span>' +
                    '</div>';
                index++;
            });
            
            let disclaimerHtml = '';
            if (data.display_mode === 'full') {
                disclaimerHtml = '<div class="scores-disclaimer">' +
                    '<small>Scores based on ZipPicks proprietary analysis</small>' +
                    '</div>';
            }
            
            return '<div class="zippicks-scores scores-' + data.display_mode + '">' +
                titleHtml +
                '<div class="score-grid">' + scoresHtml + '</div>' +
                disclaimerHtml +
                '</div>';
        },
        
        /**
         * Get score CSS class based on value
         */
        getScoreClass: function(score) {
            if (score >= 8.5) return 'score-excellent';
            if (score >= 7.5) return 'score-great';
            if (score >= 6.5) return 'score-good';
            if (score >= 5.0) return 'score-fair';
            return 'score-poor';
        },
        
        /**
         * Add invisible fingerprints for copy detection
         */
        addFingerprints: function() {
            // Add fingerprint to body
            const fingerprint = this.generateFingerprint();
            $('body').append('<span class="zp-fp" data-hash="ZP' + fingerprint + '" style="display:none;"></span>');
            
            // Add fingerprints to rendered content
            $('.zippicks-business-verification, .zippicks-business-vibes, .zippicks-scores').each(function() {
                const localFingerprint = ZipPicksTemplates.generateFingerprint();
                $(this).append('<span class="zp-fp" data-hash="ZP' + localFingerprint + '" style="display:none;"></span>');
            });
        },
        
        /**
         * Generate random fingerprint
         */
        generateFingerprint: function() {
            return Math.random().toString(36).substr(2, 8);
        },
        
        /**
         * Monitor copy attempts
         */
        monitorCopyAttempts: function() {
            let copyCount = 0;
            
            $(document).on('copy', function(e) {
                copyCount++;
                
                // Log copy attempts
                if (window.console && console.log) {
                    console.log('ZipPicks: Copy detected (' + copyCount + ')');
                }
                
                // Track excessive copying
                if (copyCount > 5) {
                    // Could send analytics event here
                    if (window.gtag) {
                        gtag('event', 'excessive_copying', {
                            'event_category': 'security',
                            'event_label': 'content_protection'
                        });
                    }
                }
            });
            
            // Monitor text selection
            let selectionCount = 0;
            $(document).on('selectstart', function() {
                selectionCount++;
                
                if (selectionCount > 20) {
                    // Potential scraping behavior
                    if (window.console && console.warn) {
                        console.warn('ZipPicks: Excessive text selection detected');
                    }
                }
            });
        }
        
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only initialize on business pages
        if ($('.zp-template-placeholder').length > 0) {
            ZipPicksTemplates.init();
        }
    });
    
    // Expose for external use
    window.ZipPicksTemplates = ZipPicksTemplates;
    
})(jQuery);