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

        // Get alias from query var or $_GET as fallback
        $alias = get_query_var('alias');
        if (empty($alias)) {
            // Validate and sanitize GET nonce
            $get_nonce = isset($_GET[$this->nonce_name]) 
                ? sanitize_text_field(wp_unslash($_GET[$this->nonce_name])) 
                : '';

            if (empty($get_nonce) || !wp_verify_nonce($get_nonce, $this->nonce_action)) {
                wp_die(
                    esc_html__('Security check failed.', 'sitemap2rss'),
                    '',
                    ['response' => 403]
                );
            }

            // Validate and sanitize alias from GET
            $alias = isset($_GET['alias']) 
                ? sanitize_text_field(wp_unslash($_GET['alias'])) 
                : '';
        }

        // Further sanitize the alias using the validator
        $alias = $this->validator->sanitize_alias($alias);

        if (!$alias) {
            wp_die(
                esc_html__('Invalid alias format', 'sitemap2rss'),
                '',
                ['response' => 400]
            );
        }

        $aliases = get_option($this->option_name, []);
        if (!isset($aliases[$alias])) {
            wp_die(
                esc_html__('Invalid sitemap alias', 'sitemap2rss'),
                '',
                ['response' => 404]
            );
        }

        if (!$this->check_rate_limit($aliases[$alias]['url'])) {
            status_header(429);
            $retry_after = get_option($this->rate_limit_option)['minimum_interval'];
            header('Retry-After: ' . absint($retry_after));
            wp_die(
                esc_html__('Rate limit exceeded. Please try again later.', 'sitemap2rss'),
                '',
                ['response' => 429]
            );
        }

        // Generate and output the feed
        $this->feed_generator->generate(
            $aliases[$alias]['url'],
            $aliases[$alias]['name']
        );
        exit;
    }

    private function check_rate_limit($sitemap_url) {
        $rate_limits = get_option($this->rate_limit_option);
        $hash = md5($sitemap_url);
        
        $last_access = get_transient("sitemap2rss_last_{$hash}");
        if (false !== $last_access) {
            $time_since_last = time() - $last_access;
            if ($time_since_last < $rate_limits['minimum_interval']) {
                return false;
            }
        }

        $count = get_transient("sitemap2rss_count_{$hash}") ?: 0;
        if ($count >= $rate_limits['requests_per_minute']) {
            return false;
        }

        set_transient("sitemap2rss_count_{$hash}", $count + 1, 60);
        set_transient("sitemap2rss_last_{$hash}", time(), 60);
        
        return true;
    }

    /**
     * Helper function to generate nonce field for forms
     */
    public function get_nonce_field() {
        return wp_nonce_field($this->nonce_action, $this->nonce_name, true, false);
    }

    /**
     * Helper function to generate nonce URL
     */
    public function get_nonce_url($url) {
        return wp_nonce_url($url, $this->nonce_action, $this->nonce_name);
    }
}
