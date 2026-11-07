<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-restaurant online payment gateway configuration. Credentials are
 * stored encrypted with the application key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 30);
            $table->boolean('is_enabled')->default(false);
            $table->string('mode', 10)->default('test');
            $table->text('credentials')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['restaurant_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_payment_gateways');
    }
};
