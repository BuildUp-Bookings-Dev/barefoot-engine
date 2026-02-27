<?php

namespace BarefootEngine\Integrations;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined('ABSPATH')) {
    exit;
}

class Github_Updater
{
    /**
     * Register Plugin Update Checker against GitHub releases.
     */
    public function register(): void
    {
        if (!class_exists(PucFactory::class)) {
            return;
        }

        $repository = apply_filters('barefoot_engine_updater_repository', BAREFOOT_ENGINE_GITHUB_REPOSITORY);
        $branch = apply_filters('barefoot_engine_updater_branch', BAREFOOT_ENGINE_GITHUB_BRANCH);

        $updater = PucFactory::buildUpdateChecker(
            (string) $repository,
            BAREFOOT_ENGINE_PLUGIN_FILE,
            'barefoot-engine'
        );

        $updater->setBranch((string) $branch);

        $vcs_api = $updater->getVcsApi();
        if (is_object($vcs_api) && method_exists($vcs_api, 'enableReleaseAssets')) {
            $vcs_api->enableReleaseAssets();
        }
    }
}
