<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($tabs, $active_tab, $active_slug, $active_template, $components_dir)) {
    return;
}

$sidebar_view = $components_dir . 'sidebar.php';
$header_view = $components_dir . 'header.php';
?>
<div class="be-admin-screen">
    <div class="be-admin-layout">
        <?php if (file_exists($sidebar_view)) : ?>
            <?php include $sidebar_view; ?>
        <?php endif; ?>

        <main class="be-admin-main">
            <div class="be-admin-container">
                <?php if (file_exists($header_view)) : ?>
                    <?php include $header_view; ?>
                <?php endif; ?>

                <?php if (file_exists($active_template)) : ?>
                    <div class="be-admin-content">
                        <?php include $active_template; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
