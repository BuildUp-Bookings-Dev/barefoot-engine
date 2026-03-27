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
            <span class="be-field-label be-label-text"><?php echo esc_html__('Barefoot Account / Portal ID', 'barefoot-engine'); ?></span>
            <span class="be-input-wrap be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">business</span>
                <input
                    type="text"
                    x-model.trim="api.company_id"
                    @input="clearFieldError('company_id')"
                    placeholder="<?php echo esc_attr__('e.g. v3ccln0929', 'barefoot-engine'); ?>"
                />
            </span>
            <template x-if="fieldErrors.company_id">
                <span class="be-field-help be-field-help-error" x-text="fieldErrors.company_id"></span>
            </template>
        </label>

        <div class="be-section be-section-offset-top">
            <h3 class="be-section-title">
                <span class="material-symbols-outlined" aria-hidden="true">tune</span>
                <?php echo esc_html__('Booking Controls', 'barefoot-engine'); ?>
            </h3>

            <div class="be-typography-row be-columns">
                <div class="be-field-label-row">
                    <div class="be-columns">
                        <span class="be-field-label be-label-text"><?php echo esc_html__('Enable Mock Mode', 'barefoot-engine'); ?></span>
                        <span class="be-field-help">
                            <?php echo esc_html__('Use local checkout/session mocks and do not block dates from Barefoot availability. Helpful for safe UI testing without creating real booking holds.', 'barefoot-engine'); ?>
                        </span>
                    </div>

                    <div class="be-rows be-inherit-toggle-wrap">
                        <span class="be-toggle-state be-field-mono be-paragraph" x-text="booking.mock_mode ? 'enabled' : 'disabled'"></span>
                        <label class="be-toggle-control">
                            <input
                                type="checkbox"
                                class="be-toggle-input"
                                x-model="booking.mock_mode"
                            />
                            <span class="be-toggle-track">
                                <span class="be-toggle-thumb"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <label class="be-field be-label be-columns">
                <span class="be-field-label be-label-text"><?php echo esc_html__('Payment Mode', 'barefoot-engine'); ?></span>
                <select x-model="booking.payment_mode" :disabled="booking.mock_mode">
                    <option value="TRUE"><?php echo esc_html__('Test mode', 'barefoot-engine'); ?></option>
                    <option value="FALSE"><?php echo esc_html__('Live mode', 'barefoot-engine'); ?></option>
                </select>
                <span class="be-field-help" x-show="!booking.mock_mode">
                    <?php echo esc_html__('Test mode still blocks dates, but sends Barefoot in test payment mode so the card is not charged. Live mode submits the payment to the gateway.', 'barefoot-engine'); ?>
                </span>
                <span class="be-field-help" x-show="booking.mock_mode">
                    <?php echo esc_html__('Payment mode is ignored while mock mode is enabled.', 'barefoot-engine'); ?>
                </span>
            </label>
        </div>
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
