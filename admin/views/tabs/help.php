<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="be-panel be-panel-flush">
    <div class="be-table-head">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">help</span>
            <?php echo esc_html__('Plugin Guide', 'barefoot-engine'); ?>
        </h3>
    </div>

    <div class="be-table-wrap">
        <table class="be-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Area', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Purpose', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('What To Check', 'barefoot-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code><?php echo esc_html__('API Integration', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Store the Barefoot login details used by the plugin.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Confirm the connection test passes and credentials are valid before syncing data.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('Properties', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Import, review, and manage synced properties.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Review property records after syncing and confirm recent changes are present.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('Updates', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Check the current plugin version and available updates.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Review the latest release details when checking for updates.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code><?php echo esc_html__('Help', 'barefoot-engine'); ?></code></td>
                    <td><?php echo esc_html__('Reference the current shortcodes, booking routes, and operational tips.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Use this page when wiring pages in Elementor, troubleshooting booking flows, or checking available widget entry points.', 'barefoot-engine'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="be-card-grid">
    <article class="be-mini-card">
        <div class="be-mini-icon is-warning">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">inventory_2</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Before You Start', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Save and test your API credentials before running any property sync.', 'barefoot-engine'); ?></p>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Use a full sync first, then use partial sync for smaller follow-up updates.', 'barefoot-engine'); ?></p>
        </div>
    </article>

    <article class="be-mini-card">
        <div class="be-mini-icon is-success">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">rule</span>
        </div>
        <div class="be-columns">
            <h3 class="be-mini-card-heading be-heading"><?php echo esc_html__('Recommended Workflow', 'barefoot-engine'); ?></h3>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Confirm API credentials first, then verify public output, then move to property operations.', 'barefoot-engine'); ?></p>
            <p class="be-mini-card-paragraph be-paragraph"><?php echo esc_html__('Run property syncs only after API credentials are confirmed.', 'barefoot-engine'); ?></p>
        </div>
    </article>
</section>

<section class="be-panel be-panel-flush">
    <div class="be-table-head">
        <h3 class="be-section-title be-heading be-rows">
            <span class="be-icon material-symbols-outlined" aria-hidden="true">short_stay</span>
            <?php echo esc_html__('Shortcodes', 'barefoot-engine'); ?>
        </h3>
    </div>

    <div class="be-table-wrap">
        <table class="be-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Shortcode', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Purpose', 'barefoot-engine'); ?></th>
                    <th><?php echo esc_html__('Notes', 'barefoot-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[barefoot_search_widget]</code></td>
                    <td><?php echo esc_html__('Standalone search entry widget.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Use for homepage or landing-page search entry points.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code>[barefoot_listings]</code></td>
                    <td><?php echo esc_html__('Listings results experience with map, filters, and AJAX search.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Use on the main results page such as /properties/.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code>[barefoot_booking_widget]</code></td>
                    <td><?php echo esc_html__('Property-page booking widget with availability and quote summary.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Usually used on single property pages.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code>[barefoot_booking_checkout]</code></td>
                    <td><?php echo esc_html__('Checkout form for guest details, payment details, and booking summary.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Usually used on /booking-confirmation/.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code>[barefoot_pricing_table]</code></td>
                    <td><?php echo esc_html__('Property pricing table with searchable date/rate rows.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Useful on property pages or inside pricing modals.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code>[barefoot_featured_properties]</code></td>
                    <td><?php echo esc_html__('Featured properties slider.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Supports the limit attribute and featured-only property output.', 'barefoot-engine'); ?></td>
                </tr>
                <tr>
                    <td><code>[barefoot_property_grid]</code></td>
                    <td><?php echo esc_html__('Property grid with optional filters and page-number pagination.', 'barefoot-engine'); ?></td>
                    <td><?php echo esc_html__('Useful for Elementor layouts or landing pages that need all active properties.', 'barefoot-engine'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
