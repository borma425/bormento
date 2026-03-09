<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'delivery_fees_paid', 'shipped'])->default('pending');
            $table->text('items_json');
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->json('customer_numbers')->nullable();
            $table->decimal('delivery_fees', 10, 2)->default(0);
            $table->string('governorate')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('user_id', 'idx_order_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
