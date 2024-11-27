<?php
namespace Sitemap2RSS;

if (!defined('ABSPATH')) {
    exit;
}

class FeedGenerator {
    private $validator;

    public function __construct() {
        $this->validator = new Validator();
    }

    /**
     * Generate RSS feed from sitemap
     *
     * @param string $sitemap_url The URL of the sitemap
     * @param string $feed_name The name of the feed
     * @param string $self_url Optional. The self-referential URL for the feed
     */
    public function generate($sitemap_url, $feed_name, $self_url = '') {
        try {
            // If no self URL provided, try to get current URL
            if (empty($self_url)) {
                $self_url = home_url(add_query_arg(array()));
            }
            
            $sitemap_content = $this->fetch_sitemap($sitemap_url);
            $result = $this->validator->validate_sitemap_content($sitemap_content);

            if (!$result['valid']) {
                throw new \Exception($result['message']);
            }

            $urls = $result['urls'];
            $this->output_feed($urls, $feed_name, $sitemap_url, $self_url);
        } catch (\Exception $e) {
            do_action('sitemap2rss_error', $e->getMessage(), [
                'sitemap_url' => $sitemap_url,
                'feed_name' => $feed_name
            ]);
            
            wp_die(
                esc_html($e->getMessage()),
                esc_html__('Feed Generation Error', 'sitemap2rss'),
                ['response' => 500]
            );
        }
    }

    private function fetch_sitemap($url) {
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(
                sprintf(
                    /* translators: %s: error message from WordPress */
                    esc_html__('Failed to fetch sitemap: %s', 'sitemap2rss'),
                    esc_html($response->get_error_message())
                )
            );
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new \Exception(
                sprintf(
                    /* translators: %d: HTTP response code */
                    esc_html__('Sitemap returned HTTP error: %d', 'sitemap2rss'),
                    absint(wp_remote_retrieve_response_code($response))
                )
            );
        }

        return wp_remote_retrieve_body($response);
    }

    public function parse_sitemap($content) {
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
                throw new \Exception(__('Failed to load XML', 'sitemap2rss'));
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

                if (!$this->validator->validate_url($url_data['loc'])) {
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
                throw new \Exception(__('No valid URLs found in sitemap', 'sitemap2rss'));
            }

            return $urls;

        } catch (\Exception $e) {
            throw new \Exception(
                sprintf(
                    /* translators: %s: error message */
                    esc_html__('Failed to parse sitemap: %s', 'sitemap2rss'),
                    esc_html($e->getMessage())
                )
            );
        } finally {
            if (is_callable($prev)) {
                libxml_set_external_entity_loader($prev);
            } else {
                libxml_set_external_entity_loader(null);
            }
            libxml_use_internal_errors(false);
        }
    }

    private function output_feed($urls, $feed_name, $sitemap_url, $self_url) {
        $this->generate_feed($feed_name, $sitemap_url, $self_url, $urls);
    }

    public function generate_feed($feed_name, $sitemap_url, $self_url, $urls) {
        // Create XML document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;

        // Create RSS root element
        $rss = $doc->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $doc->appendChild($rss);

        // Create channel element
        $channel = $doc->createElement('channel');
        $rss->appendChild($channel);

        // Add channel metadata
        $channel->appendChild($doc->createElement('title', $feed_name));
        $channel->appendChild($doc->createElement('link', $sitemap_url));
        $channel->appendChild($doc->createElement('description', 
            sprintf(
                /* translators: %s: feed name */
                __('RSS feed generated from %s', 'sitemap2rss'),
                $feed_name
            )
        ));
        $channel->appendChild($doc->createElement('language', get_bloginfo('language')));
        $channel->appendChild($doc->createElement('lastBuildDate', gmdate('r')));
        $channel->appendChild($doc->createElement('generator', 'Sitemap2RSS Converter'));

        // Always add atom:link with rel="self"
        $atom_link = $doc->createElementNS('http://www.w3.org/2005/Atom', 'atom:link');
        $atom_link->setAttribute('href', esc_url($self_url));
        $atom_link->setAttribute('rel', 'self');
        $atom_link->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($atom_link);

        // Add items
        foreach ($urls as $url_data) {
            $item = $doc->createElement('item');
            
            // Create basic elements
            $item->appendChild($doc->createElement('title', $url_data['loc']));
            $item->appendChild($doc->createElement('link', $url_data['loc']));
            $item->appendChild($doc->createElement('guid', $url_data['loc']));
            
            if (!empty($url_data['lastmod'])) {
                $item->appendChild($doc->createElement('pubDate', 
                    gmdate('r', strtotime($url_data['lastmod']))
                ));
            }
            
            // Create description
            $description_parts = [];
            if (!empty($url_data['changefreq'])) {
                $description_parts[] = 'Change frequency: ' . $url_data['changefreq'];
            }
            if (!empty($url_data['priority'])) {
                $description_parts[] = 'Priority: ' . $url_data['priority'];
            }
            
            if (!empty($description_parts)) {
                $description = $doc->createElement('description');
                $cdata = $doc->createCDATASection(implode(' | ', $description_parts));
                $description->appendChild($cdata);
                $item->appendChild($description);
            }
            
            $channel->appendChild($item);
        }

        // Generate the XML output with specific options
        $xml_output = $doc->saveXML($doc->documentElement, LIBXML_NOEMPTYTAG);

        // Validate the XML output
        if ($xml_output === false) {
            // Handle the error gracefully
            header('Content-Type: text/plain; charset=UTF-8', true, 500);
            echo 'Error generating XML feed.';
            exit;
        }

        // Set the correct Content-Type header for RSS/XML
        header('Content-Type: application/rss+xml; charset=UTF-8');

        // Disable any additional output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Output the XML content directly
        echo $xml_output;

        // Terminate the script to ensure no further output
        exit;
    }
}
