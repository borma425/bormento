<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('reply_only_mode')->default(false)->after('ecommerce_config')
                  ->comment('If true, Agent acts as Assistant and does not ask for orders');
            $table->json('shipping_zones')->nullable()->after('reply_only_mode')
                  ->comment('Array of {governorate: string, fee: decimal} objects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['reply_only_mode', 'shipping_zones']);
        });
    }
};
