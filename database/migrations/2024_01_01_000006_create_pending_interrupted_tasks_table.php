<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_interrupted_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->text('message');
            $table->dateTime('resume_at');
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_interrupted_tasks');
    }
};
