<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('restaurant_id')->constrained()->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['restaurant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['restaurant_id', 'id']);
            $table->dropConstrainedForeignId('restaurant_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
