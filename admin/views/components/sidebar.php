<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($tabs, $active_slug) || !is_array($tabs)) {
    return;
}
?>
<aside class="be-admin-sidebar" aria-label="<?php echo esc_attr__('Plugin Navigation', 'barefoot-engine'); ?>">
    <div class="be-admin-sidebar-inner">
        <div class="be-admin-logo-wrap">
            <img
                src="<?php echo esc_url(BAREFOOT_ENGINE_PLUGIN_URL . 'assets/images/bub-api.webp'); ?>"
                alt="<?php echo esc_attr__('Barefoot Engine', 'barefoot-engine'); ?>"
                class="be-admin-logo"
            />
        </div>

        <nav class="be-admin-nav" aria-label="<?php echo esc_attr__('Settings Tabs', 'barefoot-engine'); ?>">
            <?php foreach ($tabs as $slug => $tab) : ?>
                <?php
                $is_active = $slug === $active_slug;
                $link_classes = 'be-admin-nav-link' . ($is_active ? ' is-active' : '');
                $icon_classes = 'material-symbols-outlined be-admin-nav-icon' . ($is_active ? ' is-filled' : '');
                ?>
                <a class="be-link be-rows <?php echo esc_attr($link_classes); ?>" href="<?php echo esc_url((string) $tab['url']); ?>">
                    <span class="<?php echo esc_attr($icon_classes); ?>" aria-hidden="true"><?php echo esc_html((string) $tab['icon']); ?></span>
                    <span class="be-admin-nav-label"><?php echo esc_html((string) $tab['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>
