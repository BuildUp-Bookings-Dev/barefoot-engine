<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-panel-flush">
    <div class="be-table-head">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">code</span>
            <?php echo esc_html__('Shortcode Reference', 'barefoot-engine'); ?>
        </h3>
        <button class="be-link-button" type="button">
            <?php echo esc_html__('Copy All', 'barefoot-engine'); ?>
            <span class="be-icon material-symbols-outlined" aria-hidden="true">content_copy</span>
        </button>
    </div>

    <div class="be-table-wrap">
        <table class="be-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Shortcode', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Description', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Available Parameters', 'barefoot-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[barefoot_search_widget]</code></td>
                    <td><?php echo esc_html__('Renders the standalone bp-search-widget search bar and redirects search values to a target URL.', 'barefoot-engine'); ?></td>
                    <td><code>target_url</code>, <code>show_location</code>, <code>show_filter_button</code>, <code>location_label</code>, <code>location_placeholder</code>, <code>date_label</code>, <code>date_placeholder</code>, <code>fields</code>, <code>filters</code>, <code>months_to_show</code>, <code>datepicker_placement</code>, <code>class</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="be-card-grid">
    <article class="be-mini-card">
        <div class="be-mini-icon is-warning">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">integration_instructions</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Example Usage', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><code>[barefoot_search_widget target_url="/search-results/"]</code></p>
            <p class="be-mini-card-paragraph be-paragraph"><code>[barefoot_search_widget location_label="Destination" date_label="Stay Dates" months_to_show="2"]</code></p>
        </div>
    </article>

    <article class="be-mini-card">
        <div class="be-mini-icon is-success">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">travel_explore</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Redirect Query Params', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Search redirects include location, check_in, check_out, field_{key}, and filter_{key}. Array values are repeated with [] suffixes.', 'barefoot-engine'); ?></p>
        </div>
    </article>
</section>
