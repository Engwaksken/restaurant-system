<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Read-only view of a restaurant's branding with safe defaults, shared by the
 * admin layout, the public site, PDFs and emails.
 */
final class Brand
{
    public const DEFAULT_PRIMARY = '#e8590c';
    public const DEFAULT_ACCENT = '#1c1917';

    public function __construct(public readonly ?Restaurant $restaurant = null)
    {
    }

    public static function for(?Restaurant $restaurant): self
    {
        return new self($restaurant);
    }

    public function name(): string
    {
        return $this->restaurant?->name ?: (string) config('app.name', 'Restaurant');
    }

    public function tagline(): ?string
    {
        return $this->filled('tagline');
    }

    public function about(): ?string
    {
        return $this->filled('about');
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name())) ?: [];
        $letters = collect($words)->filter()->take(2)->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('');

        return $letters !== '' ? $letters : 'R';
    }

    public function currency(): string
    {
        return $this->restaurant?->currency ?: (string) config('restaurant.currency', 'UGX');
    }

    public function logoUrl(): ?string
    {
        return $this->publicUrl('logo_path');
    }

    public function faviconUrl(): ?string
    {
        return $this->publicUrl('favicon_path') ?? $this->logoUrl();
    }

    public function heroUrl(): ?string
    {
        return $this->publicUrl('hero_image_path');
    }

    /** Local filesystem path of the logo, for PDF renderers that cannot fetch URLs. */
    public function logoPath(): ?string
    {
        $path = $this->filled('logo_path');
        if ($path === null || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    /** Absolute URL for emails, where relative asset paths do not resolve. */
    public function absoluteLogoUrl(): ?string
    {
        $url = $this->logoUrl();

        return $url === null ? null : url($url);
    }

    public function primary(): string
    {
        return self::validColor($this->restaurant?->primary_color) ?? self::DEFAULT_PRIMARY;
    }

    public function accent(): string
    {
        return self::validColor($this->restaurant?->accent_color) ?? self::DEFAULT_ACCENT;
    }

    /** "r, g, b" for use inside rgba(). */
    public function primaryRgb(): string
    {
        return implode(', ', self::rgb($this->primary()));
    }

    public function accentRgb(): string
    {
        return implode(', ', self::rgb($this->accent()));
    }

    public function primaryDark(): string
    {
        return self::shade($this->primary(), -0.18);
    }

    public function accentLight(): string
    {
        return self::shade($this->accent(), 0.12);
    }

    /** Readable text colour on top of the primary colour. */
    public function onPrimary(): string
    {
        return self::readableOn($this->primary());
    }

    public function onAccent(): string
    {
        return self::readableOn($this->accent());
    }

    /** CSS custom properties consumed by the admin and public stylesheets. */
    public function cssVariables(): string
    {
        return sprintf(
            '--brand:%s;--brand-rgb:%s;--brand-dark:%s;--brand-contrast:%s;--brand-accent:%s;--brand-accent-rgb:%s;--brand-accent-light:%s;--brand-accent-contrast:%s;',
            $this->primary(),
            $this->primaryRgb(),
            $this->primaryDark(),
            $this->onPrimary(),
            $this->accent(),
            $this->accentRgb(),
            $this->accentLight(),
            $this->onAccent(),
        );
    }

    public function phone(): ?string
    {
        return $this->filled('phone');
    }

    public function email(): ?string
    {
        return $this->filled('email');
    }

    public function address(): ?string
    {
        return $this->filled('address');
    }

    public function openingHours(): ?string
    {
        return $this->filled('opening_hours');
    }

    public function emailFooter(): ?string
    {
        return $this->filled('email_footer');
    }

    /** @return array<string, array{label: string, url: string, icon: string}> */
    public function socialLinks(): array
    {
        $links = [];
        if ($url = $this->safeUrl('website_url')) {
            $links['website'] = ['label' => 'Website', 'url' => $url, 'icon' => 'fas fa-globe'];
        }
        if ($url = $this->safeUrl('facebook_url')) {
            $links['facebook'] = ['label' => 'Facebook', 'url' => $url, 'icon' => 'fab fa-facebook-f'];
        }
        if ($url = $this->safeUrl('instagram_url')) {
            $links['instagram'] = ['label' => 'Instagram', 'url' => $url, 'icon' => 'fab fa-instagram'];
        }
        $digits = preg_replace('/\D+/', '', (string) $this->restaurant?->whatsapp_number);
        if ($digits !== '' && $digits !== null) {
            $links['whatsapp'] = ['label' => 'WhatsApp', 'url' => 'https://wa.me/' . $digits, 'icon' => 'fab fa-whatsapp'];
        }

        return $links;
    }

    public static function validColor(?string $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
    }

    private function filled(string $attribute): ?string
    {
        $value = trim((string) $this->restaurant?->getAttribute($attribute));

        return $value === '' ? null : $value;
    }

    private function publicUrl(string $attribute): ?string
    {
        $path = $this->filled($attribute);
        if ($path === null) {
            return null;
        }

        return Storage::disk('public')->url($path) . '?v=' . substr(md5((string) $this->restaurant?->updated_at), 0, 8);
    }

    /** Only http(s) links are rendered, so stored values can never inject javascript: URLs. */
    private function safeUrl(string $attribute): ?string
    {
        $url = $this->filled($attribute);

        return $url !== null && preg_match('#^https?://#i', $url) ? $url : null;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function shade(string $hex, float $amount): string
    {
        return '#' . collect(self::rgb($hex))
            ->map(function (int $channel) use ($amount): string {
                $value = $amount < 0 ? $channel * (1 + $amount) : $channel + (255 - $channel) * $amount;

                return str_pad(dechex((int) round(max(0, min(255, $value)))), 2, '0', STR_PAD_LEFT);
            })
            ->implode('');
    }

    /** White or near-black, whichever has the higher WCAG contrast against $hex. */
    private static function readableOn(string $hex): string
    {
        $l = self::luminance($hex);
        $againstWhite = 1.05 / ($l + 0.05);
        $againstDark = ($l + 0.05) / 0.0625;

        return $againstWhite >= $againstDark ? '#ffffff' : '#111827';
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
