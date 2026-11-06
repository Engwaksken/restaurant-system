<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('payment_method');
            $table->decimal('amount', 15, 2);
            $table->string('transaction_reference')->nullable()->index();
            $table->enum('status', ['pending','successful','failed','refunded'])->default('successful');
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->unique();
            $table->boolean('enabled')->default(false);
            $table->boolean('sandbox')->default(true);
            $table->text('credentials')->nullable();
            $table->string('callback_url')->nullable();
            $table->string('webhook_url')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tin')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('purchase_unit')->default('unit');
            $table->string('usage_unit')->default('unit');
            $table->decimal('conversion_factor', 15, 4)->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('minimum_stock', 15, 3)->default(0);
            $table->decimal('reorder_level', 15, 3)->default(0);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->timestamps();
            $table->unique(['branch_id','inventory_item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['opening','purchase','sale','transfer_in','transfer_out','adjustment','wastage','return']);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['reference_type','reference_id']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->date('expense_date');
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('payment_gateway_settings');
        Schema::dropIfExists('payments');
    }
};
