<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferredChat extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_platform_id',
        'platform',
        'customer_name',
        'last_message',
        'transfer_reason',
        'status',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
