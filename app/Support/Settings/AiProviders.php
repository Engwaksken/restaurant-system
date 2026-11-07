<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * AI providers the assistant can use. Base URLs and default models are
 * filled in automatically; the admin only adds an API key. "Load models"
 * fetches the provider's live model list to choose from.
 *
 * protocol: anthropic (official SDK), gemini (Google API), openai (the
 * OpenAI-compatible /chat/completions API used by most other providers).
 */
final class AiProviders
{
    public const PROVIDERS = [
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'protocol' => 'anthropic',
            'base_url' => 'https://api.anthropic.com',
            'default_model' => 'claude-opus-5-5',
            'models' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'],
            'key_url' => 'https://console.anthropic.com/settings/keys',
            'key_hint' => 'Starts with sk-ant-',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'protocol' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o-mini',
            'models' => ['gpt-4o-mini', 'gpt-4o'],
            'key_url' => 'https://platform.openai.com/api-keys',
            'key_hint' => 'Starts with sk-',
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'protocol' => 'gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'default_model' => 'gemini-2.5-flash',
            'models' => ['gemini-2.5-flash', 'gemini-2.5-pro'],
            'key_url' => 'https://aistudio.google.com/app/apikey',
            'key_hint' => 'From Google AI Studio',
        ],
        'mistral' => [
            'label' => 'Mistral AI',
            'protocol' => 'openai',
            'base_url' => 'https://api.mistral.ai/v1',
            'default_model' => 'mistral-small-latest',
            'models' => ['mistral-small-latest', 'mistral-large-latest'],
            'key_url' => 'https://console.mistral.ai/api-keys',
            'key_hint' => 'From the Mistral console',
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'protocol' => 'openai',
            'base_url' => 'https://api.deepseek.com/v1',
            'default_model' => 'deepseek-chat',
            'models' => ['deepseek-chat'],
            'key_url' => 'https://platform.deepseek.com/api_keys',
            'key_hint' => 'Starts with sk-',
        ],
        'groq' => [
            'label' => 'Groq',
            'protocol' => 'openai',
            'base_url' => 'https://api.groq.com/openai/v1',
            'default_model' => 'llama-3.3-70b-versatile',
            'models' => ['llama-3.3-70b-versatile'],
            'key_url' => 'https://console.groq.com/keys',
            'key_hint' => 'Starts with gsk_',
        ],
        'xai' => [
            'label' => 'xAI Grok',
            'protocol' => 'openai',
            'base_url' => 'https://api.x.ai/v1',
            'default_model' => 'grok-3-mini',
            'models' => ['grok-3-mini', 'grok-3'],
            'key_url' => 'https://console.x.ai',
            'key_hint' => 'Starts with xai-',
        ],
        'openrouter' => [
            'label' => 'OpenRouter (many models)',
            'protocol' => 'openai',
            'base_url' => 'https://openrouter.ai/api/v1',
            'default_model' => 'openrouter/auto',
            'models' => ['openrouter/auto'],
            'key_url' => 'https://openrouter.ai/keys',
            'key_hint' => 'Starts with sk-or-',
        ],
        'together' => [
            'label' => 'Together AI',
            'protocol' => 'openai',
            'base_url' => 'https://api.together.xyz/v1',
            'default_model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            'models' => ['meta-llama/Llama-3.3-70B-Instruct-Turbo'],
            'key_url' => 'https://api.together.ai/settings/api-keys',
            'key_hint' => 'From your Together account',
        ],
    ];

    public static function has(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]);
    }

    public static function get(string $provider): array
    {
        return self::PROVIDERS[$provider] ?? self::PROVIDERS['anthropic'];
    }

    public static function keyField(string $provider): string
    {
        return 'key_' . $provider;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $provider) => $provider['label'], self::PROVIDERS);
    }
}
