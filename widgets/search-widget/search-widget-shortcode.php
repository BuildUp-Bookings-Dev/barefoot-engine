<?php

namespace BarefootEngine\Widgets\Search;

if (!defined('ABSPATH')) {
    exit;
}

class Search_Widget_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_search_widget';

    private static bool $should_enqueue_assets = false;
    private Search_Widget_Config $config;

    public function __construct()
    {
        $this->config = new Search_Widget_Config();
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_TAG, [$this, 'render']);
    }

    public function detect_shortcode_usage(): void
    {
        if (is_admin() || self::$should_enqueue_assets) {
            return;
        }

        global $wp_query;

        $posts = (is_object($wp_query) && isset($wp_query->posts) && is_array($wp_query->posts))
            ? $wp_query->posts
            : [];

        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            if (has_shortcode((string) $post->post_content, self::SHORTCODE_TAG)) {
                self::request_assets();
                return;
            }
        }
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function render($attributes = []): string
    {
        $resolved_attributes = is_array($attributes) ? $attributes : [];
        $shortcode_attributes = shortcode_atts($this->config->get_defaults(), $resolved_attributes, self::SHORTCODE_TAG);
        $prepared = $this->config->prepare_for_render($shortcode_attributes);

        self::request_assets();
        $this->enqueue_registered_assets();

        $instance_id = wp_unique_id('be-search-widget-');
        $config_id = $instance_id . '-config';
        $classes = ['barefoot-engine-search-widget', 'barefoot-engine-public'];

        if ($prepared['wrapper_class'] !== '') {
            $classes[] = $prepared['wrapper_class'];
        }

        $config_json = wp_json_encode(
            $prepared['widget_config'],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if (!is_string($config_json) || $config_json === '') {
            return '';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-search-widget__mount"
                data-be-search-widget
                data-be-search-widget-id="<?php echo esc_attr($instance_id); ?>"
                data-be-search-widget-config="<?php echo esc_attr($config_id); ?>"
            ></div>
            <script id="<?php echo esc_attr($config_id); ?>" type="application/json"><?php echo $config_json; ?></script>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function should_enqueue_assets(): bool
    {
        return self::$should_enqueue_assets;
    }

    public static function request_assets(): void
    {
        self::$should_enqueue_assets = true;
    }

    private function enqueue_registered_assets(): void
    {
        if (wp_script_is('barefoot-engine-search-widget', 'registered')) {
            wp_enqueue_script('barefoot-engine-search-widget');
        }

        if (wp_style_is('barefoot-engine-search-widget', 'registered')) {
            wp_enqueue_style('barefoot-engine-search-widget');
        }
    }
}
