<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-columns">
    <div class="be-section be-columns">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">palette</span>
            <?php echo esc_html__('Colors', 'barefoot-engine'); ?>
        </h3>

        <div class="be-color-grid be-columns">
            <label class="be-field be-field-color be-label be-columns">
                <span class="be-field-label be-label-text"><?php echo esc_html__('Primary', 'barefoot-engine'); ?></span>
                <span class="be-color-input-wrap">
                    <input type="color" value="#111111" />
                    <span>#111111</span>
                </span>
            </label>

            <label class="be-field be-field-color be-label be-columns">
                <span class="be-field-label be-label-text"><?php echo esc_html__('Secondary', 'barefoot-engine'); ?></span>
                <span class="be-color-input-wrap">
                    <input type="color" value="#64748b" />
                    <span>#64748b</span>
                </span>
            </label>

            <label class="be-field be-field-color be-label be-columns">
                <span class="be-field-label be-label-text"><?php echo esc_html__('Accent', 'barefoot-engine'); ?></span>
                <span class="be-color-input-wrap">
                    <input type="color" value="#3b82f6" />
                    <span>#3b82f6</span>
                </span>
            </label>
        </div>
    </div>

    <div class="be-section be-columns">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">text_fields</span>
            <?php echo esc_html__('Typography', 'barefoot-engine'); ?>
        </h3>

        <div class="be-field-grid">
            <label class="be-field be-label be-columns">
                <span class="be-field-label be-label-text"><?php echo esc_html__('Heading Font', 'barefoot-engine'); ?></span>
                <select>
                    <option><?php echo esc_html__('Inter', 'barefoot-engine'); ?></option>
                    <option><?php echo esc_html__('Roboto', 'barefoot-engine'); ?></option>
                    <option><?php echo esc_html__('Open Sans', 'barefoot-engine'); ?></option>
                    <option><?php echo esc_html__('Playfair Display', 'barefoot-engine'); ?></option>
                </select>
            </label>

            <label class="be-field be-label be-columns">
                <span class="be-field-label be-label-text"><?php echo esc_html__('Body Font', 'barefoot-engine'); ?></span>
                <select>
                    <option><?php echo esc_html__('Inter', 'barefoot-engine'); ?></option>
                    <option><?php echo esc_html__('Lato', 'barefoot-engine'); ?></option>
                    <option><?php echo esc_html__('Merriweather', 'barefoot-engine'); ?></option>
                    <option><?php echo esc_html__('System UI', 'barefoot-engine'); ?></option>
                </select>
            </label>
        </div>

        <label class="be-field be-label be-columns">
            <span class="be-field-label be-label-text be-field-label-row be-rows">
                <span><?php echo esc_html__('Base Font Size', 'barefoot-engine'); ?></span>
                <span class="be-field-mono">16px</span>
            </span>
            <input type="range" min="12" max="24" value="16" />
        </label>
    </div>

    <div class="be-section be-columns">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">code</span>
            <?php echo esc_html__('Custom CSS', 'barefoot-engine'); ?>
        </h3>

        <label class="be-field be-label be-columns">
            <span class="screen-reader-text be-label-text"><?php echo esc_html__('Custom CSS', 'barefoot-engine'); ?></span>
            <textarea rows="6" placeholder=".example-class {&#10;  color: #000;&#10;}"></textarea>
            <span class="be-field-help be-paragraph"><?php echo esc_html__('Add custom CSS rules to override default styles. Use !important sparingly.', 'barefoot-engine'); ?></span>
        </label>
    </div>

    <div class="be-panel-actions">
        <button class="be-button be-button-primary" type="button">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">save</span>
            <?php echo esc_html__('Save Changes', 'barefoot-engine'); ?>
        </button>


    </div>
</section>
