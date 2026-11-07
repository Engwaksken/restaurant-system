<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['earn', 'redeem', 'adjust', 'reverse']);
            $table->integer('points');
            $table->unsignedInteger('balance_after');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'id']);
            $table->index(['order_id', 'type']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('discount');
            $table->decimal('loyalty_discount', 15, 2)->default(0)->after('loyalty_points_redeemed');
        });

        Schema::create('menu_item_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['menu_item_id', 'name']);
        });

        Schema::create('menu_item_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['menu_item_id', 'name']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('menu_item_variant_id')->nullable()->after('menu_item_id')->constrained()->nullOnDelete();
            $table->string('variant_name')->nullable()->after('item_name');
            $table->json('addons')->nullable()->after('variant_name');
        });

        Schema::create('held_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('order_type');
            $table->json('items');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_orders');
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('menu_item_variant_id');
            $table->dropColumn(['variant_name', 'addons']);
        });
        Schema::dropIfExists('menu_item_addons');
        Schema::dropIfExists('menu_item_variants');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['loyalty_points_redeemed', 'loyalty_discount']);
        });
        Schema::dropIfExists('loyalty_transactions');
    }
};
