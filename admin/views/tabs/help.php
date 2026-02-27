<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-panel-flush">
    <div class="be-table-head">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">code</span>
            <?php echo esc_html__('Shortcode Reference', 'barefoot-engine'); ?>
        </h3>
        <button class="be-link-button" type="button">
            <?php echo esc_html__('Copy All', 'barefoot-engine'); ?>
            <span class="be-icon material-symbols-outlined" aria-hidden="true">content_copy</span>
        </button>
    </div>

    <div class="be-table-wrap">
        <table class="be-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Shortcode', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Description', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Available Parameters', 'barefoot-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[my_plugin_display]</code></td>
                    <td><?php echo esc_html__('Displays the main plugin output grid. This is the primary shortcode for most pages.', 'barefoot-engine'); ?></td>
                    <td>id (int), limit (int), style (string)</td>
                </tr>
                <tr>
                    <td><code>[my_plugin_single id="123"]</code></td>
                    <td><?php echo esc_html__('Embeds a single specific item by its unique ID. Useful for blog posts or landing pages.', 'barefoot-engine'); ?></td>
                    <td>id (required), show_title (bool)</td>
                </tr>
                <tr>
                    <td><code>[my_plugin_search]</code></td>
                    <td><?php echo esc_html__('Adds a dedicated search bar for plugin specific content. Can be placed in sidebars.', 'barefoot-engine'); ?></td>
                    <td>placeholder (string), button_text (string)</td>
                </tr>
                <tr>
                    <td><code>[my_plugin_filter]</code></td>
                    <td><?php echo esc_html__('Shows a filtered list of items based on taxonomy.', 'barefoot-engine'); ?></td>
                    <td>category (slug), count (int)</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="be-card-grid">
    <article class="be-mini-card">
        <div class="be-mini-icon is-warning">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">warning</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Shortcodes not rendering?', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Ensure the plugin is active and that you are not wrapping the shortcode in pre tags within the Classic Editor.', 'barefoot-engine'); ?></p>
            <a class="be-mini-card-link be-link" href="#"><?php echo esc_html__('Troubleshooting Guide', 'barefoot-engine'); ?></a>
        </div>
    </article>

    <article class="be-mini-card">
        <div class="be-mini-icon is-success">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">terminal</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Developer API', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Need more control? You can use our PHP functions directly in your theme template files.', 'barefoot-engine'); ?></p>
            <a class="be-mini-card-link be-link" href="#"><?php echo esc_html__('View Function Reference', 'barefoot-engine'); ?></a>
        </div>
    </article>
</section>
