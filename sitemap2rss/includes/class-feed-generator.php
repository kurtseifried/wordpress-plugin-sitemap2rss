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
            $sitemap_content = $this->fetch_sitemap($sitemap_url);
            $urls = $this->parse_sitemap($sitemap_content);
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

        $content = wp_remote_retrieve_body($response);
        
        $validation = $this->validator->validate_sitemap_content($content);
        if (!$validation['valid']) {
            throw new \Exception(esc_html($validation['message']));
        }

        return $content;
    }

    private function parse_sitemap($content) {
        libxml_use_internal_errors(true);
        $prev = libxml_disable_entity_loader(true);

        try {
            $xml = new \DOMDocument();
            $xml->loadXML($content, LIBXML_NONET | LIBXML_NOCDATA);

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
            libxml_disable_entity_loader($prev);
            libxml_use_internal_errors(false);
        }
    }

    private function output_feed($urls, $feed_name, $sitemap_url, $self_url) {
        // Set security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'');
        header('Content-Type: application/rss+xml; charset=UTF-8');

        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
        echo "    <channel>\n";
        
        // Feed metadata with indentation
        echo '        <title>' . esc_xml($this->xml_escape($feed_name)) . "</title>\n";
        echo '        <link>' . esc_xml($this->xml_escape($sitemap_url)) . "</link>\n";
        echo '        <description>' . esc_xml($this->xml_escape(
            sprintf(
                /* translators: %s: feed name */
                __('RSS feed generated from %s', 'sitemap2rss'),
                $feed_name
            )
        )) . "</description>\n";
        echo '        <language>' . esc_xml(get_bloginfo('language')) . "</language>\n";
        echo '        <lastBuildDate>' . esc_xml(gmdate('r')) . "</lastBuildDate>\n";
        echo '        <generator>Sitemap2RSS Converter</generator>' . "\n";
        
        // Add atom self link if provided
        if (!empty($self_url)) {
            echo '        <atom:link href="' . esc_xml($this->xml_escape($self_url)) . '" rel="self" type="application/rss+xml" />' . "\n";
        }

        // Add items with proper indentation
        foreach ($urls as $url_data) {
            echo "        <item>\n";
            echo '            <title>' . esc_xml($this->xml_escape($url_data['loc'])) . "</title>\n";
            echo '            <link>' . esc_xml($this->xml_escape($url_data['loc'])) . "</link>\n";
            echo '            <guid isPermaLink=\"true\">' . esc_xml($this->xml_escape($url_data['loc'])) . "</guid>\n";
            
            if (!empty($url_data['lastmod'])) {
                echo '            <pubDate>' . esc_xml(gmdate('r', strtotime($url_data['lastmod']))) . "</pubDate>\n";
            }
            
            // Combine sitemap metadata into description
            $description = [];
            if (!empty($url_data['changefreq'])) {
                $description[] = 'Change frequency: ' . esc_html($url_data['changefreq']);
            }
            if (!empty($url_data['priority'])) {
                $description[] = 'Priority: ' . esc_html($url_data['priority']);
            }
            
            if (!empty($description)) {
                echo '            <description><![CDATA[' . esc_xml(implode("\n", $description)) . ']]></description>' . "\n";
            }
            
            echo "        </item>\n";
        }

        // Close tags with proper indentation
        echo "    </channel>\n";
        echo "</rss>";
        exit;
    }

    private function xml_escape($string) {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
