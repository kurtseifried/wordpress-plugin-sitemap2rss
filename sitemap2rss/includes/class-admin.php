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

    public function sanitize_rate_limits($rate_limits) {
        if (!is_array($rate_limits)) {
            return [];
        }

        $sanitized = [];
        $sanitized['requests_per_minute'] = isset($rate_limits['requests_per_minute']) ? intval($rate_limits['requests_per_minute']) : 5;
        $sanitized['minimum_interval'] = isset($rate_limits['minimum_interval']) ? intval($rate_limits['minimum_interval']) : 10;

        return $sanitized;
    }

    public function handle_add_alias() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'sitemap2rss'));
        }

        check_admin_referer('sitemap2rss_add_alias');

        $aliases = get_option($this->option_name, []);
        $post_data = wp_unslash($_POST);
        $alias = isset($post_data['alias']) ? $this->validator->sanitize_alias($post_data['alias']) : '';
        $url = isset($post_data['url']) ? $this->validator->sanitize_url($post_data['url']) : '';
        $name = isset($post_data['name']) ? sanitize_text_field($post_data['name']) : $alias;

        if ($alias && $url) {
            $aliases[$alias] = [
                'name' => $name,
                'url' => $url
            ];
            update_option($this->option_name, $aliases);
            $this->add_admin_notice('alias_added', __('Alias added successfully.', 'sitemap2rss'), 'success');
        } else {
            $this->add_admin_notice('alias_error', __('Invalid alias or URL.', 'sitemap2rss'), 'error');
        }

        wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
        exit;
    }

    public function handle_delete_alias() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'sitemap2rss'));
        }

        check_admin_referer('sitemap2rss_delete_alias');

        $aliases = get_option($this->option_name, []);
        $post_data = wp_unslash($_POST);
        $alias = isset($post_data['alias']) ? $this->validator->sanitize_alias($post_data['alias']) : '';

        if ($alias && isset($aliases[$alias])) {
            unset($aliases[$alias]);
            update_option($this->option_name, $aliases);
            $this->add_admin_notice('alias_deleted', __('Alias deleted successfully.', 'sitemap2rss'), 'success');
        } else {
            $this->add_admin_notice('alias_error', __('Invalid alias.', 'sitemap2rss'), 'error');
        }

        wp_redirect(admin_url('options-general.php?page=sitemap2rss'));
        exit;
    }

    public function display_admin_notices() {
        if ($notice = get_transient('sitemap2rss_admin_notice')) {
            echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
            delete_transient('sitemap2rss_admin_notice');
        }
    }

    private function add_admin_notice($key, $message, $type = 'info') {
        set_transient('sitemap2rss_admin_notice', [
            'key' => $key,
            'message' => $message,
            'type' => $type
        ], 30);
    }

    public function render_admin_page() {
        $aliases = get_option($this->option_name, []);
        $rate_limits = get_option($this->rate_limit_option, [
            'requests_per_minute' => 5,
            'minimum_interval' => 10
        ]);
        $rate_limit_option = $this->rate_limit_option;

        include SITEMAP2RSS_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}
