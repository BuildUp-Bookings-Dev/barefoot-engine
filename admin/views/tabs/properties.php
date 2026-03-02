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
                <p class="be-paragraph"><?php echo esc_html__('Pull the latest property records from Barefoot and upsert them into the Property post type.', 'barefoot-engine'); ?></p>
                <p class="be-field-help"><?php echo esc_html__('This sync uses Barefoot\'s GetPropertyExt endpoint, which returns active properties only.', 'barefoot-engine'); ?></p>
            </div>

            <button class="be-button be-button-primary" type="button" @click="syncProperties()" :disabled="isSyncing || isLoading">
                <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isSyncing ? 'hourglass_top' : 'cloud_download'"></span>
                <span x-text="isSyncing ? '<?php echo esc_attr__('Syncing...', 'barefoot-engine'); ?>' : '<?php echo esc_attr__('Sync Properties', 'barefoot-engine'); ?>'"></span>
            </button>
        </div>

        <div
            class="be-sync-progress be-columns"
            x-show="isSyncing"
            x-cloak
            aria-live="polite"
        >
            <div
                class="be-sync-progress-track"
                role="progressbar"
                aria-label="<?php echo esc_attr__('Property sync progress', 'barefoot-engine'); ?>"
                aria-valuetext="<?php echo esc_attr__('Sync in progress', 'barefoot-engine'); ?>"
            >
                <span class="be-sync-progress-bar"></span>
            </div>
            <p class="be-sync-progress-text be-paragraph" x-text="syncProgressMessage"></p>
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

    <section class="be-panel be-columns">
        <div class="be-columns">
            <h2 class="be-section-title be-heading be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">label</span>
                <?php echo esc_html__('Field Aliases', 'barefoot-engine'); ?>
            </h2>
            <p class="be-paragraph"><?php echo esc_html__('Rename Barefoot keys globally. Custom aliases override Barefoot amenity labels and the default humanized label.', 'barefoot-engine'); ?></p>
        </div>

        <template x-if="isLoading">
            <p class="be-paragraph"><?php echo esc_html__('Loading property settings...', 'barefoot-engine'); ?></p>
        </template>

        <template x-if="!isLoading && aliasRows.length === 0">
            <div class="be-empty-state be-columns">
                <p class="be-paragraph"><?php echo esc_html__('No property fields have been discovered yet. Run a property sync first.', 'barefoot-engine'); ?></p>
            </div>
        </template>

        <template x-if="!isLoading && aliasRows.length > 0">
            <div class="be-columns">
                <div class="be-alias-table-wrap">
                    <table class="be-alias-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Barefoot Key', 'barefoot-engine'); ?></th>
                                <th><?php echo esc_html__('Default Label', 'barefoot-engine'); ?></th>
                                <th><?php echo esc_html__('Custom Alias', 'barefoot-engine'); ?></th>
                                <th><?php echo esc_html__('Effective Label', 'barefoot-engine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, index) in aliasRows" :key="row.key">
                                <tr>
                                    <td><code x-text="row.key"></code></td>
                                    <td x-text="row.default_label"></td>
                                    <td>
                                        <input
                                            type="text"
                                            class="be-input"
                                            :value="row.alias"
                                            @input="updateAlias(index, $event.target.value)"
                                            :placeholder="row.default_label"
                                        />
                                    </td>
                                    <td x-text="row.effective_label"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="be-panel-actions be-rows">
                    <button class="be-button be-button-primary" type="button" @click="saveAliases()" :disabled="isSavingAliases || isLoading">
                        <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isSavingAliases ? 'hourglass_top' : 'save'"></span>
                        <span x-text="isSavingAliases ? '<?php echo esc_attr__('Saving...', 'barefoot-engine'); ?>' : '<?php echo esc_attr__('Save Aliases', 'barefoot-engine'); ?>'"></span>
                    </button>
                </div>
            </div>
        </template>
    </section>
</div>
