<?php
namespace Sitemap2RSS;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    private $validator;
    private $option_name = 'sitemap2rss_aliases';
    private $rate_limit_option = 'sitemap2rss_rate_limits';
    private $max_aliases = 50;

    public function __construct(Validator $validator) {
        $this->validator = $validator;
        $this->init();
    }

    private function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_add_sitemap_alias', [$this, 'handle_add_alias']);
        add_action('admin_post_delete_sitemap_alias', [$this, 'handle_delete_alias']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    public function add_admin_menu() {
        add_options_page(
            __('Sitemap to RSS', 'sitemap2rss'),
            __('Sitemap to RSS', 'sitemap2rss'),
            'manage_options',
            'sitemap2rss',
            [$this, 'render_admin_page']
        );
    }

    public function register_settings() {
        register_setting(
            'sitemap2rss_options',
            $this->option_name,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_aliases']
            ]
        );

        register_setting(
            'sitemap2rss_options',
            $this->rate_limit_option,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_rate_limits']
            ]
        );
    }

    public function sanitize_aliases($aliases) {
        if (!is_array($aliases)) {
            return [];
        }

        $sanitized = [];
        foreach ($aliases as $alias => $data) {
            $alias = $this->validator->sanitize_alias($alias);
            if (!$alias) {
                continue;
            }

            $url = $this->validator->sanitize_url($data['url'] ?? '');
            if (!$url) {
                continue;
            }

            $sanitized[$alias] = [
                'name' => sanitize_text_field($data['name'] ?? $alias),
                'url' => $url
            ];
        }

        return $sanitized;
    }

    public function sanitize_rate_limits($limits) {
        return [
            'requests_per_minute' => min(60, max(1, absint($limits['requests_per_minute'] ?? 5))),
            'minimum_interval' => min(300, max(1, absint($limits['minimum_interval'] ?? 10)))
        ];
    }

    public function handle_add_alias() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access', 'sitemap2rss'), '', ['response' => 403]);
        }

        check_admin_referer('sitemap2rss_add_alias');

        // Properly sanitize the alias
        $alias = isset($_POST['alias']) ? sanitize_text_field(wp_unslash($_POST['alias'])) : '';
        $alias = $this->validator->sanitize_alias($alias);
        
        if (!$alias) {
            $this->add_admin_notice('error', __('Invalid alias format', 'sitemap2rss'));
            wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
            exit;
        }

        // Properly sanitize the URL
        $url = isset($_POST['url']) ? sanitize_url(wp_unslash($_POST['url'])) : '';
        $url = $this->validator->sanitize_url($url);
        
        if (!$url) {
            $this->add_admin_notice('error', __('Invalid URL format', 'sitemap2rss'));
            wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
            exit;
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : $alias;

        // Verify sitemap is accessible and valid
        try {
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            if (wp_remote_retrieve_response_code($response) !== 200) {
                throw new \Exception(__('Sitemap URL returned non-200 status code', 'sitemap2rss'));
            }

            $content = wp_remote_retrieve_body($response);
            $validation = $this->validator->validate_sitemap_content($content);
            
            if (!$validation['valid']) {
                throw new \Exception($validation['message']);
            }

        } catch (\Exception $e) {
            $this->add_admin_notice('error', sprintf(
                /* translators: %s: error message */
                __('Sitemap validation failed: %s', 'sitemap2rss'),
                $e->getMessage()
            ));
            wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
            exit;
        }

        $aliases = get_option($this->option_name, []);
        
        if (count($aliases) >= $this->max_aliases) {
            $this->add_admin_notice('error', __('Maximum number of aliases reached', 'sitemap2rss'));
            wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
            exit;
        }

        $aliases[$alias] = [
            'name' => $name,
            'url' => $url
        ];

        update_option($this->option_name, $aliases);
        $this->add_admin_notice('success', __('Alias added successfully', 'sitemap2rss'));
        
        wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
        exit;
    }

    public function handle_delete_alias() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access', 'sitemap2rss'), '', ['response' => 403]);
        }

        check_admin_referer('sitemap2rss_delete_alias');

        // Properly sanitize the alias
        $alias = isset($_POST['alias']) ? sanitize_text_field(wp_unslash($_POST['alias'])) : '';
        $alias = $this->validator->sanitize_alias($alias);
        
        if ($alias) {
            $aliases = get_option($this->option_name, []);
            unset($aliases[$alias]);
            update_option($this->option_name, $aliases);
            $this->add_admin_notice('success', __('Alias deleted successfully', 'sitemap2rss'));
        }

        wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
        exit;
    }

    private function add_admin_notice($type, $message) {
        $notices = get_transient('sitemap2rss_admin_notices') ?: [];
        $notices[] = [
            'type' => $type,
            'message' => $message
        ];
        set_transient('sitemap2rss_admin_notices', $notices, 45);
    }

    public function display_admin_notices() {
        $notices = get_transient('sitemap2rss_admin_notices');
        if (!$notices) {
            return;
        }

        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }

        delete_transient('sitemap2rss_admin_notices');
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $aliases = get_option($this->option_name, []);
        $rate_limits = get_option($this->rate_limit_option);

        require_once SITEMAP2RSS_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}
