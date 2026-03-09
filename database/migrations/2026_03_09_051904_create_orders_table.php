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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_id')->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_address')->nullable();
            $table->json('customer_numbers')->nullable();
            $table->string('governorate')->nullable();
            $table->decimal('delivery_fees', 10, 2)->default(0);
            $table->json('items')->comment('Array of ordered variants');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->json('shipping_data')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
