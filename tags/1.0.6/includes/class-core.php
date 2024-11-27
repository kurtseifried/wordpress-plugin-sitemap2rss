<?php
namespace Sitemap2RSS;

if (!defined('ABSPATH')) {
    exit;
}

class Core {
    private $validator;
    private $feed_generator;
    private $admin;
    private $option_name = 'sitemap2rss_aliases';
    private $rate_limit_option = 'sitemap2rss_rate_limits';
    private $max_aliases = 50;
    private $nonce_name = 'sitemap2rss_feed_nonce';
    private $nonce_action = 'sitemap2rss_feed_action';

    public function __construct() {
        $this->validator = new Validator();
        $this->feed_generator = new FeedGenerator();
    }

    public function init() {
        if (is_admin()) {
            $this->admin = new Admin($this->validator);
        }

        add_action('init', [$this, 'add_feed_endpoint'], 1);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_feed_request']);
    }

    public function activate() {
        if (false === get_option($this->rate_limit_option)) {
            update_option($this->rate_limit_option, [
                'requests_per_minute' => 5,
                'minimum_interval' => 10
            ]);
        }
        
        $this->add_feed_endpoint();
        flush_rewrite_rules();
    }

    public function add_feed_endpoint() {
        add_rewrite_rule(
            '^sitemap2rss/?(.*)',
            'index.php?sitemap2rss=1&alias=$matches[1]',
            'top'
        );
    }

    public function add_query_vars($vars) {
        $vars[] = 'sitemap2rss';
        $vars[] = 'alias';
        return $vars;
    }

    public function handle_feed_request() {
        // Check if this is our feed request
        if (!get_query_var('sitemap2rss')) {
            return;
        }

        // Verify request method
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';

        // Verify nonce if it's a form submission
        if ($request_method === 'POST') {
            // Validate and sanitize POST nonce
            $post_nonce = isset($_POST[$this->nonce_name]) 
                ? sanitize_text_field(wp_unslash($_POST[$this->nonce_name])) 
                : '';
            
            if (empty($post_nonce) || !wp_verify_nonce($post_nonce, $this->nonce_action)) {
                wp_die(
                    esc_html__('Security check failed.', 'sitemap2rss'),
                    '',
                    ['response' => 403]
                );
            }
        }

        // Handle the feed request
        $alias = get_query_var('alias');
        $aliases = get_option($this->option_name, []);

        if (!isset($aliases[$alias])) {
            wp_die(
                esc_html__('Invalid alias.', 'sitemap2rss'),
                '',
                ['response' => 404]
            );
        }

        $sitemap_url = $aliases[$alias]['url'];
        $feed_name = $aliases[$alias]['name'];
        $self_url = home_url(add_query_arg(null, null));

        $content = wp_remote_retrieve_body(wp_remote_get($sitemap_url));

        if (empty($content)) {
            wp_die(
                esc_html__('Failed to retrieve sitemap content.', 'sitemap2rss'),
                '',
                ['response' => 500]
            );
        }

        $urls = $this->validator->validate_sitemap_content($content);

        if (!$urls['valid']) {
            wp_die(
                esc_html($urls['message']),
                '',
                ['response' => 500]
            );
        }

        $this->feed_generator->generate_feed($feed_name, $sitemap_url, $self_url, $urls['urls']);
    }
}
