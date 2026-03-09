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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('currency')->default('EGP')->after('discounted_price');
            $table->boolean('is_active')->default(true)->after('currency');
            $table->json('media')->nullable()->after('description');
            $table->json('attributes')->nullable()->comment('Dynamic variants like sizes/colors')->after('media');
            $table->json('metadata')->nullable()->comment('AI tags and RAG alignments')->after('attributes');

            // Drop legacy hardcoded columns
            $table->dropColumn(['availableSizes', 'availableColors', 'stockCount', 'samples', 'sample']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'currency', 'is_active', 'media', 'attributes', 'metadata']);
            
            $table->json('availableSizes')->nullable();
            $table->json('availableColors')->nullable();
            $table->integer('stockCount')->default(0);
            $table->json('samples')->nullable();
            $table->string('sample')->nullable();
        });
    }
};
