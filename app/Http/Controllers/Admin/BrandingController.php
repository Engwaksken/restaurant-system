<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Brand;
use App\Support\BrandResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Restaurant profile and branding: name, contact details, logo, favicon, hero
 * image and colours. The brand is applied to the admin, the public website,
 * order pages, PDFs and customer emails.
 */
class BrandingController extends Controller
{
    /** Upload field => [column, folder]. */
    private const IMAGES = [
        'logo' => ['logo_path', 'branding/logos'],
        'favicon' => ['favicon_path', 'branding/favicons'],
        'hero_image' => ['hero_image_path', 'branding/hero'],
    ];

    public function edit(Request $request): View
    {
        $restaurant = $this->restaurant($this->authorizeBranding($request));

        return view('admin.settings.branding', [
            'restaurant' => $restaurant,
            'preview' => Brand::for($restaurant),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger, BrandResolver $resolver): RedirectResponse
    {
        $restaurant = $this->restaurant($this->authorizeBranding($request));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'about' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'opening_hours' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\s-]+$/'],
            'email_footer' => ['nullable', 'string', 'max:1000'],
            // SVG is excluded: it can carry script and is served from our origin.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp', 'max:512'],
            'hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_hero_image' => ['nullable', 'boolean'],
        ], [
            'primary_color.regex' => 'Choose the primary colour as a hex value such as #e8590c.',
            'accent_color.regex' => 'Choose the accent colour as a hex value such as #1c1917.',
            'whatsapp_number.regex' => 'The WhatsApp number may only contain digits, spaces, +, - and brackets.',
        ]);

        $attributes = collect($data)->except(array_merge(
            array_keys(self::IMAGES),
            array_map(fn (string $field) => 'remove_' . $field, array_keys(self::IMAGES)),
        ))->all();
        $attributes['primary_color'] = strtolower($attributes['primary_color']);
        $attributes['accent_color'] = strtolower($attributes['accent_color']);

        $disk = Storage::disk('public');
        $obsolete = [];
        foreach (self::IMAGES as $field => [$column, $folder]) {
            $current = $restaurant->{$column};
            if ($request->hasFile($field)) {
                $attributes[$column] = $request->file($field)->store($folder . '/' . $restaurant->id, 'public');
                $obsolete[] = $current;
            } elseif ($request->boolean('remove_' . $field)) {
                $attributes[$column] = null;
                $obsolete[] = $current;
            }
        }

        $restaurant->update($attributes);
        foreach (array_filter($obsolete) as $path) {
            $disk->delete($path);
        }
        $resolver->forget();

        $logger->log('settings.branding.updated', $restaurant, ['fields' => array_keys($restaurant->getChanges())]);

        return redirect()->route('admin.settings.restaurant')->with('success', 'Branding saved. Your website, admin and emails now use the updated brand.');
    }

    private function restaurant(User $user): Restaurant
    {
        return Restaurant::query()->findOrFail($user->restaurant_id);
    }

    private function authorizeBranding(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_if($user->branch_id !== null, 403, 'Branch-assigned staff cannot change restaurant branding.');
        abort_unless($user->can('settings.branding.manage') || $user->can('admin'), 403);

        return $user;
    }
}
