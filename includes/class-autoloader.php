<?php

namespace BarefootEngine\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    /**
     * Register the plugin autoloader.
     */
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Load a namespaced class from plugin directories.
     *
     * @param string $class Class name.
     */
    public static function autoload(string $class): void
    {
        $prefix = 'BarefootEngine\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        if ($relative === false || $relative === '') {
            return;
        }

        $parts = explode('\\', $relative);
        $class_name = array_pop($parts);
        if (!is_string($class_name) || $class_name === '') {
            return;
        }

        $class_slug = self::class_to_slug($class_name);
        $candidate = self::resolve_path($parts, $class_slug);

        if ($candidate !== null && file_exists($candidate)) {
            require_once $candidate;
        }
    }

    /**
     * @param array<int, string> $parts Namespace parts.
     */
    private static function resolve_path(array $parts, string $class_slug): ?string
    {
        $top = $parts[0] ?? '';
        $rest = array_slice($parts, 1);

        if ($top === 'Includes') {
            $base = BAREFOOT_ENGINE_PLUGIN_DIR . 'includes';
            $rest = array_map([self::class, 'namespace_to_slug'], $rest);
        } elseif ($top === 'Admin') {
            $base = BAREFOOT_ENGINE_PLUGIN_DIR . 'admin';
            $rest = array_map([self::class, 'namespace_to_slug'], $rest);
        } elseif ($top === 'PublicFacing') {
            $base = BAREFOOT_ENGINE_PLUGIN_DIR . 'public';
            $rest = array_map([self::class, 'namespace_to_slug'], $rest);
        } elseif ($top !== '') {
            $base = BAREFOOT_ENGINE_PLUGIN_DIR . 'src/' . $top;
        } else {
            $base = BAREFOOT_ENGINE_PLUGIN_DIR . 'includes';
        }

        $path = $base;
        if (!empty($rest)) {
            $path .= '/' . implode('/', $rest);
        }

        $path .= '/class-' . $class_slug . '.php';

        $normalized = preg_replace('#/+#', '/', $path);

        return is_string($normalized) ? $normalized : null;
    }

    private static function class_to_slug(string $class_name): string
    {
        $slug = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $class_name);
        if (!is_string($slug)) {
            return strtolower($class_name);
        }

        return strtolower(str_replace('_', '-', $slug));
    }

    private static function namespace_to_slug(string $segment): string
    {
        $slug = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $segment);
        if (!is_string($slug)) {
            return strtolower($segment);
        }

        return strtolower(str_replace('_', '-', $slug));
    }
}
