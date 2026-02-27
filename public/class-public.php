<?php

namespace BarefootEngine\PublicFacing;

use BarefootEngine\Includes\Helpers\Manifest;

if (!defined('ABSPATH')) {
    exit;
}

class Public_Facing
{
    private Manifest $manifest;

    public function __construct()
    {
        $this->manifest = new Manifest();
    }

    public function enqueue_assets(): void
    {
        $script = $this->manifest->find_entry_by_name('public-script');
        $style = $this->manifest->find_entry_by_source('assets/src/scss/public/index.scss');

        if (is_array($script) && !empty($script['file'])) {
            wp_enqueue_script(
                'barefoot-engine-public',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $script['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION,
                true
            );
        }

        if (is_array($style) && !empty($style['file'])) {
            wp_enqueue_style(
                'barefoot-engine-public',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $style['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION
            );
        }
    }
}
