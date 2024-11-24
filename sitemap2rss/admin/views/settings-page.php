<?php
if (!defined('ABSPATH')) {
    exit;
}

// Ensure required variables are available
if (!isset($aliases) || !isset($rate_limits) || !isset($rate_limit_option)) {
    wp_die(esc_html__('Invalid configuration', 'sitemap2rss'));
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Sitemap to RSS Converter', 'sitemap2rss'); ?></h1>

    <div class="notice notice-info">
        <p>
            <?php esc_html_e('Configure sitemaps to be converted into RSS feeds. Each sitemap needs an alias that will be used in the feed URL.', 'sitemap2rss'); ?>
        </p>
    </div>

    <h2><?php esc_html_e('Rate Limit Settings', 'sitemap2rss'); ?></h2>
    <form method="post" action="options.php">
        <?php settings_fields('sitemap2rss_options'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="requests_per_minute">
                        <?php esc_html_e('Requests Per Minute', 'sitemap2rss'); ?>
                    </label>
                </th>
                <td>
                    <input type="number"
                           id="requests_per_minute"
                           name="<?php echo esc_attr($rate_limit_option); ?>[requests_per_minute]"
                           value="<?php echo esc_attr($rate_limits['requests_per_minute']); ?>"
                           min="1" max="60" required 
                           class="small-text" />
                    <p class="description">
                        <?php esc_html_e('Maximum number of requests allowed per minute for each sitemap.', 'sitemap2rss'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="minimum_interval">
                        <?php esc_html_e('Minimum Interval (seconds)', 'sitemap2rss'); ?>
                    </label>
                </th>
                <td>
                    <input type="number"
                           id="minimum_interval"
                           name="<?php echo esc_attr($rate_limit_option); ?>[minimum_interval]"
                           value="<?php echo esc_attr($rate_limits['minimum_interval']); ?>"
                           min="1" max="300" required 
                           class="small-text" />
                    <p class="description">
                        <?php esc_html_e('Minimum time (in seconds) between requests to the same sitemap.', 'sitemap2rss'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Save Rate Limit Settings', 'sitemap2rss')); ?>
    </form>

    <h2><?php esc_html_e('Add New Sitemap Alias', 'sitemap2rss'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="validate">
        <input type="hidden" name="action" value="add_sitemap_alias" />
        <?php wp_nonce_field('sitemap2rss_add_alias'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="alias">
                        <?php esc_html_e('Alias', 'sitemap2rss'); ?>
                        <span class="required">*</span>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           name="alias" 
                           id="alias" 
                           class="regular-text code"
                           pattern="[a-z0-9\-]+"
                           required />
                    <p class="description">
                        <?php esc_html_e('The alias to be used in the feed URL.', 'sitemap2rss'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="name">
                        <?php esc_html_e('Name', 'sitemap2rss'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="regular-text" 
                           required />
                    <p class="description">
                        <?php esc_html_e('The name of the sitemap.', 'sitemap2rss'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="url">
                        <?php esc_html_e('Sitemap URL', 'sitemap2rss'); ?>
                        <span class="required">*</span>
                    </label>
                </th>
                <td>
                    <input type="url" 
                           name="url" 
                           id="url" 
                           class="regular-text code" 
                           required />
                    <p class="description">
                        <?php esc_html_e('The URL of the sitemap to be converted.', 'sitemap2rss'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Add Sitemap Alias', 'sitemap2rss')); ?>
    </form>

    <h2><?php esc_html_e('Existing Sitemap Aliases', 'sitemap2rss'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Alias', 'sitemap2rss'); ?></th>
                <th><?php esc_html_e('Name', 'sitemap2rss'); ?></th>
                <th><?php esc_html_e('URL', 'sitemap2rss'); ?></th>
                <th><?php esc_html_e('Actions', 'sitemap2rss'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($aliases)) : ?>
                <?php foreach ($aliases as $alias => $data) : ?>
                    <tr>
                        <td><?php echo esc_html($alias); ?></td>
                        <td><a href="<?php echo esc_url(home_url('/sitemap2rss/' . $alias)); ?>" target="_blank"><?php echo esc_html($data['name']); ?></a></td>
                        <td>
                            <?php echo esc_url($data['url']); ?>
                            <button type="button" class="button copy-url" data-url="<?php echo esc_url($data['url']); ?>"><?php esc_html_e('Copy URL', 'sitemap2rss'); ?></button>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="delete_sitemap_alias" />
                                <?php wp_nonce_field('sitemap2rss_delete_alias'); ?>
                                <input type="hidden" name="alias" value="<?php echo esc_attr($alias); ?>" />
                                <?php submit_button(__('Delete', 'sitemap2rss'), 'delete', '', false); ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e('No aliases found.', 'sitemap2rss'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const copyButtons = document.querySelectorAll('.copy-url');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                alert('<?php esc_html_e('URL copied to clipboard', 'sitemap2rss'); ?>');
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        });
    });
});
</script>
