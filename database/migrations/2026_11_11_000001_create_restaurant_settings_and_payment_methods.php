<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-restaurant settings (AI, SMS, email, receipt, invoice, accessibility,
 * backups) and admin-defined manual payment methods shown at checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('group', 40);
            $table->string('key', 60);
            // Secrets are stored encrypted with the application key.
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
            $table->unique(['restaurant_id', 'group', 'key']);
        });

        Schema::create('manual_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('icon', 40)->default('fas fa-wallet');
            $table->text('instructions');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_methods');
        Schema::dropIfExists('restaurant_settings');
    }
};
