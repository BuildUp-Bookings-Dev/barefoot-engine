<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($active_tab) || !is_array($active_tab)) {
    return;
}
?>
<header class="be-admin-header">
    <h1 class="be-admin-title"><?php echo esc_html((string) $active_tab['title']); ?></h1>
    <p class="be-admin-subtitle"><?php echo esc_html((string) $active_tab['subtitle']); ?></p>
</header>
