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
                    <td><?php echo esc_html__('Manage colors, typography, and custom styling for the site.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Check that saved styling changes appear as expected on the site.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('API Integration', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Store the Barefoot login details used by the plugin.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Confirm the connection test passes and credentials are valid before syncing data.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('Properties', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Import, review, and manage synced properties.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Review property records after syncing and confirm recent changes are present.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('Updates', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Check the current plugin version and available updates.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Review the latest release details when checking for updates.', 'barefoot-engine'); ?></td>
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
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Before You Start', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Save and test your API credentials before running any property sync.', 'barefoot-engine'); ?></p>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Use a full sync first, then use partial sync for smaller follow-up updates.', 'barefoot-engine'); ?></p>
        </div>
    </article>

    <article class="be-mini-card">
        <div class="be-mini-icon is-success">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">rule</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Recommended Workflow', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Save General settings first, then verify public output, then move to API and property operations.', 'barefoot-engine'); ?></p>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Run property syncs only after API credentials are confirmed.', 'barefoot-engine'); ?></p>
        </div>
    </article>
</section>

<section class="be-panel">
    <div class="be-columns">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">tips_and_updates</span>
            <?php echo esc_html__('Helpful Tips', 'barefoot-engine'); ?>
        </h3>
        <p class="be-paragraph"><?php echo esc_html__('After saving settings, refresh the relevant admin screen and a frontend page to confirm the changes.', 'barefoot-engine'); ?></p>
        <p class="be-paragraph"><?php echo esc_html__('If changes do not appear right away, clear site or builder caches and reload the page.', 'barefoot-engine'); ?></p>
        <p class="be-paragraph"><?php echo esc_html__('Run another sync after major property updates so the latest information is pulled into WordPress.', 'barefoot-engine'); ?></p>
    </div>
</section>
