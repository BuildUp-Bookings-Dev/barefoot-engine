<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-panel-narrow be-columns" x-data="beApiIntegrationForm" x-init="init()">
    <form class="be-field-stack be-columns" @submit.prevent="saveSettings">
        <label class="be-field be-label be-columns">
            <span class="be-field-label be-label-text"><?php echo esc_html__('Username', 'barefoot-engine'); ?></span>
            <span class="be-input-wrap be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">person</span>
                <input
                    type="text"
                    x-model.trim="api.username"
                    @input="clearFieldError('username')"
                    placeholder="<?php echo esc_attr__('Enter your API username', 'barefoot-engine'); ?>"
                />
            </span>
            <template x-if="fieldErrors.username">
                <span class="be-field-help be-field-help-error" x-text="fieldErrors.username"></span>
            </template>
        </label>

        <label class="be-field be-label be-columns">
            <span class="be-field-label be-label-text"><?php echo esc_html__('Password', 'barefoot-engine'); ?></span>
            <span class="be-input-wrap be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">lock</span>
                <input
                    :type="showPassword ? 'text' : 'password'"
                    x-model="passwordInput"
                    autocomplete="new-password"
                    :placeholder="hasPassword ? '********' : '<?php echo esc_attr__('Enter your API password', 'barefoot-engine'); ?>'"
                />

            </span>
            <span class="be-field-help" x-show="hasPassword && passwordInput === ''">
                <?php echo esc_html__('A password is already stored. Leave this blank to keep the current password.', 'barefoot-engine'); ?>
            </span>
        </label>

        <label class="be-field be-label be-columns">
            <span class="be-field-label be-label-text"><?php echo esc_html__('Company ID', 'barefoot-engine'); ?></span>
            <span class="be-input-wrap be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">business</span>
                <input
                    type="text"
                    x-model.trim="api.company_id"
                    @input="clearFieldError('company_id')"
                    placeholder="<?php echo esc_attr__('e.g. COMP-12345', 'barefoot-engine'); ?>"
                />
            </span>
            <template x-if="fieldErrors.company_id">
                <span class="be-field-help be-field-help-error" x-text="fieldErrors.company_id"></span>
            </template>
            <span class="be-field-help"><?php echo esc_html__('Your unique company identifier provided in the developer dashboard.', 'barefoot-engine'); ?></span>
        </label>
    </form>

    <div class="be-panel-actions be-rows">
        <button class="be-button be-button-primary" type="button" @click="saveSettings()" :disabled="isSaving">
            <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isSaving ? 'hourglass_top' : 'save'"></span>
            <span x-text="isSaving ? '<?php echo esc_attr__('Saving...', 'barefoot-engine'); ?>' : '<?php echo esc_attr__('Save Changes', 'barefoot-engine'); ?>'"></span>
        </button>

        <button class="be-button be-button-outline" type="button" @click="testConnection()" :disabled="isTesting">
            <span class="be-icon material-symbols-outlined" aria-hidden="true" x-text="isTesting ? 'hourglass_top' : 'wifi_tethering'"></span>
            <span x-text="isTesting ? '<?php echo esc_attr__('Testing...', 'barefoot-engine'); ?>' : '<?php echo esc_attr__('Test Connection', 'barefoot-engine'); ?>'"></span>
        </button>
    </div>
</section>
