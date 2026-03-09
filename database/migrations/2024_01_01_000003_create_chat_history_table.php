<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_history', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('role', 50); // 'human', 'ai', 'tool'
            $table->longText('content');
            $table->string('tool_call_id')->nullable();
            $table->json('metadata')->nullable(); // For tool_calls in AIMessages
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'idx_user_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_history');
    }
};
