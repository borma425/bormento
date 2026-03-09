<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->string('user_id')->primary();
            $table->string('agent_type', 50)->default('greeting');
            $table->string('agent_name')->default('Store Agent');
            $table->string('user_name')->default('User');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('user_id', 'idx_session_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
