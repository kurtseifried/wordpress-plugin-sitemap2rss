<?php
namespace Sitemap2RSS;

if (!defined('ABSPATH')) {
    exit;
}

class Validator {
    const MAX_ALIAS_LENGTH = 50;
    const MIN_ALIAS_LENGTH = 3;
    const MAX_URL_LENGTH = 2083;

    public function sanitize_alias($alias) {
        $alias = sanitize_key($alias);
        return $this->validate_alias($alias) ? $alias : false;
    }

    public function validate_alias($alias) {
        if (strlen($alias) > self::MAX_ALIAS_LENGTH || strlen($alias) < self::MIN_ALIAS_LENGTH) {
            return false;
        }
        
        if (!preg_match('/^[a-z0-9\-]+$/', $alias)) {
            return false;
        }
        
        $reserved_terms = ['feed', 'rdf', 'rss', 'rss2', 'atom', 'wp', 'wp-admin', 'admin'];
        return !in_array($alias, $reserved_terms);
    }

    public function sanitize_url($url) {
        $url = esc_url_raw($url);
        return $this->validate_url($url) ? $url : false;
    }

    public function validate_url($url) {
        if (strlen($url) > self::MAX_URL_LENGTH) {
            return false;
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }
        
        $suspicious_patterns = [
            '/(<|>|`|\{|\}|\[|\]|\|)/',
            '/(\\\)/',
            '/(javascript:|data:)/i',
            '/(onload=|onerror=)/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }
        
        return true;
    }

    public function validate_sitemap_content($content) {
        if (empty($content)) {
            return [
                'valid' => false,
                'message' => __('Empty sitemap content', 'sitemap2rss')
            ];
        }

        libxml_use_internal_errors(true);
        $prev = libxml_disable_entity_loader(true); // Security: disable external entities

        try {
            $xml = new \DOMDocument();
            $loaded = $xml->loadXML($content, LIBXML_NONET | LIBXML_NOCDATA);
            
            if (!$loaded) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return [
                    'valid' => false,
                    'message' => __('Invalid XML structure', 'sitemap2rss')
                ];
            }

            // Verify it has URL elements
            $urls = $xml->getElementsByTagName('url');
            if ($urls->length === 0) {
                return [
                    'valid' => false,
                    'message' => __('No URLs found in sitemap', 'sitemap2rss')
                ];
            }

            return [
                'valid' => true,
                'message' => __('Valid sitemap content', 'sitemap2rss')
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => __('Error processing XML content', 'sitemap2rss')
            ];
        } finally {
            libxml_disable_entity_loader($prev);
            libxml_use_internal_errors(false);
        }
    }
}
