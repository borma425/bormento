<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingTask extends Model
{
    protected $table = 'pending_interrupted_tasks';
    public $timestamps = false;

    protected $fillable = ['user_id', 'message', 'resume_at', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'resume_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
