<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id','slug']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('track_inventory')->default(false);
            $table->unsignedInteger('preparation_minutes')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['menu_category_id','is_available']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reservation_number')->unique();
            $table->string('guest_name');
            $table->string('phone');
            $table->unsignedInteger('guest_count')->default(1);
            $table->dateTime('reserved_at');
            $table->enum('status', ['pending','confirmed','seated','completed','cancelled','no_show'])->default('pending');
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->text('special_requests')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->enum('order_type', ['dine_in','takeaway','delivery','online'])->default('dine_in');
            $table->enum('status', ['pending','confirmed','preparing','ready','served','completed','cancelled'])->default('pending');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->decimal('delivery_fee', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id','status','ordered_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->text('notes')->nullable();
            $table->enum('kitchen_status', ['pending','preparing','ready','served','cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
