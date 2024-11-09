<?php
if (!defined('ABSPATH')) {
    exit;
}

// Ensure required variables are available
if (!isset($aliases) || !isset($rate_limits) || !isset($this->rate_limit_option)) {
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
                           name="<?php echo esc_attr($this->rate_limit_option); ?>[requests_per_minute]"
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
                           name="<?php echo esc_attr($this->rate_limit_option); ?>[minimum_interval]"
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
                           required
                           maxlength="50"
                           title="<?php esc_attr_e('Only lowercase letters, numbers, and hyphens allowed', 'sitemap2rss'); ?>"
                           placeholder="<?php esc_attr_e('e.g., my-sitemap', 'sitemap2rss'); ?>" />
                    <p class="description">
                        <?php esc_html_e('Used in URL: sitemap2rss/?alias=your-alias', 'sitemap2rss'); ?>
                        <br>
                        <?php esc_html_e('3-50 characters, lowercase letters, numbers, and hyphens only.', 'sitemap2rss'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="name">
                        <?php esc_html_e('Display Name', 'sitemap2rss'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="regular-text"
                           maxlength="100"
                           placeholder="<?php esc_attr_e('e.g., Tech Page', 'sitemap2rss'); ?>" />
                    <p class="description">
                        <?php esc_html_e('A friendly name for your feed. If left empty, the alias will be used.', 'sitemap2rss'); ?>
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
                           required
                           maxlength="2083"
                           placeholder="<?php esc_attr_e('https://example.com/sitemap.xml', 'sitemap2rss'); ?>" />
                    <p class="description">
                        <?php esc_html_e('The full URL of your XML sitemap. Must be accessible and contain valid sitemap data.', 'sitemap2rss'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Add Sitemap Alias', 'sitemap2rss')); ?>
    </form>

    <h2><?php esc_html_e('Existing Aliases', 'sitemap2rss'); ?></h2>
    <?php if (empty($aliases)): ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e('No aliases configured yet. Add your first sitemap alias using the form above.', 'sitemap2rss'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-primary"><?php esc_html_e('Alias', 'sitemap2rss'); ?></th>
                    <th scope="col"><?php esc_html_e('Name', 'sitemap2rss'); ?></th>
                    <th scope="col"><?php esc_html_e('Sitemap URL', 'sitemap2rss'); ?></th>
                    <th scope="col"><?php esc_html_e('RSS Feed URL', 'sitemap2rss'); ?></th>
                    <th scope="col"><?php esc_html_e('Actions', 'sitemap2rss'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aliases as $alias => $data): 
                    $feed_url = add_query_arg(
                        ['alias' => urlencode($alias)],
                        home_url('/sitemap2rss/')
                    );
                ?>
                    <tr>
                        <td class="column-primary">
                            <strong><?php echo esc_html($alias); ?></strong>
                            <button type="button" class="toggle-row">
                                <span class="screen-reader-text">
                                    <?php esc_html_e('Show more details', 'sitemap2rss'); ?>
                                </span>
                            </button>
                        </td>
                        <td data-colname="<?php esc_attr_e('Name', 'sitemap2rss'); ?>">
                            <?php echo esc_html($data['name']); ?>
                        </td>
                        <td data-colname="<?php esc_attr_e('Sitemap URL', 'sitemap2rss'); ?>">
                            <code class="sitemap-url"><?php echo esc_url($data['url']); ?></code>
                        </td>
                        <td data-colname="<?php esc_attr_e('RSS Feed URL', 'sitemap2rss'); ?>">
                            <code class="feed-url"><?php echo esc_url($feed_url); ?></code>
                            <button type="button" 
                                    class="button button-small copyurl" 
                                    data-clipboard-text="<?php echo esc_attr($feed_url); ?>">
                                <?php esc_html_e('Copy URL', 'sitemap2rss'); ?>
                            </button>
                        </td>
                        <td data-colname="<?php esc_attr_e('Actions', 'sitemap2rss'); ?>">
                            <form method="post" 
                                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
                                  style="display:inline;">
                                <input type="hidden" name="action" value="delete_sitemap_alias" />
                                <input type="hidden" name="alias" value="<?php echo esc_attr($alias); ?>" />
                                <?php wp_nonce_field('sitemap2rss_delete_alias'); ?>
                                <button type="submit" 
                                        class="button button-small button-link-delete"
                                        onclick="return confirm('<?php echo esc_js(sprintf(
                                            /* translators: %s: alias name */
                                            __('Are you sure you want to delete the alias "%s"?', 'sitemap2rss'),
                                            $alias
                                        )); ?>')">
                                    <?php esc_html_e('Delete', 'sitemap2rss'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copyurl').forEach(function(button) {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-clipboard-text');
            navigator.clipboard.writeText(url).then(function() {
                const originalText = button.textContent;
                button.textContent = '<?php echo esc_js(__('Copied!', 'sitemap2rss')); ?>';
                setTimeout(function() {
                    button.textContent = originalText;
                }, 2000);
            });
        });
    });
});
</script>
