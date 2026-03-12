<?php

namespace WPLAT;

if (! defined('ABSPATH')) {
    exit;
}

class Translator
{
    public function translate_batch($texts, $config)
    {
        if (! is_array($texts) || empty($texts)) {
            return new \WP_Error('invalid_texts', __('No texts to translate.', 'wp-localized-auto-translate'), ['status' => 400]);
        }

        $cleanTexts = [];
        foreach ($texts as $text) {
            $value = trim(wp_strip_all_tags((string) $text));
            if ($value !== '') {
                $cleanTexts[] = $value;
            }
        }

        if (empty($cleanTexts)) {
            return [];
        }

        $cacheKey = 'wplat_' . md5(wp_json_encode([$cleanTexts, $config['target_locale'], $config['style_hint']]));
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $systemPrompt = sprintf(
            'You are a professional website localizer. Translate each input string from English into %s. Preserve meaning and business tone. Follow this locale guidance: %s. Return strict JSON object: {"translations":["..."]}. Keep array length exactly equal to input length.',
            $config['target_locale'],
            $config['style_hint']
        );

        $payload = [
            'model' => $config['model'],
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => wp_json_encode(['texts' => array_values($cleanTexts)])],
            ],
            'temperature' => 0.2,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new \WP_Error('translation_failed', __('Translation provider returned an error.', 'wp-localized-auto-translate'), [
                'status' => 502,
                'provider_status' => $status,
                'provider_body' => wp_remote_retrieve_body($response),
            ]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || $content === '') {
            return new \WP_Error('bad_provider_payload', __('Empty translation result from provider.', 'wp-localized-auto-translate'), ['status' => 502]);
        }

        $parsed = json_decode($content, true);
        if (! isset($parsed['translations']) || ! is_array($parsed['translations'])) {
            return new \WP_Error('invalid_translation_payload', __('Malformed translation JSON.', 'wp-localized-auto-translate'), ['status' => 502]);
        }

        $translations = array_map('strval', $parsed['translations']);
        set_transient($cacheKey, $translations, 12 * HOUR_IN_SECONDS);

        return $translations;
    }
}
