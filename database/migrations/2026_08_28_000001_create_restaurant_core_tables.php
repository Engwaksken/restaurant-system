<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tin')->nullable();
            $table->string('currency', 10)->default('UGX');
            $table->string('timezone')->default('Africa/Kampala');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('capacity')->default(2);
            $table->enum('status', ['available','reserved','occupied','cleaning','inactive'])->default('available');
            $table->string('qr_token')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general');
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('type')->default('string');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('restaurants');
    }
};
