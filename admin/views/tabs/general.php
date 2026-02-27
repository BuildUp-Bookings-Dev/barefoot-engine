<?php
if (!defined('ABSPATH')) {
    exit;
}

$typography_rows = [
    [
        'key' => 'header',
        'title' => __('Header', 'barefoot-engine'),
        'family_label' => __('Header Font Family', 'barefoot-engine'),
        'size_label' => __('Header Font Size', 'barefoot-engine'),
    ],
    [
        'key' => 'label',
        'title' => __('Labels', 'barefoot-engine'),
        'family_label' => __('Labels Font Family', 'barefoot-engine'),
        'size_label' => __('Labels Font Size', 'barefoot-engine'),
    ],
    [
        'key' => 'body',
        'title' => __('Body', 'barefoot-engine'),
        'family_label' => __('Body Font Family', 'barefoot-engine'),
        'size_label' => __('Body Font Size', 'barefoot-engine'),
    ],
];
?>
<section class="be-panel be-columns" x-data="beGeneralSettingsForm" x-init="init()">
    <form class="be-columns" @submit.prevent="saveSettings">
        <div class="be-section be-columns">
            <h3 class="be-section-title be-heading be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">palette</span>
                <?php echo esc_html__('Colors', 'barefoot-engine'); ?>
            </h3>

            <div class="be-color-grid be-columns">
                <label class="be-field be-field-color be-label be-columns">
                    <span class="be-field-label be-label-text"><?php echo esc_html__('Primary', 'barefoot-engine'); ?></span>
                    <span class="be-color-input-wrap be-rows">
                        <input type="color" x-model="colors.primary" @input="clearFieldError('colors.primary')" />
                        <span class="be-field-mono be-paragraph" x-text="colors.primary"></span>
                    </span>
                    <template x-if="fieldError('colors.primary')">
                        <span class="be-field-help be-field-help-error" x-text="fieldError('colors.primary')"></span>
                    </template>
                </label>

                <label class="be-field be-field-color be-label be-columns">
                    <span class="be-field-label be-label-text"><?php echo esc_html__('Secondary', 'barefoot-engine'); ?></span>
                    <span class="be-color-input-wrap be-rows">
                        <input type="color" x-model="colors.secondary" @input="clearFieldError('colors.secondary')" />
                        <span class="be-field-mono be-paragraph" x-text="colors.secondary"></span>
                    </span>
                    <template x-if="fieldError('colors.secondary')">
                        <span class="be-field-help be-field-help-error" x-text="fieldError('colors.secondary')"></span>
                    </template>
                </label>

                <label class="be-field be-field-color be-label be-columns">
                    <span class="be-field-label be-label-text"><?php echo esc_html__('Accent', 'barefoot-engine'); ?></span>
                    <span class="be-color-input-wrap be-rows">
                        <input type="color" x-model="colors.accent" @input="clearFieldError('colors.accent')" />
                        <span class="be-field-mono be-paragraph" x-text="colors.accent"></span>
                    </span>
                    <template x-if="fieldError('colors.accent')">
                        <span class="be-field-help be-field-help-error" x-text="fieldError('colors.accent')"></span>
                    </template>
                </label>
            </div>
        </div>

        <div class="be-section be-columns">
            <h3 class="be-section-title be-heading be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">text_fields</span>
                <?php echo esc_html__('Typography', 'barefoot-engine'); ?>
            </h3>

            <div class="be-typography-rows be-columns">
                <?php foreach ($typography_rows as $row) : ?>
                    <?php
                    $row_key = (string) $row['key'];
                    $family_field = $row_key . '_font_family';
                    $size_field = $row_key . '_font_size';
                    $family_toggle_id = 'be-' . $row_key . '-font-family-inherit';
                    $size_toggle_id = 'be-' . $row_key . '-font-size-inherit';
                    $size_input_id = 'be-' . $row_key . '-font-size';
                    ?>
                    <div class="be-typography-row be-columns">
                        <div class="be-typography-row-header be-rows">
                            <h4 class="be-typography-row-title be-heading"><?php echo esc_html((string) $row['title']); ?></h4>
                        </div>

                        <div class="be-typography-row-controls">
                            <div class="be-typography-control be-field be-label be-columns">
                                <div class="be-field-label be-label-text be-field-label-row be-rows">
                                    <span><?php echo esc_html((string) $row['family_label']); ?></span>
                                    <span class="be-rows">
                                        <span
                                            class="be-toggle-state be-field-mono be-paragraph"
                                            x-show="isFontInherited('<?php echo esc_attr($row_key); ?>')"
                                            x-cloak
                                        ><?php echo esc_html__('inherit', 'barefoot-engine'); ?></span>
                                        <label for="<?php echo esc_attr($family_toggle_id); ?>" class="be-toggle-control">
                                            <span class="screen-reader-text"><?php echo esc_html(sprintf(__('Use inherit for %s', 'barefoot-engine'), (string) $row['family_label'])); ?></span>
                                            <input
                                                id="<?php echo esc_attr($family_toggle_id); ?>"
                                                type="checkbox"
                                                class="be-toggle-input"
                                                :checked="isFontInherited('<?php echo esc_attr($row_key); ?>')"
                                                @change="toggleFontInherit('<?php echo esc_attr($row_key); ?>', $event)"
                                            />
                                            <span class="be-toggle-track">
                                                <span class="be-toggle-thumb"></span>
                                            </span>
                                        </label>
                                    </span>
                                </div>

                                <div x-show="!isFontInherited('<?php echo esc_attr($row_key); ?>')" x-cloak>
                                    <select
                                        x-model="typography.<?php echo esc_attr($family_field); ?>"
                                        @change="clearFieldError('typography.<?php echo esc_attr($family_field); ?>')"
                                    >
                                        <template x-for="option in fontOptions" :key="option.value">
                                            <option :value="option.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </div>

                                <template x-if="fieldError('typography.<?php echo esc_attr($family_field); ?>')">
                                    <span class="be-field-help be-field-help-error" x-text="fieldError('typography.<?php echo esc_attr($family_field); ?>')"></span>
                                </template>
                            </div>

                            <div class="be-typography-control be-field be-label be-columns">
                                <div class="be-field-label be-label-text be-field-label-row be-rows">
                                    <span><?php echo esc_html((string) $row['size_label']); ?></span>
                                    <span class="be-rows">
                                        <span
                                            class="be-toggle-state be-field-mono be-paragraph"
                                            x-show="isSizeInherited('<?php echo esc_attr($row_key); ?>')"
                                            x-cloak
                                        ><?php echo esc_html__('inherit', 'barefoot-engine'); ?></span>
                                        <label for="<?php echo esc_attr($size_toggle_id); ?>" class="be-toggle-control">
                                            <span class="screen-reader-text"><?php echo esc_html(sprintf(__('Use inherit for %s', 'barefoot-engine'), (string) $row['size_label'])); ?></span>
                                            <input
                                                id="<?php echo esc_attr($size_toggle_id); ?>"
                                                type="checkbox"
                                                class="be-toggle-input"
                                                :checked="isSizeInherited('<?php echo esc_attr($row_key); ?>')"
                                                @change="toggleSizeInherit('<?php echo esc_attr($row_key); ?>', $event)"
                                            />
                                            <span class="be-toggle-track">
                                                <span class="be-toggle-thumb"></span>
                                            </span>
                                        </label>
                                    </span>
                                </div>

                                <div x-show="!isSizeInherited('<?php echo esc_attr($row_key); ?>')" x-cloak>
                                    <div class="be-range-row">
                                        <label for="<?php echo esc_attr($size_input_id); ?>" class="screen-reader-text be-label-text"><?php echo esc_html((string) $row['size_label']); ?></label>
                                        <input
                                            id="<?php echo esc_attr($size_input_id); ?>"
                                            type="range"
                                            class="be-range-input"
                                            :min="config.min"
                                            :max="config.max"
                                            :step="config.step"
                                            :value="sizeValue('<?php echo esc_attr($row_key); ?>')"
                                            @input="onSizeInput('<?php echo esc_attr($row_key); ?>', $event)"
                                        />
                                        <span class="be-field-mono be-paragraph be-range-value" x-text="sizeLabel('<?php echo esc_attr($row_key); ?>')"></span>
                                    </div>
                                </div>

                                <template x-if="fieldError('typography.<?php echo esc_attr($size_field); ?>')">
                                    <span class="be-field-help be-field-help-error" x-text="fieldError('typography.<?php echo esc_attr($size_field); ?>')"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="be-section be-columns">
            <h3 class="be-section-title be-heading be-rows">
                <span class="be-icon material-symbols-outlined" aria-hidden="true">code</span>
                <?php echo esc_html__('Custom CSS', 'barefoot-engine'); ?>
            </h3>

            <label class="be-field be-label be-columns">
                <span class="screen-reader-text be-label-text"><?php echo esc_html__('Custom CSS', 'barefoot-engine'); ?></span>
                <textarea
                    rows="8"
                    x-model="customCss"
                    @input="clearFieldError('custom_css')"
                    placeholder=".barefoot-engine-public .be-paragraph {&#10;  color: #0f172a;&#10;}"
                ></textarea>
                <span class="be-field-help be-paragraph"><?php echo esc_html__('Saved CSS is printed on frontend in a style tag with class be-custom-css.', 'barefoot-engine'); ?></span>
                <template x-if="fieldError('custom_css')">
                    <span class="be-field-help be-field-help-error" x-text="fieldError('custom_css')"></span>
                </template>
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
