<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ability', 190);
            $table->timestamps();
            $table->unique(['user_id', 'ability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
