<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['user_id', 'agent_type', 'agent_name', 'user_name'];

    protected $attributes = [
        'agent_type' => 'greeting',
        'agent_name' => 'Store Agent',
        'user_name' => 'User',
    ];
}
