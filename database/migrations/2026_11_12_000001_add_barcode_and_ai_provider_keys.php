<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Inventory items get a barcode (unique per restaurant) for scanning.
 * - AI settings move to one API key per provider (key_<provider>) and a
 *   single "model" field; earlier saved Anthropic/Gemini values are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->string('barcode', 64)->nullable()->after('sku');
            $table->unique(['restaurant_id', 'barcode']);
        });

        if (Schema::hasTable('restaurant_settings')) {
            DB::table('restaurant_settings')->where('group', 'ai')->where('key', 'api_key')->update(['key' => 'key_anthropic']);
            DB::table('restaurant_settings')->where('group', 'ai')->where('key', 'gemini_api_key')->update(['key' => 'key_gemini']);

            // Gemini users keep their Gemini model in the shared "model" field.
            $geminiRestaurants = DB::table('restaurant_settings')->where('group', 'ai')->where('key', 'provider')->where('value', 'gemini')->pluck('restaurant_id');
            foreach ($geminiRestaurants as $restaurantId) {
                $geminiModel = DB::table('restaurant_settings')->where(['restaurant_id' => $restaurantId, 'group' => 'ai', 'key' => 'gemini_model'])->value('value');
                if ($geminiModel) {
                    DB::table('restaurant_settings')->where(['restaurant_id' => $restaurantId, 'group' => 'ai', 'key' => 'model'])->update(['value' => $geminiModel]);
                }
            }
            DB::table('restaurant_settings')->where('group', 'ai')->where('key', 'gemini_model')->delete();
        }
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id', 'barcode']);
            $table->dropColumn('barcode');
        });

        if (Schema::hasTable('restaurant_settings')) {
            DB::table('restaurant_settings')->where('group', 'ai')->where('key', 'key_anthropic')->update(['key' => 'api_key']);
            DB::table('restaurant_settings')->where('group', 'ai')->where('key', 'key_gemini')->update(['key' => 'gemini_api_key']);
        }
    }
};
