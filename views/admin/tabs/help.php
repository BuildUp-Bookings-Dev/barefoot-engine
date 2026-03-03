<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-panel-flush">
    <div class="be-table-head">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">help</span>
            <?php echo esc_html__('Plugin Guide', 'barefoot-engine'); ?>
        </h3>
    </div>

    <div class="be-table-wrap">
        <table class="be-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Area', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Purpose', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('What To Check', 'barefoot-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code><?php echo esc_html__('General', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Controls shared appearance, typography, colors, and custom CSS used on the public site.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Verify brand colors, fonts, and any custom CSS output after saving changes.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('API Integration', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Stores API credentials and connection settings used for remote Barefoot data access.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Confirm the connection test passes and credentials are valid before syncing data.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('Properties', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Manages property sync behavior, aliases, post type data, and property-specific admin tools.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Check property records, alias mapping, and sync actions after changing related settings.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('Updates', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Displays plugin update status and release information from the configured repository.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Confirm the repository values are correct and that update checks resolve expected versions.', 'barefoot-engine'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="be-card-grid">
    <article class="be-mini-card">
        <div class="be-mini-icon is-warning">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">inventory_2</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Current Scope', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('This plugin currently focuses on shared settings, API integration, property management, and plugin updates.', 'barefoot-engine'); ?></p>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Widget functionality is being rebuilt in controlled steps so each new piece can be validated before more configuration is added.', 'barefoot-engine'); ?></p>
        </div>
    </article>

    <article class="be-mini-card">
        <div class="be-mini-icon is-success">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">rule</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Recommended Workflow', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Save General settings first, then verify public output, then move to API and property operations.', 'barefoot-engine'); ?></p>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Run property syncs only after API credentials and alias settings are confirmed.', 'barefoot-engine'); ?></p>
        </div>
    </article>
</section>

<section class="be-panel">
    <div class="be-columns">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">tips_and_updates</span>
            <?php echo esc_html__('Support Notes', 'barefoot-engine'); ?>
        </h3>
        <p class="be-paragraph"><?php echo esc_html__('After changing settings that affect public styling or plugin behavior, reload the relevant admin screen and a frontend page to confirm the output.', 'barefoot-engine'); ?></p>
        <p class="be-paragraph"><?php echo esc_html__('If Elementor or another builder caches rendered content, clear its generated files after major plugin changes so new asset references and markup are picked up.', 'barefoot-engine'); ?></p>
        <p class="be-paragraph"><?php echo esc_html__('Keep new widget behavior incremental so shortcode output, assets, and frontend behavior can be checked before advanced configuration is introduced.', 'barefoot-engine'); ?></p>
    </div>
</section>
