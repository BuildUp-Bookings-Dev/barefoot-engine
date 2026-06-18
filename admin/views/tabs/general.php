<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-columns" x-data="beGeneralSettingsForm" x-init="init()">
    <form class="be-columns" @submit.prevent="saveSettings">
        <div class="be-section be-columns">
            <h3 class="be-section-title be-heading be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">monitoring</span>
                <?php echo esc_html__('Tracking', 'barefoot-engine'); ?>
            </h3>

            <label class="be-tracking-toggle be-label">
                <input
                    type="checkbox"
                    class="be-checkbox"
                    x-model="tracking.enabled"
                    @change="clearFieldError('tracking.enabled')"
                />
                <span class="be-label-text"><?php echo esc_html__('Enable booking tracking events', 'barefoot-engine'); ?></span>
            </label>

            <label class="be-field be-label be-columns">
                <span class="be-field-label be-label-text"><?php echo esc_html__('Google Tag or Measurement ID', 'barefoot-engine'); ?></span>
                <input
                    type="text"
                    x-model="tracking.google_tag_id"
                    @input="tracking.google_tag_id = tracking.google_tag_id.toUpperCase(); clearFieldError('tracking.google_tag_id')"
                    placeholder="G-XXXXXXXXXX"
                    autocomplete="off"
                />
                <template x-if="fieldError('tracking.google_tag_id')">
                    <span class="be-field-help be-field-help-error" x-text="fieldError('tracking.google_tag_id')"></span>
                </template>
                <span class="be-field-help"><?php echo esc_html__('Use a GA4 Measurement ID such as G-XXXXXXXXXX or a GTM container ID such as GTM-XXXXXXX. Booking events are sent to this ID; if the matching Google tag is already embedded, the plugin reuses it instead of adding another copy.', 'barefoot-engine'); ?></span>
            </label>
        </div>

        <div class="be-panel-actions be-rows">
            <button class="be-button be-button-primary" type="submit" :disabled="isSaving">
                <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isSaving ? 'hourglass_top' : 'save'"></span>
                <span x-text="isSaving ? '<?php echo esc_attr__('Saving...', 'barefoot-engine'); ?>' : '<?php echo esc_attr__('Save Changes', 'barefoot-engine'); ?>'"></span>
            </button>
        </div>
    </form>
</section>
