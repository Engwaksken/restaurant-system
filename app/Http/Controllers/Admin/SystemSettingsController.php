<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Ai\RestaurantAssistant;
use App\Services\Notifications\SmsService;
use App\Services\Settings\MailSettings;
use App\Services\Settings\RestaurantSettings;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Settings pages for AI, SMS, email, receipt, invoice and accessibility.
 * Backups have their own controller but share the same storage.
 */
class SystemSettingsController extends Controller
{
    public const PAGES = ['ai', 'sms', 'email', 'receipt', 'invoice', 'accessibility'];

    public function edit(Request $request, string $group, RestaurantSettings $settings): View
    {
        $user = $this->authorizeSettings($request);
        abort_unless(in_array($group, self::PAGES, true), 404);

        return view('admin.settings.system', [
            'groupKey' => $group,
            'group' => SettingsRegistry::group($group),
            'values' => $settings->group($user->restaurant_id, $group),
            'secrets' => $this->secretStatus($user->restaurant_id, $group, $settings),
        ]);
    }

    public function update(Request $request, string $group, RestaurantSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeSettings($request);
        abort_unless(in_array($group, self::PAGES, true), 404);

        $data = $request->validate(SettingsRegistry::rules($group));
        foreach (SettingsRegistry::group($group)['fields'] as $key => $field) {
            if ($field['type'] === 'toggle') {
                $data[$key] = $request->boolean($key);
            }
        }
        $this->assertRequiredWhenEnabled($user->restaurant_id, $group, $data, $settings);

        $settings->save($user->restaurant_id, $group, $data);
        $logger->log('settings.' . $group . '.updated', null, [
            // Never log secret values.
            'fields' => array_keys(array_filter(
                SettingsRegistry::group($group)['fields'],
                fn (array $field) => $field['type'] !== 'secret',
            )),
        ]);

        return redirect()->route('admin.settings.system', $group)
            ->with('success', SettingsRegistry::group($group)['label'] . ' settings saved.');
    }

    /** Send a test email / SMS, or ask the AI assistant a test question. */
    public function test(Request $request, string $group, RestaurantSettings $settings): RedirectResponse
    {
        $user = $this->authorizeSettings($request);
        abort_unless(in_array($group, ['email', 'sms', 'ai'], true), 404);
        $restaurant = Restaurant::query()->findOrFail($user->restaurant_id);

        try {
            if ($group === 'email') {
                $data = $request->validate(['test_to' => ['required', 'email', 'max:255']]);
                app(MailSettings::class)->apply($restaurant);
                Mail::raw(
                    'This is a test email from ' . $restaurant->name . '. Your email settings are working.',
                    fn ($message) => $message->to($data['test_to'])->subject('Test email from ' . $restaurant->name),
                );

                return back()->with('success', 'Test email sent to ' . $data['test_to'] . '. Check the inbox (and spam folder).');
            }

            if ($group === 'sms') {
                $data = $request->validate(['test_to' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ]{9,15}$/']]);
                app(SmsService::class)->send($restaurant, $data['test_to'], 'Test SMS from ' . $restaurant->name . '. Your SMS settings are working.', force: true);

                return back()->with('success', 'Test SMS sent to ' . $data['test_to'] . '.');
            }

            $data = $request->validate(['test_question' => ['required', 'string', 'max:300']]);
            $answer = app(RestaurantAssistant::class)->reply($restaurant, $data['test_question'], force: true);

            return back()->with('success', 'Assistant replied: ' . $answer['reply'])->withInput();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'The test failed: ' . $this->safeMessage($exception));
        }
    }

    private function assertRequiredWhenEnabled(int $restaurantId, string $group, array $data, RestaurantSettings $settings): void
    {
        $definition = SettingsRegistry::group($group);
        $required = [];
        if (isset($definition['required_when_enabled']) && !empty($data['enabled'])) {
            $required = $definition['required_when_enabled'][$data['provider'] ?? ''] ?? [];
        }
        foreach ($definition['required_when'] ?? [] as $toggle => $fields) {
            if (!empty($data[$toggle])) {
                $required = array_merge($required, $fields);
            }
        }

        $missing = [];
        foreach ($required as $key) {
            $field = $definition['fields'][$key];
            $present = $field['type'] === 'secret'
                ? (trim((string) ($data[$key] ?? '')) !== '' || ($settings->hasSecret($restaurantId, $group, $key) && empty($data['clear_' . $key])))
                : trim((string) ($data[$key] ?? '')) !== '';
            if (!$present) {
                $missing[$key] = 'Enter the ' . strtolower($field['label']) . ' to turn this on.';
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    private function secretStatus(int $restaurantId, string $group, RestaurantSettings $settings): array
    {
        $status = [];
        foreach (SettingsRegistry::group($group)['fields'] as $key => $field) {
            if ($field['type'] === 'secret') {
                $value = (string) $settings->get($restaurantId, $group, $key, '');
                $status[$key] = $value === '' ? null : str_repeat('*', 8) . substr($value, -4);
            }
        }

        return $status;
    }

    /** Provider errors can echo request details; keep the message short and generic. */
    private function safeMessage(Throwable $exception): string
    {
        $message = preg_replace('/\s+/', ' ', $exception->getMessage()) ?? '';

        return mb_strimwidth($message !== '' ? $message : class_basename($exception), 0, 180, '...');
    }

    private function authorizeSettings(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_if($user->branch_id !== null, 403, 'Branch-assigned staff cannot change restaurant settings.');
        abort_unless($user->can('settings.system.manage') || $user->can('admin'), 403);

        return $user;
    }
}
