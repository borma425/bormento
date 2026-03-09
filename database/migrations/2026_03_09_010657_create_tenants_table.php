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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry')->default('retail'); // e.g. retail, restaurant, electronics
            
            // Social Media Identifiers
            $table->string('fb_page_id')->nullable()->unique();
            $table->string('fb_page_token')->nullable();
            
            $table->string('ig_account_id')->nullable()->unique();
            $table->string('ig_access_token')->nullable();
            
            // API Keys
            $table->string('openai_api_key')->nullable();
            
            // Dynamic Configurations
            $table->json('ecommerce_config')->nullable(); // e.g. provider=gravoni, api_url, secret_key
            $table->json('payment_config')->nullable();   // e.g. provider=cashup, api_url, api_key, app_id
            $table->json('ai_config')->nullable();        // e.g. tone, custom_rules, default_language
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
