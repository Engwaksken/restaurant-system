<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * Definitions for every settings page: fields, types, defaults and rules.
 * Field types: text, email, url, number, textarea, toggle, select, secret.
 * Secrets are write-only (stored encrypted, shown masked, kept when blank).
 */
final class SettingsRegistry
{
    public const GROUPS = [
        'ai' => [
            'label' => 'AI Assistant',
            'icon' => 'fas fa-brain',
            'description' => 'Power the chat assistant on your website with AI. Pick a provider and paste its API key; the address and model are filled in for you.',
            'sections' => [
                'Assistant' => ['enabled', 'assistant_name', 'greeting', 'instructions'],
                'Provider & key' => ['provider', 'model'],
            ],
            'fields' => [
                'enabled' => ['label' => 'Use AI for the website chat', 'type' => 'toggle', 'default' => false, 'help' => 'When off, the chat answers simple menu questions without AI.'],
                'assistant_name' => ['label' => 'Assistant name', 'type' => 'text', 'default' => 'Restaurant Assistant', 'rules' => ['nullable', 'string', 'max:60']],
                'greeting' => ['label' => 'Greeting message', 'type' => 'text', 'default' => 'Hello! Ask me about our menu, prices, opening hours or how to order.', 'rules' => ['nullable', 'string', 'max:200']],
                'instructions' => ['label' => 'Extra instructions', 'type' => 'textarea', 'default' => '', 'help' => 'Optional tone or rules, e.g. "Recommend our daily special" or "Answer in English and Luganda".', 'rules' => ['nullable', 'string', 'max:1500']],
                'provider' => ['label' => 'AI provider', 'type' => 'select', 'default' => 'anthropic', 'options' => '@providers'],
                'model' => ['label' => 'Model', 'type' => 'model', 'default' => '', 'help' => 'Filled in automatically for the provider. Use "Load models" to pick from the provider\'s current list.', 'rules' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/\-]*$/']],
            ],
            'required_when_enabled' => '@provider_keys',
        ],
        'taxes' => [
            'label' => 'Taxes',
            'icon' => 'fas fa-percent',
            'description' => 'Tax applied to POS and online orders, and how it appears on receipts and invoices.',
            'sections' => [
                'Tax' => ['enabled', 'tax_name', 'rate', 'mode'],
                'Registration' => ['tin'],
            ],
            'fields' => [
                'enabled' => ['label' => 'Charge tax on orders', 'type' => 'toggle', 'default' => false],
                'tax_name' => ['label' => 'Tax name', 'type' => 'text', 'default' => 'VAT', 'help' => 'Shown on the POS, receipts and invoices, e.g. VAT or GST.', 'rules' => ['nullable', 'string', 'max:30']],
                'rate' => ['label' => 'Tax rate (%)', 'type' => 'number', 'step' => '0.01', 'default' => '18', 'rules' => ['nullable', 'numeric', 'min:0', 'max:100']],
                'mode' => ['label' => 'Menu prices', 'type' => 'select', 'default' => 'exclusive', 'options' => ['exclusive' => 'Exclude tax (tax is added at checkout)', 'inclusive' => 'Include tax (tax is part of the menu price)']],
                'tin' => ['label' => 'Tax identification number (TIN)', 'type' => 'text', 'default' => '', 'help' => 'Printed on receipts and invoices when enabled in those settings.', 'rules' => ['nullable', 'string', 'max:50']],
            ],
            'required_when' => ['enabled' => ['rate']],
        ],
        'sms' => [
            'label' => 'SMS',
            'icon' => 'fas fa-message',
            'description' => 'Text customers when their order is placed and when it is ready.',
            'sections' => [
                'Notifications' => ['enabled', 'notify_order_placed', 'notify_order_ready'],
                'Provider' => ['provider', 'sender_id', 'at_username', 'at_api_key', 'twilio_sid', 'twilio_token', 'twilio_from'],
                'Message templates' => ['template_order_placed', 'template_order_ready'],
            ],
            'fields' => [
                'enabled' => ['label' => 'Send SMS notifications', 'type' => 'toggle', 'default' => false],
                'notify_order_placed' => ['label' => 'When an online order is placed', 'type' => 'toggle', 'default' => true],
                'notify_order_ready' => ['label' => 'When an order is ready', 'type' => 'toggle', 'default' => true],
                'provider' => ['label' => 'SMS provider', 'type' => 'select', 'default' => 'africastalking', 'options' => ['africastalking' => "Africa's Talking", 'twilio' => 'Twilio']],
                'sender_id' => ['label' => 'Sender ID', 'type' => 'text', 'default' => '', 'help' => 'Approved alphanumeric sender name, if your provider gave you one.', 'rules' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 ]*$/'], 'show_if' => ['provider' => 'africastalking']],
                'at_username' => ['label' => "Africa's Talking username", 'type' => 'text', 'default' => '', 'help' => 'Use "sandbox" to test without sending real messages.', 'rules' => ['nullable', 'string', 'max:80'], 'show_if' => ['provider' => 'africastalking']],
                'at_api_key' => ['label' => "Africa's Talking API key", 'type' => 'secret', 'show_if' => ['provider' => 'africastalking']],
                'twilio_sid' => ['label' => 'Twilio Account SID', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:64', 'regex:/^(AC[a-zA-Z0-9]{32})?$/'], 'show_if' => ['provider' => 'twilio']],
                'twilio_token' => ['label' => 'Twilio Auth token', 'type' => 'secret', 'show_if' => ['provider' => 'twilio']],
                'twilio_from' => ['label' => 'Twilio sending number', 'type' => 'text', 'default' => '', 'help' => 'In international format, e.g. +15005550006.', 'rules' => ['nullable', 'string', 'max:20', 'regex:/^(\+[0-9]{6,15})?$/'], 'show_if' => ['provider' => 'twilio']],
                'template_order_placed' => ['label' => 'Order placed message', 'type' => 'textarea', 'default' => 'Hi {name}, {restaurant} received order {order} ({total}). Track it: {link}', 'help' => 'Placeholders: {name} {restaurant} {order} {total} {link}', 'rules' => ['nullable', 'string', 'max:320']],
                'template_order_ready' => ['label' => 'Order ready message', 'type' => 'textarea', 'default' => 'Hi {name}, your order {order} from {restaurant} is ready.', 'help' => 'Placeholders: {name} {restaurant} {order}', 'rules' => ['nullable', 'string', 'max:320']],
            ],
            'required_when_enabled' => ['africastalking' => ['at_username', 'at_api_key'], 'twilio' => ['twilio_sid', 'twilio_token', 'twilio_from']],
        ],
        'email' => [
            'label' => 'Email',
            'icon' => 'fas fa-envelope',
            'description' => 'Send customer emails from your own mail server and address.',
            'sections' => [
                'Sending' => ['send_order_confirmations', 'from_address', 'from_name', 'reply_to'],
                'Mail server (SMTP)' => ['use_custom_smtp', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password'],
            ],
            'fields' => [
                'send_order_confirmations' => ['label' => 'Email customers an order confirmation', 'type' => 'toggle', 'default' => true],
                'from_address' => ['label' => 'From address', 'type' => 'email', 'default' => '', 'help' => 'Leave blank to use the server default. Must be allowed by your mail server.', 'rules' => ['nullable', 'email', 'max:255']],
                'from_name' => ['label' => 'From name', 'type' => 'text', 'default' => '', 'help' => 'Defaults to your restaurant name.', 'rules' => ['nullable', 'string', 'max:100']],
                'reply_to' => ['label' => 'Reply-to address', 'type' => 'email', 'default' => '', 'help' => 'Defaults to your restaurant email.', 'rules' => ['nullable', 'email', 'max:255']],
                'use_custom_smtp' => ['label' => 'Use my own SMTP server', 'type' => 'toggle', 'default' => false, 'help' => 'Off uses the server configuration.'],
                'smtp_host' => ['label' => 'SMTP host', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]*$/']],
                'smtp_port' => ['label' => 'SMTP port', 'type' => 'number', 'default' => '587', 'rules' => ['nullable', 'integer', 'in:25,465,587,2525']],
                'smtp_encryption' => ['label' => 'Encryption', 'type' => 'select', 'default' => 'tls', 'options' => ['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None']],
                'smtp_username' => ['label' => 'SMTP username', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'smtp_password' => ['label' => 'SMTP password', 'type' => 'secret'],
            ],
            'required_when' => ['use_custom_smtp' => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password']],
        ],
        'receipt' => [
            'label' => 'Receipt',
            'icon' => 'fas fa-receipt',
            'description' => 'Layout of printed customer receipts from the POS and order pages.',
            'sections' => [
                'Layout' => ['paper_width', 'show_logo', 'show_address', 'show_tin', 'show_cashier'],
                'Text' => ['header_text', 'footer_text'],
            ],
            'fields' => [
                'paper_width' => ['label' => 'Paper width', 'type' => 'select', 'default' => '80', 'options' => ['58' => '58 mm thermal', '80' => '80 mm thermal', 'a4' => 'A4 / Letter']],
                'show_logo' => ['label' => 'Print logo', 'type' => 'toggle', 'default' => true],
                'show_address' => ['label' => 'Print address and phone', 'type' => 'toggle', 'default' => true],
                'show_tin' => ['label' => 'Print tax ID (TIN)', 'type' => 'toggle', 'default' => false],
                'show_cashier' => ['label' => 'Print cashier name', 'type' => 'toggle', 'default' => true],
                'header_text' => ['label' => 'Header message', 'type' => 'textarea', 'default' => '', 'help' => 'Printed under your name, e.g. "Welcome!"', 'rules' => ['nullable', 'string', 'max:300']],
                'footer_text' => ['label' => 'Footer message', 'type' => 'textarea', 'default' => 'Thank you for your visit!', 'rules' => ['nullable', 'string', 'max:300']],
            ],
        ],
        'invoice' => [
            'label' => 'Invoice',
            'icon' => 'fas fa-file-invoice',
            'description' => 'Numbering, payment terms and bank details on customer invoices.',
            'sections' => [
                'Numbering & terms' => ['prefix', 'due_days', 'show_tin'],
                'Payment details' => ['bank_details', 'payment_terms', 'notes'],
            ],
            'fields' => [
                'prefix' => ['label' => 'Invoice number prefix', 'type' => 'text', 'default' => 'INV', 'help' => 'The invoice number is this prefix plus the order number, e.g. INV-WEB-2611-0001.', 'rules' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]*$/']],
                'due_days' => ['label' => 'Payment due after (days)', 'type' => 'number', 'default' => '0', 'help' => '0 means due on receipt.', 'rules' => ['nullable', 'integer', 'min:0', 'max:365']],
                'show_tin' => ['label' => 'Show tax ID (TIN)', 'type' => 'toggle', 'default' => true],
                'bank_details' => ['label' => 'Bank / mobile money details', 'type' => 'textarea', 'default' => '', 'help' => 'Account name, number and bank, or merchant code.', 'rules' => ['nullable', 'string', 'max:800']],
                'payment_terms' => ['label' => 'Payment terms', 'type' => 'textarea', 'default' => '', 'rules' => ['nullable', 'string', 'max:800']],
                'notes' => ['label' => 'Footer notes', 'type' => 'textarea', 'default' => 'Thank you for your business.', 'rules' => ['nullable', 'string', 'max:800']],
            ],
        ],
        'accessibility' => [
            'label' => 'Accessibility',
            'icon' => 'fas fa-universal-access',
            'description' => 'Accessibility tools for customers and staff, and their starting defaults.',
            'sections' => [
                'Widget' => ['show_on_website', 'show_in_admin'],
                'Defaults for new visitors' => ['default_text_size', 'high_contrast', 'reduce_motion'],
            ],
            'fields' => [
                'show_on_website' => ['label' => 'Show accessibility button on the website', 'type' => 'toggle', 'default' => true],
                'show_in_admin' => ['label' => 'Show accessibility button in the admin', 'type' => 'toggle', 'default' => true],
                'default_text_size' => ['label' => 'Default text size', 'type' => 'select', 'default' => '0', 'options' => ['0' => 'Normal', '1' => 'Large (110%)', '2' => 'Larger (120%)']],
                'high_contrast' => ['label' => 'High contrast by default', 'type' => 'toggle', 'default' => false],
                'reduce_motion' => ['label' => 'Reduce motion by default', 'type' => 'toggle', 'default' => false],
            ],
        ],
        'backups' => [
            'label' => 'Backups',
            'icon' => 'fas fa-database',
            'description' => 'Export a copy of your restaurant data you can download and keep safe.',
            'sections' => [
                'Schedule' => ['frequency', 'keep'],
            ],
            'fields' => [
                'frequency' => ['label' => 'Automatic backups', 'type' => 'select', 'default' => 'off', 'options' => ['off' => 'Off (manual only)', 'daily' => 'Every day', 'weekly' => 'Every week (Monday)'], 'help' => 'Requires the Laravel scheduler (php artisan schedule:run) to run every minute on the server.'],
                'keep' => ['label' => 'Backups to keep', 'type' => 'number', 'default' => '7', 'help' => 'Older backups are deleted automatically.', 'rules' => ['nullable', 'integer', 'min:1', 'max:60']],
            ],
        ],
    ];

    public static function has(string $group): bool
    {
        return isset(self::GROUPS[$group]);
    }

    public static function group(string $group): array
    {
        $definition = self::GROUPS[$group];
        if ($group === 'ai') {
            $definition['fields']['provider']['options'] = AiProviders::options();
            $definition['required_when_enabled'] = [];
            foreach (AiProviders::PROVIDERS as $key => $provider) {
                $field = AiProviders::keyField($key);
                $definition['fields'][$field] = [
                    'label' => $provider['label'] . ' API key',
                    'type' => 'secret',
                    'help' => $provider['key_hint'] . '. Get one at ' . $provider['key_url'],
                    'show_if' => ['provider' => $key],
                ];
                $definition['sections']['Provider & key'][] = $field;
                $definition['required_when_enabled'][$key] = [$field];
            }
        }

        return $definition;
    }

    /** @return array<string, mixed> */
    public static function defaults(string $group): array
    {
        return collect(self::group($group)['fields'])
            ->map(fn (array $field) => $field['default'] ?? null)
            ->all();
    }

    /** Validation rules for a group's form. */
    public static function rules(string $group): array
    {
        $rules = [];
        foreach (self::group($group)['fields'] as $key => $field) {
            $rules[$key] = $field['rules'] ?? match ($field['type']) {
                'toggle' => ['nullable', 'boolean'],
                'select' => ['nullable', 'in:' . implode(',', array_map('strval', array_keys($field['options'])))],
                'secret' => ['nullable', 'string', 'max:500'],
                'email' => ['nullable', 'email', 'max:255'],
                'url' => ['nullable', 'url:http,https', 'max:255'],
                'number' => ['nullable', 'integer'],
                'textarea' => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:255'],
            };
            if ($field['type'] === 'secret') {
                $rules['clear_' . $key] = ['nullable', 'boolean'];
            }
        }

        return $rules;
    }
}
