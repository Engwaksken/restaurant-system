<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualPaymentMethod;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Payments\GatewaySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Online payment gateway settings per restaurant. Secrets are write-only:
 * they are stored encrypted, shown masked, and kept when the field is left
 * blank. API endpoints come from the selected mode, never from user input.
 */
class PaymentGatewayController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $this->authorizeGateways($request);
        $saved = PaymentGatewaySetting::query()->with('editor:id,name')->where('restaurant_id', $user->restaurant_id)->get()->keyBy('gateway');
        $envDefaults = (array) config('restaurant.gateways', []);

        $gateways = collect(GatewaySettings::GATEWAYS)->map(function (array $definition, string $key) use ($saved, $envDefaults): array {
            $setting = $saved->get($key);
            $credentials = (array) ($setting?->credentials ?? []);
            $values = [];
            foreach ($definition['fields'] as $field => $meta) {
                $value = $credentials[$field] ?? null;
                $values[$field] = $meta['secret'] ? GatewaySettings::mask($value) : $value;
            }
            $ready = $setting !== null && $setting->is_enabled && collect($definition['required'])->every(fn ($field) => !empty($credentials[$field]));

            return $definition + [
                'key' => $key,
                'setting' => $setting,
                'values' => $values,
                'enabled' => (bool) $setting?->is_enabled,
                'mode' => $setting?->mode ?? 'test',
                'ready' => $ready,
                'usesEnv' => $setting === null && app(GatewaySettings::class)->isReady($key, (array) ($envDefaults[$key] ?? [])),
            ];
        });

        $pageStats = [
            ['label' => 'Gateways live for customers', 'value' => number_format($gateways->filter(fn ($g) => $g['ready'] || $g['usesEnv'])->count()) . ' of ' . $gateways->count(), 'icon' => 'fas fa-plug-circle-check', 'tone' => 'success'],
            ['label' => 'In live mode', 'value' => number_format($gateways->filter(fn ($g) => $g['ready'] && $g['mode'] === 'live')->count()), 'icon' => 'fas fa-bolt', 'tone' => 'brand', 'hint' => 'Charging real money'],
            ['label' => 'In test mode', 'value' => number_format($gateways->filter(fn ($g) => $g['ready'] && $g['mode'] === 'test')->count()), 'icon' => 'fas fa-flask', 'tone' => 'info', 'hint' => 'Sandbox payments only'],
            ['label' => 'Pay on delivery', 'value' => 'Always on', 'icon' => 'fas fa-money-bill-wave', 'tone' => 'neutral', 'hint' => 'Cash at delivery or pickup'],
        ];

        $methods = ManualPaymentMethod::query()->where('restaurant_id', $user->restaurant_id)->orderBy('display_order')->orderBy('name')->get();
        $pageStats[3] = ['label' => 'Other payment methods', 'value' => number_format($methods->where('is_active', true)->count()), 'icon' => 'fas fa-wallet', 'tone' => 'neutral', 'hint' => 'Plus pay on delivery, always on'];

        return view('admin.settings.payment-gateways', [
            'pageStats' => $pageStats,
            'gateways' => $gateways,
            'methods' => $methods,
            'methodIcons' => self::METHOD_ICONS,
        ]);
    }

    public function update(Request $request, string $gateway, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeGateways($request);
        abort_unless(isset(GatewaySettings::GATEWAYS[$gateway]), 404);
        $definition = GatewaySettings::GATEWAYS[$gateway];

        $rules = [
            'is_enabled' => ['nullable', 'boolean'],
            'mode' => ['required', Rule::in(['test', 'live'])],
        ];
        foreach ($definition['fields'] as $field => $meta) {
            $rules[$field] = ['nullable', 'string', 'max:500'];
        }
        $data = $request->validateWithBag($gateway, $rules);

        $setting = PaymentGatewaySetting::query()->firstOrNew(['restaurant_id' => $user->restaurant_id, 'gateway' => $gateway]);
        $credentials = (array) ($setting->credentials ?? []);
        foreach ($definition['fields'] as $field => $meta) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($meta['secret']) {
                // Blank (or the masked placeholder) keeps the stored secret.
                if ($value !== '' && !str_starts_with($value, '********')) {
                    $credentials[$field] = $value;
                }
                if ($request->boolean('clear_' . $field)) {
                    unset($credentials[$field]);
                }
            } else {
                $credentials[$field] = $value === '' ? null : $value;
            }
        }

        $enabled = $request->boolean('is_enabled');
        if ($enabled) {
            $missing = collect($definition['required'])->filter(fn ($field) => empty($credentials[$field]));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(
                    $missing->mapWithKeys(fn ($field) => [$field => 'Enter the ' . strtolower($definition['fields'][$field]['label']) . ' before turning ' . $definition['label'] . ' on.'])->all()
                )->errorBag($gateway);
            }
            if ($gateway === 'flutterwave' && !empty($credentials['secret_key'])) {
                $isTestKey = str_contains((string) $credentials['secret_key'], '_TEST');
                if ($data['mode'] === 'live' && $isTestKey) {
                    throw ValidationException::withMessages(['secret_key' => 'This is a test key. Use your live secret key, or switch to test mode.'])->errorBag($gateway);
                }
            }
        }

        $setting->fill([
            'is_enabled' => $enabled,
            'mode' => $data['mode'],
            'credentials' => array_filter($credentials, fn ($value) => $value !== null && $value !== ''),
            'updated_by' => $user->id,
        ])->save();

        $logger->log('settings.payment_gateway.updated', $setting, [
            'gateway' => $gateway,
            'enabled' => $enabled,
            'mode' => $data['mode'],
            // Never log secret values, only which fields are set.
            'configured_fields' => array_keys($setting->credentials ?? []),
        ]);

        return redirect()->route('admin.settings.payment-gateways', ['tab' => $gateway])
            ->with('success', $definition['label'] . ' settings saved' . ($enabled ? ' and switched on (' . $data['mode'] . ' mode).' : '. It is switched off.'));
    }

    public function storeMethod(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeGateways($request);
        $data = $request->validateWithBag('method', $this->methodRules());
        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        $method = ManualPaymentMethod::create($data + ['restaurant_id' => $user->restaurant_id, 'is_active' => $request->boolean('is_active', true)]);
        $logger->log('settings.payment_method.created', $method, ['name' => $method->name]);

        return redirect()->route('admin.settings.payment-gateways', ['tab' => 'other'])->with('success', $method->name . ' added. Customers can now choose it at checkout.');
    }

    public function updateMethod(Request $request, int $method, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeGateways($request);
        $record = ManualPaymentMethod::query()->where('restaurant_id', $user->restaurant_id)->findOrFail($method);
        $data = $request->validateWithBag('method-' . $record->id, $this->methodRules());
        $data['display_order'] = (int) ($data['display_order'] ?? 0);
        $record->update($data + ['is_active' => $request->boolean('is_active')]);
        $logger->log('settings.payment_method.updated', $record, ['name' => $record->name]);

        return redirect()->route('admin.settings.payment-gateways', ['tab' => 'other'])->with('success', $record->name . ' updated.');
    }

    public function destroyMethod(Request $request, int $method, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeGateways($request);
        $record = ManualPaymentMethod::query()->where('restaurant_id', $user->restaurant_id)->findOrFail($method);
        $record->delete();
        $logger->log('settings.payment_method.deleted', null, ['name' => $record->name]);

        return redirect()->route('admin.settings.payment-gateways', ['tab' => 'other'])->with('success', $record->name . ' removed from checkout.');
    }

    private const METHOD_ICONS = [
        'fas fa-mobile-screen-button' => 'Mobile money',
        'fas fa-building-columns' => 'Bank',
        'fas fa-money-bill-wave' => 'Cash',
        'fas fa-credit-card' => 'Card',
        'fas fa-wallet' => 'Wallet',
        'fas fa-qrcode' => 'QR code',
    ];

    private function methodRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['required', Rule::in(array_keys(self::METHOD_ICONS))],
            'instructions' => ['required', 'string', 'max:1000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    private function authorizeGateways(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_if($user->branch_id !== null, 403, 'Branch-assigned staff cannot change payment settings.');
        abort_unless($user->can('settings.payment-gateways.manage') || $user->can('admin'), 403);

        return $user;
    }
}
