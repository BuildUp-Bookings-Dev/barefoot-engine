<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-data="beUpdatesTab" x-init="init()" class="be-columns">
    <section class="be-status-card be-columns">
        <div class="be-status-card-main be-rows">
            <div class="be-status-icon-wrap">
                <span class="be-icon material-symbols-outlined be-status-icon is-filled" aria-hidden="true">check_circle</span>
            </div>
            <div class="be-columns">
                <div class="be-status-title-row be-rows">
                    <h2 class="be-status-title be-heading" x-text="`Current Version: v${status.currentVersion || '0.0.0'}`"></h2>
                    <span :class="badgeClass()" x-text="badgeText()"></span>
                </div>
                <p class="be-status-copy be-paragraph" x-text="status.summary"></p>
                <p class="be-status-meta be-paragraph" x-text="`Last checked: ${status.lastChecked}`"></p>
                <p class="be-status-meta be-paragraph" x-show="status.latestVersion" x-cloak x-text="`Latest release: v${status.latestVersion}`"></p>
            </div>
        </div>

        <button class="be-button be-button-primary" type="button" @click="checkForUpdates()" :disabled="isChecking">
            <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isChecking ? 'hourglass_top' : 'cached'"></span>
            <span x-text="isChecking ? '<?php echo esc_attr__('Checking...', 'barefoot-engine'); ?>' : '<?php echo esc_attr__('Check for Updates', 'barefoot-engine'); ?>'"></span>
        </button>
    </section>

    <section class="be-panel be-panel-tight be-columns">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">history</span>
            <?php echo esc_html__('Release history', 'barefoot-engine'); ?>
        </h3>

        <div class="be-changelog-list be-columns">
            <template x-if="isLoadingReleases">
                <article class="be-changelog-item be-columns">
                    <p class="be-paragraph"><?php echo esc_html__('Loading release history...', 'barefoot-engine'); ?></p>
                </article>
            </template>

            <template x-if="!isLoadingReleases && releases.length === 0">
                <article class="be-changelog-item be-columns">
                    <p class="be-paragraph"><?php echo esc_html__('No published releases were found for this repository yet.', 'barefoot-engine'); ?></p>
                </article>
            </template>

            <template x-for="release in releases" :key="release.tagName">
                <article class="be-changelog-item be-columns">
                    <div class="be-changelog-head be-rows">
                        <div class="be-changelog-version-group be-rows">
                            <span class="be-badge be-badge-primary" x-text="release.tagName"></span>
                            <span class="be-changelog-date" x-text="release.publishedAt"></span>
                        </div>
                        <span class="be-badge be-badge-neutral" x-text="release.prerelease ? '<?php echo esc_attr__('Prerelease', 'barefoot-engine'); ?>' : '<?php echo esc_attr__('Release', 'barefoot-engine'); ?>'"></span>
                    </div>

                    <p class="be-release-excerpt be-paragraph" x-text="release.bodyExcerpt"></p>

                    <p class="be-release-link-wrap be-paragraph" x-show="release.url" x-cloak>
                        <a class="be-link be-mini-card-link" :href="release.url" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html__('View release on GitHub', 'barefoot-engine'); ?>
                        </a>
                    </p>
                </article>
            </template>
        </div>
    </section>
</div>
