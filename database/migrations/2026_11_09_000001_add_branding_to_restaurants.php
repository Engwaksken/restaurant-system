<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant branding: identity (tagline, about), imagery (favicon, hero),
 * colours used by the admin, public site and emails, and contact/social links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('tagline', 160)->nullable()->after('name');
            $table->text('about')->nullable()->after('tagline');
            $table->string('favicon_path')->nullable()->after('logo_path');
            $table->string('hero_image_path')->nullable()->after('favicon_path');
            $table->string('primary_color', 7)->nullable()->after('hero_image_path');
            $table->string('accent_color', 7)->nullable()->after('primary_color');
            $table->string('opening_hours', 500)->nullable()->after('accent_color');
            $table->string('website_url')->nullable()->after('opening_hours');
            $table->string('facebook_url')->nullable()->after('website_url');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('whatsapp_number', 40)->nullable()->after('instagram_url');
            $table->string('email_footer', 1000)->nullable()->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn([
                'tagline', 'about', 'favicon_path', 'hero_image_path', 'primary_color', 'accent_color',
                'opening_hours', 'website_url', 'facebook_url', 'instagram_url', 'whatsapp_number', 'email_footer',
            ]);
        });
    }
};
