<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-data="bePropertiesTab" x-init="init()" class="be-columns">
    <section class="be-panel be-columns">
        <div class="be-sync-panel-head be-rows">
            <div class="be-columns">
                <h2 class="be-section-title be-heading be-rows">
                    <span class="be-icon material-symbols-outlined" aria-hidden="true">sync</span>
                    <?php echo esc_html__('Property Sync', 'barefoot-engine'); ?>
                </h2>
                <p class="be-paragraph"><?php echo esc_html__('Import and update the latest property records from Barefoot.', 'barefoot-engine'); ?></p>
            </div>

            <div class="be-sync-actions">
                <button class="be-button be-button-primary" type="button" @click="syncProperties()" :disabled="isSyncing || isLoading">
                    <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isSyncing ? 'hourglass_top' : 'cloud_download'"></span>
                    <span x-text="isSyncing ? '<?php echo esc_attr__('Syncing...', 'barefoot-engine'); ?>' : fullSyncButtonLabel()"></span>
                </button>

                <button
                    class="be-button be-button-secondary"
                    type="button"
                    @click="partialSyncProperties()"
                    :disabled="isSyncing || isLoading"
                    x-show="canRunPartialSync()"
                    x-cloak
                >
                    <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isSyncing ? 'hourglass_top' : 'update'"></span>
                    <span><?php echo esc_html__('Partial Sync', 'barefoot-engine'); ?></span>
                </button>
            </div>
        </div>

        <div
            class="be-sync-progress be-columns"
            x-show="isSyncing || progress.active"
            x-cloak
            aria-live="polite"
        >
            <div
                class="be-sync-progress-track"
                role="progressbar"
                aria-label="<?php echo esc_attr__('Property sync progress', 'barefoot-engine'); ?>"
                aria-valuemin="0"
                :aria-valuenow="progress.current"
                :aria-valuemax="progressValueMax()"
                :aria-valuetext="progressAriaText()"
            >
                <span
                    class="be-sync-progress-bar"
                    :class="{ 'is-determinate': hasDeterminateProgress() }"
                    :style="progressBarStyle()"
                ></span>
            </div>
            <p class="be-sync-progress-text be-paragraph" x-text="progressSummaryText()"></p>
            <p
                class="be-sync-progress-text be-sync-progress-detail be-paragraph"
                x-show="progressCurrentItemText()"
                x-text="progressCurrentItemText()"
            ></p>
        </div>

        <div class="be-sync-summary-grid">
            <article class="be-sync-stat-card be-columns">
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Last Finished', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="syncState.last_finished_human || '<?php echo esc_attr__('Not available', 'barefoot-engine'); ?>'"></strong>
            </article>

            <article class="be-sync-stat-card be-columns">
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Status', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="statusLabel()"></strong>
            </article>

            <article class="be-sync-stat-card be-columns">
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Properties Seen', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="summary.total_seen"></strong>
            </article>

            <article class="be-sync-stat-card be-columns">
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Created / Updated', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="`${summary.created} / ${summary.updated}`"></strong>
            </article>
        </div>

        <div class="be-sync-summary-grid">
            <article class="be-sync-stat-card be-columns">
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Unchanged', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="summary.unchanged"></strong>
            </article>

            <article class="be-sync-stat-card be-columns">
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Deactivated', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="summary.deactivated"></strong>
            </article>

            <article class="be-sync-stat-card be-columns">
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Skipped', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="summary.skipped"></strong>
            </article>

            <article class="be-sync-stat-card be-columns" x-show="syncState.last_error" x-cloak>
                <span class="be-sync-stat-label be-label-text"><?php echo esc_html__('Last Error', 'barefoot-engine'); ?></span>
                <strong class="be-sync-stat-value be-heading" x-text="syncState.last_error"></strong>
            </article>
        </div>
    </section>
</div>
