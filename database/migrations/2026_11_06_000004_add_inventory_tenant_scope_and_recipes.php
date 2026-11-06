<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['suppliers', 'inventory_categories', 'inventory_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
                $table->index(['restaurant_id', 'id']);
            });
        }

        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->timestamps();
            $table->unique(['recipe_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');

        foreach (['inventory_items', 'inventory_categories', 'suppliers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['restaurant_id', 'id']);
                $table->dropConstrainedForeignId('restaurant_id');
            });
        }
    }
};
