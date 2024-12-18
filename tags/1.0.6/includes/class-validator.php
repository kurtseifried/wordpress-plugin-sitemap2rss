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
                'message' => __('Empty sitemap content', 'sitemap2rss'),
                'urls' => []
            ];
        }

        libxml_use_internal_errors(true);
        $prev = libxml_set_external_entity_loader(function($public, $system, $context) {
            return null;
        });

        try {
            $xml = new \DOMDocument();
            $loaded = $xml->loadXML($content, LIBXML_NONET | LIBXML_NOCDATA);
            
            if (!$loaded) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return [
                    'valid' => false,
                    'message' => __('Invalid XML content', 'sitemap2rss'),
                    'urls' => []
                ];
            }

            $urls = [];
            $url_elements = $xml->getElementsByTagName('url');

            foreach ($url_elements as $url_element) {
                $url_data = [
                    'loc' => '',
                    'lastmod' => '',
                    'changefreq' => '',
                    'priority' => ''
                ];

                foreach ($url_data as $field => &$value) {
                    $nodes = $url_element->getElementsByTagName($field);
                    if ($nodes->length > 0) {
                        $value = $nodes->item(0)->nodeValue;
                    }
                }

                if (!$this->validate_url($url_data['loc'])) {
                    continue;
                }

                if (!empty($url_data['lastmod'])) {
                    $timestamp = strtotime($url_data['lastmod']);
                    if ($timestamp === false) {
                        $url_data['lastmod'] = '';
                    }
                }

                $urls[] = $url_data;
            }

            if (empty($urls)) {
                return [
                    'valid' => false,
                    'message' => __('No valid URLs found in sitemap', 'sitemap2rss'),
                    'urls' => []
                ];
            }

            return [
                'valid' => true,
                'message' => __('Valid sitemap content', 'sitemap2rss'),
                'urls' => $urls
            ];

        } finally {
            if (is_callable($prev)) {
                libxml_set_external_entity_loader($prev);
            } else {
                libxml_set_external_entity_loader(null);
            }
            libxml_use_internal_errors(false);
        }
    }
}
