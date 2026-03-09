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
        Schema::create('transferred_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('user_platform_id')->comment('e.g., PSID or IGID');
            $table->string('platform')->comment('messenger, instagram, html');
            $table->string('customer_name')->nullable();
            $table->text('last_message')->nullable();
            $table->text('transfer_reason')->nullable();
            $table->string('status')->default('pending')->comment('pending, resolved');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferred_chats');
    }
};
