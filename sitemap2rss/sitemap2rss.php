<?php
/**
 * Plugin Name: sitemap2rss
 * Plugin URI: https://github.com/kurtseifried/wordpress-plugin-sitemap2rss
 * Description: Convert XML sitemaps into RSS feeds using predefined aliases.
 * Version: 1.0.5
 * Requires at least: 6.6.2
 * Requires PHP: 7.2
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sitemap2rss
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SITEMAP2RSS_VERSION', '1.0.5');
define('SITEMAP2RSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SITEMAP2RSS_PLUGIN_FILE', __FILE__);

require_once SITEMAP2RSS_PLUGIN_DIR . 'includes/class-validator.php';
require_once SITEMAP2RSS_PLUGIN_DIR . 'includes/class-feed-generator.php';
require_once SITEMAP2RSS_PLUGIN_DIR . 'includes/class-admin.php';
require_once SITEMAP2RSS_PLUGIN_DIR . 'includes/class-core.php';

function sitemap2rss_init() {
    $core = new Sitemap2RSS\Core();
    $core->init();
}
add_action('plugins_loaded', 'sitemap2rss_init');

register_activation_hook(__FILE__, function() {
    if (version_compare(PHP_VERSION, '7.2', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('This plugin requires PHP 7.2 or higher.', 'sitemap2rss'),
            esc_html__('Plugin Activation Error', 'sitemap2rss'),
            ['back_link' => true]
        );
    }

    $core = new Sitemap2RSS\Core();
    $core->activate();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
