<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-status-card">
    <div class="be-status-card-main">
        <div class="be-status-icon-wrap">
            <span class="material-symbols-outlined be-status-icon is-filled" aria-hidden="true">check_circle</span>
        </div>
        <div>
            <div class="be-status-title-row">
                <h2 class="be-status-title"><?php echo esc_html__('Current Version: v2.4.5', 'barefoot-engine'); ?></h2>
                <span class="be-badge be-badge-success"><?php echo esc_html__('Latest', 'barefoot-engine'); ?></span>
            </div>
            <p class="be-status-copy"><?php echo esc_html__('You are running the most recent version of the plugin. Automatic updates are enabled.', 'barefoot-engine'); ?></p>
            <p class="be-status-meta"><?php echo esc_html__('Last checked: Today at 09:42 AM', 'barefoot-engine'); ?></p>
        </div>
    </div>

    <button class="be-button be-button-primary" type="button">
        <span class="material-symbols-outlined" aria-hidden="true">cached</span>
        <?php echo esc_html__('Check for Updates', 'barefoot-engine'); ?>
    </button>
</section>

<section class="be-panel be-panel-tight">
    <h3 class="be-section-title">
        <span class="material-symbols-outlined" aria-hidden="true">history</span>
        <?php echo esc_html__('Changelog history', 'barefoot-engine'); ?>
    </h3>

    <div class="be-changelog-list">
        <article class="be-changelog-item">
            <div class="be-changelog-head">
                <div class="be-changelog-version-group">
                    <span class="be-badge be-badge-primary">v2.4.5</span>
                    <span class="be-changelog-date"><?php echo esc_html__('October 24, 2023', 'barefoot-engine'); ?></span>
                </div>
                <span class="be-badge be-badge-neutral"><?php echo esc_html__('Security Patch', 'barefoot-engine'); ?></span>
            </div>
            <ul class="be-changelog-points">
                <li>
                    <span class="material-symbols-outlined is-green" aria-hidden="true">check_small</span>
                    <?php echo esc_html__('Fixed a critical CSS bug causing layout shifts on mobile devices.', 'barefoot-engine'); ?>
                </li>
                <li>
                    <span class="material-symbols-outlined is-blue" aria-hidden="true">add</span>
                    <?php echo esc_html__('Added new API Key field in General Settings for enhanced integration support.', 'barefoot-engine'); ?>
                </li>
            </ul>
        </article>

        <article class="be-changelog-item">
            <div class="be-changelog-head">
                <div class="be-changelog-version-group">
                    <span class="be-badge be-badge-neutral">v2.4.4</span>
                    <span class="be-changelog-date"><?php echo esc_html__('September 15, 2023', 'barefoot-engine'); ?></span>
                </div>
                <span class="be-badge be-badge-neutral"><?php echo esc_html__('Performance', 'barefoot-engine'); ?></span>
            </div>
            <ul class="be-changelog-points">
                <li>
                    <span class="material-symbols-outlined is-blue" aria-hidden="true">bolt</span>
                    <?php echo esc_html__('Improved load times by optimizing database queries on initialization.', 'barefoot-engine'); ?>
                </li>
                <li>
                    <span class="material-symbols-outlined is-green" aria-hidden="true">check_small</span>
                    <?php echo esc_html__('Addressed minor security vulnerability in the file uploader component.', 'barefoot-engine'); ?>
                </li>
                <li>
                    <span class="material-symbols-outlined is-green" aria-hidden="true">check_small</span>
                    <?php echo esc_html__('Updated third-party dependencies to latest stable versions.', 'barefoot-engine'); ?>
                </li>
            </ul>
        </article>

        <article class="be-changelog-item">
            <div class="be-changelog-head">
                <div class="be-changelog-version-group">
                    <span class="be-badge be-badge-neutral">v2.4.3</span>
                    <span class="be-changelog-date"><?php echo esc_html__('August 02, 2023', 'barefoot-engine'); ?></span>
                </div>
                <span class="be-badge be-badge-neutral"><?php echo esc_html__('Maintenance', 'barefoot-engine'); ?></span>
            </div>
            <ul class="be-changelog-points">
                <li>
                    <span class="material-symbols-outlined is-green" aria-hidden="true">check_small</span>
                    <?php echo esc_html__('Resolved conflict with popular SEO plugin.', 'barefoot-engine'); ?>
                </li>
                <li>
                    <span class="material-symbols-outlined is-blue" aria-hidden="true">add</span>
                    <?php echo esc_html__('Added dashboard widget for quick stats overview.', 'barefoot-engine'); ?>
                </li>
            </ul>
        </article>
    </div>
</section>
