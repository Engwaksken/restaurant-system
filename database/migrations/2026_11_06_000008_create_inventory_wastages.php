<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wastages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('reason', 1000);
            $table->string('reference')->nullable();
            $table->timestamp('wasted_at');
            $table->timestamps();
            $table->index(['branch_id', 'wasted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastages');
    }
};
