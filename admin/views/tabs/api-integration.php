<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-panel-narrow">
    <div class="be-field-stack">
        <label class="be-field">
            <span class="be-field-label"><?php echo esc_html__('Username', 'barefoot-engine'); ?></span>
            <span class="be-input-wrap">
                <span class="material-symbols-outlined" aria-hidden="true">person</span>
                <input type="text" placeholder="<?php echo esc_attr__('Enter your API username', 'barefoot-engine'); ?>" />
            </span>
        </label>

        <label class="be-field">
            <span class="be-field-label"><?php echo esc_html__('Password', 'barefoot-engine'); ?></span>
            <span class="be-input-wrap">
                <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                <input type="password" value="supersecretpassword" />
                <button class="be-icon-button" type="button" aria-label="<?php echo esc_attr__('Toggle password visibility', 'barefoot-engine'); ?>">
                    <span class="material-symbols-outlined" aria-hidden="true">visibility_off</span>
                </button>
            </span>
        </label>

        <label class="be-field">
            <span class="be-field-label"><?php echo esc_html__('Company ID', 'barefoot-engine'); ?></span>
            <span class="be-input-wrap">
                <span class="material-symbols-outlined" aria-hidden="true">business</span>
                <input type="text" placeholder="<?php echo esc_attr__('e.g. COMP-12345', 'barefoot-engine'); ?>" />
            </span>
            <span class="be-field-help"><?php echo esc_html__('Your unique company identifier provided in the developer dashboard.', 'barefoot-engine'); ?></span>
        </label>
    </div>

    <div class="be-panel-actions">
        <button class="be-button be-button-primary" type="button">
            <span class="material-symbols-outlined" aria-hidden="true">save</span>
            <?php echo esc_html__('Save Changes', 'barefoot-engine'); ?>
        </button>

        <button class="be-button be-button-outline" type="button">
            <span class="material-symbols-outlined" aria-hidden="true">wifi_tethering</span>
            <?php echo esc_html__('Test Connection', 'barefoot-engine'); ?>
        </button>

        <span class="be-inline-status">
            <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
            <?php echo esc_html__('Settings Saved', 'barefoot-engine'); ?>
        </span>
    </div>
</section>


