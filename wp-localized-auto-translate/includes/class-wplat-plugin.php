<?php

namespace WPLAT;

if (! defined('ABSPATH')) {
    exit;
}

require_once WPLAT_PLUGIN_DIR . 'includes/class-wplat-translator.php';

class Plugin
{
    const OPTION_KEY = 'wplat_settings';

    /** @var self|null */
    private static $instance = null;

    /** @var Translator */
    private $translator;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->translator = new Translator();
    }

    public function boot()
    {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_admin_page()
    {
        add_options_page(
            __('Localized Auto Translate', 'wp-localized-auto-translate'),
            __('Localized Auto Translate', 'wp-localized-auto-translate'),
            'manage_options',
            'wplat-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [$this, 'sanitize_settings']);

        add_settings_section(
            'wplat_main',
            __('General Settings', 'wp-localized-auto-translate'),
            function () {
                echo '<p>' . esc_html__('Configure your translation model and locales.', 'wp-localized-auto-translate') . '</p>';
            },
            self::OPTION_KEY
        );

        add_settings_field('provider', __('Provider', 'wp-localized-auto-translate'), [$this, 'render_provider_field'], self::OPTION_KEY, 'wplat_main');
        add_settings_field('api_key', __('API Key', 'wp-localized-auto-translate'), [$this, 'render_api_key_field'], self::OPTION_KEY, 'wplat_main');
        add_settings_field('model', __('Model', 'wp-localized-auto-translate'), [$this, 'render_model_field'], self::OPTION_KEY, 'wplat_main');
        add_settings_field('locales', __('Target Locales', 'wp-localized-auto-translate'), [$this, 'render_locales_field'], self::OPTION_KEY, 'wplat_main');
        add_settings_field('min_chars', __('Minimum Characters', 'wp-localized-auto-translate'), [$this, 'render_min_chars_field'], self::OPTION_KEY, 'wplat_main');
        add_settings_field('show_switcher', __('Show Floating Switcher', 'wp-localized-auto-translate'), [$this, 'render_switcher_field'], self::OPTION_KEY, 'wplat_main');
    }

    public function sanitize_settings($input)
    {
        $safe = [];
        $safe['provider'] = isset($input['provider']) ? sanitize_text_field($input['provider']) : 'openai';
        $safe['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $safe['model'] = isset($input['model']) ? sanitize_text_field($input['model']) : 'gpt-4o-mini';
        $safe['min_chars'] = isset($input['min_chars']) ? max(1, absint($input['min_chars'])) : 20;
        $safe['show_switcher'] = ! empty($input['show_switcher']) ? 1 : 0;

        $locales = isset($input['locales']) ? explode("\n", (string) $input['locales']) : [];
        $parsed = [];

        foreach ($locales as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }

            [$locale, $styleHint] = array_map('trim', explode('|', $line, 2));
            if ($locale === '' || $styleHint === '') {
                continue;
            }

            $parsed[] = [
                'locale' => sanitize_text_field($locale),
                'style' => sanitize_text_field($styleHint),
            ];
        }

        $safe['locales'] = $parsed;

        return $safe;
    }

    private function get_settings()
    {
        $defaults = [
            'provider' => 'openai',
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'min_chars' => 20,
            'show_switcher' => 1,
            'locales' => [
                ['locale' => 'de-DE', 'style' => 'Standard German for Germany'],
                ['locale' => 'de-CH', 'style' => 'Swiss High German conventions and vocabulary'],
                ['locale' => 'es-ES', 'style' => 'Peninsular Spanish for Spain'],
                ['locale' => 'es-MX', 'style' => 'Mexican Spanish idioms and formal business tone'],
            ],
        ];

        return wp_parse_args(get_option(self::OPTION_KEY, []), $defaults);
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Localized Auto Translate', 'wp-localized-auto-translate'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_KEY);
                do_settings_sections(self::OPTION_KEY);
                submit_button();
                ?>
            </form>
            <p><?php echo esc_html__('Locale format: one per line as locale|style guidance, e.g. de-CH|Swiss business German.', 'wp-localized-auto-translate'); ?></p>
        </div>
        <?php
    }

    public function render_provider_field()
    {
        $settings = $this->get_settings();
        ?>
        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[provider]">
            <option value="openai" <?php selected($settings['provider'], 'openai'); ?>>OpenAI Compatible</option>
        </select>
        <?php
    }

    public function render_api_key_field()
    {
        $settings = $this->get_settings();
        ?>
        <input type="password" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" autocomplete="off" />
        <?php
    }

    public function render_model_field()
    {
        $settings = $this->get_settings();
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[model]" value="<?php echo esc_attr($settings['model']); ?>" />
        <?php
    }

    public function render_min_chars_field()
    {
        $settings = $this->get_settings();
        ?>
        <input type="number" min="1" max="500" name="<?php echo esc_attr(self::OPTION_KEY); ?>[min_chars]" value="<?php echo esc_attr((string) $settings['min_chars']); ?>" />
        <?php
    }

    public function render_switcher_field()
    {
        $settings = $this->get_settings();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_switcher]" value="1" <?php checked((int) $settings['show_switcher'], 1); ?> />
            <?php echo esc_html__('Enable a bottom-right floating language switcher on frontend.', 'wp-localized-auto-translate'); ?>
        </label>
        <?php
    }

    public function render_locales_field()
    {
        $settings = $this->get_settings();
        $lines = [];
        foreach ($settings['locales'] as $locale) {
            $lines[] = $locale['locale'] . '|' . $locale['style'];
        }
        ?>
        <textarea name="<?php echo esc_attr(self::OPTION_KEY); ?>[locales]" rows="8" cols="80"><?php echo esc_textarea(implode("\n", $lines)); ?></textarea>
        <?php
    }

    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        $settings = $this->get_settings();
        wp_enqueue_script(
            'wplat-frontend',
            WPLAT_PLUGIN_URL . 'assets/js/wplat-frontend.js',
            [],
            WPLAT_VERSION,
            true
        );

        wp_localize_script('wplat-frontend', 'WPLAT_CONFIG', [
            'restUrl' => esc_url_raw(rest_url('wplat/v1/translate')),
            'nonce' => wp_create_nonce('wp_rest'),
            'locales' => array_map(function ($item) {
                return $item['locale'];
            }, $settings['locales']),
            'minChars' => (int) $settings['min_chars'],
            'showSwitcher' => (int) $settings['show_switcher'] === 1,
            'sourceLocale' => 'en',
        ]);
    }

    public function register_rest_routes()
    {
        register_rest_route('wplat/v1', '/translate', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'handle_translate_request'],
            'args' => [
                'texts' => ['required' => true, 'type' => 'array'],
                'targetLocale' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    public function handle_translate_request($request)
    {
        $settings = $this->get_settings();
        $texts = $request->get_param('texts');
        $target = sanitize_text_field((string) $request->get_param('targetLocale'));

        $styleHint = $this->find_style_hint($target, $settings['locales']);
        if (! $styleHint) {
            return new \WP_Error('unsupported_locale', __('Locale is not configured.', 'wp-localized-auto-translate'), ['status' => 400]);
        }

        if (empty($settings['api_key'])) {
            return new \WP_Error('missing_api_key', __('Missing API key in plugin settings.', 'wp-localized-auto-translate'), ['status' => 500]);
        }

        $translated = $this->translator->translate_batch($texts, [
            'provider' => $settings['provider'],
            'api_key' => $settings['api_key'],
            'model' => $settings['model'],
            'target_locale' => $target,
            'style_hint' => $styleHint,
        ]);

        if (is_wp_error($translated)) {
            return $translated;
        }

        return rest_ensure_response([
            'translations' => $translated,
        ]);
    }

    private function find_style_hint($targetLocale, $locales)
    {
        foreach ($locales as $locale) {
            if (strcasecmp($locale['locale'], $targetLocale) === 0) {
                return $locale['style'];
            }
        }

        return null;
    }
}
